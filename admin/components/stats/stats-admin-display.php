<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Puck_Press_Stats_Admin_Display {

	private $stats_preview;

	public function __construct() {
		require_once plugin_dir_path( __FILE__ ) . 'class-puck-press-stats-admin-preview-card.php';

		$this->stats_preview = new Puck_Press_Stats_Admin_Preview_Card(
			array(
				'title'    => 'Stats Preview',
				'subtitle' => 'Preview how the stats table will appear on the public website.',
				'id'       => 'stats-preview',
			)
		);
		$this->stats_preview->init();
	}

	public function render() {
		$defaults = Puck_Press_Stats_Wpdb_Utils::get_default_column_settings();
		$saved    = get_option( 'pp_stats_column_settings', array() );
		$col      = array_merge( $defaults, is_array( $saved ) ? $saved : array() );

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

						<div style="margin-top:16px;display:flex;align-items:center;gap:12px;">
							<button class="pp-button pp-button-primary" id="pp-stats-save-columns">Save Column Settings</button>
							<span id="pp-stats-columns-msg" style="display:none;font-size:0.875rem;"></span>
						</div>
					</div>
				</div>

				<!-- ── Stats Preview ── -->
				<?php echo $this->stats_preview->render(); ?>

			</main>

			<?php include plugin_dir_path( __FILE__ ) . 'stats-color-palette-modal.php'; ?>
		</div>
		<?php
		return ob_get_clean();
	}
}
