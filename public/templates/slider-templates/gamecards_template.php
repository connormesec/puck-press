<?php

/**
 * Game Cards Slider Template
 *
 * A horizontal carousel of game cards ported from the Montana Tech theme's
 * `.mt-game-grid` look (now self-contained under the `pp-` prefix). Renders an
 * "upcoming" or "final" variant per game, auto-scrolls to the next upcoming
 * game, and exposes prev/next arrows when the games overflow the track.
 *
 * Enhancements over the theme version:
 *   1. Completed games link to their recap post ("Summary →") when one exists.
 *   2. Prev/next arrow navigation turns the grid into a carousel.
 */
class GameCardsTemplate extends PuckPressTemplate {

	const DEFAULT_LOGO = 'https://upload.wikimedia.org/wikipedia/commons/thumb/4/47/TBD-W.svg/768px-TBD-W.svg.png?20200316192217';

	public static function get_key(): string {
		return 'gamecards';
	}

	public static function get_label(): string {
		return 'Game Cards';
	}

	protected static function get_directory(): string {
		return 'slider-templates';
	}

	public static function forceResetColors(): bool {
		return false;
	}

	/**
	 * Curated palette. Defaults match the Montana Tech look. Each key maps to a
	 * theme design token + hardcoded fallback in gamecards.css.
	 */
	public static function get_default_colors(): array {
		return array(
			'accent'          => '#c47e3c',
			'home_badge_bg'   => '#215530',
			'home_badge_text' => '#ffffff',
			'away_badge_bg'   => '#f1efe8',
			'soft_text'       => '#4a4a44',
			'card_bg'         => '#ffffff',
			'card_border'     => '#e6e3da',
			'ink'             => '#14231b',
			'muted'           => '#8a8a80',
			'button_bg'       => '#f7f5ef',
			'button_text'     => '#215530',
			'win'             => '#2f8f4e',
			'loss'            => '#c0492f',
		);
	}

	public static function get_color_labels(): array {
		return array(
			'accent'          => 'Accent (top border / button hover / OTL)',
			'home_badge_bg'   => 'Home Badge Background',
			'home_badge_text' => 'Home Badge Text',
			'away_badge_bg'   => 'Away Badge Background',
			'soft_text'       => 'Secondary Text (away badge, venue)',
			'card_bg'         => 'Card Background',
			'card_border'     => 'Card Border',
			'ink'             => 'Primary Text (opponent, score, time)',
			'muted'           => 'Muted Text (date, vs, tie)',
			'button_bg'       => 'Button Background',
			'button_text'     => 'Button Text',
			'win'             => 'Win Badge (W)',
			'loss'            => 'Loss Badge (L)',
		);
	}

	public static function get_default_fonts(): array {
		return array(
			'display_font' => '',
			'mono_font'    => '',
			'body_font'    => '',
		);
	}

	public static function get_font_labels(): array {
		return array(
			'display_font' => 'Display Font (team names, scores)',
			'mono_font'    => 'Label Font (badges, dates)',
			'body_font'    => 'Body Font (buttons, meta)',
		);
	}

	public static function get_js_dependencies(): array {
		return array(); // Vanilla — no jQuery / glider.
	}

	// -------------------------------------------------------------------------

	public function render_with_options( array $games, array $options ): string {
		$schedule_id  = isset( $options['schedule_id'] ) ? (int) $options['schedule_id'] : 0;
		$details_url  = isset( $options['details_url'] ) ? (string) $options['details_url'] : '';
		$container_id = $schedule_id > 0 ? 'pp-slider-' . $schedule_id : '';
		$scope        = $container_id ? '#' . $container_id : ':root';
		$colors       = $schedule_id > 0 ? self::get_slider_colors( $schedule_id ) : null;
		$fonts        = $schedule_id > 0 ? self::get_slider_fonts( $schedule_id ) : null;
		$inline_css   = self::get_inline_css( $scope, $colors, $fonts );
		$css_block    = $inline_css ? '<style>' . $inline_css . '</style>' : '';

		if ( empty( $games ) ) {
			return $css_block . $this->render_empty_state( $container_id );
		}

		$split      = $this->split_games_by_time( $games );
		$next_index = count( $split['past_games'] );
		$sorted     = $this->sort_games_by_chronological_order( $games );

		ob_start();
		echo $css_block;
		?>
		<div class="gamecards_slider_container pp-gamecards"<?php echo $container_id ? ' id="' . esc_attr( $container_id ) . '"' : ''; ?>>

			<button class="pp-gamecards-nav pp-gamecards-prev" type="button" aria-label="Previous games">
				<svg width="8" height="13" viewBox="0 0 7 12" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
					<path d="M6 1L1 6L6 11" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"/>
				</svg>
			</button>

			<div class="pp-game-grid" data-next-index="<?php echo (int) $next_index; ?>">
				<?php
				foreach ( $sorted as $game ) {
					echo $this->render_card( $game, $details_url );
				}
				?>
			</div>

			<button class="pp-gamecards-nav pp-gamecards-next" type="button" aria-label="Next games">
				<svg width="8" height="13" viewBox="0 0 7 12" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
					<path d="M1 1L6 6L1 11" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"/>
				</svg>
			</button>

		</div>
		<?php
		return ob_get_clean();
	}

	// -------------------------------------------------------------------------

	/**
	 * Render a single card, choosing the upcoming or final variant.
	 */
	private function render_card( array $game, string $details_url = '' ): string {
		$now        = new DateTime();
		$is_future  = false;
		$date_label = $game['game_date_day'] ?? '';

		if ( ! empty( $game['game_timestamp'] ) ) {
			try {
				$dt         = new DateTime( $game['game_timestamp'] );
				$is_future  = $dt >= $now;
				$date_label = $dt->format( 'D, M j' );
			} catch ( Exception $e ) {
				// keep $date_label fallback
			}
		}

		// Home/away — tolerate both 'home'/'away' and 'H'/'A' stored values.
		$ha_raw   = trim( (string) ( $game['home_or_away'] ?? '' ) );
		$is_away  = $ha_raw !== '' && strtoupper( $ha_raw[0] ) === 'A';
		$ha_label = $is_away ? 'Away' : 'Home';
		$ha_class = $is_away ? 'pp-game-ha--away' : 'pp-game-ha--home';
		$vs_label = $is_away ? 'at' : 'vs';

		$opponent_logo = ! empty( $game['opponent_team_logo'] ) ? $game['opponent_team_logo'] : self::DEFAULT_LOGO;
		$opponent_name = $game['opponent_team_name'] ?? '';

		$shared = array(
			'date_label'    => $date_label,
			'ha_label'      => $ha_label,
			'ha_class'      => $ha_class,
			'vs_label'      => $vs_label,
			'opponent_logo' => $opponent_logo,
			'opponent_name' => $opponent_name,
		);

		if ( $is_future ) {
			return $this->render_upcoming_card( $game, $shared, $details_url );
		}

		return $this->render_final_card( $game, $shared );
	}

	private function render_card_head( array $s, bool $is_final ): string {
		$date = $is_final ? 'Final · ' . $s['date_label'] : $s['date_label'];
		ob_start();
		?>
		<div class="pp-game-card-top">
			<span class="pp-game-ha <?php echo esc_attr( $s['ha_class'] ); ?>"><?php echo esc_html( $s['ha_label'] ); ?></span>
			<span class="pp-game-date"><?php echo esc_html( $date ); ?></span>
		</div>
		<div class="pp-game-match">
			<img class="pp-game-logo" src="<?php echo esc_url( $s['opponent_logo'] ); ?>" alt="<?php echo esc_attr( $s['opponent_name'] ); ?>" loading="lazy">
			<span class="pp-game-vs"><?php echo esc_html( $s['vs_label'] ); ?></span>
			<span class="pp-game-opp"><?php echo esc_html( $s['opponent_name'] ); ?></span>
		</div>
		<?php
		return ob_get_clean();
	}

	private function render_upcoming_card( array $game, array $s, string $details_url ): string {
		$time   = $game['game_time'] ?? '';
		$venue  = $game['venue'] ?? '';
		$ticket = $game['promo_ticket_link'] ?? '';

		ob_start();
		?>
		<div class="pp-game-card">
			<?php echo $this->render_card_head( $s, false ); ?>
			<div class="pp-game-meta">
				<?php if ( $time ) : ?><span class="pp-game-time"><?php echo esc_html( $time ); ?></span><?php endif; ?>
				<?php if ( $venue ) : ?><span class="pp-game-venue"><?php echo esc_html( $venue ); ?></span><?php endif; ?>
			</div>
			<?php if ( ! empty( $ticket ) ) : ?>
				<a class="pp-game-btn" href="<?php echo esc_url( $ticket ); ?>" target="_blank" rel="noopener noreferrer">Tickets</a>
			<?php elseif ( ! empty( $details_url ) ) : ?>
				<a class="pp-game-btn" href="<?php echo esc_url( $details_url ); ?>">Details</a>
			<?php endif; ?>
		</div>
		<?php
		return ob_get_clean();
	}

	private function render_final_card( array $game, array $s ): string {
		$ts = $game['target_score'] ?? null;
		$os = $game['opponent_score'] ?? null;

		$has_score = $ts !== null && $ts !== '' && $ts !== '-'
			&& $os !== null && $os !== '' && $os !== '-';

		$post_link = $game['post_link'] ?? '';

		ob_start();
		?>
		<div class="pp-game-card pp-game-card--final">
			<?php echo $this->render_card_head( $s, true ); ?>
			<?php if ( $has_score ) : ?>
				<?php
				$ts_i   = (int) $ts;
				$os_i   = (int) $os;
				$status = strtoupper( str_replace( '/', ' ', (string) ( $game['game_status'] ?? '' ) ) );

				if ( $ts_i > $os_i ) {
					$res       = 'W';
					$res_class = 'pp-result-w';
				} elseif ( $ts_i < $os_i ) {
					if ( strpos( $status, 'OT' ) !== false || strpos( $status, 'SO' ) !== false ) {
						$res       = 'OTL';
						$res_class = 'pp-result-otl';
					} else {
						$res       = 'L';
						$res_class = 'pp-result-l';
					}
				} else {
					$res       = 'T';
					$res_class = 'pp-result-t';
				}
				?>
				<div class="pp-game-result">
					<span class="pp-game-resbadge <?php echo esc_attr( $res_class ); ?>"><?php echo esc_html( $res ); ?></span>
					<span class="pp-game-score"><?php echo esc_html( $ts_i . '–' . $os_i ); ?></span>
				</div>
			<?php else : ?>
				<div class="pp-game-meta">
					<?php if ( ! empty( $game['venue'] ) ) : ?><span class="pp-game-venue"><?php echo esc_html( $game['venue'] ); ?></span><?php endif; ?>
				</div>
			<?php endif; ?>
			<?php if ( ! empty( $post_link ) ) : ?>
				<a class="pp-game-btn" href="<?php echo esc_url( $post_link ); ?>">Summary &rarr;</a>
			<?php endif; ?>
		</div>
		<?php
		return ob_get_clean();
	}

	private function render_empty_state( string $container_id ): string {
		ob_start();
		?>
		<div class="gamecards_slider_container pp-gamecards pp-gamecards--empty"<?php echo $container_id ? ' id="' . esc_attr( $container_id ) . '"' : ''; ?>>
			<div class="pp-gamecards-empty-inner">
				<svg class="pp-gamecards-empty-icon" width="28" height="28" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
					<rect x="3" y="5" width="18" height="16" rx="2" stroke="currentColor" stroke-width="1.75"/>
					<path d="M3 10H21" stroke="currentColor" stroke-width="1.75"/>
					<path d="M8 3V7" stroke="currentColor" stroke-width="1.75" stroke-linecap="round"/>
					<path d="M16 3V7" stroke="currentColor" stroke-width="1.75" stroke-linecap="round"/>
				</svg>
				<div class="pp-gamecards-empty-text">
					<div class="pp-gamecards-empty-title">No games scheduled</div>
					<div class="pp-gamecards-empty-sub">Check back soon for upcoming matchups</div>
				</div>
			</div>
		</div>
		<?php
		return ob_get_clean();
	}
}
