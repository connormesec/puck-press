<?php

/**
 * Score Strip Slider Template
 *
 * A compact ticker-style horizontal bar showing recent results and the next
 * upcoming game. Designed to span the full width of its container with nav
 * arrows flanking a scrollable Glider.js track.
 */
class ScorestripTemplate extends PuckPressTemplate {

	public static function get_key(): string {
		return 'scorestrip';
	}

	public static function get_label(): string {
		return 'Score Strip';
	}

	protected static function get_directory(): string {
		return 'slider-templates';
	}

	public static function forceResetColors(): bool {
		return false;
	}

	public static function get_default_colors(): array {
		return array(
			'strip_bg'        => '#1a1f2e',
			'date_text'       => '#64748b',
			'team_dim'        => '#94a3b8',
			'team_bright'     => '#f1f5f9',
			'score_winner'    => '#facc15',
			'score_loser'     => '#475569',
			'item_divider'    => '#2d3548',
			'next_badge_bg'   => '#2d2200',
			'next_badge_text' => '#facc15',
			'nav_bg'          => '#2d3548',
			'nav_text'        => '#94a3b8',
			'overlay_bg'      => '#1a1f2e',
			'overlay_text'    => '#f1f5f9',
			'recap_hint'      => '#64748b',
		);
	}

	public static function get_color_labels(): array {
		return array(
			'strip_bg'        => 'Strip Background',
			'date_text'       => 'Date / Status Text',
			'team_dim'        => 'Team Name (neutral / loser)',
			'team_bright'     => 'Team Name (winner)',
			'score_winner'    => 'Winning Score',
			'score_loser'     => 'Losing Score',
			'item_divider'    => 'Item Divider',
			'next_badge_bg'   => '"Next Game" Badge Background',
			'next_badge_text' => '"Next Game" Badge Text',
			'nav_bg'          => 'Nav Arrow Background',
			'nav_text'        => 'Nav Arrow Icon',
			'overlay_bg'      => 'Recap Overlay Background',
			'overlay_text'    => 'Recap Overlay Text',
			'recap_hint'      => 'Recap Indicator Dots',
		);
	}

	public static function get_default_fonts(): array {
		return array( 'strip_font' => '' );
	}

	public static function get_font_labels(): array {
		return array( 'strip_font' => 'Strip Font' );
	}

	public static function get_js_dependencies(): array {
		return array( 'jquery', 'glider-js' );
	}

	// -------------------------------------------------------------------------

	public function render_with_options( array $games, array $options ): string {
		$split        = $this->split_games_by_time( $games );
		$next_index   = count( $split['past_games'] );
		$sorted       = $this->sort_games_by_chronological_order( $games );
		$total        = count( $sorted );
		if ( $next_index >= $total ) {
			// No future games — scroll to show the last 4 past games
			$scroll_to = max( 0, $total - 4 );
		} else {
			// Position next game as the rightmost of 4 visible items
			$scroll_to = max( 0, $next_index - 3 );
		}
		$schedule_id  = isset( $options['schedule_id'] ) ? (int) $options['schedule_id'] : 0;
		$container_id = $schedule_id > 0 ? 'pp-slider-' . $schedule_id : '';
		$scope        = $container_id ? '#' . $container_id : ':root';
		$colors       = $schedule_id > 0 ? self::get_slider_colors( $schedule_id ) : null;
		$fonts        = $schedule_id > 0 ? self::get_slider_fonts( $schedule_id ) : null;
		$inline_css   = self::get_inline_css( $scope, $colors, $fonts );
		$css_block    = $inline_css ? '<style>' . $inline_css . '</style>' : '';

		ob_start();
		echo $css_block;
		?>
		<div class="scorestrip_slider_container pp-ss-container"<?php echo $container_id ? ' id="' . esc_attr( $container_id ) . '"' : ''; ?>>
			<div class="pp-ss-inner">

				<button class="pp-ss-nav pp-ss-prev" aria-label="Previous">
					<svg width="7" height="12" viewBox="0 0 7 12" fill="none" xmlns="http://www.w3.org/2000/svg">
						<path d="M6 1L1 6L6 11" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"/>
					</svg>
				</button>

				<div class="pp-ss-glider-wrap">
					<div class="pp-ss-glider">
						<?php foreach ( $sorted as $game ) : ?>
							<?php echo $this->render_item( $game ); ?>
						<?php endforeach; ?>
					</div>
				</div>

				<button class="pp-ss-nav pp-ss-next" aria-label="Next">
					<svg width="7" height="12" viewBox="0 0 7 12" fill="none" xmlns="http://www.w3.org/2000/svg">
						<path d="M1 1L6 6L1 11" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"/>
					</svg>
				</button>

			</div>
		</div>
		<script>window.ppScoreStripIndex = <?php echo (int) $scroll_to; ?>;</script>
		<?php
		return ob_get_clean();
	}

	// -------------------------------------------------------------------------

	private function render_item( array $game ): string {
		$now        = new DateTime();
		$is_future  = false;
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

		$post_link  = ! $is_future ? ( $game['post_link'] ?? '' ) : '';
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

		$default_logo  = 'https://upload.wikimedia.org/wikipedia/commons/thumb/4/47/TBD-W.svg/768px-TBD-W.svg.png?20200316192217';
		$target_logo   = ! empty( $game['target_team_logo'] ) ? $game['target_team_logo'] : $default_logo;
		$opponent_logo = ! empty( $game['opponent_team_logo'] ) ? $game['opponent_team_logo'] : $default_logo;
		$target_name   = $game['target_team_name'] ?? '';
		$opponent_name = $game['opponent_team_name'] ?? '';

		if ( $is_future ) {
			return $this->render_upcoming_item( $game, $date_label, $opponent_logo, $opponent_name );
		}

		// Completed game — determine winner
		$target_wins   = $has_score && (int) $game['target_score'] > (int) $game['opponent_score'];
		$opponent_wins = $has_score && (int) $game['opponent_score'] > (int) $game['target_score'];
		$t_row_class   = $target_wins ? ' pp-ss-row--won' : '';
		$o_row_class   = $opponent_wins ? ' pp-ss-row--won' : '';

		if ( ! empty( $game['game_status'] ) ) {
			$tag = $game['game_status'];
		} elseif ( ! empty( $game['game_time'] ) ) {
			$tag = $game['game_time'];
		} else {
			$tag = 'Final';
		}

		ob_start();
		?>
		<div class="pp-ss-item<?php echo $has_recap ? ' pp-ss-item--has-recap' : ''; ?>">
			<span class="pp-ss-date"><?php echo esc_html( $date_label ); ?></span>
			<div class="pp-ss-matchup">
				<div class="pp-ss-row<?php echo $t_row_class; ?>">
					<img class="pp-ss-logo" src="<?php echo esc_url( $target_logo ); ?>" alt="<?php echo esc_attr( $target_name ); ?>" loading="lazy">
					<span class="pp-ss-team"><?php echo esc_html( $target_name ); ?></span>
					<?php if ( $has_score ) : ?>
						<span class="pp-ss-pts"><?php echo esc_html( $game['target_score'] ); ?></span>
					<?php endif; ?>
				</div>
				<div class="pp-ss-row<?php echo $o_row_class; ?>">
					<img class="pp-ss-logo" src="<?php echo esc_url( $opponent_logo ); ?>" alt="<?php echo esc_attr( $opponent_name ); ?>" loading="lazy">
					<span class="pp-ss-team"><?php echo esc_html( $opponent_name ); ?></span>
					<?php if ( $has_score ) : ?>
						<span class="pp-ss-pts"><?php echo esc_html( $game['opponent_score'] ); ?></span>
					<?php endif; ?>
				</div>
			</div>
			<span class="pp-ss-tag"><?php echo esc_html( $tag ); ?></span>
			<?php if ( $has_recap ) : ?>
			<div class="pp-ss-recap-overlay">
				<div class="pp-ss-recap-meta">
					<span class="pp-ss-recap-date"><?php echo esc_html( $date_label ); ?></span>
					<?php if ( ! empty( $game['venue'] ) ) : ?>
						<span class="pp-ss-recap-sep" aria-hidden="true">·</span>
						<span class="pp-ss-recap-venue"><?php echo esc_html( $game['venue'] ); ?></span>
					<?php endif; ?>
				</div>
				<?php if ( $post_title_truncated ) : ?>
					<div class="pp-ss-recap-title"><?php echo esc_html( $post_title_truncated ); ?></div>
				<?php endif; ?>
				<a class="pp-ss-recap-link"
				   href="<?php echo esc_url( $post_link ); ?>"
				   target="_blank" rel="noopener noreferrer">
					Read Recap →
				</a>
			</div>
			<div class="pp-ss-recap-hint" aria-hidden="true">
				<svg width="18" height="4" viewBox="0 0 18 4" fill="currentColor" xmlns="http://www.w3.org/2000/svg">
					<circle cx="2"  cy="2" r="1.75"/>
					<circle cx="9"  cy="2" r="1.75"/>
					<circle cx="16" cy="2" r="1.75"/>
				</svg>
			</div>
			<?php endif; ?>
		</div>
		<?php
		return ob_get_clean();
	}

	private function render_upcoming_item( array $game, string $date_label, string $opponent_logo, string $opponent_name ): string {
		$is_away = strtoupper( trim( $game['home_or_away'] ?? '' ) ) === 'A';
		$label   = ( $is_away ? '@ ' : 'vs. ' ) . $opponent_name;
		$venue   = $game['venue'] ?? '';
		$time    = $game['game_time'] ?? '';
		$detail  = implode( ' · ', array_filter( array( $time, $venue ) ) );

		ob_start();
		?>
		<div class="pp-ss-item pp-ss-item--upcoming">
			<span class="pp-ss-date"><?php echo esc_html( $date_label ); ?></span>
			<img class="pp-ss-logo pp-ss-logo--lg" src="<?php echo esc_url( $opponent_logo ); ?>" alt="<?php echo esc_attr( $opponent_name ); ?>" loading="lazy">
			<div class="pp-ss-upcoming">
				<div class="pp-ss-upcoming-label"><?php echo esc_html( $label ); ?></div>
				<?php if ( $detail ) : ?>
					<div class="pp-ss-upcoming-detail"><?php echo esc_html( $detail ); ?></div>
				<?php endif; ?>
			</div>
			<span class="pp-ss-tag pp-ss-tag--next">Next</span>
		</div>
		<?php
		return ob_get_clean();
	}
}
