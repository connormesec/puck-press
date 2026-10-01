<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Puck_Press_Instagram_Post_Importer {

	// Set on a pp_insta_post once its featured image is linked and every
	// sub-size exists. Posts without it are candidates for repair.
	const IMAGE_OK_META = '_pp_insta_image_ok';

	// Number of repair attempts on a post. Posts that keep failing are tried
	// last so they can't use up every run's repair limit.
	const REPAIR_ATTEMPTS_META = '_pp_insta_image_repair_attempts';

	// Held while a background import run (including its continuations) is active.
	const RUN_LOCK_TRANSIENT = 'pp_insta_import_lock';
	const RUN_LOCK_TTL       = 900;

	// Stop starting new teams after this many seconds and hand the rest to a
	// fresh request, so one run never approaches the PHP time limit.
	const RUN_TIME_BUDGET = 120;

	// Max broken posts repaired per background run (each repair resizes an image).
	const SELF_HEAL_LIMIT = 5;

	public function run_daily(): array {
		global $wpdb;
		$messages = array();

		$api_key = get_option( 'pp_insta_scraper_api_key', '' );
		if ( empty( $api_key ) ) {
			$messages[] = 'Instagram API key is not set.';
			return $messages;
		}

		$secret = get_option( 'pp_insta_loopback_secret', '' );
		if ( empty( $secret ) ) {
			$messages[] = 'Loopback secret not set — re-activate the plugin.';
			return $messages;
		}

		$rows = $wpdb->get_results(
			"SELECT option_name, option_value FROM {$wpdb->options}
             WHERE option_name LIKE 'pp_team_%_insta_enabled'
               AND option_value = '1'"
		);

		if ( empty( $rows ) ) {
			$messages[] = 'No teams have Instagram import enabled.';
			return $messages;
		}

		$team_ids = array();
		foreach ( $rows as $row ) {
			if ( ! preg_match( '/^pp_team_(\d+)_insta_enabled$/', $row->option_name, $m ) ) {
				continue;
			}
			$team_id = (int) $m[1];
			$handle  = get_option( "pp_team_{$team_id}_insta_handle", '' );
			if ( empty( $handle ) ) {
				$messages[] = "Team {$team_id}: no Instagram handle configured, skipping.";
				continue;
			}
			$team_ids[] = $team_id;
		}

		// Teams with no handle are skipped above, but broken posts can still be
		// repaired, so dispatch even when the team list is empty.
		if ( get_transient( self::RUN_LOCK_TRANSIENT ) ) {
			$messages[] = 'Previous Instagram import is still running, skipping this run.';
			return $messages;
		}

		// One background request imports the teams one after another. Running
		// them concurrently (one request per team) overloaded shared hosting and
		// got processes killed mid-resize, leaving posts without featured images.
		$token = wp_generate_password( 20, false );
		set_transient( self::RUN_LOCK_TRANSIENT, $token, self::RUN_LOCK_TTL );
		self::dispatch_loopback( $team_ids, $token, $secret );

		$messages[] = empty( $team_ids )
			? 'No teams to import; dispatched image repair.'
			: 'Dispatched background import for team(s) ' . implode( ', ', $team_ids ) . '.';

		return $messages;
	}

	/**
	 * @param int[] $team_ids
	 */
	private static function dispatch_loopback( array $team_ids, string $token, string $secret ): void {
		wp_remote_post(
			admin_url( 'admin-ajax.php' ),
			array(
				'blocking' => false,
				'timeout'  => 1,
				'body'     => array(
					'action'   => 'pp_run_team_insta_import',
					'team_ids' => implode( ',', $team_ids ),
					'token'    => $token,
					'secret'   => $secret,
				),
			)
		);
	}

	public static function handle_loopback_team_import(): void {
		// The dispatcher disconnects after ~1s. Keep running after it does, and
		// allow the same time the cron path gets.
		ignore_user_abort( true );
		@set_time_limit( 300 ); // phpcs:ignore

		$secret   = get_option( 'pp_insta_loopback_secret', '' );
		$provided = isset( $_POST['secret'] ) ? sanitize_text_field( wp_unslash( $_POST['secret'] ) ) : '';

		if ( empty( $secret ) || ! hash_equals( $secret, $provided ) ) {
			wp_send_json_error( 'Unauthorized', 403 );
			return;
		}

		// Legacy single-team requests (dispatched by a version before the
		// sequential runner) carry team_id and no token.
		if ( isset( $_POST['team_id'] ) && ! isset( $_POST['team_ids'] ) ) {
			$team_id = (int) $_POST['team_id'];
			if ( $team_id <= 0 ) {
				wp_send_json_error( 'Invalid team ID', 400 );
				return;
			}
			self::finish_response();
			$importer = new self();
			$importer->run_for_team( $team_id );
			return;
		}

		$token = isset( $_POST['token'] ) ? sanitize_text_field( wp_unslash( $_POST['token'] ) ) : '';
		$held  = get_transient( self::RUN_LOCK_TRANSIENT );
		if ( $held && ! hash_equals( (string) $held, $token ) ) {
			wp_send_json_error( 'Another import is running', 409 );
			return;
		}

		$team_ids = isset( $_POST['team_ids'] ) ? sanitize_text_field( wp_unslash( $_POST['team_ids'] ) ) : '';
		$team_ids = array_values( array_filter( array_map( 'intval', explode( ',', $team_ids ) ) ) );

		self::finish_response();

		$importer = new self();
		$importer->run_queue( $team_ids, $token, $secret );
	}

	/**
	 * Sends the response and closes the connection while PHP keeps running, so
	 * a web server that aborts requests on client disconnect has nothing to abort.
	 */
	private static function finish_response(): void {
		if ( ! headers_sent() ) {
			status_header( 202 );
			header( 'Content-Type: application/json; charset=' . get_option( 'blog_charset' ) );
		}
		echo wp_json_encode( array( 'success' => true ) );

		if ( function_exists( 'litespeed_finish_request' ) ) {
			litespeed_finish_request();
		} elseif ( function_exists( 'fastcgi_finish_request' ) ) {
			fastcgi_finish_request();
		}
	}

	/**
	 * Imports each team in turn, then repairs a few broken posts. If the time
	 * budget runs out, the remaining teams go to a new background request.
	 *
	 * @param int[] $team_ids
	 */
	private function run_queue( array $team_ids, string $token, string $secret ): void {
		$start  = microtime( true );
		$budget = self::RUN_TIME_BUDGET;
		$limit  = (int) ini_get( 'max_execution_time' );
		if ( $limit > 0 ) {
			$budget = min( $budget, (int) floor( $limit / 2 ) );
		}

		while ( ! empty( $team_ids ) ) {
			if ( microtime( true ) - $start > $budget ) {
				set_transient( self::RUN_LOCK_TRANSIENT, $token, self::RUN_LOCK_TTL );
				self::dispatch_loopback( $team_ids, $token, $secret );
				error_log( 'Puck Press Instagram: time budget reached, continuing team(s) ' . implode( ', ', $team_ids ) . ' in a new request.' );
				return;
			}

			$team_id = array_shift( $team_ids );
			try {
				foreach ( $this->run_for_team( $team_id ) as $msg ) {
					error_log( "Puck Press Instagram: team {$team_id} — {$msg}" );
				}
			} catch ( Throwable $e ) {
				error_log( "Puck Press Instagram: team {$team_id} failed — " . $e->getMessage() );
			}
		}

		if ( microtime( true ) - $start <= $budget ) {
			$report = $this->repair_post_images( self::SELF_HEAL_LIMIT );
			if ( $report['relinked'] || $report['regenerated'] || $report['errors'] ) {
				error_log( 'Puck Press Instagram: self-heal — ' . self::format_repair_report( $report ) );
			}
		}

		delete_transient( self::RUN_LOCK_TRANSIENT );
	}

	public function run_for_team( int $team_id ): array {
		$messages = array();

		if ( ! get_option( 'pp_enable_insta_post', 0 ) ) {
			$messages[] = 'Instagram import feature is disabled.';
			return $messages;
		}

		$handle = get_option( "pp_team_{$team_id}_insta_handle", '' );
		if ( empty( $handle ) ) {
			$messages[] = "Team {$team_id}: no Instagram handle configured.";
			return $messages;
		}

		$existing_ids = $this->get_existing_insta_ids( $team_id );
		$fetch_result = $this->fetch_instagram_posts( $existing_ids, $handle );

		if ( ! $fetch_result['success'] ) {
			$messages[] = 'Error fetching Instagram posts: ' . $fetch_result['message'];
			return $messages;
		}

		// API returns newest-first; reverse so oldest is inserted first and
		// WP post_date order matches Instagram chronological order.
		foreach ( array_reverse( $fetch_result['data'] ) as $post_data ) {
			$title      = isset( $post_data['post_title'] ) ? $post_data['post_title'] : 'Instagram Post';
			$content    = isset( $post_data['post_body'] ) ? $post_data['post_body'] : '';
			$b64_image  = isset( $post_data['image_buffer'] ) ? $post_data['image_buffer'] : '';
			$insta_id   = isset( $post_data['insta_id'] ) ? $post_data['insta_id'] : '';
			$image_name = 'insta-' . $insta_id . '.jpg';
			$slug       = isset( $post_data['slug'] ) ? $post_data['slug'] : '';

			if ( in_array( $insta_id, $existing_ids, true ) || preg_grep( '/^' . preg_quote( $insta_id, '/' ) . '-/', $existing_ids ) ) {
				$messages[] = 'Post with Instagram ID ' . $insta_id . ' already exists.';
				continue;
			}

			$post_id = $this->create_instagram_post( $title, $content, 'publish', $slug, $b64_image, $image_name, $insta_id, $team_id );

			if ( is_wp_error( $post_id ) ) {
				$messages[] = 'Failed to create post for slug ' . $slug . ': ' . $post_id->get_error_message();
				continue;
			}
			$messages[] = 'Successfully created post ID ' . $post_id . ': ' . ( mb_strlen( $title ) > 20 ? mb_substr( $title, 0, 19 ) . '…' : $title );
		}

		return $messages;
	}

	/**
	 * @param string[] $existing_post_ids
	 * @return array{success: bool, message?: string, data?: array<int, array{insta_id: string, slug: string, post_title: string, post_body: string, image_url: string, image_buffer: string}>}
	 */
	public function fetch_instagram_posts( array $existing_post_ids = array(), string $handle = '' ): array {
		$api_key = get_option( 'pp_insta_scraper_api_key', '' );
		if ( empty( $handle ) ) {
			$handle = get_option( 'pp_insta_handle', '' );
		}

		if ( empty( $api_key ) ) {
			return array(
				'success' => false,
				'message' => 'Instagram API key is not set',
			);
		}

		$response = wp_remote_post(
			'https://8qoqtj3pm0.execute-api.us-east-2.amazonaws.com/default/getWpPostFromInstaAPI',
			array(
				'headers' => array(
					'Authorization' => 'Bearer ' . $api_key,
					'Content-Type'  => 'application/json',
				),
				'timeout' => 30,
				'body'    => wp_json_encode(
					array(
						'instagram_handle'  => $handle,
						'existing_post_ids' => $existing_post_ids,
					)
				),
			)
		);

		if ( is_wp_error( $response ) ) {
			return array(
				'success' => false,
				'message' => 'Failed to fetch posts: ' . $response->get_error_message(),
			);
		}

		$response_code = wp_remote_retrieve_response_code( $response );
		if ( $response_code !== 200 ) {
			return array(
				'success' => false,
				'message' => 'API returned error code: ' . $response_code,
			);
		}

		$body = wp_remote_retrieve_body( $response );
		$data = json_decode( $body, true );

		if ( json_last_error() !== JSON_ERROR_NONE ) {
			return array(
				'success' => false,
				'message' => 'Invalid JSON response from API',
			);
		}

		$formatted_posts = array();
		if ( ! empty( $data['new_posts'] ) && is_array( $data['new_posts'] ) ) {
			foreach ( $data['new_posts'] as $post ) {
				if ( empty( $post['postTitle'] ) || empty( $post['imgSrc'] ) || empty( $post['featuredImageBuffer'] ) ) {
					continue;
				}
				$full_text = $post['postText'] ?? $post['postTitle'];

				if ( $this->is_placeholder_caption( $full_text ) ) {
					$title = 'See latest post';
					// Keep the "Click here…" anchor as the body (decoded so it renders as a link).
					$body = html_entity_decode( $full_text, ENT_QUOTES, 'UTF-8' );
					$slug = $this->image_url_to_slug( $post['imgSrc'], $title );
				} else {
					$split = $this->split_caption( $full_text );
					$title = $split['title'];
					$body  = $split['body'];
					$slug  = $this->title_to_slug( $title );
				}

				$formatted_posts[] = array(
					'insta_id'     => ! empty( $post['post_id'] ) ? $post['post_id'] : ( $post['postSlug'] ?? '' ),
					'slug'         => $slug,
					'post_title'   => $title,
					'post_body'    => $body,
					'image_url'    => $post['imgSrc'],
					'image_buffer' => $post['featuredImageBuffer'],
				);
			}
		}

		return array(
			'success' => true,
			'data'    => $formatted_posts,
		);
	}

	/**
	 * @return int|WP_Error
	 */
	public function create_instagram_post( string $title, string $content, string $status = 'publish', string $slug = '', string $b64_image = '', string $image_name = 'instagram-image.png', string $insta_id = '', int $team_id = 0 ) {
		$tag_id = term_exists( 'Instagram', 'post_tag' );
		if ( $tag_id ) {
			$tag_id = (int) $tag_id['term_id'];
		} else {
			$tag    = wp_insert_term( 'Instagram', 'post_tag' );
			$tag_id = ! is_wp_error( $tag ) ? (int) $tag['term_id'] : 0;
		}

		if ( ! $tag_id ) {
			return new WP_Error( 'tag_error', 'Could not create or retrieve Instagram tag.' );
		}

		$postarr = array(
			'post_title'   => $title,
			'post_content' => $content,
			'post_status'  => $status,
			'post_author'  => ( ( $pp_user = get_user_by( 'login', 'puck-press' ) ) ? $pp_user->ID : 1 ),
			'post_type'    => 'pp_insta_post',
		);

		if ( ! empty( $slug ) ) {
			$slug          = sanitize_title( $slug );
			$existing_post = get_page_by_path( $slug, OBJECT, 'pp_insta_post' );
			if ( $existing_post && ! empty( $insta_id ) ) {
				$slug = $slug . '-' . sanitize_title( $insta_id );
			}
			$postarr['post_name'] = $slug;
		}

		$post_id = wp_insert_post( $postarr, true );
		if ( is_wp_error( $post_id ) ) {
			return $post_id;
		}

		if ( ! empty( $insta_id ) ) {
			update_post_meta( $post_id, '_insta_post_id', sanitize_text_field( $insta_id ) );
		}

		if ( $team_id > 0 ) {
			update_post_meta( $post_id, '_pp_team_id', $team_id );
		}

		wp_set_post_terms( $post_id, array( $tag_id ), 'post_tag', true );

		if ( ! empty( $b64_image ) ) {
			// Links the image as the featured image itself, before resizing.
			$image_id = $this->save_base64_image_to_media( $b64_image, $image_name, $post_id );
			if ( is_wp_error( $image_id ) ) {
				error_log( "Puck Press Instagram: image for post {$post_id} failed — " . $image_id->get_error_message() );
			}
		}

		$this->enforce_post_cap();

		return $post_id;
	}

	private function enforce_post_cap() {
		$max = (int) get_option( 'pp_insta_post_max_count', 0 );
		if ( $max <= 0 ) {
			return;
		}
		$posts = get_posts(
			array(
				'post_type'      => 'pp_insta_post',
				'post_status'    => 'publish',
				'posts_per_page' => -1,
				'orderby'        => 'date',
				'order'          => 'ASC',
				'fields'         => 'ids',
			)
		);
		$over  = count( $posts ) - $max;
		for ( $i = 0; $i < $over; $i++ ) {
			$pid = $posts[ $i ];

			// The featured image plus any child images (posts broken before the
			// featured image was linked early still own an attachment).
			$attachment_ids = get_children(
				array(
					'post_parent' => $pid,
					'post_type'   => 'attachment',
					'fields'      => 'ids',
				)
			);
			$thumb_id       = (int) get_post_thumbnail_id( $pid );
			if ( $thumb_id && (int) get_post_field( 'post_parent', $thumb_id ) === $pid ) {
				$attachment_ids[] = $thumb_id;
			}

			foreach ( array_unique( array_map( 'intval', $attachment_ids ) ) as $attach_id ) {
				// Older imports could point several attachments at one file;
				// deleting this one would delete the image the others still use.
				if ( ! $this->attachment_file_is_shared( $attach_id ) ) {
					wp_delete_attachment( $attach_id, true );
				}
			}
			wp_delete_post( $pid, true );
		}
	}

	private function attachment_file_is_shared( int $attach_id ): bool {
		global $wpdb;

		$file = get_post_meta( $attach_id, '_wp_attached_file', true );
		if ( empty( $file ) ) {
			return false;
		}

		return (bool) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT 1 FROM {$wpdb->postmeta}
                 WHERE meta_key = '_wp_attached_file' AND meta_value = %s AND post_id != %d
                 LIMIT 1",
				$file,
				$attach_id
			)
		);
	}

	/**
	 * Saves the image as an attachment of $post_id and, when $post_id is set,
	 * links it as the post's featured image before generating sub-sizes.
	 *
	 * @return int|WP_Error
	 */
	private function save_base64_image_to_media( string $b64_image, string $filename = 'instagram-image.png', int $post_id = 0 ) {
		$decoded = base64_decode( $b64_image );
		if ( ! $decoded ) {
			return new WP_Error( 'b64_decode_error', 'Failed to decode base64 image.' );
		}

		// wp_upload_bits() picks a unique filename (never overwrites an existing
		// upload) and reports write failures.
		$upload = wp_upload_bits( sanitize_file_name( $filename ), null, $decoded );
		if ( ! empty( $upload['error'] ) ) {
			return new WP_Error( 'upload_error', $upload['error'] );
		}
		$file_path = $upload['file'];

		$filetype = wp_check_filetype( $file_path, null );

		$attachment = array(
			'post_mime_type' => $filetype['type'],
			'post_title'     => sanitize_file_name( pathinfo( $file_path, PATHINFO_FILENAME ) ),
			'post_content'   => '',
			'post_status'    => 'inherit',
		);

		$attach_id = wp_insert_attachment( $attachment, $file_path, $post_id, true );
		if ( is_wp_error( $attach_id ) ) {
			wp_delete_file( $file_path );
			return $attach_id;
		}

		// Link the featured image now, before the slow resize below. If the
		// process dies mid-resize, the post still has its image and the
		// self-heal pass fills in the missing sub-sizes. set_post_thumbnail()
		// isn't used because it can reject an attachment with no metadata yet.
		if ( $post_id > 0 ) {
			update_post_meta( $post_id, '_thumbnail_id', $attach_id );
		}

		require_once ABSPATH . 'wp-admin/includes/image.php';
		$attach_data = wp_generate_attachment_metadata( $attach_id, $file_path );
		wp_update_attachment_metadata( $attach_id, $attach_data );

		add_filter( 'wp_get_missing_image_subsizes', array( self::class, 'filter_missing_subsizes' ), 10, 3 );
		$complete = empty( wp_get_missing_image_subsizes( $attach_id ) );
		remove_filter( 'wp_get_missing_image_subsizes', array( self::class, 'filter_missing_subsizes' ), 10 );

		if ( $post_id > 0 && ! empty( $attach_data ) && $complete ) {
			update_post_meta( $post_id, self::IMAGE_OK_META, 1 );
		}

		return $attach_id;
	}

	/**
	 * Finds pp_insta_post posts whose featured image is missing or whose image
	 * is missing sub-sizes, and repairs them: links the post's own attachment
	 * as the featured image and generates only the missing sub-sizes.
	 *
	 * Posts already verified healthy carry IMAGE_OK_META and are skipped, so a
	 * run only inspects new or broken posts.
	 *
	 * @param int  $limit   Max posts to repair (relink or resize). 0 = no limit.
	 *                      Healthy posts found along the way don't count.
	 * @param bool $dry_run Report what would be repaired without changing anything.
	 * @return array{checked: int, healthy: int, relinked: int, regenerated: int, deferred: int, errors: int, no_attachment: int[], file_missing: int[]}
	 */
	public function repair_post_images( int $limit = 0, bool $dry_run = false ): array {
		global $wpdb;

		require_once ABSPATH . 'wp-admin/includes/image.php';

		$report = array(
			'checked'       => 0,
			'healthy'       => 0,
			'relinked'      => 0,
			'regenerated'   => 0,
			'deferred'      => 0,
			'errors'        => 0,
			'no_attachment' => array(),
			'file_missing'  => array(),
		);

		$post_ids = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT DISTINCT p.ID
                 FROM {$wpdb->posts} p
                 LEFT JOIN {$wpdb->postmeta} ok ON ok.post_id = p.ID AND ok.meta_key = %s
                 LEFT JOIN {$wpdb->postmeta} th ON th.post_id = p.ID AND th.meta_key = '_thumbnail_id'
                 LEFT JOIN {$wpdb->postmeta} att ON att.post_id = p.ID AND att.meta_key = %s
                 WHERE p.post_type = 'pp_insta_post'
                   AND p.post_status NOT IN ('trash', 'auto-draft')
                   AND ( ok.meta_id IS NULL OR th.meta_id IS NULL OR th.meta_value = '' OR th.meta_value = '0' )
                 ORDER BY CAST( COALESCE( att.meta_value, '0' ) AS UNSIGNED ) ASC, p.ID DESC",
				self::IMAGE_OK_META,
				self::REPAIR_ATTEMPTS_META
			)
		);

		// Applies to both the check below and wp_update_image_subsizes(), which
		// calls wp_get_missing_image_subsizes() internally.
		add_filter( 'wp_get_missing_image_subsizes', array( self::class, 'filter_missing_subsizes' ), 10, 3 );

		$repaired = 0;
		foreach ( array_map( 'intval', $post_ids ) as $post_id ) {
			++$report['checked'];

			$thumb_id  = (int) get_post_meta( $post_id, '_thumbnail_id', true );
			$attach_id = ( $thumb_id && wp_attachment_is_image( $thumb_id ) ) ? $thumb_id : $this->find_child_image( $post_id );

			if ( ! $attach_id ) {
				$report['no_attachment'][] = $post_id;
				continue;
			}

			$file = get_attached_file( $attach_id );
			if ( ! $file || ! file_exists( $file ) ) {
				$report['file_missing'][] = $post_id;
				continue;
			}

			$needs_link   = $thumb_id !== $attach_id;
			$needs_resize = empty( wp_get_attachment_metadata( $attach_id ) ) || ! empty( wp_get_missing_image_subsizes( $attach_id ) );

			if ( ! $needs_link && ! $needs_resize ) {
				++$report['healthy'];
				if ( ! $dry_run ) {
					update_post_meta( $post_id, self::IMAGE_OK_META, 1 );
				}
				continue;
			}

			if ( $limit > 0 && $repaired >= $limit ) {
				++$report['deferred'];
				continue;
			}
			++$repaired;

			if ( ! $dry_run ) {
				// Counted before the work so a killed process still counts.
				update_post_meta( $post_id, self::REPAIR_ATTEMPTS_META, (int) get_post_meta( $post_id, self::REPAIR_ATTEMPTS_META, true ) + 1 );
			}

			if ( $needs_link ) {
				++$report['relinked'];
				if ( ! $dry_run ) {
					update_post_meta( $post_id, '_thumbnail_id', $attach_id );
				}
			}

			if ( $needs_resize ) {
				if ( $dry_run ) {
					++$report['regenerated'];
					continue;
				}
				// Creates only the sub-sizes the metadata doesn't list yet (or
				// all of them when there is no metadata at all).
				$result = wp_update_image_subsizes( $attach_id );
				if ( is_wp_error( $result ) ) {
					++$report['errors'];
					error_log( "Puck Press Instagram: could not resize attachment {$attach_id} for post {$post_id} — " . $result->get_error_message() );
					continue;
				}
				++$report['regenerated'];
			}

			if ( ! $dry_run ) {
				update_post_meta( $post_id, self::IMAGE_OK_META, 1 );
				delete_post_meta( $post_id, self::REPAIR_ATTEMPTS_META );
			}
		}

		remove_filter( 'wp_get_missing_image_subsizes', array( self::class, 'filter_missing_subsizes' ), 10 );

		return $report;
	}

	/**
	 * wp_get_missing_image_subsizes() compares against every registered size,
	 * but wp_create_image_subsizes() first runs the list through
	 * 'intermediate_image_sizes_advanced', where themes drop sizes they never
	 * want for this image (Divi drops responsive sizes wider than the image).
	 * Applying the same filter here keeps those sizes from being reported as
	 * missing forever, and from being generated when they were never wanted.
	 */
	public static function filter_missing_subsizes( $missing_sizes, $image_meta, $attachment_id ) {
		if ( empty( $missing_sizes ) || empty( $image_meta ) ) {
			return $missing_sizes;
		}
		$filtered = apply_filters( 'intermediate_image_sizes_advanced', $missing_sizes, $image_meta, $attachment_id );
		return is_array( $filtered ) ? array_diff_key( $filtered, $image_meta['sizes'] ?? array() ) : $missing_sizes;
	}

	private function find_child_image( int $post_id ): int {
		$children = get_children(
			array(
				'post_parent'    => $post_id,
				'post_type'      => 'attachment',
				'post_mime_type' => 'image',
				'orderby'        => 'ID',
				'order'          => 'DESC',
				'fields'         => 'ids',
			)
		);
		return $children ? (int) reset( $children ) : 0;
	}

	public static function format_repair_report( array $report, bool $dry_run = false ): string {
		$parts = array(
			"checked {$report['checked']}",
			"{$report['healthy']} already healthy",
			( $dry_run ? 'would relink ' : 'relinked ' ) . $report['relinked'],
			( $dry_run ? 'would regenerate sizes for ' : 'regenerated sizes for ' ) . $report['regenerated'],
		);
		if ( $report['deferred'] ) {
			$parts[] = "{$report['deferred']} left for a later run";
		}
		if ( $report['errors'] ) {
			$parts[] = "{$report['errors']} failed";
		}
		if ( $report['no_attachment'] ) {
			$parts[] = count( $report['no_attachment'] ) . ' with no attachment (post IDs ' . implode( ', ', $report['no_attachment'] ) . ')';
		}
		if ( $report['file_missing'] ) {
			$parts[] = count( $report['file_missing'] ) . ' whose image file is missing (post IDs ' . implode( ', ', $report['file_missing'] ) . ')';
		}
		return implode( '; ', $parts ) . '.';
	}

	/**
	 * @return string[]
	 */
	private function get_instagram_post_slugs( int $limit = -1 ): array {
		$args = array(
			'post_type'      => 'post',
			'tag_slug__in'   => array( 'instagram' ),
			'posts_per_page' => $limit,
			'post_status'    => 'publish',
			'fields'         => 'ids',
		);

		$query = new WP_Query( $args );
		$slugs = array();

		if ( $query->have_posts() ) {
			foreach ( $query->posts as $post_id ) {
				$slug = get_post_field( 'post_name', $post_id );
				if ( $slug ) {
					$slugs[] = $slug;
				}
			}
		}

		wp_reset_postdata();
		return $slugs;
	}

	/**
	 * @return string[]
	 */
	public function get_existing_insta_ids( int $team_id = 0 ): array {
		global $wpdb;

		if ( $team_id > 0 ) {
			$meta_ids = $wpdb->get_col(
				$wpdb->prepare(
					"SELECT DISTINCT pm.meta_value
                     FROM {$wpdb->postmeta} pm
                     INNER JOIN {$wpdb->postmeta} tm ON tm.post_id = pm.post_id
                     INNER JOIN {$wpdb->posts} p ON p.ID = pm.post_id
                     WHERE pm.meta_key = '_insta_post_id'
                       AND tm.meta_key = '_pp_team_id'
                       AND tm.meta_value = %d
                       AND p.post_status != 'trash'",
					$team_id
				)
			);
		} else {
			$meta_ids = $wpdb->get_col(
				"SELECT DISTINCT pm.meta_value
                 FROM {$wpdb->postmeta} pm
                 INNER JOIN {$wpdb->posts} p ON p.ID = pm.post_id
                 WHERE pm.meta_key = '_insta_post_id'
                   AND p.post_status != 'trash'"
			);
		}

		$meta_ids  = $meta_ids ?: array();
		$old_slugs = $this->get_instagram_post_slugs( -1 );
		return array_values( array_unique( array_merge( $meta_ids, $old_slugs ) ) );
	}

	/**
	 * Detects captions that carry no real text — the scraper API returns an
	 * "…Click here to see full post on Instagram" anchor for caption-less posts.
	 */
	private function is_placeholder_caption( string $text ): bool {
		$decoded = html_entity_decode( $text, ENT_QUOTES, 'UTF-8' );

		// The API appends a "Click here to see full post on Instagram" link to
		// EVERY caption, so its presence says nothing about whether the post has
		// real text. Strip that link/phrase out and see if anything is left.
		$without_link = preg_replace( '#<a\b[^>]*>.*?</a>#is', '', $decoded );
		$without_link = preg_replace( '/Click here to see full post on Instagram\.?/i', '', $without_link );

		return trim( wp_strip_all_tags( $without_link ) ) === '';
	}

	/**
	 * Builds a unique slug for caption-less posts from the post's image, so that
	 * identical generic titles don't collapse to one slug.
	 */
	private function image_url_to_slug( string $image_url, string $title ): string {
		// Hash only the path basename — Instagram CDN query params are volatile.
		$path = wp_parse_url( $image_url, PHP_URL_PATH );
		$key  = $path ? basename( $path ) : $image_url;
		$hash = substr( md5( $key ), 0, 12 );

		// Cap the base title first, THEN append the hash so it is never truncated.
		return $this->title_to_slug( $title ) . '-' . $hash;
	}

	private function title_to_slug( string $title ): string {
		$slug = sanitize_title( $title );
		if ( strlen( $slug ) > 60 ) {
			$slug = substr( $slug, 0, 60 );
			$slug = rtrim( $slug, '-' );
		}
		return $slug ?: 'instagram-post';
	}

	private function extract_title( string $caption ): string {
		return $this->split_caption( $caption )['title'];
	}

	/**
	 * Splits an Instagram caption into a title and body.
	 *
	 * The title is the first sentence (ending in .!?) or the text up to the
	 * first line break, whichever comes first. The body is everything after.
	 *
	 * @return array{ title: string, body: string }
	 */
	private function split_caption( string $caption ): array {
		$text = html_entity_decode( $caption, ENT_QUOTES, 'UTF-8' );
		$text = trim( $text );

		if ( empty( $text ) ) {
			return array( 'title' => 'Instagram Post', 'body' => '' );
		}

		// Split at the first line break — a line break terminates the title.
		$parts      = preg_split( '/\r\n|\r|\n/', $text, 2 );
		$first_line = preg_replace( '/\s+/', ' ', trim( $parts[0] ) );
		$rest       = isset( $parts[1] ) ? trim( $parts[1] ) : '';

		// Within the first line, also check for sentence-ending punctuation.
		if ( preg_match( '/^(.+?[.!?])\s/', $first_line . ' ', $m ) && mb_strlen( $m[1] ) <= 200 ) {
			$title      = trim( $m[1] );
			$after      = trim( mb_substr( $first_line, mb_strlen( $title ) ) );
			$body_parts = array_filter( array( $after, $rest ) );
			return array(
				'title' => $title,
				'body'  => implode( "\n\n", $body_parts ),
			);
		}

		// No sentence end — use the entire first line as the title.
		if ( mb_strlen( $first_line ) <= 200 ) {
			return array(
				'title' => $first_line ?: 'Instagram Post',
				'body'  => $rest,
			);
		}

		// First line is too long — truncate at a word boundary.
		$cut        = mb_substr( $first_line, 0, 200 );
		$last_space = mb_strrpos( $cut, ' ' );
		if ( $last_space && $last_space > 100 ) {
			$cut = mb_substr( $cut, 0, $last_space );
		}
		$title      = rtrim( $cut, '.,;:!?&# ' ) . '…';
		$after      = trim( mb_substr( $first_line, mb_strlen( $cut ) ) );
		$body_parts = array_filter( array( $after, $rest ) );

		return array(
			'title' => $title,
			'body'  => implode( "\n\n", $body_parts ),
		);
	}
}
