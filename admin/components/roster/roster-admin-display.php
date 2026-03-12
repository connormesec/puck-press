<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Puck_Press_Roster_Admin_Display {

	private $roster_data_sources;
	private $roster_edits_table;
	private $roster_preview_card;
	private $roster_archive_card;
	private $groups_card;
	private $last_run;
	private array $roster_groups;
	private int $active_roster_id;
	private string $active_roster_slug;

	public function __construct() {
		$wpdb_utils              = new Puck_Press_Roster_Wpdb_Utils();
		$this->roster_groups     = $wpdb_utils->get_all_groups();
		$this->active_roster_id  = (int) get_option( 'pp_admin_active_roster_id', 1 );

		$valid_ids = array_column( $this->roster_groups, 'id' );
		if ( ! in_array( (string) $this->active_roster_id, $valid_ids, false ) ) {
			$this->active_roster_id = 1;
		}

		$active_group             = array_values(
			array_filter(
				$this->roster_groups,
				fn( $g ) => (int) $g['id'] === $this->active_roster_id
			)
		);
		$this->active_roster_slug = $active_group[0]['slug'] ?? 'default';

		$rid = $this->active_roster_id;

		$this->groups_card = new Puck_Press_Roster_Admin_Groups_Card(
			array(
				'title'    => 'Roster Groups',
				'subtitle' => 'Manage multiple roster groups',
				'id'       => 'roster-groups',
			)
		);

		$this->roster_data_sources = new Puck_Press_Roster_Admin_Data_Sources_Card(
			array(
				'title'    => 'Data Sources',
				'subtitle' => 'Manage external data sources for the roster',
				'id'       => 'data-sources-table',
			),
			$rid
		);
		$this->roster_edits_table  = new Puck_Press_Roster_Admin_Edits_Table_Card(
			array(
				'title'    => 'Roster Edits',
				'subtitle' => 'Manage your roster edits',
				'id'       => 'roster-edits-table',
			),
			$rid
		);
		$this->roster_preview_card = new Puck_Press_Roster_Admin_Preview_Card(
			array(
				'title'    => 'Roster Preview',
				'subtitle' => 'Preview your roster before publishing',
				'id'       => 'roster-preview',
			),
			$rid
		);
		$this->roster_archive_card = new Puck_Press_Roster_Admin_Archive_Card(
			array(
				'title'    => 'Roster Archives',
				'subtitle' => 'Snapshots of past season stats',
				'id'       => 'roster-archives',
			)
		);
		$this->roster_preview_card->init();
		$this->roster_archive_card->init();
		$this->last_run = get_option( 'puck_press_cron_last_run', 'Never' );
	}

	public function render() {
		ob_start();
		$roster_sc = '[pp-roster' . ( $this->active_roster_slug !== 'default' ? ' roster="' . esc_attr( $this->active_roster_slug ) . '"' : '' ) . ']';
		?>
		<div class="pp-container">
			<main class="pp-main">
				<div class="pp-section-header">
					<div>
						<h1 class="pp-section-title">Roster</h1>
						<p class="pp-section-description">Manage your team's roster.</p>
					</div>

					<div class="pp-shortcode-container">
						<div class="pp-shortcode-label">Roster Shortcode</div>
						<div class="pp-shortcode-input-group">
							<input
								type="text"
								id="pp-roster-shortcode"
								name="pp-roster-shortcode"
								class="pp-shortcode-input"
								value="<?php echo esc_attr( $roster_sc ); ?>"
								size="<?php echo strlen( $roster_sc ); ?>"
								spellcheck="false"
								aria-label="shortcode"
								onfocus="this.select();"
								readonly>
							<button class="pp-shortcode-copy-btn" aria-label="Copy URL">
								<svg class="pp-shortcode-copy-icon" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
									<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 5H6a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2v-1M8 5a2 2 0 002 2h2a2 2 0 002-2M8 5a2 2 0 012-2h2a2 2 0 012 2m0 0h2a2 2 0 012 2v3m2 4H10m0 0l3-3m-3 3l3 3" />
								</svg>
							</button>
							<div class="pp-shortcode-tooltip">Copied!</div>
						</div>
					</div>

					<div class="pp-flex-row">

						<button class="pp-button pp-button-secondary" id="pp-refresh-button">
							<i>🔄</i>
							Refresh All Sources
						</button>

						<div class="pp-adv-button-container">
							<button class="pp-button" id="pp-advancedBtn">
								<svg class="pp-gear-icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
									<circle cx="12" cy="12" r="3"></circle>
									<path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1 0 2.83 2 2 0 0 1-2.83 0l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-2 2 2 2 0 0 1-2-2v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83 0 2 2 0 0 1 0-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1-2-2 2 2 0 0 1 2-2h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 0-2.83 2 2 0 0 1 2.83 0l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 2-2 2 2 0 0 1 2 2v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 0 2 2 0 0 1 0 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 2 2 2 2 0 0 1-2 2h-.09a1.65 1.65 0 0 0-1.51 1z"></path>
								</svg>
								Advanced
							</button>
							<div class="pp-dropdown-menu" id="pp-advancedDropdown">
								<div class="pp-dropdown-header">Sources</div>
								<div class="pp-dropdown-item">Reset Game Data</div>
								<div class="pp-dropdown-item">Reset Data Sources</div>
								<div class="pp-dropdown-item danger">Reset Everything</div>
								<div class="pp-dropdown-header">Edits</div>
								<div class="pp-dropdown-item danger" id="pp-reset-all-roster-edits">Reset All Edits</div>
								<div class="pp-dropdown-header">Archives</div>
								<div class="pp-dropdown-item" id="pp-archive-roster-btn">Archive Roster</div>
								<div class="pp-dropdown-header">Database</div>
								<div class="pp-dropdown-item" id="pp-fix-databases-btn">Fix Database Tables</div>
							</div>
						</div>
					</div>
				</div>

				<p class="pp-refresh-info">Last refreshed: <?php echo esc_html( $this->last_run ); ?></p>

				<?php echo $this->groups_card->render(); ?>

				<div class="pp-card" style="margin-bottom: 16px;">
					<div class="pp-card-content" style="padding: 16px 24px; display: flex; gap: 12px; align-items: center; flex-wrap: wrap;">
						<label for="pp-roster-group-selector"><strong>Editing roster:</strong></label>
						<select id="pp-roster-group-selector" class="pp-select">
							<?php foreach ( $this->roster_groups as $group ) : ?>
								<option value="<?php echo esc_attr( $group['id'] ); ?>"
									data-slug="<?php echo esc_attr( $group['slug'] ); ?>"
									<?php selected( (int) $group['id'], $this->active_roster_id ); ?>>
									<?php echo esc_html( $group['name'] ); ?> (<?php echo esc_html( $group['slug'] ); ?>)
								</option>
							<?php endforeach; ?>
						</select>
						<span style="color: #666; font-size: 0.85rem;">
							Shortcode: <code>[pp-roster<?php echo $this->active_roster_slug !== 'default' ? ' roster="' . esc_attr( $this->active_roster_slug ) . '"' : ''; ?>]</code>
						</span>
					</div>
				</div>

				<input type="hidden" id="pp-active-roster-id" value="<?php echo esc_attr( $this->active_roster_id ); ?>">

				<?php echo $this->roster_data_sources->render(); ?>

				<?php echo $this->roster_edits_table->render(); ?>

				<?php echo $this->roster_preview_card->render(); ?>

				<?php echo $this->roster_archive_card->render(); ?>

			</main>
			<?php
			include plugin_dir_path( __DIR__ ) . 'roster/roster-add-source-modal.php';
			$source_modal = new Puck_Press_Roster_Add_Source_Modal( 'pp-add-source-modal' );
			echo $source_modal->render();
			include plugin_dir_path( __DIR__ ) . 'roster/roster-edit-player-modal.php';
			include plugin_dir_path( __DIR__ ) . 'roster/roster-bulk-edit-modal.php';
			include plugin_dir_path( __DIR__ ) . 'roster/roster-add-player-modal.php';
			include plugin_dir_path( __DIR__ ) . 'roster/roster-color-palette-modal.php';
			$archive_modal = new Puck_Press_Roster_Archive_Modal( 'pp-roster-archive-modal' );
			echo $archive_modal->render();
			?>

		</div>
		<?php
		return ob_get_clean();
	}
}
