<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Puck_Press_Instagram_Post_Importer {

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

			wp_remote_post(
				admin_url( 'admin-ajax.php' ),
				array(
					'blocking' => false,
					'timeout'  => 1,
					'body'     => array(
						'action'  => 'pp_run_team_insta_import',
						'team_id' => $team_id,
						'secret'  => $secret,
					),
				)
			);

			$messages[] = "Team {$team_id} ({$handle}): dispatched async import.";
		}

		return $messages;
	}

	public static function handle_loopback_team_import(): void {
		$secret   = get_option( 'pp_insta_loopback_secret', '' );
		$provided = isset( $_POST['secret'] ) ? sanitize_text_field( wp_unslash( $_POST['secret'] ) ) : '';

		if ( empty( $secret ) || ! hash_equals( $secret, $provided ) ) {
			wp_send_json_error( 'Unauthorized', 403 );
			return;
		}

		$team_id = isset( $_POST['team_id'] ) ? (int) $_POST['team_id'] : 0;
		if ( $team_id <= 0 ) {
			wp_send_json_error( 'Invalid team ID', 400 );
			return;
		}

		$importer = new self();
		$messages = $importer->run_for_team( $team_id );
		wp_send_json_success( $messages );
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

		foreach ( $fetch_result['data'] as $post_data ) {
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
				$formatted_posts[] = array(
					'insta_id'     => ! empty( $post['post_id'] ) ? $post['post_id'] : ( $post['postSlug'] ?? '' ),
					'slug'         => $this->title_to_slug( $post['postTitle'] ),
					'post_title'   => $post['postTitle'],
					'post_body'    => $post['postText'] ?? '',
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
			'post_author'  => get_current_user_id(),
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
			$image_id = $this->save_base64_image_to_media( $b64_image, $image_name, $post_id );
			if ( $image_id && ! is_wp_error( $image_id ) ) {
				set_post_thumbnail( $post_id, $image_id );
			}
		}

		return $post_id;
	}

	/**
	 * @return int|WP_Error
	 */
	private function save_base64_image_to_media( string $b64_image, string $filename = 'instagram-image.png', int $post_id = 0 ) {
		$decoded = base64_decode( $b64_image );
		if ( ! $decoded ) {
			return new WP_Error( 'b64_decode_error', 'Failed to decode base64 image.' );
		}

		$upload_dir = wp_upload_dir();
		$file_path  = trailingslashit( $upload_dir['path'] ) . $filename;

		file_put_contents( $file_path, $decoded );

		$filetype = wp_check_filetype( $filename, null );

		$attachment = array(
			'post_mime_type' => $filetype['type'],
			'post_title'     => sanitize_file_name( pathinfo( $filename, PATHINFO_FILENAME ) ),
			'post_content'   => '',
			'post_status'    => 'inherit',
		);

		$attach_id = wp_insert_attachment( $attachment, $file_path, $post_id );

		require_once ABSPATH . 'wp-admin/includes/image.php';
		$attach_data = wp_generate_attachment_metadata( $attach_id, $file_path );
		wp_update_attachment_metadata( $attach_id, $attach_data );

		return $attach_id;
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

	private function title_to_slug( string $title ): string {
		$slug = sanitize_title( $title );
		if ( strlen( $slug ) > 60 ) {
			$slug = substr( $slug, 0, 60 );
			$slug = rtrim( $slug, '-' );
		}
		return $slug ?: 'instagram-post';
	}
}
