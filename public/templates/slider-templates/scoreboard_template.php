<?php

/**
 * Scoreboard Slider Template
 *
 * Displays a 5-game window (2 past + next + 2 upcoming) as compact score-card
 * slides. The next upcoming game is highlighted with the accent colour.
 * Cards lift and overlap neighbours on hover; the footer (venue + ticket)
 * slides up from the card bottom on hover.
 */
class ScoreboardTemplate extends PuckPressTemplate {

	public static function get_key(): string {
		return 'scoreboard';
	}

	public static function get_label(): string {
		return 'Scoreboard';
	}

	protected static function get_directory(): string {
		return 'slider-templates';
	}

	public static function forceResetColors(): bool {
		return false;
	}

	public static function get_default_colors(): array {
		return array(
			'card_bg'      => '#ffffff',
			'card_border'  => '#e5e7eb',
			'header_text'  => '#6b7280',
			'team_text'    => '#111827',
			'score_text'   => '#111827',
			'accent_color' => '#c8102e',
			'accent_text'  => '#ffffff',
			'nav_color'    => '#c8102e',
		);
	}

	public static function get_color_labels(): array {
		return array(
			'card_bg'      => 'Card Background',
			'card_border'  => 'Card Border',
			'header_text'  => 'Date / Status Text',
			'team_text'    => 'Team Name Text',
			'score_text'   => 'Score Text',
			'accent_color' => 'Accent Color (Hover, Active & Nav)',
			'accent_text'  => 'Accent Text',
			'nav_color'    => 'Navigation Arrow Color',
		);
	}

	public static function get_default_fonts(): array {
		return array( 'slider_font' => '' );
	}

	public static function get_font_labels(): array {
		return array( 'slider_font' => 'Slider Font' );
	}

	public static function get_js_dependencies(): array {
		return array( 'jquery', 'glider-js' );
	}

	// -------------------------------------------------------------------------

	public function render_with_options( array $games, array $options ): string {
		$split      = $this->split_games_by_time( $games );
		$next_index = count( $split['past_games'] ); // position of first future game in full sorted set
		$sorted     = $this->sort_games_by_chronological_order( $games );

		// Scroll so the next upcoming game lands as the 3rd visible card (2 past games to its left).
		// Glider.js handles the visible window via slidesToShow — no PHP slicing needed.
		$scroll_to    = max( 0, $next_index - 2 );
		$schedule_id  = isset( $options['schedule_id'] ) ? (int) $options['schedule_id'] : 0;
		$cal_url      = ( $schedule_id > 0 ? get_option( "pp_slider_{$schedule_id}_cal_url", '' ) : get_option( 'pp_slider_cal_url', '' ) ) ?: '#';
		$container_id = $schedule_id > 0 ? 'pp-slider-' . $schedule_id : '';
		$scope        = $container_id ? '#' . $container_id : ':root';
		$colors       = $schedule_id > 0 ? self::get_slider_colors( $schedule_id ) : null;
		$fonts        = $schedule_id > 0 ? self::get_slider_fonts( $schedule_id ) : null;
		$inline_css   = self::get_inline_css( $scope, $colors, $fonts );
		$css_block    = $inline_css ? '<style>' . $inline_css . '</style>' : '';

		ob_start();
		echo $css_block;
		?>
		<div class="scoreboard_slider_container pp-sb-container"<?php echo $container_id ? ' id="' . esc_attr( $container_id ) . '"' : ''; ?>>
			<div class="pp-sb-outer">

				<button class="pp-sb-nav pp-sb-prev" aria-label="Previous">
					<svg width="8" height="14" viewBox="0 0 8 14" fill="none" xmlns="http://www.w3.org/2000/svg">
						<path d="M7 1L1 7L7 13" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
					</svg>
				</button>

				<div class="pp-sb-glider-contain">
					<div class="pp-sb-glider">
						<?php foreach ( $sorted as $i => $game ) : ?>
							<?php echo $this->render_card( $game ); ?>
						<?php endforeach; ?>
					</div>
				</div>

				<button class="pp-sb-nav pp-sb-next" aria-label="Next">
					<svg width="8" height="14" viewBox="0 0 8 14" fill="none" xmlns="http://www.w3.org/2000/svg">
						<path d="M1 1L7 7L1 13" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
					</svg>
				</button>

				<a class="pp-sb-cal-btn" href="<?php echo esc_url( $cal_url ); ?>" aria-label="View full schedule">
					<svg width="20" height="20" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
						<rect x="3" y="4" width="18" height="18" rx="2" stroke="currentColor" stroke-width="2"/>
						<path d="M3 9H21" stroke="currentColor" stroke-width="2"/>
						<path d="M8 2V6" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
						<path d="M16 2V6" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
						<circle cx="8" cy="14" r="1.5" fill="currentColor"/>
						<circle cx="12" cy="14" r="1.5" fill="currentColor"/>
						<circle cx="16" cy="14" r="1.5" fill="currentColor"/>
						<circle cx="8" cy="18" r="1.5" fill="currentColor"/>
						<circle cx="12" cy="18" r="1.5" fill="currentColor"/>
					</svg>
				</a>

			</div>
		</div>
		<script>window.ppScoreboardIndex = <?php echo (int) $scroll_to; ?>;</script>
		<?php
		return ob_get_clean();
	}

	// -------------------------------------------------------------------------

	private function render_card( array $game ): string {
		$now        = new DateTime();
		$is_future  = false;
		$date_label = $game['game_date_day'] ?? '';

		if ( ! empty( $game['game_timestamp'] ) ) {
			try {
				$dt         = new DateTime( $game['game_timestamp'] );
				$is_future  = $dt >= $now;
				$date_label = $dt->format( 'M j' ); // e.g. "Feb 13"
			} catch ( Exception $e ) {
				// leave $date_label as game_date_day fallback
			}
		}

		// A game is unscored when target_score is null, empty, or the placeholder '-'
		$ts          = $game['target_score'] ?? null;
		$is_unscored = ( $ts === null || $ts === '' || $ts === '-' );
		$has_score   = ! $is_future && ! $is_unscored;

		// Header status/time label
		if ( ! empty( $game['game_status'] ) ) {
			$status_label = $game['game_status'];
		} elseif ( ! empty( $game['game_time'] ) ) {
			$status_label = $game['game_time'];
		} else {
			$status_label = 'TBA';
		}

		$default_logo  = 'https://upload.wikimedia.org/wikipedia/commons/thumb/4/47/TBD-W.svg/768px-TBD-W.svg.png?20200316192217';
		$target_logo   = ! empty( $game['target_team_logo'] ) ? $game['target_team_logo'] : $default_logo;
		$opponent_logo = ! empty( $game['opponent_team_logo'] ) ? $game['opponent_team_logo'] : $default_logo;
		$target_name   = $game['target_team_name'] ?? '';
		$opponent_name = $game['opponent_team_name'] ?? '';
		$venue         = $game['venue'] ?? '';
		$ticket_url    = $game['promo_ticket_link'] ?? '';

		$target_wins   = $has_score && (int) $game['target_score'] > (int) $game['opponent_score'];
		$opponent_wins = $has_score && (int) $game['opponent_score'] > (int) $game['target_score'];

		ob_start();
		?>
		<div class="pp-sb-card<?php echo ! empty( $ticket_url ) ? ' pp-sb-card--has-ticket' : ''; ?>">

			<div class="pp-sb-card-hdr">
				<span class="pp-sb-date-label"><?php echo esc_html( $date_label ); ?></span>
				<span class="pp-sb-sep" aria-hidden="true">•</span>
				<span class="pp-sb-status"><?php echo esc_html( $status_label ); ?></span>
			</div>

			<div class="pp-sb-teams">
				<div class="pp-sb-team-row">
					<img class="pp-sb-logo" src="<?php echo esc_url( $target_logo ); ?>" alt="<?php echo esc_attr( $target_name ); ?>" loading="lazy">
					<span class="pp-sb-name"><?php echo esc_html( $target_name ); ?></span>
					<?php if ( $has_score ) : ?>
						<span class="pp-sb-score<?php echo $target_wins ? ' pp-sb-score--w' : ''; ?>"><?php echo esc_html( $game['target_score'] ); ?></span>
					<?php endif; ?>
				</div>
				<div class="pp-sb-team-row">
					<img class="pp-sb-logo" src="<?php echo esc_url( $opponent_logo ); ?>" alt="<?php echo esc_attr( $opponent_name ); ?>" loading="lazy">
					<span class="pp-sb-name"><?php echo esc_html( $opponent_name ); ?></span>
					<?php if ( $has_score ) : ?>
						<span class="pp-sb-score<?php echo $opponent_wins ? ' pp-sb-score--w' : ''; ?>"><?php echo esc_html( $game['opponent_score'] ); ?></span>
					<?php endif; ?>
				</div>
			</div>

			<?php if ( ! empty( $ticket_url ) ) : ?>
			<div class="pp-sb-overlay">
				<div class="pp-sb-ovl-hdr">
					<span class="pp-sb-ovl-date"><?php echo esc_html( $date_label ); ?></span>
					<span class="pp-sb-ovl-sep" aria-hidden="true">•</span>
					<span class="pp-sb-ovl-time"><?php echo esc_html( $status_label ); ?></span>
				</div>
				<?php if ( ! empty( $venue ) ) : ?>
					<div class="pp-sb-ovl-venue"><?php echo esc_html( $venue ); ?></div>
				<?php endif; ?>
				<div class="pp-sb-ovl-links">
					<a class="pp-sb-ovl-link" href="<?php echo esc_url( $ticket_url ); ?>" target="_blank" rel="noopener noreferrer" aria-label="Buy tickets">
						<svg width="25" height="25" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
							<path d="M2 9C2 8.44772 2.44772 8 3 8H21C21.5523 8 22 8.44772 22 9V11C21.1716 11 20.5 11.6716 20.5 12.5C20.5 13.3284 21.1716 14 22 14V16C22 16.5523 21.5523 17 21 17H3C2.44772 17 2 16.5523 2 16V14C2.82843 14 3.5 13.3284 3.5 12.5C3.5 11.6716 2.82843 11 2 11V9Z" stroke="currentColor" stroke-width="2" stroke-linejoin="round"/>
						</svg>
					</a>
				</div>
			</div>
			<div class="pp-sb-hint" aria-hidden="true">
				<svg width="18" height="4" viewBox="0 0 18 4" fill="currentColor" xmlns="http://www.w3.org/2000/svg">
					<circle cx="2" cy="2" r="1.75"/>
					<circle cx="9" cy="2" r="1.75"/>
					<circle cx="16" cy="2" r="1.75"/>
				</svg>
			</div>
			<?php endif; ?>

		</div>
		<?php
		return ob_get_clean();
	}
}
