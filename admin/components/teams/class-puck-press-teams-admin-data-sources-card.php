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

		$has_acha_seed = false;
		foreach ( $data_sources as $source ) {
			if ( $source->type === 'achaGameScheduleUrl' ) {
				$od = json_decode( $source->other_data ?? '{}', true );
				if ( ! empty( $od['auto_discover'] ) ) {
					$has_acha_seed = true;
					break;
				}
			}
		}

		ob_start();
		?>
		<?php if ( $has_acha_seed ) : ?>
			<div style="margin-bottom: 12px;">
				<button type="button" id="pp-discover-acha-seasons" class="pp-button">Discover New Seasons</button>
				<span id="pp-discover-result" style="margin-left: 10px; font-style: italic;"></span>
			</div>
		<?php endif; ?>
		<table class="pp-table" id="pp-sources-table">
			<thead class="pp-thead">
				<tr>
					<th class="pp-th"><?php esc_html_e( 'Name', 'puck-press' ); ?></th>
					<th class="pp-th"><?php esc_html_e( 'Type', 'puck-press' ); ?></th>
					<th class="pp-th"><?php esc_html_e( 'Source', 'puck-press' ); ?></th>
					<th class="pp-th"><?php esc_html_e( 'Last Updated', 'puck-press' ); ?></th>
					<th class="pp-th"><?php esc_html_e( 'Status', 'puck-press' ); ?></th>
					<th class="pp-th"><?php esc_html_e( 'Actions', 'puck-press' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( $data_sources as $source ) : ?>
					<?php
					$od       = json_decode( $source->other_data ?? '{}', true );
					$is_seed  = ! empty( $od['auto_discover'] );
					$is_auto  = ! empty( $od['auto_discovered'] );
					?>
					<tr data-id="<?php echo esc_attr( $source->id ); ?>">
						<td class="pp-td">
							<?php echo esc_html( $source->name ); ?>
							<?php if ( $is_seed ) : ?>
								<span class="pp-badge pp-badge--seed" title="Seed source — future seasons discovered from this" style="margin-left:6px;font-size:10px;background:#e8f4fd;color:#1a6b9a;padding:2px 6px;border-radius:3px;">Seed</span>
							<?php elseif ( $is_auto ) : ?>
								<span class="pp-badge pp-badge--auto" title="Created automatically by season discovery" style="margin-left:6px;font-size:10px;background:#f0f9f0;color:#2a6a2a;padding:2px 6px;border-radius:3px;">Auto</span>
							<?php endif; ?>
						</td>
						<td class="pp-td"><span class="pp-tag pp-tag-<?php echo esc_attr( $source->type ); ?>"><?php echo esc_html( $source->type ); ?></span></td>
						<td class="pp-td">
						<?php
						if ( $source->type === 'achaGameScheduleUrl' ) {
							echo 'Team: ' . esc_html( $source->source_url_or_path );
							if ( ! empty( $od['season_id'] ) ) {
								echo ' / Season: ' . esc_html( $od['season_id'] );
							}
						} elseif ( $source->type === 'usphlGameScheduleUrl' ) {
							$display = 'Team: ' . esc_html( $source->source_url_or_path );
							if ( ! empty( $od['season_id'] ) ) {
								$display .= ' / Season: ' . esc_html( $od['season_id'] );
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
