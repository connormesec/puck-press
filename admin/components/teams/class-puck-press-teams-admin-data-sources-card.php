<?php
class Puck_Press_Teams_Admin_Data_Sources_Card extends Puck_Press_Admin_Card_Abstract {

	private $table_name = 'pp_team_sources';
	private int $team_id;

	public function __construct( array $args = array(), int $team_id = 0 ) {
		parent::__construct( $args );
		$this->team_id = $team_id;
	}

	public function render_header_button_content() {
		ob_start();
		?>
		<button class="pp-button pp-button-primary" id="pp-add-source-button">
			+ Add Source
		</button>
		<?php
		return ob_get_clean();
	}

	public function render_content() {
		ob_start();
		?>
		<div id="pp-data-sources-table">
			<?php echo $this->render_team_data_sources(); ?>
		</div>
		<?php
		return ob_get_clean();
	}

	public function render_team_data_sources() {
		global $wpdb;
		$wp_table_name = $wpdb->prefix . $this->table_name;

		$data_sources = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM $wp_table_name WHERE team_id = %d ORDER BY id ASC",
				$this->team_id
			)
		);

		ob_start();
		?>
		<table class="pp-table" id="pp-sources-table">
			<thead class="pp-thead">
				<tr>
					<th class="pp-th"><?php esc_html_e( 'Name', 'puck-press' ); ?></th>
					<th class="pp-th"><?php esc_html_e( 'Type', 'puck-press' ); ?></th>
					<th class="pp-th"><?php esc_html_e( 'URL', 'puck-press' ); ?></th>
					<th class="pp-th"><?php esc_html_e( 'Last Updated', 'puck-press' ); ?></th>
					<th class="pp-th"><?php esc_html_e( 'Status', 'puck-press' ); ?></th>
					<th class="pp-th"><?php esc_html_e( 'Actions', 'puck-press' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( $data_sources as $source ) : ?>
					<tr data-id="<?php echo esc_attr( $source->id ); ?>">
						<td class="pp-td"><?php echo esc_html( $source->name ); ?></td>
						<td class="pp-td"><span class="pp-tag pp-tag-<?php echo esc_attr( $source->type ); ?>"><?php echo esc_html( $source->type ); ?></span></td>
						<td class="pp-td">
						<?php
						if ( $source->type === 'usphlGameScheduleUrl' ) {
							$other   = json_decode( $source->other_data ?? '{}', true );
							$display = 'Team: ' . esc_html( $source->source_url_or_path );
							if ( ! empty( $other['season_id'] ) ) {
								$display .= ' / Season: ' . esc_html( $other['season_id'] );
							}
							echo $display;
						} else {
							echo esc_html( $source->source_url_or_path );
						}
						?>
						</td>
						<td class="pp-td"><?php echo esc_html( $source->last_updated ? date( 'M d, Y h:i A', strtotime( $source->last_updated ) ) : '—' ); ?></td>
						<td class="pp-td">
							<label class="pp-data-source-toggle-switch">
								<input type="checkbox" <?php echo $source->status === 'active' ? 'checked' : ''; ?> data-id="<?php echo esc_attr( $source->id ); ?>">
								<span class="pp-slider"></span>
							</label>
							<span style="margin-left: 10px;"><?php echo esc_html( ucfirst( $source->status ) ); ?></span>
						</td>
						<td class="pp-td">
							<div class="pp-flex-small-gap">
								<button class="pp-button-icon pp-delete-team-source-btn" data-id="<?php echo esc_attr( $source->id ); ?>">🗑️</button>
							</div>
						</td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
		<?php if ( empty( $data_sources ) ) : ?>
			<p id="kill-me-please"><?php esc_html_e( 'No data sources yet.', 'puck-press' ); ?></p>
		<?php endif; ?>
		<?php
		return ob_get_clean();
	}
}
