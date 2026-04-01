<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Puck_Press_Admin_Instagram_Post_Importer_Display {

	public function render() {
		if ( isset( $_POST['pp_save_global_settings'] ) ) {
			check_admin_referer( 'pp_insta_post_nonce' );

			update_option( 'pp_insta_scraper_api_key', sanitize_text_field( $_POST['pp_insta_scraper_api_key'] ) );
			$enabled = isset( $_POST['pp_enable_insta_post'] ) ? 1 : 0;
			update_option( 'pp_enable_insta_post', $enabled );
			update_option( 'pp_insta_post_max_count', absint( $_POST['pp_insta_post_max_count'] ?? 0 ) );

			echo '<div class="updated"><p>Global settings saved.</p></div>';
		}

		$api_key   = esc_attr( get_option( 'pp_insta_scraper_api_key', '' ) );
		$enabled   = get_option( 'pp_enable_insta_post', 0 );
		$max_count = (int) get_option( 'pp_insta_post_max_count', 0 );

		require_once plugin_dir_path( __FILE__ ) . '../../../includes/teams/class-puck-press-teams-wpdb-utils.php';
		$teams_utils = new Puck_Press_Teams_Wpdb_Utils();
		$teams       = $teams_utils->get_all_teams();
		?>
		<div class="wrap">
			<h1>Instagram Post Maker</h1>

			<h2>Global Settings</h2>
			<form method="post">
				<?php wp_nonce_field( 'pp_insta_post_nonce' ); ?>
				<table class="form-table">
					<tr>
						<th scope="row">Enable Instagram Import</th>
						<td>
							<label>
								<input type="checkbox" name="pp_enable_insta_post" value="1" <?php checked( $enabled, 1 ); ?> />
								Enable automatic daily import of Instagram posts
							</label>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="pp_insta_scraper_api_key">Instagram API Key</label></th>
						<td>
							<input type="text" name="pp_insta_scraper_api_key" id="pp_insta_scraper_api_key"
								value="<?php echo $api_key; ?>" class="regular-text" />
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="pp_insta_post_max_count">Max Instagram Posts</label></th>
						<td>
							<input type="number" name="pp_insta_post_max_count" id="pp_insta_post_max_count"
								value="<?php echo $max_count; ?>" class="small-text" min="0" />
							<p class="description">Maximum number of Instagram posts to keep. When a new post is created and the limit is exceeded, the oldest post is deleted. Set to 0 for unlimited.</p>
						</td>
					</tr>
				</table>
				<?php submit_button( 'Save Global Settings', 'primary', 'pp_save_global_settings' ); ?>
			</form>

			<h2>Team Instagram Handles</h2>
			<?php if ( empty( $teams ) ) : ?>
				<p>No teams found. Create a team first.</p>
			<?php else : ?>
				<table class="widefat striped" style="max-width: 700px;">
					<thead>
						<tr>
							<th>Team</th>
							<th>Instagram Handle</th>
							<th>Auto-import enabled</th>
							<th></th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( $teams as $team ) : ?>
							<?php
							$team_id      = (int) $team['id'];
							$team_name    = esc_html( $team['name'] );
							$team_handle  = esc_attr( get_option( "pp_team_{$team_id}_insta_handle", '' ) );
							$team_enabled = get_option( "pp_team_{$team_id}_insta_enabled", 0 );
							?>
							<tr data-team-id="<?php echo $team_id; ?>">
								<td><?php echo $team_name; ?></td>
								<td>
									<input type="text"
										class="pp-team-handle regular-text"
										value="<?php echo $team_handle; ?>"
										placeholder="e.g. nhl"
										style="width: 180px;" />
								</td>
								<td>
									<label>
										<input type="checkbox"
											class="pp-team-enabled"
											value="1"
											<?php checked( $team_enabled, 1 ); ?> />
										Enabled
									</label>
								</td>
								<td>
									<button class="button button-secondary pp-save-team-handle"
										data-team-id="<?php echo $team_id; ?>">
										Save
									</button>
									<span class="pp-save-team-result" style="margin-left: 8px;"></span>
								</td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			<?php endif; ?>

			<h2 style="margin-top: 30px;">Test &amp; Import by Team</h2>
			<?php if ( ! empty( $teams ) ) : ?>
				<div style="display: flex; align-items: center; gap: 12px; margin-bottom: 16px; flex-wrap: wrap;">
					<select id="pp-test-team-select" style="min-width: 180px;">
						<option value="">— select a team —</option>
						<?php foreach ( $teams as $team ) : ?>
							<option value="<?php echo (int) $team['id']; ?>">
								<?php echo esc_html( $team['name'] ); ?>
							</option>
						<?php endforeach; ?>
					</select>

					<button class="button button-secondary" id="pp-fetch-team-posts">Fetch Posts</button>
					<span id="pp-fetch-result"></span>
				</div>

				<div id="pp-test-posts-container" style="display: none; margin-top: 20px;">
					<div id="pp-test-posts-grid" style="display: grid; grid-template-columns: repeat(auto-fill, minmax(300px, 1fr)); gap: 20px;"></div>
				</div>
			<?php endif; ?>

			<style>
				.pp-post-item {
					border: 1px solid #ddd;
					border-radius: 8px;
					padding: 15px;
					background: #fff;
					box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
				}
				.pp-post-image {
					width: 100%;
					height: 200px;
					object-fit: cover;
					border-radius: 4px;
					margin-bottom: 10px;
				}
				.pp-post-title {
					font-weight: bold;
					font-size: 14px;
					line-height: 1.4;
					color: #333;
					margin-bottom: 6px;
				}
				.pp-post-caption {
					font-size: 13px;
					line-height: 1.4;
					color: #555;
					margin-bottom: 10px;
					max-height: 80px;
					overflow: hidden;
				}
				.pp-post-meta {
					font-size: 12px;
					color: #888;
					margin-bottom: 4px;
				}
				.pp-post-actions {
					margin-top: 10px;
					display: flex;
					align-items: center;
					gap: 8px;
				}
				.pp-loading { color: #0073aa; font-style: italic; }
				.pp-error   { color: #dc3232; }
				.pp-success { color: #46b450; }
			</style>
		</div>
		<?php
	}

	public function ajax_save_team_handle() {
		check_ajax_referer( 'pp_insta_post_nonce', 'nonce' );

		$team_id = isset( $_POST['team_id'] ) ? (int) $_POST['team_id'] : 0;
		if ( $team_id <= 0 ) {
			wp_send_json_error( 'Invalid team ID' );
			return;
		}

		$handle  = isset( $_POST['handle'] ) ? sanitize_text_field( wp_unslash( $_POST['handle'] ) ) : '';
		$enabled = isset( $_POST['enabled'] ) && '1' === $_POST['enabled'] ? 1 : 0;

		update_option( "pp_team_{$team_id}_insta_handle", $handle );
		update_option( "pp_team_{$team_id}_insta_enabled", $enabled );

		wp_send_json_success( 'Saved.' );
	}

	public function ajax_get_team_example_posts() {
		check_ajax_referer( 'pp_insta_post_nonce', 'nonce' );

		$team_id = isset( $_POST['team_id'] ) ? (int) $_POST['team_id'] : 0;
		if ( $team_id <= 0 ) {
			wp_send_json_error( 'Invalid team ID' );
			return;
		}

		$handle = get_option( "pp_team_{$team_id}_insta_handle", '' );
		if ( empty( $handle ) ) {
			wp_send_json_error( 'No Instagram handle configured for this team.' );
			return;
		}

		require_once plugin_dir_path( __FILE__ ) . '../../../includes/instagram-post-importer/class-puck-press-instagram-post-importer.php';

		$importer     = new Puck_Press_Instagram_Post_Importer();
		$existing_ids = $importer->get_existing_insta_ids( $team_id );
		$result       = $importer->fetch_instagram_posts( $existing_ids, $handle );

		if ( $result['success'] ) {
			wp_send_json_success( $result['data'] );
		} else {
			wp_send_json_error( $result['message'] );
		}
	}

	public function ajax_create_team_insta_post() {
		check_ajax_referer( 'pp_insta_post_nonce', 'nonce' );

		$team_id   = isset( $_POST['team_id'] ) ? (int) $_POST['team_id'] : 0;
		$insta_id  = isset( $_POST['insta_id'] ) ? sanitize_text_field( wp_unslash( $_POST['insta_id'] ) ) : '';
		$title     = isset( $_POST['post_title'] ) ? sanitize_text_field( wp_unslash( $_POST['post_title'] ) ) : '';
		$content   = isset( $_POST['post_body'] ) ? wp_kses_post( wp_unslash( $_POST['post_body'] ) ) : '';
		$slug      = isset( $_POST['slug'] ) ? sanitize_text_field( wp_unslash( $_POST['slug'] ) ) : '';
		$b64_image = isset( $_POST['image_buffer'] ) ? sanitize_text_field( wp_unslash( $_POST['image_buffer'] ) ) : '';

		if ( $team_id <= 0 || empty( $insta_id ) ) {
			wp_send_json_error( 'Missing required fields.' );
			return;
		}

		require_once plugin_dir_path( __FILE__ ) . '../../../includes/instagram-post-importer/class-puck-press-instagram-post-importer.php';

		$importer     = new Puck_Press_Instagram_Post_Importer();
		$existing_ids = $importer->get_existing_insta_ids( $team_id );

		if ( in_array( $insta_id, $existing_ids, true ) || preg_grep( '/^' . preg_quote( $insta_id, '/' ) . '-/', $existing_ids ) ) {
			wp_send_json_error( 'Post with Instagram ID ' . $insta_id . ' already exists.' );
			return;
		}

		$image_name = 'insta-' . $insta_id . '.jpg';
		$post_id    = $importer->create_instagram_post( $title, $content, 'publish', $slug, $b64_image, $image_name, $insta_id, $team_id );

		if ( is_wp_error( $post_id ) ) {
			wp_send_json_error( $post_id->get_error_message() );
			return;
		}

		wp_send_json_success( array( 'post_id' => $post_id ) );
	}
}
