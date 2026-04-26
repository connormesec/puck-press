<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Puck_Press_Game_Post_Creator {

	private $table_name;

	public function __construct() {
		global $wpdb;
		$this->table_name = $wpdb->prefix . 'pp_schedule_games_display';
	}

	/**
	 * Entry point for cron job
	 */
	public function run_daily() {
		$messages        = array();
		$completed_games = $this->get_recent_completed_games( 3 );
		if ( empty( $completed_games ) ) {
			$messages[] = 'No completed games found.';
			return $messages;
		}
		if ( ! get_option( 'pp_enable_game_summary_post', 0 ) ) {
			$messages[] = 'Game summary post feature is disabled.';
			return $messages;
		}

		include_once plugin_dir_path( __FILE__ ) . 'class-puck-press-post-game-summary-initiator.php';

		foreach ( $completed_games as $game ) {

			// Guard: a custom URL or prior auto-post permalink is already stored
			if ( ! empty( $game->post_link ) ) {
				$messages[] = "Game {$game->game_id} already has a post link. Skipping.";
				continue;
			}

			// Guard: a WordPress post with _game_id meta already exists
			if ( $this->game_post_exists( $game->game_id ) ) {
				$messages[] = "Post for game {$game->game_id} already exists. Skipping.";
				continue;
			}

			$initiator = new Puck_Press_Post_Game_Summary_Initiator( $game->game_id, $game->source_type );

			$data = array(
				'image'     => null,
				'blog_data' => null,
				'errors'    => array(),
			);

			try {
				$summary    = $initiator->returnGameDataInImageAPIFormat();
				$messages[] = 'Summary data generated successfully.';
			} catch ( Throwable $e ) {
				$messages[] = 'Failed to generate summary data: ' . $e->getMessage();
				continue;
			}

			try {
				$data['image'] = $initiator->getImageFromImageAPI( $summary );
				$messages[]    = 'Image generated successfully.';
			} catch ( Throwable $e ) {
				$messages[]       = 'Failed to generate image: ' . $e->getMessage();
				$data['errors'][] = 'Image generation failed: ' . $e->getMessage();
			}

			try {
				$data['blog_data'] = $initiator->getGameSummaryFromBlogAPI( $summary );
				$messages[]        = 'Blog summary generated successfully.';
			} catch ( Throwable $e ) {
				$messages[]       = 'Failed to generate blog summary: ' . $e->getMessage();
				$data['errors'][] = 'Blog summary failed: ' . $e->getMessage();
			}

			try {
				if ( empty( $data['blog_data'] ) || empty( $data['image'] ) ) {
					$messages[] = 'Missing blog text or image for post creation.';
				} else {
					$link_result               = $this->linkify_player_names( $data['blog_data']['body'], $data['blog_data']['prompt_players'] ?? array() );
					$data['blog_data']['body'] = $link_result['body'];
					$mentioned_slugs           = $link_result['slugs'];

					$post_title = $data['blog_data']['title'];
					$permalink  = $this->testCreatePost( $game->game_id, $data['blog_data']['body'], $post_title, $data['image'], $mentioned_slugs, (int) $game->team_id );

					if ( ! is_wp_error( $permalink ) ) {
						$this->save_post_link_for_game( $game->game_id, $permalink, (int) $game->team_id );
						$messages[] = 'Post created successfully: ' . $permalink;
					} else {
						$messages[]       = 'Post creation failed: ' . $permalink->get_error_message();
						$data['errors'][] = 'Post creation failed: ' . $permalink->get_error_message();
					}
				}
			} catch ( Throwable $e ) {
				$data['errors'][] = 'Post creation failed: ' . $e->getMessage();
			}
		}
		return $messages;
	}

	/**
	 * Scan all published WordPress posts and link any that match a game in the
	 * schedule back into pp_team_game_mods / pp_team_games_display / pp_schedule_games_display.
	 */
	public function autodiscover_post_links() {
		global $wpdb;

		$messages = array();
		$found    = 0;
		$skipped  = 0;

		$games = $wpdb->get_results(
			"SELECT game_id, team_id FROM {$this->table_name}
             WHERE post_link IS NULL OR post_link = ''"
		);

		if ( empty( $games ) ) {
			$messages[] = 'All games already have post links. Nothing to discover.';
			return $messages;
		}

		$posts_with_meta = get_posts(
			array(
				'post_type'      => array( 'post', 'pp_game_summary' ),
				'post_status'    => 'publish',
				'posts_per_page' => -1,
				'meta_key'       => '_game_id',
				'fields'         => 'ids',
			)
		);

		$meta_lookup = array();
		foreach ( $posts_with_meta as $post_id ) {
			$val = get_post_meta( $post_id, '_game_id', true );
			if ( $val !== '' && $val !== false ) {
				$meta_lookup[ $val ] = $post_id;
			}
		}

		foreach ( $games as $game ) {
			$game_id      = $game->game_id;
			$team_id      = (int) $game->team_id;
			$game_id_slug = sanitize_title( $game_id );
			$matched_id   = null;

			if ( isset( $meta_lookup[ $game_id_slug ] ) ) {
				$matched_id = $meta_lookup[ $game_id_slug ];
			}

			if ( ! $matched_id ) {
				$suffix     = '-' . $game_id_slug;
				$suffix_len = strlen( $suffix );
				foreach ( $meta_lookup as $meta_val => $post_id ) {
					if ( strlen( $meta_val ) > $suffix_len &&
						substr( $meta_val, -$suffix_len ) === $suffix ) {
						$matched_id = $post_id;
						break;
					}
				}
			}

			if ( ! $matched_id ) {
				$fallback = get_posts(
					array(
						'post_type'      => array( 'post', 'pp_game_summary' ),
						'post_status'    => 'publish',
						'name'           => $game_id_slug,
						'posts_per_page' => 1,
						'fields'         => 'ids',
					)
				);
				if ( $fallback ) {
					$matched_id = $fallback[0];
					if ( ! get_post_meta( $matched_id, '_game_id', true ) ) {
						update_post_meta( $matched_id, '_game_id', $game_id_slug );
					}
				}
			}

			if ( $matched_id ) {
				$permalink = get_permalink( $matched_id );
				$this->save_post_link_for_game( $game_id, $permalink, $team_id );
				$this->set_team_ids_on_post( $matched_id, $this->get_team_ids_for_game( $game_id, $team_id ) );
				$messages[] = "Linked game {$game_id} → {$permalink}";
				++$found;
			} else {
				++$skipped;
			}
		}

		$messages[] = "Done. Found and linked {$found} post(s). {$skipped} game(s) had no matching post.";
		return $messages;
	}

	private function get_main_schedule_id() {
		global $wpdb;
		$id = $wpdb->get_var(
			"SELECT id FROM {$wpdb->prefix}pp_schedules WHERE is_main = 1 LIMIT 1"
		);
		if ( ! $id ) {
			$id = $wpdb->get_var(
				"SELECT id FROM {$wpdb->prefix}pp_schedules ORDER BY id ASC LIMIT 1"
			);
		}
		return $id ? (int) $id : null;
	}

	private function get_recent_completed_games( $limit = 3 ) {
		global $wpdb;

		$schedule_id = $this->get_main_schedule_id();
		if ( ! $schedule_id ) {
			return array();
		}

		return $wpdb->get_results(
			$wpdb->prepare(
				"SELECT *
                 FROM {$this->table_name}
                 WHERE schedule_id = %d
                   AND game_status REGEXP %s
                   AND game_timestamp < NOW()
                 ORDER BY game_timestamp DESC
                 LIMIT %d",
				$schedule_id,
				'^Final',
				$limit
			)
		);
	}

	private function game_post_exists( $game_id ) {
		$posts = get_posts(
			array(
				'post_type'      => array( 'post', 'pp_game_summary' ),
				'meta_key'       => '_game_id',
				'meta_value'     => sanitize_title( $game_id ),
				'post_status'    => 'publish',
				'posts_per_page' => 1,
				'fields'         => 'ids',
			)
		);
		return ! empty( $posts );
	}

	/**
	 * Persist a post permalink back into the schedule pipeline.
	 *
	 * 1. Upsert into pp_team_game_mods (persists through any rebuild)
	 * 2. Update pp_team_games_display directly (shows immediately)
	 * 3. Update all rows in pp_schedule_games_display by game_id (all team perspectives)
	 */
	public function save_post_link_for_game( $game_id, $permalink, $team_id = null ) {
		global $wpdb;

		$current_time  = current_time( 'mysql' );
		$mods_table    = $wpdb->prefix . 'pp_team_game_mods';
		$team_display  = $wpdb->prefix . 'pp_team_games_display';
		$sched_display = $wpdb->prefix . 'pp_schedule_games_display';

		if ( $team_id ) {
			$existing = $wpdb->get_row(
				$wpdb->prepare(
					"SELECT id, edit_data FROM {$mods_table}
                     WHERE external_id = %s AND team_id = %d AND edit_action = 'update'
                     LIMIT 1",
					$game_id,
					$team_id
				)
			);

			if ( $existing ) {
				$fields              = json_decode( $existing->edit_data, true ) ?: array();
				$fields['post_link'] = $permalink;
				$wpdb->update(
					$mods_table,
					array(
						'edit_data'  => wp_json_encode( $fields ),
						'updated_at' => $current_time,
					),
					array( 'id' => (int) $existing->id ),
					array( '%s', '%s' ),
					array( '%d' )
				);
			} else {
				$wpdb->insert(
					$mods_table,
					array(
						'team_id'     => $team_id,
						'external_id' => $game_id,
						'edit_action' => 'update',
						'edit_data'   => wp_json_encode(
							array(
								'external_id' => $game_id,
								'post_link'   => $permalink,
							)
						),
						'created_at'  => $current_time,
						'updated_at'  => $current_time,
					),
					array( '%d', '%s', '%s', '%s', '%s', '%s' )
				);
			}

			$wpdb->update(
				$team_display,
				array( 'post_link' => $permalink ),
				array( 'game_id' => $game_id, 'team_id' => $team_id ),
				array( '%s' ),
				array( '%s', '%d' )
			);
		}

		// Update all perspectives of this game in the schedule display table
		$wpdb->update(
			$sched_display,
			array( 'post_link' => $permalink ),
			array( 'game_id' => $game_id ),
			array( '%s' ),
			array( '%s' )
		);
	}

	public function delete_post_for_game( $game_id ) {
		$posts = get_posts(
			array(
				'post_type'      => array( 'post', 'pp_game_summary' ),
				'meta_key'       => '_game_id',
				'meta_value'     => sanitize_title( $game_id ),
				'post_status'    => 'publish',
				'posts_per_page' => 1,
				'fields'         => 'ids',
			)
		);

		foreach ( $posts as $post_id ) {
			wp_delete_post( $post_id, true );
		}

		$this->save_post_link_for_game( $game_id, '' );
	}

	/**
	 * Replace each prompt player's name in $body with a link to their player page.
	 * Only players found in pp_team_players_display are linked; others are silently skipped.
	 *
	 * @param string $body           Post body HTML.
	 * @param array  $prompt_players Array of full name strings.
	 * @return array { body: string, slugs: string[] }
	 */
	public function linkify_player_names( string $body, array $prompt_players ): array {
		global $wpdb;
		$slugs = array();

		foreach ( $prompt_players as $name ) {
			$full_name = trim( $name );
			if ( empty( $full_name ) || $full_name === 'Unknown Player' ) {
				continue;
			}

			$found = $wpdb->get_var(
				$wpdb->prepare(
					"SELECT name FROM {$wpdb->prefix}pp_team_players_display WHERE name = %s LIMIT 1",
					$full_name
				)
			);

			if ( ! $found ) {
				continue;
			}

			$slug    = sanitize_title( $full_name );
			$href    = home_url( '/player/' . $slug . '/' );
			$link    = '<a href="' . esc_url( $href ) . '" class="pp-player-link">' . esc_html( $full_name ) . '</a>';
			$body    = str_replace( $full_name, $link, $body );
			$slugs[] = $slug;
		}

		return array(
			'body'  => $body,
			'slugs' => array_unique( $slugs ),
		);
	}

	private function get_team_ids_for_game( string $game_id, int $primary_team_id ): array {
		global $wpdb;
		$ids = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT DISTINCT team_id FROM {$wpdb->prefix}pp_schedule_games_display WHERE game_id = %s AND team_id > 0",
				$game_id
			)
		);
		$ids = array_map( 'intval', $ids ?: array() );
		if ( $primary_team_id > 0 && ! in_array( $primary_team_id, $ids, true ) ) {
			$ids[] = $primary_team_id;
		}
		return $ids;
	}

	private function set_team_ids_on_post( int $post_id, array $team_ids ): void {
		delete_post_meta( $post_id, '_pp_team_id' );
		foreach ( $team_ids as $team_id ) {
			if ( $team_id > 0 ) {
				add_post_meta( $post_id, '_pp_team_id', $team_id, false );
			}
		}
	}

	private function create_game_post( $slug, $post_body, $post_title, $image_buffer = null, $mentioned_slugs = array() ) {
		$post_data = array(
			'post_title'   => $post_title,
			'post_content' => $post_body,
			'post_status'  => 'publish',
			'post_author'  => ( ( $pp_user = get_user_by( 'login', 'puck-press' ) ) ? $pp_user->ID : 1 ),
			'post_name'    => $slug,
			'post_type'    => 'pp_game_summary',
		);

		$post_id = wp_insert_post( $post_data );

		if ( ! is_wp_error( $post_id ) ) {
			update_post_meta( $post_id, '_game_id', sanitize_title( $slug ) );

			if ( ! empty( $mentioned_slugs ) ) {
				update_post_meta( $post_id, '_mentioned_player_slugs', wp_json_encode( $mentioned_slugs ) );
			}

			if ( ! empty( $image_buffer ) ) {
				$this->attach_featured_image_from_base64( $post_id, $image_buffer, $slug );
			}

			$this->enforce_post_cap();
		}

		return $post_id;
	}

	private function enforce_post_cap() {
		$max = (int) get_option( 'pp_game_summary_max_count', 0 );
		if ( $max <= 0 ) {
			return;
		}
		$posts = get_posts(
			array(
				'post_type'      => 'pp_game_summary',
				'post_status'    => 'publish',
				'posts_per_page' => -1,
				'orderby'        => 'date',
				'order'          => 'ASC',
				'fields'         => 'ids',
			)
		);
		$over  = count( $posts ) - $max;
		for ( $i = 0; $i < $over; $i++ ) {
			$pid       = $posts[ $i ];
			$permalink = get_permalink( $pid );
			$thumb_id  = get_post_thumbnail_id( $pid );
			if ( $thumb_id ) {
				wp_delete_attachment( $thumb_id, true );
			}
			wp_delete_post( $pid, true );

			if ( $permalink ) {
				global $wpdb;
				$wpdb->query(
					$wpdb->prepare(
						"UPDATE {$wpdb->prefix}pp_team_games_archive SET post_link = NULL WHERE post_link = %s",
						$permalink
					)
				);
			}
		}
	}

	private function attach_featured_image_from_base64( $post_id, $image_buffer, $slug ) {
		if ( strpos( $image_buffer, 'base64,' ) !== false ) {
			$image_buffer = explode( 'base64,', $image_buffer )[1];
		}

		$decoded = base64_decode( $image_buffer );
		if ( ! $decoded ) {
			error_log( "Failed to decode base64 image for game post {$post_id}" );
			return;
		}

		$upload_dir = wp_upload_dir();
		if ( ! empty( $upload_dir['error'] ) ) {
			error_log( 'Upload dir error: ' . $upload_dir['error'] );
			return;
		}

		$filename  = 'game-summary-' . sanitize_title( $slug ) . '-' . time() . '.png';
		$file_path = trailingslashit( $upload_dir['path'] ) . $filename;

		file_put_contents( $file_path, $decoded );

		$filetype   = wp_check_filetype( $filename, null );
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

		set_post_thumbnail( $post_id, $attach_id );
	}

	public function testCreatePost( $game_id, $post_body, $post_title, $image_buffer, $mentioned_slugs = array(), int $team_id = 0 ) {
		if ( $this->game_post_exists( $game_id ) ) {
			$msg = "Post for game_id {$game_id} already exists. Skipping creation.";
			error_log( $msg );
			return new WP_Error( 'post_exists', $msg );
		}

		$slug = sanitize_title( ( $post_title ?? '' ) . '-' . $game_id );

		$post_id = $this->create_game_post( $slug, $post_body, $post_title, $image_buffer, $mentioned_slugs );

		if ( is_wp_error( $post_id ) || ! $post_id ) {
			$msg = "Failed to create post for game_id {$game_id}.";
			error_log( $msg );
			return new WP_Error( 'post_failed', $msg );
		}

		$this->set_team_ids_on_post( $post_id, $this->get_team_ids_for_game( $game_id, $team_id ) );

		Puck_Press_Seo_Yoast::write_game_meta( $post_id, (string) $game_id );

		return get_permalink( $post_id );
	}
}
