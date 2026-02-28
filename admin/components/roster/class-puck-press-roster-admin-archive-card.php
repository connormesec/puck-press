<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Puck_Press_Roster_Admin_Archive_Card extends Puck_Press_Admin_Card_Abstract
{
	private $archive_db = null;

	public function init(): void
	{
		$this->get_archive_db();
	}

	private function get_archive_db(): Puck_Press_Roster_Archive_Wpdb_Utils
	{
		if ( ! $this->archive_db ) {
			$this->archive_db = new Puck_Press_Roster_Archive_Wpdb_Utils();
			$this->archive_db->init_tables();
		}
		return $this->archive_db;
	}

	protected function render_header_button_content(): string
	{
		return '';
	}

	protected function render_content(): string
	{
		$archives = $this->get_archive_db()->get_all_roster_archives();

		ob_start();

		if ( empty( $archives ) ) {
			echo '<p class="pp-empty-state">No roster archives yet. Use <strong>Advanced &rarr; Archive Roster</strong> to create one.</p>';
		} else {
			?>
			<table class="pp-table pp-archives-table">
				<thead>
					<tr>
						<th>Season</th>
						<th>Skaters</th>
						<th>Goalies</th>
						<th>Created</th>
						<th></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $archives as $archive ) :
						$created = ! empty( $archive['created_at'] ) ? date( 'M j, Y', strtotime( $archive['created_at'] ) ) : '&mdash;';
					?>
					<tr data-key="<?php echo esc_attr( $archive['archive_key'] ); ?>">
						<td><?php echo esc_html( $archive['season'] ); ?></td>
						<td><?php echo esc_html( $archive['skater_count'] ); ?></td>
						<td><?php echo esc_html( $archive['goalie_count'] ); ?></td>
						<td><?php echo $created; ?></td>
						<td>
							<button
								class="pp-button-icon pp-delete-roster-archive"
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

	public function ajax_get_stats_count(): void
	{
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( 'Unauthorized' );
		}
		$counts = $this->get_archive_db()->get_live_stats_count();
		wp_send_json_success( $counts );
		wp_die();
	}

	public function ajax_create_roster_archive(): void
	{
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( 'Unauthorized' );
		}

		$season = sanitize_text_field( $_POST['season'] ?? '' );
		if ( empty( $season ) ) {
			wp_send_json_error( 'Season is required.' );
		}

		$db = $this->get_archive_db();

		if ( $db->roster_archive_season_exists( $season ) ) {
			wp_send_json_error( 'A roster snapshot for this season already exists.' );
		}

		$counts = $db->get_live_stats_count();
		if ( $counts['skater_count'] === 0 && $counts['goalie_count'] === 0 ) {
			wp_send_json_error( 'There are no stats in the current roster to archive.' );
		}

		$archive_key = sanitize_title( $season );
		$db->create_stats_archive( $archive_key, $season );

		wp_send_json_success( [ 'html' => $this->render_content() ] );
		wp_die();
	}

	public function ajax_delete_roster_archive(): void
	{
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( 'Unauthorized' );
		}

		$archive_key = sanitize_text_field( $_POST['archive_key'] ?? '' );
		if ( empty( $archive_key ) ) {
			wp_send_json_error( 'Invalid archive key.' );
		}

		$this->get_archive_db()->delete_stats_archive( $archive_key );
		wp_send_json_success();
		wp_die();
	}
}
