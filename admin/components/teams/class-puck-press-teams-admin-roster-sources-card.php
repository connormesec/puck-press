<?php
class Puck_Press_Teams_Admin_Roster_Sources_Card extends Puck_Press_Admin_Card_Abstract {

	private int $team_id;

	public function __construct( int $team_id = 0 ) {
		parent::__construct( array(
			'title'    => 'Roster Sources',
			'subtitle' => 'Manage external data sources for roster',
			'id'       => 'roster-sources',
		) );
		$this->team_id = $team_id;
	}

	public function render_header_button_content() {
		return '<button class="pp-button pp-button-primary" id="pp-add-roster-source-button">+ Add Roster Source</button>';
	}

	public function render_content() {
		ob_start();
		?>
		<div id="pp-roster-sources-table">
			<?php echo $this->render_roster_sources_table(); ?>
		</div>
		<?php echo $this->render_add_roster_source_modal(); ?>
		<?php
		return ob_get_clean();
	}

	public function render_roster_sources_table() {
		global $wpdb;
		$table   = $wpdb->prefix . 'pp_team_roster_sources';
		$sources = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM $table WHERE team_id = %d ORDER BY id ASC",
				$this->team_id
			)
		);

		ob_start();
		?>
		<table class="pp-table" id="pp-roster-sources-table">
			<thead class="pp-thead">
				<tr>
					<th class="pp-th"><?php esc_html_e( 'Name', 'puck-press' ); ?></th>
					<th class="pp-th"><?php esc_html_e( 'Type', 'puck-press' ); ?></th>
					<th class="pp-th"><?php esc_html_e( 'URL/Identifier', 'puck-press' ); ?></th>
					<th class="pp-th"><?php esc_html_e( 'Last Updated', 'puck-press' ); ?></th>
					<th class="pp-th"><?php esc_html_e( 'Status', 'puck-press' ); ?></th>
					<th class="pp-th"><?php esc_html_e( 'Actions', 'puck-press' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( $sources as $source ) : ?>
					<tr data-roster-source-id="<?php echo esc_attr( $source->id ); ?>">
						<td class="pp-td"><?php echo esc_html( $source->name ); ?></td>
						<td class="pp-td"><span class="pp-tag pp-tag-<?php echo esc_attr( $source->type ); ?>"><?php echo esc_html( $source->type ); ?></span></td>
						<td class="pp-td">
							<?php
							if ( $source->type === 'usphlRosterUrl' ) {
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
							<label class="pp-roster-source-toggle-switch">
								<input type="checkbox" <?php echo $source->status === 'active' ? 'checked' : ''; ?> data-id="<?php echo esc_attr( $source->id ); ?>" data-team-id="<?php echo esc_attr( $this->team_id ); ?>">
								<span class="pp-slider"></span>
							</label>
							<span style="margin-left: 10px;"><?php echo esc_html( ucfirst( $source->status ) ); ?></span>
						</td>
						<td class="pp-td">
							<div class="pp-flex-small-gap">
								<button class="pp-button-icon pp-delete-roster-source" data-id="<?php echo esc_attr( $source->id ); ?>" data-team-id="<?php echo esc_attr( $this->team_id ); ?>">🗑️</button>
							</div>
						</td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
		<?php if ( empty( $sources ) ) : ?>
			<p><?php esc_html_e( 'No roster sources added yet.', 'puck-press' ); ?></p>
		<?php endif; ?>
		<?php
		return ob_get_clean();
	}

	private function get_season_options(): array {
		$year  = (int) date( 'Y' );
		$month = (int) date( 'n' );
		$start = ( $month >= 9 ) ? $year : $year - 1;
		return array(
			( $start - 1 ) . '-' . $start,
			$start . '-' . ( $start + 1 ),
			( $start + 1 ) . '-' . ( $start + 2 ),
		);
	}

	private function render_add_roster_source_modal() {
		ob_start();
		?>
		<div id="pp-add-roster-source-modal" class="pp-modal-overlay" style="display:none;">
			<div class="pp-modal">
				<div class="pp-modal-header">
					<h2><?php esc_html_e( 'Add Roster Source', 'puck-press' ); ?></h2>
					<button class="pp-modal-close pp-cancel-roster-source-modal">&times;</button>
				</div>
				<div class="pp-modal-body">
					<div class="pp-form-group">
						<label class="pp-form-label"><?php esc_html_e( 'Source Name', 'puck-press' ); ?></label>
						<input type="text" id="pp-roster-source-name" class="pp-form-input" placeholder="e.g. Varsity Roster">
					</div>
					<?php $season_opts = $this->get_season_options(); ?>
					<div class="pp-form-group">
						<label for="pp-roster-source-season-year" class="pp-form-label"><?php esc_html_e( 'Season Year', 'puck-press' ); ?></label>
						<select id="pp-roster-source-season-year" class="pp-select">
							<option value="<?php echo esc_attr( $season_opts[2] ); ?>"><?php echo esc_html( $season_opts[2] ); ?></option>
							<option value="<?php echo esc_attr( $season_opts[1] ); ?>" selected><?php echo esc_html( $season_opts[1] ); ?></option>
							<option value="<?php echo esc_attr( $season_opts[0] ); ?>"><?php echo esc_html( $season_opts[0] ); ?></option>
						</select>
					</div>
					<div class="pp-form-group">
						<label class="pp-form-label"><?php esc_html_e( 'Stat Period', 'puck-press' ); ?></label>
						<select id="pp-roster-source-stat-period" class="pp-select">
							<option value="">&mdash; <?php esc_html_e( 'Select Period', 'puck-press' ); ?> &mdash;</option>
							<option value="Regular Season"><?php esc_html_e( 'Regular Season', 'puck-press' ); ?></option>
							<option value="Playoffs"><?php esc_html_e( 'Playoffs', 'puck-press' ); ?></option>
							<option value="Regionals"><?php esc_html_e( 'Regionals', 'puck-press' ); ?></option>
							<option value="Nationals"><?php esc_html_e( 'Nationals', 'puck-press' ); ?></option>
							<option value="Conference Tournament"><?php esc_html_e( 'Conference Tournament', 'puck-press' ); ?></option>
							<option value="Exhibition"><?php esc_html_e( 'Exhibition', 'puck-press' ); ?></option>
							<option value="__other__"><?php esc_html_e( 'Other&hellip;', 'puck-press' ); ?></option>
						</select>
						<input type="text" id="pp-roster-source-stat-period-other" class="pp-form-input" placeholder="<?php esc_attr_e( 'Custom period name', 'puck-press' ); ?>" style="display:none;margin-top:6px;">
					</div>
					<div class="pp-form-group">
						<label class="pp-form-label"><?php esc_html_e( 'Source Type', 'puck-press' ); ?></label>
						<select id="pp-roster-source-type" class="pp-select">
							<option value="achaRosterUrl">ACHA Roster URL</option>
							<option value="usphlRosterUrl">USPHL Roster URL</option>
							<option value="csv">CSV</option>
						</select>
					</div>

					<div class="pp-dynamic-roster-source-group-achaRosterUrl pp-form-group">
						<label class="pp-form-label"><?php esc_html_e( 'ACHA Roster URL', 'puck-press' ); ?></label>
						<input type="url" id="pp-roster-source-url" class="pp-form-input" placeholder="https://...">
						<div class="pp-form-group" style="margin-top:8px;">
							<label class="pp-form-label">
								<input type="checkbox" id="pp-roster-source-include-stats" checked>
								<?php esc_html_e( 'Include Stats', 'puck-press' ); ?>
							</label>
							<p class="pp-form-help"><?php esc_html_e( 'Import player statistics from this ACHA source.', 'puck-press' ); ?></p>
						</div>
					</div>

					<div class="pp-dynamic-roster-source-group-usphlRosterUrl pp-form-group" style="display:none;">
						<label class="pp-form-label"><?php esc_html_e( 'USPHL Team ID', 'puck-press' ); ?></label>
						<input type="text" id="pp-roster-usphl-team-id" class="pp-form-input" placeholder="e.g. 12345" disabled>
						<label class="pp-form-label" style="margin-top:8px;"><?php esc_html_e( 'Season ID (optional)', 'puck-press' ); ?></label>
						<input type="text" id="pp-roster-usphl-season-id" class="pp-form-input" placeholder="e.g. 67890" disabled>
					</div>

					<div class="pp-dynamic-roster-source-group-csv pp-form-group" style="display:none;">
						<label class="pp-form-label"><?php esc_html_e( 'CSV File', 'puck-press' ); ?></label>
						<input type="file" id="pp-roster-csv-file" class="pp-form-input" accept=".csv" disabled>
					</div>

					<div class="pp-form-group">
						<label class="pp-form-label">
							<input type="checkbox" id="pp-roster-source-active" checked>
							<?php esc_html_e( 'Active', 'puck-press' ); ?>
						</label>
					</div>
				</div>
				<div class="pp-modal-footer">
					<button class="pp-button pp-cancel-roster-source-modal"><?php esc_html_e( 'Cancel', 'puck-press' ); ?></button>
					<button class="pp-button pp-button-primary" id="pp-confirm-roster-add-source" data-action="pp_add_team_roster_source"><?php esc_html_e( 'Add Source', 'puck-press' ); ?></button>
				</div>
			</div>
		</div>
		<?php
		return ob_get_clean();
	}
}
