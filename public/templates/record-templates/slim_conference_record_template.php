<?php

class SlimConferenceRecordTemplate extends PuckPressTemplate {

	public static function get_key(): string {
		return 'slim_conference';
	}

	public static function get_label(): string {
		return 'Slim Conference Standings';
	}

	protected static function get_directory(): string {
		return 'record-templates';
	}

	public static function get_default_colors(): array {
		return array(
			'table_bg'    => '#ffffff',
			'header_bg'   => '#16213e',
			'header_text' => '#ffffff',
			'row_text'    => '#111827',
			'pts_bg'      => '#1d3461',
			'pts_text'    => '#ffffff',
			'border'      => '#e5e7eb',
			'btn_bg'      => '#16213e',
			'btn_text'    => '#ffffff',
		);
	}

	public static function get_color_labels(): array {
		return array(
			'table_bg'    => 'Table Background',
			'header_bg'   => 'Header Background',
			'header_text' => 'Header Text',
			'row_text'    => 'Row Text',
			'pts_bg'      => 'Points Highlight',
			'pts_text'    => 'Points Text',
			'border'      => 'Row Border',
			'btn_bg'      => 'Button Background',
			'btn_text'    => 'Button Text',
		);
	}

	public static function forceResetColors(): bool {
		return false;
	}

	public static function get_default_fonts(): array {
		return array(
			'table_font' => '',
			'conf_label' => '',
			'btn_label'  => '',
			'btn_url'    => '',
		);
	}

	public static function get_font_labels(): array {
		return array(
			'table_font' => 'Table Font',
			'conf_label' => 'Conference Column Label',
			'btn_label'  => 'Button Label',
			'btn_url'    => 'Button URL',
		);
	}

	public function render_with_options( array $values, array $options ): string {
		$rows         = $values['rows'] ?? array();
		$schedule_id  = isset( $options['schedule_id'] ) ? (int) $options['schedule_id'] : 0;
		$container_id = $schedule_id > 0 ? 'pp-record-' . $schedule_id : '';
		$scope        = $container_id ? '#' . $container_id : ':root';
		$colors       = $schedule_id > 0 ? self::get_record_colors( $schedule_id ) : null;
		$fonts        = $schedule_id > 0 ? self::get_record_fonts( $schedule_id ) : null;
		$inline_css   = self::get_inline_css( $scope, $colors, $fonts );
		$css_block    = $inline_css ? '<style>' . $inline_css . '</style>' : '';

		$schedule_name = ! empty( $options['schedule_name'] ) ? $options['schedule_name'] : 'Conference';
		$conf_label    = ! empty( $fonts['conf_label'] ) ? $fonts['conf_label'] : $schedule_name;
		$btn_label   = ! empty( $fonts['btn_label'] )  ? $fonts['btn_label']  : 'Full Standings';
		$btn_url     = ! empty( $fonts['btn_url'] )    ? $fonts['btn_url']    : '';
		$has_overall = ! empty( $rows ) && array_key_exists( 'overall_wins', $rows[0] );

		$key = static::get_key();

		ob_start();
		echo $css_block;
		?>
		<div class="<?php echo esc_attr( $key ); ?>_record_container pp-slim-wrapper"<?php echo $container_id ? ' id="' . esc_attr( $container_id ) . '"' : ''; ?>>
			<table class="pp-slim-table">
				<thead>
					<tr class="pp-slim-header-row">
						<th class="pp-slim-th pp-slim-th--team">Team</th>
						<th class="pp-slim-th pp-slim-th--pts">PTS</th>
						<th class="pp-slim-th"><?php echo esc_html( $conf_label ); ?></th>
						<?php if ( $has_overall ) : ?>
						<th class="pp-slim-th">Overall</th>
						<?php endif; ?>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $rows as $row ) : ?>
					<?php
						$wins      = (int) ( $row['wins']   ?? 0 );
						$losses    = (int) ( $row['losses'] ?? 0 );
						$otl       = (int) ( $row['otl']    ?? 0 );
						$ties      = (int) ( $row['ties']   ?? 0 );
						$pts       = ( ( (int) ( $row['overall_wins'] ?? $wins ) ) * 2 ) + (int) ( $row['overall_otl'] ?? $otl ) + (int) ( $row['overall_ties'] ?? $ties );
						$record    = "{$wins}-{$losses}-{$otl}" . ( $ties > 0 ? "-{$ties}T" : '' );
						$team_name = esc_html( $row['team_name'] ?? '' );
						$team_logo = esc_url( $row['team_logo'] ?? '' );

						if ( $has_overall ) {
							$ow = $row['overall_wins'];
							if ( $ow === null ) {
								$overall_record = '—';
							} else {
								$ol  = (int) $row['overall_losses'];
								$oo  = (int) $row['overall_otl'];
								$ot  = (int) $row['overall_ties'];
								$overall_record = "{$ow}-{$ol}-{$oo}" . ( $ot > 0 ? "-{$ot}T" : '' );
							}
						}
					?>
					<tr class="pp-slim-row">
						<td class="pp-slim-td pp-slim-td--team">
							<?php if ( $team_logo ) : ?>
							<img class="pp-slim-logo" src="<?php echo $team_logo; ?>" alt="" loading="lazy">
							<?php endif; ?>
							<span class="pp-slim-team-name"><?php echo $team_name; ?></span>
						</td>
						<td class="pp-slim-td pp-slim-td--pts"><?php echo $pts; ?></td>
						<td class="pp-slim-td"><?php echo esc_html( $record ); ?></td>
						<?php if ( $has_overall ) : ?>
						<td class="pp-slim-td"><?php echo esc_html( $overall_record ); ?></td>
						<?php endif; ?>
					</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
			<?php if ( $btn_url ) : ?>
			<a href="<?php echo esc_url( $btn_url ); ?>" class="pp-slim-btn"><?php echo esc_html( $btn_label ); ?></a>
			<?php endif; ?>
		</div>
		<?php
		return ob_get_clean();
	}
}
