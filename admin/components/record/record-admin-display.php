<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Puck_Press_Record_Admin_Display {

	private $record_preview;

	public function __construct() {
		require_once plugin_dir_path( __FILE__ ) . 'class-puck-press-record-admin-preview-card.php';

		$schedule_id = isset( $_GET['schedule_id'] ) ? (int) $_GET['schedule_id'] : 1;

		$this->record_preview = new Puck_Press_Record_Admin_Preview_Card(
			array(
				'title'       => 'Record Card Preview',
				'subtitle'    => 'Preview how the team record card will appear on the public website',
				'id'          => 'record-preview',
				'schedule_id' => $schedule_id,
			)
		);
		$this->record_preview->init();
	}

	public function render() {
		global $wpdb;
		$schedule_id = isset( $_GET['schedule_id'] ) ? (int) $_GET['schedule_id'] : 1;
		$all_groups  = $wpdb->get_results(
			"SELECT id, name, slug FROM {$wpdb->prefix}pp_schedules ORDER BY id ASC",
			ARRAY_A
		) ?: array();

		$active_group = null;
		foreach ( $all_groups as $g ) {
			if ( (int) $g['id'] === $schedule_id ) {
				$active_group = $g;
				break;
			}
		}
		$shortcode = ( $active_group && (int) $active_group['id'] !== 1 )
			? '[pp-record schedule="' . esc_attr( $active_group['slug'] ) . '"]'
			: '[pp-record]';

		ob_start();
		?>
		<div class="pp-container">
			<main class="pp-main">
				<div class="pp-section-header">
					<div>
						<h1 class="pp-section-title">Team Record</h1>
						<p class="pp-section-description">Display your team's season record and statistics. Data is derived from scored games in the schedule.</p>
					</div>

					<div class="pp-shortcode-container">
						<div class="pp-shortcode-label">Record Shortcode</div>
						<div class="pp-shortcode-input-group">
							<input
								type="text"
								id="pp-record-shortcode"
								name="pp-record-shortcode"
								class="pp-shortcode-input"
								value="<?php echo esc_attr( $shortcode ); ?>"
								size="<?php echo strlen( $shortcode ); ?>"
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
				</div>

				<div class="pp-card" style="margin-bottom: 16px;">
					<div class="pp-card-header">
						<h2>Shortcode Attributes</h2>
						<p>Customize which fields appear in the record card</p>
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
								<tr style="border-bottom: 1px solid #f0f0f0;">
									<td style="padding: 8px 12px;"><code>show_home_away</code></td>
									<td style="padding: 8px 12px;"><code>true</code></td>
									<td style="padding: 8px 12px;">Show home / away record splits</td>
								</tr>
								<tr style="border-bottom: 1px solid #f0f0f0;">
									<td style="padding: 8px 12px;"><code>show_goals</code></td>
									<td style="padding: 8px 12px;"><code>true</code></td>
									<td style="padding: 8px 12px;">Show goals for (GF) and goals against (GA)</td>
								</tr>
								<tr style="border-bottom: 1px solid #f0f0f0;">
									<td style="padding: 8px 12px;"><code>show_diff</code></td>
									<td style="padding: 8px 12px;"><code>true</code></td>
									<td style="padding: 8px 12px;">Show goal differential (+/-) — requires show_goals=true</td>
								</tr>
								<tr style="border-bottom: 1px solid #f0f0f0;">
									<td style="padding: 8px 12px;"><code>title</code></td>
									<td style="padding: 8px 12px;"><code>Team Record</code></td>
									<td style="padding: 8px 12px;">Card heading text</td>
								</tr>
								<tr>
									<td style="padding: 8px 12px;"><code>schedule</code></td>
									<td style="padding: 8px 12px;"><code>(default)</code></td>
									<td style="padding: 8px 12px;">Schedule group slug — targets a specific group's record. Omit for the default group.</td>
								</tr>
							</tbody>
						</table>
						<p style="margin: 12px 0 0; color: #666; font-size: 0.8rem;">
							Examples: <code>[pp-record show_home_away="false" title="2024–25 Season"]</code> &nbsp;|&nbsp; <code>[pp-record schedule="eagles" title="Eagles Record"]</code>
						</p>
					</div>
				</div>

				<?php if ( count( $all_groups ) > 1 ) : ?>
				<div class="pp-card" style="margin-bottom: 16px;">
					<div class="pp-card-header">
						<h2>Schedule Group</h2>
						<p>Select a group to preview its record and customize its colors independently</p>
					</div>
					<div class="pp-card-content" style="padding: 16px 24px;">
						<form method="get" style="display: flex; align-items: center; gap: 12px;">
							<input type="hidden" name="page" value="puck-press">
							<input type="hidden" name="tab"  value="record">
							<select name="schedule_id" class="pp-select" onchange="this.form.submit()">
								<?php foreach ( $all_groups as $g ) : ?>
									<option value="<?php echo (int) $g['id']; ?>"
										<?php selected( (int) $g['id'], $schedule_id ); ?>>
										<?php echo esc_html( $g['name'] ); ?> (<?php echo esc_html( $g['slug'] ); ?>)
									</option>
								<?php endforeach; ?>
							</select>
						</form>
					</div>
				</div>
				<?php endif; ?>

				<?php echo $this->record_preview->render(); ?>

			</main>

			<?php include plugin_dir_path( __FILE__ ) . 'record-color-palette-modal.php'; ?>
		</div>
		<?php
		return ob_get_clean();
	}
}
