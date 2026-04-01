<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Puck_Press_Roster_Admin_Display {

	private $roster_preview_card;
	private $last_run;
	private int $active_roster_id;

	public function __construct() {
		$registry                = new Puck_Press_Roster_Registry_Wpdb_Utils();
		$main_id                 = $registry->get_main_roster_id() ?? 1;
		$this->active_roster_id  = (int) get_option( 'pp_admin_active_new_roster_id', $main_id );

		$all_rosters = $registry->get_all_rosters();
		$valid_ids   = array_column( $all_rosters, 'id' );
		if ( ! empty( $valid_ids ) && ! in_array( (string) $this->active_roster_id, $valid_ids, false ) ) {
			$this->active_roster_id = (int) $valid_ids[0];
			update_option( 'pp_admin_active_new_roster_id', $this->active_roster_id );
		}

		$rid = $this->active_roster_id;

		$this->roster_preview_card = new Puck_Press_Roster_Admin_Preview_Card(
			array(
				'title'    => 'Roster Preview',
				'subtitle' => 'Preview your roster before publishing',
				'id'       => 'roster-preview',
			),
			$rid
		);
		$this->roster_preview_card->init();
		$this->last_run = get_option( 'puck_press_cron_last_run', 'Never' );
	}

	public function render() {
		ob_start();
		?>
		<div class="pp-container">
			<main class="pp-main">
				<div class="pp-section-header">
					<div>
						<h1 class="pp-section-title">Roster</h1>
						<p class="pp-section-description">Manage your team's roster.</p>
					</div>

					<div class="pp-flex-row">

						<button class="pp-button pp-button-secondary" id="pp-roster-refresh-all-btn">
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
								<div class="pp-dropdown-header">Database</div>
								<div class="pp-dropdown-item danger" id="pp-wipe-and-recreate-db-btn">Wipe &amp; Recreate Database</div>
							</div>
						</div>

					</div>
				</div>

				<p class="pp-refresh-info">Last refreshed: <?php echo esc_html( $this->last_run ); ?></p>

				<?php echo $this->render_roster_content(); ?>

				<div class="pp-card" style="margin-top: 16px; margin-bottom: 16px;">
					<div class="pp-card-header">
						<h2>Shortcode Attributes</h2>
						<p>Customize the roster shortcode output</p>
					</div>
					<div class="pp-card-content" style="padding: 16px 24px;">
						<table style="border-collapse: collapse; width: 100%; font-size: 0.875rem;">
							<thead>
								<tr style="border-bottom: 1px solid #e0e0e0;">
									<th style="text-align: left; padding: 8px 12px; font-weight: 600;">Attribute</th>
									<th style="text-align: left; padding: 8px 12px; font-weight: 600;">Default</th>
									<th style="text-align: left; padding: 8px 12px; font-weight: 600;">Description</th>
								</tr>
							</thead>
							<tbody>
								<tr>
									<td style="padding: 8px 12px;"><code>roster</code></td>
									<td style="padding: 8px 12px;"><code>(default)</code></td>
									<td style="padding: 8px 12px;">Roster group slug — targets a specific group's roster. Omit for the default group.</td>
								</tr>
							</tbody>
						</table>
						<p style="margin: 12px 0 0; color: #666; font-size: 0.8rem;">
							Examples: <code>[pp-roster]</code> &nbsp;|&nbsp; <code>[pp-roster roster="eagles"]</code>
						</p>
					</div>
				</div>

			</main>
			<?php
			include plugin_dir_path( __FILE__ ) . 'roster-add-roster-modal.php';
			include plugin_dir_path( __FILE__ ) . 'roster-color-palette-modal.php';
			?>

		</div>
		<?php
		return ob_get_clean();
	}

	private function render_roster_content(): string {
		ob_start();
		echo $this->render_rosters_section();
		echo $this->roster_preview_card->render();
		return ob_get_clean();
	}

	private function render_rosters_section(): string {
		ob_start();

		$registry        = new Puck_Press_Roster_Registry_Wpdb_Utils();
		$all_rosters     = $registry->get_all_rosters();
		$active_id       = $this->active_roster_id;
		$active_roster   = $active_id ? $registry->get_roster_by_id( $active_id ) : null;
		$is_main         = $active_roster ? (int) $active_roster['is_main'] === 1 : false;
		$roster_teams    = $active_id ? $registry->get_roster_teams( $active_id ) : array();
		$available_teams = $active_id ? $registry->get_available_teams_for_roster( $active_id ) : array();

		$active_slug = $active_roster['slug'] ?? 'default';
		$shortcode   = '[pp-roster' . ( $active_slug !== 'default' ? ' roster="' . esc_attr( $active_slug ) . '"' : '' ) . ']';

		?>
		<input type="hidden" id="pp-active-new-roster-id" value="<?php echo esc_attr( $active_id ); ?>">

		<?php if ( ! empty( $all_rosters ) ) : ?>
		<div class="pp-card pp-membership-card">
			<div class="pp-card-header">
				<div>
					<h2 class="pp-card-title">Roster</h2>
					<p class="pp-card-subtitle" id="pp-roster-membership-subtitle">
						<?php if ( $is_main ) : ?>
							Main roster — auto-includes all teams.
						<?php else : ?>
							Teams assigned to this roster
						<?php endif; ?>
					</p>
				</div>
				<div class="pp-card-header-actions">
					<select id="pp-roster-selector" class="pp-select pp-select-lg">
						<?php foreach ( $all_rosters as $roster ) : ?>
							<option value="<?php echo esc_attr( $roster['id'] ); ?>"
								<?php selected( (int) $roster['id'], $active_id ); ?>>
								<?php echo esc_html( $roster['name'] ); ?>
							</option>
						<?php endforeach; ?>
					</select>
					<button class="pp-button pp-button-secondary" id="pp-add-roster-btn">+ New Roster</button>
				</div>
			</div>
			<div class="pp-card-toolbar" id="pp-roster-membership-toolbar">
				<div class="pp-shortcode-input-group">
					<input
						type="text"
						id="pp-new-roster-shortcode"
						class="pp-shortcode-input"
						value="<?php echo esc_attr( $shortcode ); ?>"
						size="<?php echo strlen( $shortcode ); ?>"
						spellcheck="false"
						aria-label="shortcode"
						onfocus="this.select();"
						readonly>
					<button class="pp-shortcode-copy-btn" aria-label="Copy shortcode">
						<svg class="pp-shortcode-copy-icon" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
							<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 5H6a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2v-1M8 5a2 2 0 002 2h2a2 2 0 002-2M8 5a2 2 0 012-2h2a2 2 0 012 2m0 0h2a2 2 0 012 2v3m2 4H10m0 0l3-3m-3 3l3 3" />
						</svg>
					</button>
					<div class="pp-shortcode-tooltip">Copied!</div>
				</div>
				<?php if ( ! $is_main && ! empty( $available_teams ) ) : ?>
				<select id="pp-add-team-select" class="pp-select">
					<option value="">— Add team —</option>
					<?php foreach ( $available_teams as $team ) : ?>
						<option value="<?php echo esc_attr( $team['id'] ); ?>">
							<?php echo esc_html( $team['name'] ); ?>
						</option>
					<?php endforeach; ?>
				</select>
				<button class="pp-button pp-button-primary" id="pp-add-team-to-roster-btn">+ Add Team</button>
				<?php endif; ?>
			</div>
			<div class="pp-card-content" id="pp-roster-teams-content">
				<?php echo $this->render_roster_teams_table( $roster_teams, $is_main, $active_id ); ?>
			</div>
			<div id="pp-roster-delete-footer" class="pp-card-footer" style="padding: 12px 24px; border-top: 1px solid #e0e0e0;<?php echo $is_main ? ' display:none;' : ''; ?>">
				<button class="pp-button pp-button-danger pp-delete-new-roster-btn"
					data-roster-id="<?php echo esc_attr( $active_id ); ?>">
					Delete Roster
				</button>
			</div>
		</div>
		<?php endif; ?>
		<?php
		return ob_get_clean();
	}

	private function render_roster_teams_table( array $teams, bool $is_main, int $roster_id ): string {
		ob_start();
		if ( empty( $teams ) ) :
			?>
			<p style="color:#888;"><?php echo $is_main ? 'All teams are auto-included in the main roster.' : 'No teams in this roster yet.'; ?></p>
			<?php
		else :
			?>
			<table class="pp-table" id="pp-roster-teams-table">
				<thead class="pp-thead">
					<tr>
						<th class="pp-th">Name</th>
						<th class="pp-th">Slug</th>
						<?php if ( ! $is_main ) : ?>
							<th class="pp-th">Actions</th>
						<?php endif; ?>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $teams as $team ) : ?>
						<tr data-team-id="<?php echo esc_attr( $team['id'] ); ?>">
							<td class="pp-td"><?php echo esc_html( $team['name'] ); ?></td>
							<td class="pp-td"><code><?php echo esc_html( $team['slug'] ); ?></code></td>
							<?php if ( ! $is_main ) : ?>
								<td class="pp-td">
									<button class="pp-button-icon pp-remove-team-from-roster-btn"
										data-team-id="<?php echo esc_attr( $team['id'] ); ?>">
										✕
									</button>
								</td>
							<?php endif; ?>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
			<?php
		endif;
		return ob_get_clean();
	}
}
