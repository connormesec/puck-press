<?php

/**
 * Pill Template
 */
class PillTemplate extends PuckPressTemplate {

	/**
	 * Returns a unique key for the template
	 */
	public static function get_key(): string {
		return 'pill';
	}

	/**
	 * Returns a human-readable label
	 */
	public static function get_label(): string {
		return 'Pill Schedule';
	}

	protected static function get_directory(): string {
		return 'schedule-templates';
	}

	public static function forceResetColors(): bool {
		return false; // only set to true if you want to reset colors, this will overwrite user settings and should be used in development only
	}

	/**
	 * Returns an array of default colors
	 */
	public static function get_default_colors(): array {
		return array(
			'home_tab_text' => '#f5f5f5',
			'home_tab_bg'   => '#333333',
			'month_text'    => '#cccccc',
			'text'          => '#dedede',
			'container_bg'  => '#66b588',
		);
	}

	public static function get_default_fonts(): array {
		return array( 'schedule_font' => '' );
	}

	public static function get_font_labels(): array {
		return array( 'schedule_font' => 'Schedule Font' );
	}

	/**
	 * Returns the template output
	 */
	public function render_with_options( array $games, array $options ): string {
		$slug         = $options['schedule_slug'] ?? '';
		$schedule_id  = isset( $options['schedule_id'] ) ? (int) $options['schedule_id'] : 0;
		$container_id = $slug ? 'pp-sched-' . sanitize_html_class( $slug ) : '';
		$scope        = $container_id ? '#' . $container_id : ':root';
		$colors       = $schedule_id > 0 ? self::get_schedule_colors( $schedule_id ) : null;
		$fonts        = $schedule_id > 0 ? self::get_schedule_fonts( $schedule_id ) : null;
		$inline_css   = self::get_inline_css( $scope, $colors, $fonts );
		$css_block    = $inline_css ? '<style>' . $inline_css . '</style>' : '';
		$id_attr      = $container_id ? ' id="' . esc_attr( $container_id ) . '"' : '';
		$html         = $css_block . '<div class="pill_schedule_container"' . $id_attr . '>';
		$html        .= $this->buildPillSchedule( $games, $options['is_archive'] ?? false );
		$html        .= '</div>';
		return $html;
	}

	public function buildPillSchedule( array $games, bool $is_archive = false ) {
		if ( $is_archive ) {
			$all_grouped = self::group_games_by_month( $games, false );
			$content     = '';
			foreach ( array_reverse( array_keys( $all_grouped ) ) as $month_year ) {
				$month_label = self::extract_month( $month_year );
				$content    .= '<div class="month_container">';
				$content    .= '<h2 class="game_month_title">' . $month_label . '</h2>';
				foreach ( array_reverse( $all_grouped[ $month_year ] ) as $game ) {
					$content .= $this->createEachGame( $game, false );
				}
				$content .= '</div>';
			}
			return $content;
		}

		$games_split = $this->split_games_by_time( $games );

		$past_future_games = (object) array(
			'past_games'   => self::group_games_by_month( $games_split['past_games'] ),
			'future_games' => self::group_games_by_month( $games_split['future_games'] ),
		);

		$months  = array( 'August', 'September', 'October', 'November', 'December', 'January', 'February', 'March', 'April', 'May', 'June', 'July' );
		$content = ' <div class="btn_header_wrap">
                <button class="header_btn" id="upcoming_games_btn" onclick="show_hide(\'future_games\',\'past_games\', \'upcoming_games_btn\', \'past_games_btn\')">Upcoming</button>
                <button class="header_btn" id="past_games_btn" onclick="show_hide(\'past_games\', \'future_games\', \'past_games_btn\', \'upcoming_games_btn\')">Past</button>
                </div>';

		$content .= '<div class="schedule_container css-transitions-only-after-page-load">';

		$content .= '<div id="past_games" class="past_games" style="display: none;">';
		foreach ( array_reverse( $months ) as $month ) {
			$content .= $this->buildScheduleByMonth( $past_future_games->past_games, $month, true );
		}
		$content .= '</div>';

		$content .= '<div id="future_games" class="future_games">';
		foreach ( $months as $month ) {
			$content .= $this->buildScheduleByMonth( $past_future_games->future_games, $month, false );
		}
		$content .= '</div>';

		$content .= '</div>';
		return $content;
	}

	private function buildScheduleByMonth( array $month_games, string $month, bool $is_past ) {
		$content = '';
		if ( isset( $month_games[ $month ] ) ) {

			$content .= '<div class="month_container">
                            <h2 class="game_month_title">' . $month . '</h2>';
			if ( $is_past ) {
				foreach ( array_reverse( $month_games[ $month ] ) as $game ) {
					$content .= $this->createEachGame( $game, false );
				}
			} else {
				foreach ( $month_games[ $month ] as $game ) {
					$content .= $this->createEachGame( $game, true );
				}
			}
			$content .= '</div>';
		}
		return $content;
	}

	private function createEachGame( $game, bool $should_hide_score ) {
		$game_result_message    = '';
		$game_status_normalized = str_replace( '/', ' ', $game['game_status'] ?? '' );
		if ( $game_status_normalized == 'Final' ) {
			if ( $game['target_score'] < $game['opponent_score'] ) {
				$game_result_message = 'L';
			} elseif ( $game['target_score'] > $game['opponent_score'] ) {
				$game_result_message = 'W';
			} else {
				$game_result_message = 'T';
			}
		} elseif ( $game_status_normalized == 'Final OT' ) {
			if ( $game['target_score'] < $game['opponent_score'] ) {
				$game_result_message = 'OTL';
			} elseif ( $game['target_score'] > $game['opponent_score'] ) {
				$game_result_message = 'OTW';
			} else {
				$game_result_message = 'OT';
			}
		} elseif ( $game_status_normalized == 'Final SO' ) {
			if ( $game['target_score'] < $game['opponent_score'] ) {
				$game_result_message = 'SOL';
			} elseif ( $game['target_score'] > $game['opponent_score'] ) {
				$game_result_message = 'SOW';
			} else {
				$game_result_message = 'SOT';
			}
		} elseif ( $game['target_score'] < $game['opponent_score'] ) {
				$game_result_message = 'L';
		} elseif ( $game['target_score'] > $game['opponent_score'] ) {
			$game_result_message = 'W';
		} else {
			$game_result_message = '';
		}

		$hide          = '';
		$accordion     = '';
		$right_actions = '';
		$promo_content = '';
		$recap_html    = '';
		if ( ! $should_hide_score && ! empty( $game['post_link'] ) ) {
			$recap_html = '<div class="pill_recap_wrap"><a class="pill-recap-btn" href="' . esc_url( $game['post_link'] ) . '" target="_blank" rel="noopener">Summary</a></div>';
		}

		if ( $should_hide_score === true ) {
			// Future game: hide score; show right-side actions and promo content if present.
			$hide = 'style="display: none;"';

			$promo_label_part = '';
			if ( $game['promo_header'] ) {
				$promo_label_part = '<div class="promotion">' . $game['promo_header'] . '</div>';
			}

			$ticket_part = '';
			if ( ! empty( $game['promo_ticket_link'] ) ) {
				$ticket_part = '<a class="pill_ticket_btn" href="' . esc_url( $game['promo_ticket_link'] ) . '" target="_blank" rel="noopener">BUY TICKETS</a>';
			}

			$chevron_part = '';
			if ( $game['promo_text'] || $game['promo_img_url'] ) {
				$accordion    = 'pill_accordion';
				$chevron_part = '<div class="arrow_wrap"><span class="Chevron"></span></div>';

				$img_html = '';
				if ( ! empty( $game['promo_img_url'] ) ) {
					$img_html = '<div class="item game_promotion_image">
                        <img src="' . esc_url( $game['promo_img_url'] ) . '" loading="lazy" alt="">
                    </div>';
				}
				$promo_content = '<div class="accordion-content">
                    ' . $img_html . '
                <div class="promo_group">
                    <div class="item promo_dropdown_header">
                        ' . $game['promo_header'] . '
                    </div>
                    <div class="item promotion_text">
                        ' . nl2br( $game['promo_text'] ) . '
                    </div>
                </div>
                </div>';
			}

			// Order: promo label → ticket button → chevron (chevron always rightmost).
			if ( $promo_label_part || $ticket_part || $chevron_part ) {
				$right_actions = '<div class="pill_right_actions">'
					. $promo_label_part
					. $ticket_part
					. $chevron_part
					. '</div>';
			}
		}
		$content = '
                    <div class="game_container ' . $accordion . '">
                        <div class="date_time_location">
                            <div class="home_away_container ' . strtolower( $game['home_or_away'] ) . '">
                                <div class="home_away_text">
                                    ' . $game['home_or_away'] . '
                                </div>
                            </div>
                            <div class="date_time_container">
                                <div class="game_date">
                                    ' . $game['game_date_day'] . '
                                </div>
                                <div class="game_time">
                                    ' . $game['game_time'] . '
                                </div>
                            </div>
                        </div>
                        <div class="op_logo_name">
                            <img src="' . $game['opponent_team_logo'] . '" decoding="async" loading="lazy" class="op_logo">
                            <div class="op_name_nickname">
                                <div class="op_name">
                                    ' . $game['opponent_team_name'] . '
                                </div>
                                <div class="op_nickname">
                                    ' . $game['opponent_team_nickname'] . '
                                </div>
                            </div>
                        </div>
                        <div class="arena">
                            ' . $game['venue'] . '
                        </div>
                        <div class="results"' . $hide . '>
                            <div class="results_wrap">
                                <span class="results_text_wrap">
                                    <span class="results_text_WL win_or_loss_' . $game_result_message . '">
                                        ' . $game_result_message . '
                                    </span>
                                    <span class="results_text_score">
                                        ' . $game['target_score'] . '-' . $game['opponent_score'] . '
                                    </span>
                                </span>
                            </div>
                        </div>
                        ' . $right_actions . '
                        ' . $recap_html . '
                    </div>
                    ' . $promo_content;
		return $content;
	}
}
