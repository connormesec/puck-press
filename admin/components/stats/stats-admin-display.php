<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Puck_Press_Stats_Admin_Display {

	private $stats_preview;
	private $stat_leaders_preview;

	public function __construct() {
		require_once plugin_dir_path( __FILE__ ) . 'class-puck-press-stats-admin-preview-card.php';
		require_once plugin_dir_path( __FILE__ ) . 'class-puck-press-stat-leaders-admin-preview-card.php';

		$this->stats_preview = new Puck_Press_Stats_Admin_Preview_Card(
			array(
				'title'    => 'Stats Preview',
				'subtitle' => 'Preview how the stats table will appear on the public website.',
				'id'       => 'stats-preview',
			)
		);
		$this->stats_preview->init();

		$this->stat_leaders_preview = new Puck_Press_Stat_Leaders_Admin_Preview_Card(
			array(
				'title'    => 'Stat Leaders Preview',
				'subtitle' => 'Preview how the stat leaders widget will appear on the public website.',
				'id'       => 'stat-leaders-preview',
			)
		);
		$this->stat_leaders_preview->init();
	}

	public function render() {
		$defaults         = Puck_Press_Stats_Wpdb_Utils::get_default_column_settings();
		$saved            = get_option( 'pp_stats_column_settings', array() );
		$col              = array_merge( $defaults, is_array( $saved ) ? $saved : array() );
		$sl_skater        = get_option( 'pp_stat_leaders_skater_settings', Puck_Press_Stat_Leaders_Wpdb_Utils::get_default_skater_settings() );
		$sl_goalie        = get_option( 'pp_stat_leaders_goalie_settings', Puck_Press_Stat_Leaders_Wpdb_Utils::get_default_goalie_settings() );
		ob_start();
		?>
		<div class="pp-container">
			<main class="pp-main">

				<!-- ── Section Header ── -->
				<div class="pp-section-header">
					<div>
						<h1 class="pp-section-title">Stats Leaderboard</h1>
						<p class="pp-section-description">Display skater and goalie statistics pulled from your roster data sources.</p>
					</div>

					<div class="pp-shortcode-container">
						<div class="pp-shortcode-label">Stats Shortcode</div>
						<div class="pp-shortcode-input-group">
							<input
								type="text"
								id="pp-stats-shortcode"
								name="pp-stats-shortcode"
								class="pp-shortcode-input"
								value="[pp-stats]"
								size="10"
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
					</div>
				</div>


				<!-- ── Shortcode Reference ── -->
				<div class="pp-card" style="margin-bottom: 16px;">
					<div class="pp-card-header">
						<div>
							<h2 class="pp-card-title">Shortcode Reference</h2>
							<p class="pp-card-subtitle">All supported attributes for the <code>[pp-stats]</code> shortcode</p>
						</div>
					</div>
					<div class="pp-card-content" style="padding: 16px 24px;">
						<table style="width:100%;border-collapse:collapse;font-size:0.875rem;">
							<thead>
								<tr style="background:#f5f5f5;">
									<th style="text-align:left;padding:8px 12px;border:1px solid #e0e0e0;font-weight:600;">Attribute</th>
									<th style="text-align:left;padding:8px 12px;border:1px solid #e0e0e0;font-weight:600;">Default</th>
									<th style="text-align:left;padding:8px 12px;border:1px solid #e0e0e0;font-weight:600;">Description</th>
									<th style="text-align:left;padding:8px 12px;border:1px solid #e0e0e0;font-weight:600;">Example</th>
								</tr>
							</thead>
							<tbody>
								<tr>
									<td style="padding:8px 12px;border:1px solid #e0e0e0;"><code>team</code></td>
									<td style="padding:8px 12px;border:1px solid #e0e0e0;"><em>(all teams)</em></td>
									<td style="padding:8px 12px;border:1px solid #e0e0e0;">Filter to one or more teams by team ID. Use a comma-separated list for multiple teams. Find team IDs in the Teams admin tab.</td>
									<td style="padding:8px 12px;border:1px solid #e0e0e0;"><code>[pp-stats team=&quot;3,4&quot;]</code></td>
								</tr>
							</tbody>
						</table>
					</div>
				</div>

				<!-- ── Column Settings ── -->
				<div class="pp-card" style="margin-bottom: 16px;">
					<div class="pp-card-header">
						<div>
							<h2 class="pp-card-title">Column Settings</h2>
							<p class="pp-card-subtitle">Choose which optional columns appear in the stats table</p>
						</div>
					</div>
					<div class="pp-card-content" style="padding: 16px 24px;">
						<div id="pp-stats-column-settings">

							<p style="font-weight:600; margin-bottom:8px; margin-top:0;">General</p>
							<div style="display:flex; flex-wrap:wrap; gap:12px 32px; margin-bottom:20px;">
								<label style="display:flex;align-items:center;gap:6px;cursor:pointer;">
									<input type="checkbox" name="show_team" <?php checked( ! empty( $col['show_team'] ) ); ?>>
									Team — Show Team Column
								</label>
							</div>

							<p style="font-weight:600; margin-bottom:8px; margin-top:0;">Skater Columns</p>
							<div style="display:flex; flex-wrap:wrap; gap:12px 32px; margin-bottom:20px;">
								<label style="display:flex;align-items:center;gap:6px;cursor:pointer;">
									<input type="checkbox" name="show_pim" <?php checked( ! empty( $col['show_pim'] ) ); ?>>
									PIM — Penalty Minutes
								</label>
								<label style="display:flex;align-items:center;gap:6px;cursor:pointer;">
									<input type="checkbox" name="show_ppg" <?php checked( ! empty( $col['show_ppg'] ) ); ?>>
									PPG — Power Play Goals
								</label>
								<label style="display:flex;align-items:center;gap:6px;cursor:pointer;">
									<input type="checkbox" name="show_shg" <?php checked( ! empty( $col['show_shg'] ) ); ?>>
									SHG — Short-Handed Goals
								</label>
								<label style="display:flex;align-items:center;gap:6px;cursor:pointer;">
									<input type="checkbox" name="show_gwg" <?php checked( ! empty( $col['show_gwg'] ) ); ?>>
									GWG — Game Winning Goals
								</label>
								<label style="display:flex;align-items:center;gap:6px;cursor:pointer;">
									<input type="checkbox" name="show_pts_per_game" <?php checked( ! empty( $col['show_pts_per_game'] ) ); ?>>
									Pts/GP — Points Per Game
								</label>
								<label style="display:flex;align-items:center;gap:6px;cursor:pointer;">
									<input type="checkbox" name="show_sh_pct" <?php checked( ! empty( $col['show_sh_pct'] ) ); ?>>
									SH% — Shooting Percentage
								</label>
							</div>

							<p style="font-weight:600; margin-bottom:8px; margin-top:0;">Goalie Columns</p>
							<div style="display:flex; flex-wrap:wrap; gap:12px 32px; margin-bottom:8px;">
								<label style="display:flex;align-items:center;gap:6px;cursor:pointer;">
									<input type="checkbox" name="show_goalie_otl" <?php checked( ! empty( $col['show_goalie_otl'] ) ); ?>>
									OTL — Overtime Losses
								</label>
								<label style="display:flex;align-items:center;gap:6px;cursor:pointer;">
									<input type="checkbox" name="show_goalie_gaa" <?php checked( ! empty( $col['show_goalie_gaa'] ) ); ?>>
									GAA — Goals Against Average
								</label>
								<label style="display:flex;align-items:center;gap:6px;cursor:pointer;">
									<input type="checkbox" name="show_goalie_svpct" <?php checked( ! empty( $col['show_goalie_svpct'] ) ); ?>>
									SV% — Save Percentage
								</label>
								<label style="display:flex;align-items:center;gap:6px;cursor:pointer;">
									<input type="checkbox" name="show_goalie_sa" <?php checked( ! empty( $col['show_goalie_sa'] ) ); ?>>
									SA — Shots Against
								</label>
								<label style="display:flex;align-items:center;gap:6px;cursor:pointer;">
									<input type="checkbox" name="show_goalie_saves" <?php checked( ! empty( $col['show_goalie_saves'] ) ); ?>>
									Saves
								</label>
							</div>
						</div>

						<div style="margin-top:20px;border-top:1px solid #e0e0e0;padding-top:16px;">
							<p style="font-weight:600; margin-bottom:8px; margin-top:0;">Season Label</p>
							<p style="font-size:0.8rem;color:#5f6368;margin:0 0 10px;">Label shown in the season dropdown for the current (live) season, e.g. <strong>2025-2026</strong>.</p>
							<input
								type="text"
								id="pp-stats-current-season-label"
								name="current_season_label"
								value="<?php echo esc_attr( get_option( 'puck_press_current_season_label', '' ) ); ?>"
								placeholder="e.g. 2025-2026"
								style="width:200px;padding:6px 10px;border:1px solid #ddd;border-radius:4px;">
						</div>

						<div style="margin-top:16px;display:flex;align-items:center;gap:12px;">
							<button class="pp-button pp-button-primary" id="pp-stats-save-columns">Save Column Settings</button>
							<span id="pp-stats-columns-msg" style="display:none;font-size:0.875rem;"></span>
						</div>
					</div>
				</div>

				<!-- ── Stats Preview ── -->
				<?php echo $this->stats_preview->render(); ?>

				<!-- ── Stat Leaders Shortcode Reference ── -->
				<div class="pp-card" style="margin-bottom: 16px; margin-top: 32px;">
					<div class="pp-card-header">
						<div>
							<h2 class="pp-card-title">Stat Leaders Shortcodes</h2>
							<p class="pp-card-subtitle">Use these shortcodes to display the stat leaders widget on any page</p>
						</div>
					</div>
					<div class="pp-card-content" style="padding: 16px 24px;">
						<table style="width:100%;border-collapse:collapse;font-size:0.875rem;">
							<thead>
								<tr style="background:#f5f5f5;">
									<th style="text-align:left;padding:8px 12px;border:1px solid #e0e0e0;font-weight:600;">Shortcode</th>
									<th style="text-align:left;padding:8px 12px;border:1px solid #e0e0e0;font-weight:600;">Description</th>
								</tr>
							</thead>
							<tbody>
								<tr>
									<td style="padding:8px 12px;border:1px solid #e0e0e0;"><code>[pp-stat-leaders-skaters]</code></td>
									<td style="padding:8px 12px;border:1px solid #e0e0e0;">Skater stat leaders widget (Goals, Assists, Points and any other enabled rows).</td>
								</tr>
								<tr>
									<td style="padding:8px 12px;border:1px solid #e0e0e0;"><code>[pp-stat-leaders-goalies]</code></td>
									<td style="padding:8px 12px;border:1px solid #e0e0e0;">Goalie stat leaders widget (GAA, Saves and any other enabled rows).</td>
								</tr>
								<tr>
									<td style="padding:8px 12px;border:1px solid #e0e0e0;"><code>[pp-stat-leaders-skaters roster="varsity"]</code></td>
									<td style="padding:8px 12px;border:1px solid #e0e0e0;">Filter leaders to a specific roster group slug.</td>
								</tr>
								<tr>
									<td style="padding:8px 12px;border:1px solid #e0e0e0;"><code>[pp-stat-leaders-skaters show_header="false"]</code></td>
									<td style="padding:8px 12px;border:1px solid #e0e0e0;">Hide the "Stat Leaders" header and "More" link. Works on both skater and goalie shortcodes. Default is <code>true</code>.</td>
								</tr>
							</tbody>
						</table>
					</div>
				</div>

				<!-- ── Stat Leaders Settings ── -->
				<div class="pp-card" style="margin-bottom: 16px;">
					<div class="pp-card-header">
						<div>
							<h2 class="pp-card-title">Stat Leaders Settings</h2>
							<p class="pp-card-subtitle">Choose which stat rows appear in each widget</p>
						</div>
					</div>
					<div class="pp-card-content" style="padding: 16px 24px;">
						<p style="font-weight:600; margin-bottom:8px; margin-top:0;">Display</p>
						<div style="display:flex; flex-wrap:wrap; gap:12px 32px; margin-bottom:20px;">
							<label style="display:flex;align-items:center;gap:6px;cursor:pointer;">
								<input type="checkbox" name="pp_sl_show_team" <?php checked( (bool) get_option( 'pp_stat_leaders_show_team', 1 ) ); ?>>
								Show team name on player cards
							</label>
						</div>

						<p style="font-weight:600; margin-bottom:8px; margin-top:0;">Skater Leader Rows</p>
						<div style="display:flex; flex-wrap:wrap; gap:12px 32px; margin-bottom:20px;">
							<label style="display:flex;align-items:center;gap:6px;cursor:pointer;">
								<input type="checkbox" name="pp_sl_show_goals" <?php checked( ! empty( $sl_skater['show_goals'] ) ); ?>>
								Goals
							</label>
							<label style="display:flex;align-items:center;gap:6px;cursor:pointer;">
								<input type="checkbox" name="pp_sl_show_assists" <?php checked( ! empty( $sl_skater['show_assists'] ) ); ?>>
								Assists
							</label>
							<label style="display:flex;align-items:center;gap:6px;cursor:pointer;">
								<input type="checkbox" name="pp_sl_show_points" <?php checked( ! empty( $sl_skater['show_points'] ) ); ?>>
								Points
							</label>
							<label style="display:flex;align-items:center;gap:6px;cursor:pointer;">
								<input type="checkbox" name="pp_sl_show_pim" <?php checked( ! empty( $sl_skater['show_pim'] ) ); ?>>
								PIM — Penalty Minutes
							</label>
						</div>

						<p style="font-weight:600; margin-bottom:8px; margin-top:0;">Goalie Leader Rows</p>
						<div style="display:flex; flex-wrap:wrap; gap:12px 32px; margin-bottom:8px;">
							<label style="display:flex;align-items:center;gap:6px;cursor:pointer;">
								<input type="checkbox" name="pp_sl_show_gaa" <?php checked( ! empty( $sl_goalie['show_gaa'] ) ); ?>>
								GAA — Goals Against Average
							</label>
							<label style="display:flex;align-items:center;gap:6px;cursor:pointer;">
								<input type="checkbox" name="pp_sl_show_saves" <?php checked( ! empty( $sl_goalie['show_saves'] ) ); ?>>
								Saves
							</label>
							<label style="display:flex;align-items:center;gap:6px;cursor:pointer;">
								<input type="checkbox" name="pp_sl_show_sv_pct" <?php checked( ! empty( $sl_goalie['show_sv_pct'] ) ); ?>>
								SV% — Save Percentage
							</label>
							<label style="display:flex;align-items:center;gap:6px;cursor:pointer;">
								<input type="checkbox" name="pp_sl_show_wins" <?php checked( ! empty( $sl_goalie['show_wins'] ) ); ?>>
								Wins
							</label>
						</div>

						<div style="margin-top:16px;display:flex;align-items:center;gap:12px;">
							<button class="pp-button pp-button-primary" id="pp-stat-leaders-save-settings">Save Settings</button>
							<span id="pp-stat-leaders-settings-msg" style="display:none;font-size:0.875rem;"></span>
						</div>
					</div>
				</div>

				<!-- ── Stat Leaders Preview ── -->
				<?php echo $this->stat_leaders_preview->render(); ?>

			</main>

			<?php include plugin_dir_path( __FILE__ ) . 'stats-color-palette-modal.php'; ?>
			<?php include plugin_dir_path( __FILE__ ) . 'stat-leaders-color-palette-modal.php'; ?>
		</div>
		<?php
		return ob_get_clean();
	}
}
