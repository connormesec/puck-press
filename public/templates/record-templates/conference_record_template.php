<?php

class ConferenceRecordTemplate extends PuckPressTemplate {

	public static function get_key(): string {
		return 'conference';
	}

	public static function get_label(): string {
		return 'Conference Standings Row';
	}

	protected static function get_directory(): string {
		return 'record-templates';
	}

	public static function get_default_colors(): array {
		return array(
			'table_bg'    => '#ffffff',
			'header_bg'   => '#1a1a2e',
			'header_text' => '#ffffff',
			'row_text'    => '#111827',
			'pts_bg'      => '#dbeafe',
			'pts_text'    => '#1a1a2e',
			'border'      => '#e5e7eb',
		);
	}

	public static function get_color_labels(): array {
		return array(
			'table_bg'    => 'Table Background',
			'header_bg'   => 'Column Header Background',
			'header_text' => 'Column Header Text',
			'row_text'    => 'Row Values Text',
			'pts_bg'      => 'Pts Column Highlight',
			'pts_text'    => 'Pts Value Text',
			'border'      => 'Table Border Color',
		);
	}

	public static function forceResetColors(): bool {
		return false;
	}

	public static function get_default_fonts(): array {
		return array( 'table_font' => '' );
	}

	public static function get_font_labels(): array {
		return array( 'table_font' => 'Table Font' );
	}

	public function render_with_options( array $values, array $options ): string {
		$rows           = $values['rows'] ?? array();
		$show_home_away = ! isset( $values['show_home_away'] ) || filter_var( $values['show_home_away'], FILTER_VALIDATE_BOOLEAN );
		$show_goals     = ! isset( $values['show_goals'] )     || filter_var( $values['show_goals'],     FILTER_VALIDATE_BOOLEAN );
		$show_diff      = ! isset( $values['show_diff'] )      || filter_var( $values['show_diff'],      FILTER_VALIDATE_BOOLEAN );

		$any_ties = false;
		foreach ( $rows as $row ) {
			if ( (int) ( $row['ties'] ?? 0 ) > 0 ) {
				$any_ties = true;
				break;
			}
		}

		$key         = static::get_key();
		$schedule_id = isset( $options['schedule_id'] ) ? (int) $options['schedule_id'] : 0;
		$colors      = $schedule_id > 0 ? self::get_record_colors( $schedule_id ) : null;
		$fonts       = $schedule_id > 0 ? self::get_record_fonts( $schedule_id ) : null;
		$inline_css  = self::get_inline_css( ':root', $colors, $fonts );
		$css_block   = $inline_css ? '<style>' . $inline_css . '</style>' : '';

		ob_start();
		echo $css_block;
		?>
		<div class="<?php echo esc_attr( $key ); ?>_record_container pp-conference-wrapper">
			<div class="pp-conference-scroll">
				<table class="pp-conference-table">
					<thead>
						<tr class="pp-conference-header-row">
							<th class="pp-conference-th pp-conference-th--team">Team</th>
							<th class="pp-conference-th">GP</th>
							<th class="pp-conference-th">W</th>
							<th class="pp-conference-th">L</th>
							<th class="pp-conference-th">OTL</th>
							<?php if ( $any_ties ) : ?>
							<th class="pp-conference-th">T</th>
							<?php endif; ?>
							<th class="pp-conference-th pp-conference-th--pts">Pts</th>
							<th class="pp-conference-th">P%</th>
							<?php if ( $show_goals ) : ?>
							<th class="pp-conference-th">GF</th>
							<th class="pp-conference-th">GA</th>
							<?php if ( $show_diff ) : ?>
							<th class="pp-conference-th">Diff</th>
							<?php endif; ?>
							<?php endif; ?>
							<?php if ( $show_home_away ) : ?>
							<th class="pp-conference-th">Home</th>
							<th class="pp-conference-th">Away</th>
							<?php endif; ?>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( $rows as $row ) : ?>
						<?php
							$wins        = (int) ( $row['wins']        ?? 0 );
							$losses      = (int) ( $row['losses']      ?? 0 );
							$otl         = (int) ( $row['otl']         ?? 0 );
							$ties        = (int) ( $row['ties']        ?? 0 );
							$gf          = (int) ( $row['gf']          ?? 0 );
							$ga          = (int) ( $row['ga']          ?? 0 );
							$home_wins   = (int) ( $row['home_wins']   ?? 0 );
							$home_losses = (int) ( $row['home_losses'] ?? 0 );
							$home_otl    = (int) ( $row['home_otl']    ?? 0 );
							$home_ties   = (int) ( $row['home_ties']   ?? 0 );
							$away_wins   = (int) ( $row['away_wins']   ?? 0 );
							$away_losses = (int) ( $row['away_losses'] ?? 0 );
							$away_otl    = (int) ( $row['away_otl']    ?? 0 );
							$away_ties   = (int) ( $row['away_ties']   ?? 0 );

							$gp  = $wins + $losses + $otl + $ties;
							$pts = ( $wins * 2 ) + $otl + $ties;
							$pct = $gp > 0 ? number_format( $pts / ( $gp * 2 ), 3 ) : '—';
							if ( $pct !== '—' ) {
								$pct = ltrim( $pct, '0' ) ?: '.000';
							}

							$diff        = $gf - $ga;
							$diff_str    = ( $diff >= 0 ? '+' : '' ) . $diff;
							$diff_cls    = $diff >= 0 ? 'pp-conference-diff--pos' : 'pp-conference-diff--neg';
							$home_record = "{$home_wins}-{$home_losses}-{$home_otl}" . ( $home_ties > 0 ? "-{$home_ties}T" : '' );
							$away_record = "{$away_wins}-{$away_losses}-{$away_otl}" . ( $away_ties > 0 ? "-{$away_ties}T" : '' );
							$team_name   = esc_html( $row['team_name'] ?? '' );
							$team_logo   = esc_url( $row['team_logo'] ?? '' );
						?>
						<tr class="pp-conference-row">
							<td class="pp-conference-td pp-conference-td--team">
								<?php if ( $team_logo ) : ?>
								<img class="pp-conference-team-logo" src="<?php echo $team_logo; ?>" alt="" loading="lazy">
								<?php endif; ?>
								<span class="pp-conference-team-name"><?php echo $team_name; ?></span>
							</td>
							<td class="pp-conference-td"><?php echo $gp; ?></td>
							<td class="pp-conference-td"><?php echo $wins; ?></td>
							<td class="pp-conference-td"><?php echo $losses; ?></td>
							<td class="pp-conference-td"><?php echo $otl; ?></td>
							<?php if ( $any_ties ) : ?>
							<td class="pp-conference-td"><?php echo $ties; ?></td>
							<?php endif; ?>
							<td class="pp-conference-td pp-conference-td--pts"><?php echo $pts; ?></td>
							<td class="pp-conference-td"><?php echo $pct; ?></td>
							<?php if ( $show_goals ) : ?>
							<td class="pp-conference-td"><?php echo $gf; ?></td>
							<td class="pp-conference-td"><?php echo $ga; ?></td>
							<?php if ( $show_diff ) : ?>
							<td class="pp-conference-td <?php echo esc_attr( $diff_cls ); ?>"><?php echo $diff_str; ?></td>
							<?php endif; ?>
							<?php endif; ?>
							<?php if ( $show_home_away ) : ?>
							<td class="pp-conference-td"><?php echo esc_html( $home_record ); ?></td>
							<td class="pp-conference-td"><?php echo esc_html( $away_record ); ?></td>
							<?php endif; ?>
						</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			</div>
		</div>
		<?php
		return ob_get_clean();
	}
}
