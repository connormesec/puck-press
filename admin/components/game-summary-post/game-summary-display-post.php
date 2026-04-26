<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Puck_Press_Admin_Game_Summary_Post_Display {

	public function render() {
		if ( isset( $_POST['pp_save_keys'] ) ) {
			check_admin_referer( 'pp_save_game_summary_keys' );

			update_option( 'pp_openai_api_key', sanitize_text_field( $_POST['pp_openai_api_key'] ) );
			update_option( 'pp_image_api_key', sanitize_text_field( $_POST['pp_image_api_key'] ) );

			$enabled = isset( $_POST['pp_enable_game_summary_post'] ) ? 1 : 0;
			update_option( 'pp_enable_game_summary_post', $enabled );
			update_option( 'pp_game_summary_max_count', absint( $_POST['pp_game_summary_max_count'] ?? 0 ) );

			echo '<div class="updated"><p>Settings saved.</p></div>';
		}

		if ( isset( $_POST['pp_save_seo'] ) ) {
			check_admin_referer( 'pp_save_game_summary_seo' );

			update_option( 'pp_seo_enabled', isset( $_POST['pp_seo_enabled'] ) ? 1 : 0 );
			update_option( 'pp_seo_primary_keyword', sanitize_text_field( $_POST['pp_seo_primary_keyword'] ?? '' ) );
			update_option( 'pp_seo_team_short_name', sanitize_text_field( $_POST['pp_seo_team_short_name'] ?? '' ) );
			update_option( 'pp_seo_city', sanitize_text_field( $_POST['pp_seo_city'] ?? '' ) );
			update_option( 'pp_seo_state', sanitize_text_field( $_POST['pp_seo_state'] ?? '' ) );

			echo '<div class="updated"><p>SEO settings saved.</p></div>';
		}

		$openai_key = esc_attr( get_option( 'pp_openai_api_key', '' ) );
		$image_key  = esc_attr( get_option( 'pp_image_api_key', '' ) );
		$enabled    = get_option( 'pp_enable_game_summary_post', 0 );
		$max_count  = (int) get_option( 'pp_game_summary_max_count', 0 )
		?>
		<div class="wrap">
			<h1>Game Summary Post Maker</h1>
			<form method="post">
				<?php wp_nonce_field( 'pp_save_game_summary_keys' ); ?>
				<table class="form-table">
					<tr>
						<th scope="row">Enable Game Summary Post Feature</th>
						<td>
							<label>
								<input type="checkbox" name="pp_enable_game_summary_post" value="1" <?php checked( $enabled, 1 ); ?> />
								Enable automatic creation of game summary posts
							</label>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="pp_openai_api_key">OpenAI API Key</label></th>
						<td><input type="text" name="pp_openai_api_key" id="pp_openai_api_key"
								value="<?php echo $openai_key; ?>" class="regular-text" /></td>
					</tr>
					<tr>
						<th scope="row"><label for="pp_image_api_key">Image API Key</label></th>
						<td><input type="text" name="pp_image_api_key" id="pp_image_api_key"
								value="<?php echo $image_key; ?>" class="regular-text" /></td>
					</tr>
					<tr>
						<th scope="row"><label for="pp_game_summary_max_count">Max Game Recap Posts</label></th>
						<td>
							<input type="number" name="pp_game_summary_max_count" id="pp_game_summary_max_count"
								value="<?php echo $max_count; ?>" class="small-text" min="0" />
							<p class="description">Maximum number of game recap posts to keep. When a new post is created and the limit is exceeded, the oldest post is deleted. Set to 0 for unlimited.</p>
						</td>
					</tr>
				</table>

				<?php submit_button( 'Save Settings', 'primary', 'pp_save_keys' ); ?>
			</form>

			<h2>Test API Connections</h2>
			<p>
				<button class="button button-secondary" id="pp-test-openai">Test OpenAI API</button>
				<span id="pp-test-openai-result"></span>
			</p>

			<?php $this->render_game_summary_test(); ?>

			<?php $this->render_seo_section(); ?>
		</div>
		<?php
	}

	private function render_seo_section(): void {
		require_once plugin_dir_path( __DIR__ ) . '../../includes/seo/class-puck-press-seo-detector.php';

		$detected = Puck_Press_Seo_Detector::detect();
		$seo_on   = (bool) get_option( 'pp_seo_enabled', 1 );
		$kw       = (string) get_option( 'pp_seo_primary_keyword', '' );
		$short    = (string) get_option( 'pp_seo_team_short_name', '' );
		$city     = (string) get_option( 'pp_seo_city', '' );
		$state    = (string) get_option( 'pp_seo_state', '' );

		require_once plugin_dir_path( __DIR__ ) . '../../includes/seo/class-puck-press-seo-avatar.php';

		global $wpdb;

		$yoast_active   = is_plugin_active( 'wordpress-seo/wp-seo.php' );
		$author_user    = get_user_by( 'login', 'puck-press' );
		$author_bio     = $author_user ? (string) get_user_meta( $author_user->ID, 'description', true ) : '';
		$author_fb      = $author_user ? (string) get_user_meta( $author_user->ID, 'facebook', true ) : '';
		$author_ig      = $author_user ? (string) get_user_meta( $author_user->ID, 'instagram', true ) : '';
		$user_edit_url  = $author_user ? admin_url( 'user-edit.php?user_id=' . (int) $author_user->ID ) : admin_url( 'user-new.php' );
		$avatar_url     = Puck_Press_Seo_Avatar::resolved_url();

		// Reassign-status auto-detection
		$total_recaps   = (int) $wpdb->get_var(
			"SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = 'pp_game_summary'"
		);
		$mismatched     = $author_user ? (int) $wpdb->get_var( $wpdb->prepare(
			"SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = 'pp_game_summary' AND post_author != %d",
			$author_user->ID
		) ) : $total_recaps;
		$reassign_done  = ( $total_recaps === 0 ) || ( $mismatched === 0 );
		?>
		<h2>SEO</h2>
		<p class="description">
			Auto-applies Yoast metadata to new game recap posts and emits Schema.org <code>SportsEvent</code> + <code>NewsArticle</code> JSON-LD on recap and schedule pages.
			Most fields auto-derive from your game data — overrides below are optional.
			<?php if ( ! $yoast_active ) : ?>
				<br /><strong>Yoast SEO is not active.</strong> SEO features are inactive until Yoast is installed and activated.
			<?php endif; ?>
		</p>

		<h3 style="margin-top:1.5em;">One-time setup checklist</h3>
		<p class="description">Complete these once per site. The plugin will then auto-tag every new game recap with full SEO metadata, structured data, and a proper author byline.</p>
		<ul style="list-style:none;padding-left:0;margin:0 0 1em 0;">
			<li><?php echo $yoast_active ? '✅' : '⬜'; ?> <strong>Yoast SEO plugin installed and active</strong>
				<?php if ( ! $yoast_active ) : ?>
					&nbsp;<a href="<?php echo esc_url( admin_url( 'plugin-install.php?s=yoast+seo&tab=search&type=term' ) ); ?>">Install Yoast</a>
				<?php endif; ?>
			</li>
			<li><?php echo $author_user ? '✅' : '⬜'; ?> <strong>Author user created</strong>
				<?php if ( ! $author_user ) : ?>
					&nbsp;<a href="<?php echo esc_url( admin_url( 'user-new.php' ) ); ?>">Add user</a> with Username <code>puck-press</code>, Role <em>Author</em>, and a team-branded Display Name (e.g. "MSU Bobcats Hockey").
				<?php else : ?>
					(<code>puck-press</code> · displays as "<?php echo esc_html( $author_user->display_name ); ?>")
				<?php endif; ?>
			</li>
			<li><?php echo ( $author_user && $author_bio !== '' ) ? '✅' : '⬜'; ?> <strong>Author bio filled in</strong>
				<?php if ( $author_user && $author_bio === '' ) : ?>
					&nbsp;<a href="<?php echo esc_url( $user_edit_url ); ?>">Edit user</a> → Biographical Info. Be transparent: e.g. "Game recaps from the [Team] program. Stats sourced from [League]; summaries are auto-generated and reviewed by team staff."
				<?php endif; ?>
			</li>
			<li><?php echo ( $author_user && ( $author_fb !== '' || $author_ig !== '' ) ) ? '✅' : '⬜'; ?> <strong>Author social URLs filled in (Yoast section of user profile)</strong>
				<?php if ( $author_user && $author_fb === '' && $author_ig === '' ) : ?>
					&nbsp;<a href="<?php echo esc_url( $user_edit_url ); ?>">Edit user</a> → scroll to Yoast SEO section → paste your team's Facebook + Instagram URLs.
				<?php endif; ?>
			</li>
			<li><?php echo $avatar_url ? '✅' : '⬜'; ?> <strong>Author profile photo</strong>
				<?php if ( $avatar_url ) : ?>
					— Puck Press is auto-serving the team logo for the <code>puck-press</code> author byline (source: <code><?php echo esc_html( basename( $avatar_url ) ); ?></code>). Renders in Google's author chips.
				<?php else : ?>
					— Set the Yoast organization logo (Yoast → Search Appearance → Knowledge Graph → Organization Logo) and Puck Press will auto-serve it as the team byline avatar. Or upload one via Gravatar / a local-avatar plugin.
				<?php endif; ?>
			</li>
			<li><?php echo $reassign_done ? '✅' : '⬜'; ?> <strong>Reassign existing recaps to the author</strong>
				<?php if ( $total_recaps === 0 ) : ?>
					&nbsp;<em>(no game recaps yet — nothing to reassign)</em>
				<?php elseif ( $reassign_done ) : ?>
					&nbsp;(<?php echo (int) $total_recaps; ?>/<?php echo (int) $total_recaps; ?> recaps authored by <code>puck-press</code>)
				<?php else : ?>
					&nbsp;<?php echo (int) $mismatched; ?> of <?php echo (int) $total_recaps; ?> recaps still attributed to a different user. One-time DB update — run this SQL: <code style="display:block;margin-top:4px;padding:6px;background:#f0f0f1;">UPDATE wp_posts SET post_author = (SELECT ID FROM wp_users WHERE user_login = 'puck-press') WHERE post_type = 'pp_game_summary';</code>
				<?php endif; ?>
			</li>
		</ul>

		<p class="description" style="margin-top:1em;">
			<strong>After setup:</strong> paste a recap URL into <a href="https://search.google.com/test/rich-results" target="_blank" rel="noopener">Google's Rich Results Test</a> to confirm the schema is readable. Look for "Sports event" and "Article" both detected as valid.
		</p>

		<form method="post">
			<?php wp_nonce_field( 'pp_save_game_summary_seo' ); ?>
			<table class="form-table">
			<tr>
				<th scope="row">Detected from your schedule</th>
				<td>
					<?php if ( $detected['school_name'] === '' ) : ?>
						<em>No game data yet — values will populate after the first schedule import.</em>
					<?php else : ?>
						<table style="border-collapse:collapse;">
							<tr><td style="padding:2px 12px 2px 0;color:#646970;">Team:</td>
								<td><?php echo esc_html( trim( $detected['school_name'] . ' · ' . $detected['team_nickname'], ' ·' ) ); ?></td></tr>
							<tr><td style="padding:2px 12px 2px 0;color:#646970;">Short name:</td>
								<td><?php echo esc_html( $detected['team_short_name'] ?: '—' ); ?></td></tr>
							<tr><td style="padding:2px 12px 2px 0;color:#646970;">League:</td>
								<td><?php echo esc_html( $detected['league'] ?: '—' ); ?>
								<?php echo $detected['division'] !== '' ? ' ' . esc_html( $detected['division'] ) : ''; ?></td></tr>
							<tr><td style="padding:2px 12px 2px 0;color:#646970;">Primary keyword:</td>
								<td><?php echo esc_html( $detected['primary_keyword'] ?: '—' ); ?></td></tr>
						</table>
					<?php endif; ?>
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="pp_seo_primary_keyword">Primary keyword (override)</label></th>
				<td>
					<input type="text" name="pp_seo_primary_keyword" id="pp_seo_primary_keyword"
						value="<?php echo esc_attr( $kw ); ?>" class="regular-text"
						placeholder="<?php echo esc_attr( $detected['primary_keyword'] ); ?>" />
					<p class="description">Used in recap titles &amp; descriptions. Leave blank to use the detected value above.</p>
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="pp_seo_team_short_name">Team short name (override)</label></th>
				<td>
					<input type="text" name="pp_seo_team_short_name" id="pp_seo_team_short_name"
						value="<?php echo esc_attr( $short ); ?>" class="regular-text"
						placeholder="<?php echo esc_attr( $detected['team_short_name'] ); ?>" />
					<p class="description">For compact titles like "MSU Bobcats vs Idaho." Leave blank to use the detected value.</p>
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="pp_seo_city">City</label></th>
				<td>
					<input type="text" name="pp_seo_city" id="pp_seo_city"
						value="<?php echo esc_attr( $city ); ?>" class="regular-text" placeholder="Bozeman" />
					<p class="description">Used in JSON-LD location data. Optional.</p>
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="pp_seo_state">State</label></th>
				<td>
					<input type="text" name="pp_seo_state" id="pp_seo_state"
						value="<?php echo esc_attr( $state ); ?>" class="regular-text" placeholder="MT" maxlength="2" />
					<p class="description">Two-letter state code. Optional.</p>
				</td>
			</tr>
			<tr>
				<th scope="row">Enable SEO features</th>
				<td>
					<label>
						<input type="checkbox" name="pp_seo_enabled" value="1" <?php checked( $seo_on, true ); ?> />
						Auto-apply Yoast metadata &amp; emit SportsEvent schema
					</label>
				</td>
			</tr>
		</table>
		<?php submit_button( 'Save SEO Settings', 'primary', 'pp_save_seo' ); ?>
		</form>
		<?php
	}

	public function ajax_test_openai() {
		check_ajax_referer( 'pp_game_summary_nonce', 'nonce' );

		$api_key = get_option( 'pp_openai_api_key' );
		if ( empty( $api_key ) ) {
			wp_send_json_error( 'OpenAI API key not set.' );
		}

		$response = wp_remote_post(
			'https://api.openai.com/v1/chat/completions',
			array(
				'headers' => array(
					'Authorization' => 'Bearer ' . $api_key,
					'Content-Type'  => 'application/json',
				),
				'body'    => wp_json_encode(
					array(
						'model'      => 'gpt-4o-mini',
						'messages'   => array(
							array(
								'role'    => 'user',
								'content' => 'Write a short sample hockey game summary.',
							),
						),
						'max_tokens' => 100,
					)
				),
			)
		);

		if ( is_wp_error( $response ) ) {
			wp_send_json_error( $response->get_error_message() );
		}

		$code = wp_remote_retrieve_response_code( $response );
		if ( $code !== 200 ) {
			wp_send_json_error( 'OpenAI API returned status: ' . $code );
		}

		$body = json_decode( wp_remote_retrieve_body( $response ), true );
		$text = $body['choices'][0]['message']['content'] ?? 'No content returned';

		wp_send_json_success( $text );
	}

	function pp_create_game_summary() {
		check_ajax_referer( 'pp_game_summary_nonce', 'nonce' );

		$api_key = get_option( 'pp_image_api_key' );
		if ( empty( $api_key ) ) {
			wp_send_json_error( array( 'error' => 'Image API key not set.' ) );
		}

		$game_id     = isset( $_POST['game_id'] ) ? intval( $_POST['game_id'] ) : 0;
		$source_type = isset( $_POST['source_type'] ) ? sanitize_text_field( $_POST['source_type'] ) : '';
		$team_id     = isset( $_POST['team_id'] ) ? intval( $_POST['team_id'] ) : 0;

		if ( ! $game_id || ! $source_type ) {
			wp_send_json_error( array( 'error' => 'No game ID or source type provided.' ) );
		}

		if ( isset( $_POST['pp_action'] ) ) {
			$action = sanitize_text_field( $_POST['pp_action'] );

			include_once plugin_dir_path( __DIR__ ) . '../../includes/game-summary-post/class-puck-press-post-game-summary-initiator.php';

			if ( $action === 'generate' ) {

				$initiator = new Puck_Press_Post_Game_Summary_Initiator( $game_id, $source_type );

				$data = array(
					'image'     => null,
					'blog_data' => null,
					'errors'    => array(),
				);

				try {
					$summary = $initiator->returnGameDataInImageAPIFormat();
				} catch ( Throwable $e ) {
					wp_send_json_error(
						array(
							'error'   => 'Failed to generate summary data',
							'details' => $e->getMessage(),
						)
					);
				}

				try {
					$data['image'] = $initiator->getImageFromImageAPI( $summary );
				} catch ( Throwable $e ) {
					$data['errors'][] = 'Image generation failed: ' . $e->getMessage();
				}

				try {
					$data['blog_data'] = $initiator->getGameSummaryFromBlogAPI( $summary );
				} catch ( Throwable $e ) {
					$data['errors'][] = 'Blog summary failed: ' . $e->getMessage();
				}

				if ( ! empty( $data['blog_data'] ) ) {
					include_once plugin_dir_path( __DIR__ ) . '../../includes/game-summary-post/class-puck-press-game-post-creator.php';
					$post_creator_preview      = new Puck_Press_Game_Post_Creator();
					$link_result               = $post_creator_preview->linkify_player_names( $data['blog_data']['body'], $data['blog_data']['prompt_players'] ?? array() );
					$data['blog_data']['body'] = $link_result['body'];
				}

				wp_send_json_success( $data );
				wp_die();

			} elseif ( $action === 'generate_and_post' ) {
				include_once plugin_dir_path( __DIR__ ) . '../../includes/game-summary-post/class-puck-press-game-post-creator.php';
				$post_creator = new Puck_Press_Game_Post_Creator();
				$initiator    = new Puck_Press_Post_Game_Summary_Initiator( $game_id, $source_type );

				// Handle overwrite: delete existing post before creating new one
				if ( isset( $_POST['overwrite'] ) && $_POST['overwrite'] === '1' ) {
					$post_creator->delete_post_for_game( $game_id );
				}

				$data = array(
					'image'     => null,
					'blog_data' => null,
					'errors'    => array(),
				);

				try {
					$summary = $initiator->returnGameDataInImageAPIFormat();
				} catch ( Throwable $e ) {
					wp_send_json_error(
						array(
							'error'   => 'Failed to generate summary data',
							'details' => $e->getMessage(),
						)
					);
				}

				try {
					$data['image'] = $initiator->getImageFromImageAPI( $summary );
				} catch ( Throwable $e ) {
					$data['errors'][] = 'Image generation failed: ' . $e->getMessage();
				}

				try {
					$data['blog_data'] = $initiator->getGameSummaryFromBlogAPI( $summary );
				} catch ( Throwable $e ) {
					$data['errors'][] = 'Blog summary failed: ' . $e->getMessage();
				}

				try {
					if ( empty( $data['blog_data'] ) || empty( $data['image'] ) ) {
						throw new Exception( 'Missing blog text or image for post creation.' );
					} else {
						$link_result               = $post_creator->linkify_player_names( $data['blog_data']['body'], $data['blog_data']['prompt_players'] ?? array() );
						$data['blog_data']['body'] = $link_result['body'];
						$mentioned_slugs           = $link_result['slugs'];
						$permalink = $post_creator->testCreatePost( $game_id, $data['blog_data']['body'], $data['blog_data']['title'], $data['image'], $mentioned_slugs );
						if ( ! is_wp_error( $permalink ) ) {
							$post_creator->save_post_link_for_game( $game_id, $permalink, $team_id ?: null );
						}
						$data['post_link'] = $permalink;
					}
				} catch ( Throwable $e ) {
					$data['errors'][] = 'Post creation failed: ' . $e->getMessage();
				}

				wp_send_json_success( $data );
				wp_die();

			} else {
				wp_send_json_error( array( 'error' => 'Unknown action.' ) );
			}
		}
	}

	function render_game_summary_test() {
		global $wpdb;

		$table = $wpdb->prefix . 'pp_team_games_display';
		$today = current_time( 'Y-m-d' );

		$games = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT game_id, source_type, team_id, game_timestamp, game_date_day,
                        target_team_name, opponent_team_name, target_score, opponent_score, post_link
                 FROM {$table}
                 WHERE game_timestamp < %s
                   AND source_type IN ('achaGameScheduleUrl', 'usphlGameScheduleUrl')
                 ORDER BY game_timestamp DESC
                 LIMIT 50",
				$today
			)
		);

		?>
		<h1>Select Past Game</h1>

		<form id="pp-game-summary-form" method="post">
			<?php wp_nonce_field( 'pp_game_summary_nonce', 'pp_game_summary_nonce_field' ); ?>

			<select name="game_id" id="pp-game-select">
				<option value="">-- Select a past game --</option>
				<?php foreach ( $games as $game ) : ?>
					<option value="<?php echo esc_attr( $game->game_id ); ?>"
						data-source-type="<?php echo esc_attr( $game->source_type ); ?>"
						data-team-id="<?php echo esc_attr( $game->team_id ); ?>"
						data-post-link="<?php echo esc_attr( $game->post_link ?? '' ); ?>">
						<?php
						$label = "{$game->game_date_day} {$game->target_team_name} vs {$game->opponent_team_name} ({$game->target_score} - {$game->opponent_score})";
						if ( ! empty( $game->post_link ) ) {
							$label .= ' ✓';
						}
						echo esc_html( $label );
						?>
					</option>
				<?php endforeach; ?>
			</select>

			<br /><br />

			<button type="submit" class="button button-primary" id="pp-generate-btn">
				Create Game Summary
			</button>
		</form>

		<div id="pp-game-summary-result"></div>
		<?php
	}
}
