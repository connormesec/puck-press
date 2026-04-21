<?php

/**
 * CardStack Template
 */
class GameSliderTemplate extends PuckPressTemplate {

	/**
	 * Returns a unique key for the template
	 */
	public static function get_key(): string {
		return 'gameslider';
	}

	/**
	 * Returns a human-readable label
	 */
	public static function get_label(): string {
		return 'Game Slider';
	}

	protected static function get_directory(): string {
		return 'slider-templates';
	}

	public static function forceResetColors(): bool {
		return false; // only set to true if you want to reset colors, this will overwrite user settings and should be used in development only
	}

	/**
	 * Returns an array of default colors
	 */
	public static function get_default_colors(): array {
		// colors should be in hex format and be uniquely named
		return array(
			'header_text_color' => '#f5f5f5',
			'header_bg_color'   => '#333333',
			'body_bg_color'     => '#cccccc',
			'body_text_color'   => '#000000',
			'nav_arrow_color'   => '#215533',
			'border_color'      => '#000000',
			'overlay_bg'        => '#333333',
			'overlay_text'      => '#f5f5f5',
			'recap_hint_color'  => '#215533',
		);
	}

	public static function get_color_labels(): array {
		return array(
			'header_text_color' => 'Header Text',
			'header_bg_color'   => 'Header Background',
			'body_bg_color'     => 'Card Background',
			'body_text_color'   => 'Card Text',
			'nav_arrow_color'   => 'Nav Arrow',
			'border_color'      => 'Card Border',
			'overlay_bg'        => 'Recap Overlay Background',
			'overlay_text'      => 'Recap Overlay Text',
			'recap_hint_color'  => 'Recap Indicator Dots',
		);
	}

	public static function get_default_fonts(): array {
		return array( 'slider_font' => '' );
	}

	public static function get_font_labels(): array {
		return array( 'slider_font' => 'Slider Font' );
	}

	// use this to set additional js dependencies make sure to also update the registry in the template manager abstract file
	public static function get_js_dependencies() {
		return array( 'jquery', 'glider-js' );
	}

	/**
	 * Returns the template output
	 */
	public function render_with_options( array $games, array $options ): string {
		$schedule_id  = isset( $options['schedule_id'] ) ? (int) $options['schedule_id'] : 0;
		$container_id = $schedule_id > 0 ? 'pp-slider-' . $schedule_id : '';
		$scope        = $container_id ? '#' . $container_id : ':root';
		$colors       = $schedule_id > 0 ? self::get_slider_colors( $schedule_id ) : null;
		$fonts        = $schedule_id > 0 ? self::get_slider_fonts( $schedule_id ) : null;
		$inline_css   = self::get_inline_css( $scope, $colors, $fonts );
		$css_block    = $inline_css ? '<style>' . $inline_css . '</style>' : '';

		return $css_block . $this->buildSlider( $games, $container_id );
	}

	public function buildSlider( array $games, string $container_id = '' ) {
		// Split games into past and future
		$split   = $this->split_games_by_time( $games );
		$counter = count( $split['past_games'] );

		$sorted_games = $this->sort_games_by_chronological_order( $games );
		ob_start();
		?>
		<div class="gameslider_slider_container clearfix"<?php echo $container_id ? ' id="' . esc_attr( $container_id ) . '"' : ''; ?>>
			<div class="glider-contain" style="max-height: 130px; overflow: hidden;">
				<div class="glider">
					<?php
					foreach ( $sorted_games as $game ) {
						echo $this->render_game_slide( $game );
					}
					?>
				</div>
				<button aria-label="Previous" class="glider-prev">‹</button>
				<button aria-label="Next" class="glider-next">›</button>
			</div>
		</div>
		<script>
			window.gameSliderScrollIndex = <?php echo json_encode( $counter ); ?>;
		</script>
		<?php
		return ob_get_clean();
	}

	private function render_game_slide( $game ) {
		$now            = new DateTime();
		$is_future_game = false;
		$date_label     = '';

		if ( ! empty( $game['game_timestamp'] ) ) {
			try {
				$game_time      = new DateTime( $game['game_timestamp'] );
				$is_future_game = $game_time >= $now;
				$date_label     = $game_time->format( 'M j' );
			} catch ( Exception $e ) {
				$is_future_game = false;
			}
		}

		if ( empty( $date_label ) ) {
			$date_label = $game['game_date_day'] ?? '';
		}

		$post_link  = ! $is_future_game ? ( $game['post_link'] ?? '' ) : '';
		$has_recap  = ! empty( $post_link );

		$post_title_truncated = '';
		if ( $has_recap ) {
			$post_id = url_to_postid( $post_link );
			if ( $post_id ) {
				$raw                  = get_the_title( $post_id );
				$post_title_truncated = mb_strlen( $raw ) > 55
					? mb_substr( $raw, 0, 52 ) . '…'
					: $raw;
			}
		}

		$is_unscored_past_game = isset( $game['target_score'] ) && $game['target_score'] === '-';

		// Default fallback logo
		$default_logo = 'https://upload.wikimedia.org/wikipedia/commons/thumb/4/47/TBD-W.svg/768px-TBD-W.svg.png?20200316192217'; // Adjust path as needed

		// Check and assign logos, fallback if empty or invalid
		$opponent_logo = ! empty( $game['opponent_team_logo'] ) ? $game['opponent_team_logo'] : $default_logo;
		$target_logo   = ! empty( $game['target_team_logo'] ) ? $game['target_team_logo'] : $default_logo;

		ob_start();
		?>
		<div class="content">
			<div class="entry" style="height: 120px">
				<div class="game_vs_message">
					<div class="home_or_away"><?php echo ( $game['game_status'] ) ? $game['game_status'] : esc_html( $game['home_or_away'] ?? '' ); ?></div>
					<span class="vs">
						<?php
						echo ( $is_future_game || $is_unscored_past_game )
							? 'VS'
							: esc_html( ( $game['target_score'] ?? '' ) . ' - ' . ( $game['opponent_score'] ?? '' ) )
						?>
					</span>
				</div>
				<div class="hometeam">
					<div class="thumb">
						<img src="<?php echo esc_url( $opponent_logo ); ?>" alt="<?php echo esc_attr( $game['opponent_team_name'] ?? '' ); ?>" loading="lazy">
					</div>
				</div>
				<div class="awayteam_active">
					<div class="thumb">
						<img src="<?php echo esc_url( $target_logo ); ?>" alt="<?php echo esc_attr( $game['target_team_name'] ?? 'Away Team' ); ?>" loading="lazy">
					</div>
				</div>
				<div class="details">
					<span class="time"><?php echo esc_html( $game['game_date_day'] ?? '' ); ?></span>
				</div>
				<?php if ( $has_recap ) : ?>
				<div class="gs-recap-overlay">
					<div class="gs-recap-meta">
						<span class="gs-recap-date"><?php echo esc_html( $date_label ); ?></span>
						<?php if ( ! empty( $game['venue'] ) ) : ?>
							<span class="gs-recap-sep" aria-hidden="true">·</span>
							<span class="gs-recap-venue"><?php echo esc_html( $game['venue'] ); ?></span>
						<?php endif; ?>
					</div>
					<?php if ( $post_title_truncated ) : ?>
						<div class="gs-recap-title"><?php echo esc_html( $post_title_truncated ); ?></div>
					<?php endif; ?>
					<a class="gs-recap-link"
					   href="<?php echo esc_url( $post_link ); ?>"
					   target="_blank" rel="noopener noreferrer">
						Read Recap →
					</a>
				</div>
				<div class="gs-recap-hint" aria-hidden="true">
					<svg width="18" height="4" viewBox="0 0 18 4" fill="currentColor" xmlns="http://www.w3.org/2000/svg">
						<circle cx="2"  cy="2" r="1.75"/>
						<circle cx="9"  cy="2" r="1.75"/>
						<circle cx="16" cy="2" r="1.75"/>
					</svg>
				</div>
				<?php endif; ?>
			</div>
		</div>
		<?php
		return ob_get_clean();
	}
}
