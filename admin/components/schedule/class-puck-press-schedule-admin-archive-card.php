<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Puck_Press_Schedule_Admin_Archive_Card extends Puck_Press_Admin_Card_Abstract {

	private $archive_db = null;

	public function init(): void {
		$this->archive_db = new Puck_Press_Schedule_Archive_Wpdb_Utils();
		$this->archive_db->init_tables();
	}

	private function get_archive_db(): Puck_Press_Schedule_Archive_Wpdb_Utils {
		if ( ! $this->archive_db ) {
			$this->archive_db = new Puck_Press_Schedule_Archive_Wpdb_Utils();
		}
		return $this->archive_db;
	}

	protected function render_header_button_content(): string {
		return '';
	}

	protected function render_content(): string {
		$archives = $this->get_archive_db()->get_all_archives();

		ob_start();

		if ( empty( $archives ) ) {
			echo '<p class="pp-empty-state">No archives yet. Use <strong>Advanced &rarr; Archive Season</strong> to create one.</p>';
		} else {
			?>
			<table class="pp-table pp-archives-table">
				<thead>
					<tr>
						<th>Season</th>
						<th>Games</th>
						<th>Date Range</th>
						<th>Created</th>
						<th>Shortcode</th>
						<th></th>
					</tr>
				</thead>
				<tbody>
					<?php
					foreach ( $archives as $archive ) :
						$date_range = '';
						if ( ! empty( $archive['date_min'] ) && ! empty( $archive['date_max'] ) ) {
							$date_range = date( 'M Y', strtotime( $archive['date_min'] ) ) . ' &ndash; ' . date( 'M Y', strtotime( $archive['date_max'] ) );
						}
						$created   = ! empty( $archive['created_at'] ) ? date( 'M j, Y', strtotime( $archive['created_at'] ) ) : '&mdash;';
						$shortcode = '[pp-schedule archive="' . esc_attr( $archive['season'] ) . '"]';
						?>
					<tr data-key="<?php echo esc_attr( $archive['archive_key'] ); ?>">
						<td><?php echo esc_html( $archive['season'] ); ?></td>
						<td><?php echo esc_html( $archive['game_count'] ); ?></td>
						<td><?php echo $date_range ?: '&mdash;'; ?></td>
						<td><?php echo $created; ?></td>
						<td>
							<div class="pp-shortcode-input-group">
								<input
									type="text"
									class="pp-shortcode-input"
									value="<?php echo esc_attr( $shortcode ); ?>"
									spellcheck="false"
									onfocus="this.select();"
									readonly>
								<button class="pp-shortcode-copy-btn" aria-label="Copy shortcode">
									<svg class="pp-shortcode-copy-icon" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
										<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 5H6a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2v-1M8 5a2 2 0 002 2h2a2 2 0 002-2M8 5a2 2 0 012-2h2a2 2 0 012 2m0 0h2a2 2 0 012 2v3m2 4H10m0 0l3-3m-3 3l3 3" />
									</svg>
								</button>
								<div class="pp-shortcode-tooltip">Copied!</div>
							</div>
						</td>
						<td>
							<button
								class="pp-button-icon pp-delete-archive"
								data-key="<?php echo esc_attr( $archive['archive_key'] ); ?>"
								title="Delete archive"
							>🗑️</button>
						</td>
					</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
			<?php
		}

		return ob_get_clean();
	}

	public function ajax_get_game_count(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( 'Unauthorized' );
		}
		$count = $this->get_archive_db()->get_display_game_count();
		wp_send_json_success( array( 'count' => $count ) );
		wp_die();
	}

	public function ajax_create_archive(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( 'Unauthorized' );
		}

		$season = sanitize_text_field( $_POST['season'] ?? '' );
		if ( empty( $season ) ) {
			wp_send_json_error( 'Season is required.' );
		}

		$db = $this->get_archive_db();

		if ( $db->season_exists( $season ) ) {
			wp_send_json_error( 'A snapshot for this season already exists.' );
		}

		if ( $db->get_display_game_count() === 0 ) {
			wp_send_json_error( 'There are no games in the current schedule to archive.' );
		}

		$archive_key = sanitize_title( $season );
		$created     = $db->create_archive( $archive_key, $season );

		if ( ! $created ) {
			wp_send_json_error( 'Archive creation failed. Make sure there are games in the schedule.' );
		}

		wp_send_json_success( array( 'html' => $this->render_content() ) );
		wp_die();
	}

	public function ajax_delete_archive(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( 'Unauthorized' );
		}

		$archive_key = sanitize_text_field( $_POST['archive_key'] ?? '' );
		if ( empty( $archive_key ) ) {
			wp_send_json_error( 'Invalid archive key.' );
		}

		$this->get_archive_db()->delete_archive( $archive_key );
		wp_send_json_success();
		wp_die();
	}
}
