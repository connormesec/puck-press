<?php

/**
 * Provide a admin area view for the plugin
 *
 * @link       https://https://github.com/connormesec/
 * @since      1.0.0
 *
 * @package    Puck_Press
 * @subpackage Puck_Press/admin/partials/schedule
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Puck_Press_Schedule_Admin_Display {

	private $game_template_preview;
	private $game_slider_preview;
	private $last_run;
	private array $teams;
	private array $new_schedules;
	private int $active_new_schedule_id;

	public function __construct() {
		$teams_utils                  = new Puck_Press_Teams_Wpdb_Utils();
		$this->teams                  = $teams_utils->get_all_teams();
		$schedules_utils              = new Puck_Press_Schedules_Wpdb_Utils();
		$this->new_schedules          = $schedules_utils->get_all_schedules();
		$this->active_new_schedule_id = (int) get_option( 'pp_admin_active_new_schedule_id', 0 );
		if ( $this->active_new_schedule_id === 0 && ! empty( $this->new_schedules ) ) {
			$this->active_new_schedule_id = (int) $this->new_schedules[0]['id'];
		}

		$sid = $this->active_new_schedule_id;

		$this->game_template_preview = new Puck_Press_Schedule_Admin_Preview_Card(
			array(
				'title'    => 'Preview',
				'subtitle' => 'Preview how the schedule will appear on the public website',
				'id'       => 'game-schedule-preview',
			),
			$sid
		);
		$this->game_slider_preview   = new Puck_Press_Schedule_Admin_Slider_Preview_Card(
			array(
				'title'    => 'Game Slider Preview',
				'subtitle' => 'Preview how the game slider will appear on the public website',
				'id'       => 'game-slider-preview',
			),
			$sid,
			'default'
		);
		if ( $sid > 0 && empty( $schedules_utils->get_schedule_games_display( $sid ) ) ) {
			require_once plugin_dir_path( __FILE__ ) . '../../../includes/schedule/class-puck-press-schedule-materializer.php';
			( new Puck_Press_Schedule_Materializer() )->materialize_schedule( $sid );
		}

		$this->game_template_preview->init();
		$this->game_slider_preview->init();
		$this->last_run = get_option( 'puck_press_cron_last_run', 'Never' );
	}

	public function render() {
		ob_start();
		?>
		<div class="pp-container">
			<main class="pp-main">
				<div class="pp-section-header">
					<div>
						<h1 class="pp-section-title">Game Schedule</h1>
						<p class="pp-section-description">Manage your team's game schedule and results.</p>
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
								<div class="pp-dropdown-header">Database</div>
								<div class="pp-dropdown-item danger" id="pp-wipe-and-recreate-db-btn">Wipe &amp; Recreate Database</div>
																																																							</div>
						</div>
					</div>
				</div>

				<p class="pp-refresh-info">Last refreshed: <?php echo esc_html( $this->last_run ); ?></p>

				<?php echo $this->render_schedule_content(); ?>

			</main>
			<?php
			include plugin_dir_path( __DIR__ ) . 'schedule/schedule-color-palette-modal.php';
			include plugin_dir_path( __DIR__ ) . 'schedule/slider-color-palette-modal.php';
			echo $this->render_add_schedule_modal();
			?>

		</div>
		<?php
		return ob_get_clean();
	}

	private function render_schedule_content(): string {
		ob_start();
		?>
		<?php echo $this->render_schedules_section(); ?>

		<?php echo $this->game_template_preview->render(); ?>
		<?php echo $this->game_slider_preview->render(); ?>
		<?php
		return ob_get_clean();
	}

	private function render_schedules_section(): string {
		ob_start();
		$new_schedules          = $this->new_schedules;
		$active_new_schedule_id = $this->active_new_schedule_id;

		$schedules_utils = new Puck_Press_Schedules_Wpdb_Utils();
		$schedule_teams  = array();
		if ( $active_new_schedule_id ) {
			$schedule_teams = $schedules_utils->get_schedule_teams( $active_new_schedule_id );
		}
		$active_schedule_obj = $active_new_schedule_id ? $schedules_utils->get_schedule_by_id( $active_new_schedule_id ) : null;
		$is_main             = $active_schedule_obj ? (int) $active_schedule_obj['is_main'] === 1 : false;

		$active_sched_entry = array_values( array_filter( $new_schedules, fn( $s ) => (int) $s['id'] === $active_new_schedule_id ) );
		$active_slug        = $active_sched_entry[0]['slug'] ?? 'default';
		$shortcode          = '[pp-schedule' . ( $active_slug !== 'default' ? ' schedule="' . esc_attr( $active_slug ) . '"' : '' ) . ']';

		$existing_team_ids  = array_column( $schedule_teams, 'id' );
		$available_teams    = array_filter( $this->teams, fn( $t ) => ! in_array( $t['id'], $existing_team_ids, false ) );

		?>
		<input type="hidden" id="pp-active-new-schedule-id" value="<?php echo esc_attr( $active_new_schedule_id ); ?>">

		<?php if ( ! empty( $new_schedules ) ) : ?>
		<!-- Schedule membership card -->
		<div class="pp-card pp-membership-card">
			<div class="pp-card-header">
				<div>
					<h2 class="pp-card-title">Schedule</h2>
					<p class="pp-card-subtitle" id="pp-schedule-membership-subtitle">
						<?php if ( $is_main ) : ?>
							Main schedule — auto-includes all teams.
						<?php else : ?>
							Teams assigned to this schedule
						<?php endif; ?>
					</p>
				</div>
				<div class="pp-card-header-actions">
					<select id="pp-schedule-group-selector" class="pp-select pp-select-lg">
						<?php foreach ( $new_schedules as $sched ) : ?>
							<option value="<?php echo esc_attr( $sched['id'] ); ?>"
								<?php selected( (int) $sched['id'], $active_new_schedule_id ); ?>>
								<?php echo esc_html( $sched['name'] ); ?>
							</option>
						<?php endforeach; ?>
					</select>
					<button class="pp-button pp-button-secondary" id="pp-add-schedule-btn">+ New Schedule</button>
				</div>
			</div>
			<div class="pp-card-toolbar" id="pp-schedule-membership-toolbar">
				<div class="pp-shortcode-input-group">
					<input
						type="text"
						id="pp-new-schedule-shortcode"
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
				<select id="pp-add-team-to-schedule-select" class="pp-select" data-schedule-id="<?php echo esc_attr( $active_new_schedule_id ); ?>">
					<option value="">— Add team —</option>
					<?php foreach ( $available_teams as $team ) : ?>
						<option value="<?php echo esc_attr( $team['id'] ); ?>">
							<?php echo esc_html( $team['name'] ); ?>
						</option>
					<?php endforeach; ?>
				</select>
				<button class="pp-button pp-button-primary" id="pp-add-team-to-schedule-btn">+ Add Team</button>
				<?php endif; ?>
			</div>
			<div class="pp-card-content" id="pp-schedule-teams-content">
				<?php echo $this->render_schedule_teams_table( $schedule_teams, $is_main, $active_new_schedule_id ); ?>
			</div>
			<div id="pp-schedule-delete-footer" class="pp-card-footer" style="padding: 12px 24px; border-top: 1px solid #e0e0e0;<?php echo $is_main ? ' display:none;' : ''; ?>">
				<button class="pp-button pp-button-danger pp-delete-new-schedule-btn"
					data-schedule-id="<?php echo esc_attr( $active_new_schedule_id ); ?>">
					Delete Schedule
				</button>
			</div>
		</div>
		<?php endif; ?>
		<?php
		return ob_get_clean();
	}

	private function render_schedule_teams_table( array $teams, bool $is_main, int $schedule_id ): string {
		ob_start();
		if ( empty( $teams ) ) :
			?>
			<p style="color:#888;"><?php echo $is_main ? 'All teams are auto-included in the main schedule.' : 'No teams in this schedule yet.'; ?></p>
			<?php
		else :
			?>
			<table class="pp-table" id="pp-schedule-teams-table">
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
									<button class="pp-button-icon pp-remove-team-from-schedule-btn"
										data-schedule-id="<?php echo esc_attr( $schedule_id ); ?>"
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

	private function render_add_schedule_modal(): string {
		ob_start();
		?>
		<div id="pp-add-schedule-modal" class="pp-modal-overlay" style="display:none;">
			<div class="pp-modal">
				<div class="pp-modal-header">
					<h2>Add Schedule</h2>
					<button class="pp-modal-close" id="pp-add-schedule-modal-close">&times;</button>
				</div>
				<div class="pp-modal-body">
					<div class="pp-form-group">
						<label class="pp-form-label">Schedule Name</label>
						<input type="text" id="pp-new-schedule-name" class="pp-form-input" placeholder="e.g. Eagles Schedule">
					</div>
					<div class="pp-form-group">
						<label class="pp-form-label">Slug</label>
						<input type="text" id="pp-new-schedule-slug" class="pp-form-input" placeholder="e.g. eagles-schedule">
					</div>
				</div>
				<div class="pp-modal-footer">
					<button class="pp-button" id="pp-add-schedule-modal-cancel">Cancel</button>
					<button class="pp-button pp-button-primary" id="pp-add-schedule-modal-confirm">Create Schedule</button>
				</div>
			</div>
		</div>
		<?php
		return ob_get_clean();
	}
}
