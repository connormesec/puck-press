<?php

/**
 * Compact Slider Template
 *
 * Game slider with circular nav buttons below the track and a "Full Schedule"
 * text link floating over the upper-right corner of the cards. Completed games
 * that have a matching pp_game_summary recap post show a "Read Recap" link in
 * the card overlay instead of a ticket button.
 */
class CompactTemplate extends PuckPressTemplate {

	public static function get_key(): string {
		return 'compact';
	}

	public static function get_label(): string {
		return 'Compact';
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
			'card_border'  => '#e2e8f0',
			'header_text'  => '#64748b',
			'team_text'    => '#0f172a',
			'score_text'   => '#0f172a',
			'accent_color' => '#1e40af',
			'accent_text'  => '#ffffff',
			'nav_bg'       => '#1e293b',
			'nav_text'     => '#ffffff',
		);
	}

	public static function get_color_labels(): array {
		return array(
			'card_bg'      => 'Card Background',
			'card_border'  => 'Card Border',
			'header_text'  => 'Date / Status Text',
			'team_text'    => 'Team Name Text',
			'score_text'   => 'Score Text',
			'accent_color' => 'Accent Color (Top Strip, Link & Overlay)',
			'accent_text'  => 'Accent Text',
			'nav_bg'       => 'Nav Button Background',
			'nav_text'     => 'Nav Button Arrow Color',
		);
	}

	public static function get_js_dependencies(): array {
		return array( 'jquery', 'glider-js' );
	}

	// -------------------------------------------------------------------------

	public function render_with_options( array $games, array $options ): string {
		$split        = $this->split_games_by_time( $games );
		$next_index   = count( $split['past_games'] );
		$sorted       = $this->sort_games_by_chronological_order( $games );
		$scroll_to    = max( 0, $next_index - 2 );
		$cal_url      = get_option( 'pp_slider_cal_url', '' ) ?: '#';
		$schedule_id  = isset( $options['schedule_id'] ) ? (int) $options['schedule_id'] : 0;
		$container_id = $schedule_id > 0 ? 'pp-slider-' . $schedule_id : '';
		$scope        = $container_id ? '#' . $container_id : ':root';
		$colors       = $schedule_id > 0 ? self::get_slider_colors( $schedule_id ) : null;
		$inline_css   = self::get_inline_css( $scope, $colors, null );
		$css_block    = $inline_css ? '<style>' . $inline_css . '</style>' : '';

		ob_start();
		echo $css_block;
		?>
		<div class="compact_slider_container pp-cs-container"<?php echo $container_id ? ' id="' . esc_attr( $container_id ) . '"' : ''; ?>>

			<div class="pp-cs-header">
				<a class="pp-cs-sched-link" href="<?php echo esc_url( $cal_url ); ?>">
					Full Schedule &#x2192;
				</a>
			</div>

			<div class="pp-cs-glider-contain">
				<div class="pp-cs-glider">
					<?php foreach ( $sorted as $game ) : ?>
						<?php echo $this->render_card( $game ); ?>
					<?php endforeach; ?>
				</div>
			</div>

			<div class="pp-cs-nav-row">
				<button class="pp-cs-nav pp-cs-prev" aria-label="Previous">
					<svg width="8" height="14" viewBox="0 0 8 14" fill="none" xmlns="http://www.w3.org/2000/svg">
						<path d="M7 1L1 7L7 13" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
					</svg>
				</button>
				<button class="pp-cs-nav pp-cs-next" aria-label="Next">
					<svg width="8" height="14" viewBox="0 0 8 14" fill="none" xmlns="http://www.w3.org/2000/svg">
						<path d="M1 1L7 7L1 13" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
					</svg>
				</button>
			</div>
		</div>
		<script>window.ppCompactIndex = <?php echo (int) $scroll_to; ?>;</script>
		<?php
		return ob_get_clean();
	}

	// -------------------------------------------------------------------------

	private function render_card( array $game ): string {
		$now       = new DateTime();
		$is_future = false;
		$date_label = $game['game_date_day'] ?? '';

		if ( ! empty( $game['game_timestamp'] ) ) {
			try {
				$dt         = new DateTime( $game['game_timestamp'] );
				$is_future  = $dt >= $now;
				$date_label = $dt->format( 'M j' );
			} catch ( Exception $e ) {
				// leave $date_label as fallback
			}
		}

		$ts          = $game['target_score'] ?? null;
		$is_unscored = ( $ts === null || $ts === '' || $ts === '-' );
		$has_score   = ! $is_future && ! $is_unscored;

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
		$venue      = $game['venue'] ?? '';
		$ticket_url = $game['promo_ticket_link'] ?? '';
		// post_link is stored directly on the game row by save_post_link_for_game().
		$recap_url  = ( ! $is_future && ! empty( $game['post_link'] ) ) ? $game['post_link'] : '';

		$target_wins   = $has_score && (int) $game['target_score'] > (int) $game['opponent_score'];
		$opponent_wins = $has_score && (int) $game['opponent_score'] > (int) $game['target_score'];

		// A card has an interactive overlay if there is a recap or a future ticket link.
		$has_overlay = $recap_url || ( $is_future && $ticket_url );

		ob_start();
		?>
		<div class="pp-cs-card<?php echo $has_overlay ? ' pp-cs-card--has-overlay' : ''; ?>">

			<div class="pp-cs-card-hdr">
				<span class="pp-cs-date-label"><?php echo esc_html( $date_label ); ?></span>
				<span class="pp-cs-sep" aria-hidden="true">•</span>
				<span class="pp-cs-status"><?php echo esc_html( $status_label ); ?></span>
			</div>

			<div class="pp-cs-teams">
				<div class="pp-cs-team-row">
					<img class="pp-cs-logo" src="<?php echo esc_url( $target_logo ); ?>" alt="<?php echo esc_attr( $target_name ); ?>" loading="lazy">
					<span class="pp-cs-name"><?php echo esc_html( $target_name ); ?></span>
					<?php if ( $has_score ) : ?>
						<span class="pp-cs-score<?php echo $target_wins ? ' pp-cs-score--w' : ''; ?>"><?php echo esc_html( $game['target_score'] ); ?></span>
					<?php endif; ?>
				</div>
				<div class="pp-cs-team-row">
					<img class="pp-cs-logo" src="<?php echo esc_url( $opponent_logo ); ?>" alt="<?php echo esc_attr( $opponent_name ); ?>" loading="lazy">
					<span class="pp-cs-name"><?php echo esc_html( $opponent_name ); ?></span>
					<?php if ( $has_score ) : ?>
						<span class="pp-cs-score<?php echo $opponent_wins ? ' pp-cs-score--w' : ''; ?>"><?php echo esc_html( $game['opponent_score'] ); ?></span>
					<?php endif; ?>
				</div>
			</div>

			<?php if ( $has_overlay ) : ?>
			<div class="pp-cs-overlay">
				<div class="pp-cs-ovl-hdr">
					<span class="pp-cs-ovl-date"><?php echo esc_html( $date_label ); ?></span>
					<span class="pp-cs-ovl-sep" aria-hidden="true">•</span>
					<span class="pp-cs-ovl-time"><?php echo esc_html( $status_label ); ?></span>
				</div>
				<?php if ( ! empty( $venue ) ) : ?>
					<div class="pp-cs-ovl-venue"><?php echo esc_html( $venue ); ?></div>
				<?php endif; ?>
				<div class="pp-cs-ovl-links">
					<?php if ( $recap_url ) : ?>
						<a class="pp-cs-ovl-link pp-cs-ovl-link--recap" href="<?php echo esc_url( $recap_url ); ?>" aria-label="Read recap">
							Read Recap &#x2192;
						</a>
					<?php elseif ( $is_future && $ticket_url ) : ?>
						<a class="pp-cs-ovl-link pp-cs-ovl-link--ticket" href="<?php echo esc_url( $ticket_url ); ?>" target="_blank" rel="noopener noreferrer" aria-label="Buy tickets">
							<svg width="25" height="25" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
								<path d="M2 9C2 8.44772 2.44772 8 3 8H21C21.5523 8 22 8.44772 22 9V11C21.1716 11 20.5 11.6716 20.5 12.5C20.5 13.3284 21.1716 14 22 14V16C22 16.5523 21.5523 17 21 17H3C2.44772 17 2 16.5523 2 16V14C2.82843 14 3.5 13.3284 3.5 12.5C3.5 11.6716 2.82843 11 2 11V9Z" stroke="currentColor" stroke-width="2" stroke-linejoin="round"/>
							</svg>
						</a>
					<?php endif; ?>
				</div>
			</div>
			<div class="pp-cs-hint" aria-hidden="true">
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
