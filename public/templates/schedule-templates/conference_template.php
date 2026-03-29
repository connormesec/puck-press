<?php

class ConferenceTemplate extends PuckPressTemplate {

	public static function get_key(): string {
		return 'conference';
	}

	public static function get_label(): string {
		return 'Conference Scoreboard';
	}

	protected static function get_directory(): string {
		return 'schedule-templates';
	}

	public static function forceResetColors(): bool {
		return false;
	}

	public static function get_default_colors(): array {
		return array(
			'accent'      => '#CC0000',
			'page_bg'     => '#F7F9FA',
			'card_bg'     => '#FFFFFF',
			'header_bg'   => '#1A1A2E',
			'header_text' => '#FFFFFF',
			'text'        => '#111111',
			'text_muted'  => '#777777',
			'divider'     => '#E8EBEF',
		);
	}

	public static function get_color_labels(): array {
		return array(
			'accent'      => 'Accent Color',
			'page_bg'     => 'Page Background',
			'card_bg'     => 'Card Background',
			'header_bg'   => 'Date Header Background',
			'header_text' => 'Date Header Text',
			'text'        => 'Primary Text',
			'text_muted'  => 'Muted Text',
			'divider'     => 'Divider Color',
		);
	}

	public static function get_default_fonts(): array {
		return array( 'schedule_font' => '' );
	}

	public static function get_font_labels(): array {
		return array( 'schedule_font' => 'Schedule Font' );
	}

	/** @var array<string,true> Team names that own a schedule entry (i.e. Puck Press / conference teams). */
	private array $conference_teams = array();

	// ── Entry point ───────────────────────────────────────────────────────────

	public function render_with_options( array $games, array $options ): string {
		$slug         = $options['schedule_slug'] ?? '';
		$schedule_id  = isset( $options['schedule_id'] ) ? (int) $options['schedule_id'] : 0;
		$container_id = $slug ? 'pp-sched-' . sanitize_html_class( $slug ) : '';
		$scope        = $container_id ? '#' . $container_id : ':root';
		$colors       = $schedule_id > 0 ? self::get_schedule_colors( $schedule_id ) : null;
		$fonts        = $schedule_id > 0 ? self::get_schedule_fonts( $schedule_id ) : null;
		$inline_css   = self::get_inline_css( $scope, $colors, $fonts );
		$css_block    = $inline_css ? '<style>' . $inline_css . '</style>' : '';
		return $css_block . $this->buildScoreboard( $games, $options['is_archive'] ?? false, $container_id );
	}

	// ── Layout shell ──────────────────────────────────────────────────────────

	private function buildScoreboard( array $raw_games, bool $is_archive, string $container_id = '' ): string {
		foreach ( $raw_games as $game ) {
			if ( ! empty( $game['target_team_name'] ) ) {
				$this->conference_teams[ $game['target_team_name'] ] = true;
			}
		}

		$games = $this->normalize_and_deduplicate( $raw_games );

		if ( empty( $games ) ) {
			return $this->renderContainer( '<p class="csb-no-games">No games found.</p>', $container_id );
		}

		$records = $this->compute_records( $games );

		if ( $is_archive ) {
			$games_by_date = array_reverse( $this->group_by_date( $games ), true );
			return $this->renderContainer( $this->renderAllDates( $games_by_date, $records ), $container_id );
		}

		$split          = $this->split_games_by_time( $games );
		$upcoming_dates = $this->group_by_date( $split['future_games'] );
		$past_dates     = array_reverse( $this->group_by_date( $split['past_games'] ), true );

		$html  = $this->renderTabs();
		$html .= '<div class="csb-panel" id="csb-upcoming">';
		$html .= empty( $upcoming_dates )
			? '<p class="csb-no-games">No upcoming games.</p>'
			: $this->renderAllDates( $upcoming_dates, $records );
		$html .= '</div>';

		$html .= '<div class="csb-panel" id="csb-results" style="display:none;">';
		$html .= empty( $past_dates )
			? '<p class="csb-no-games">No results yet.</p>'
			: $this->renderAllDates( $past_dates, $records );
		$html .= '</div>';

		return $this->renderContainer( $html, $container_id );
	}

	private function renderContainer( string $inner, string $container_id = '' ): string {
		$id_attr = $container_id ? ' id="' . esc_attr( $container_id ) . '"' : '';
		return '<div class="conference_schedule_container"' . $id_attr . '>' . $inner . '</div>';
	}

	private function renderTabs(): string {
		return '<div class="csb-tabs">'
			. '<button class="csb-tab-btn csb-tab-active" data-csb-tab="csb-upcoming">Upcoming</button>'
			. '<button class="csb-tab-btn" data-csb-tab="csb-results">Results</button>'
			. '</div>';
	}

	private function renderAllDates( array $games_by_date, array $records ): string {
		$html = '';
		foreach ( $games_by_date as $ymd => $day_games ) {
			$html .= $this->renderDateGroup( $ymd, $day_games, $records );
		}
		return $html;
	}

	// ── Date group ────────────────────────────────────────────────────────────

	private function renderDateGroup( string $ymd, array $games, array $records ): string {
		$html  = '<div class="csb-date-group">';
		$html .= '<div class="csb-date-header">' . esc_html( $this->format_date_heading( $ymd ) ) . '</div>';
		foreach ( $games as $game ) {
			$html .= $this->is_final( $game['game_status'] )
				? $this->renderFinalCard( $game, $records )
				: $this->renderUpcomingCard( $game, $records );
		}
		$html .= '</div>';
		return $html;
	}

	// ── Final game card ───────────────────────────────────────────────────────

	private function renderFinalCard( array $game, array $records ): string {
		$hs = $game['home_score'];
		$as = $game['away_score'];
		$hw = ( $hs > $as );
		$aw = ( $as > $hs );

		$html  = '<div class="csb-card csb-card--final">';
		$html .= '<div class="csb-card__left">';
		$html .= '<span class="csb-status">' . esc_html( $this->status_label( $game['game_status'] ) ) . '</span>';
		$html .= $this->renderTeamScoreRow( $game['away_team_logo'], $game['away_team_name'], $this->format_record( $records, $game['away_team_name'] ), $as, $aw );
		$html .= $this->renderTeamScoreRow( $game['home_team_logo'], $game['home_team_name'], $this->format_record( $records, $game['home_team_name'] ), $hs, $hw );
		$html .= '</div>';

		$html .= '<div class="csb-card__right">';
		if ( ! empty( $game['post_link'] ) ) {
			$html .= '<a class="csb-action-btn" href="' . esc_url( $game['post_link'] ) . '" target="_blank" rel="noopener">Game Summary</a>';
		}
		$html .= '</div>';

		$html .= '</div>';
		return $html;
	}

	private function renderTeamScoreRow( string $logo, string $name, string $record, int $score, bool $winner ): string {
		$row_class = $winner ? 'csb-team-row--winner' : 'csb-team-row--loser';

		$html  = '<div class="csb-team-row ' . $row_class . '">';
		$html .= '<div class="csb-team-identity">';
		if ( ! empty( $logo ) ) {
			$html .= '<img class="csb-team-logo" src="' . esc_url( $logo ) . '" loading="lazy" decoding="async" alt="' . esc_attr( $name ) . '">';
		}
		$html .= '<span class="csb-team-name">' . esc_html( $name ) . '</span>';
		if ( ! empty( $record ) && isset( $this->conference_teams[ $name ] ) ) {
			$html .= '<span class="csb-team-record">(' . esc_html( $record ) . ')</span>';
		}
		$html .= '</div>';
		$html .= '<span class="csb-score">' . esc_html( (string) $score ) . '</span>';
		if ( $winner ) {
			$html .= '<span class="csb-winner-arrow" aria-hidden="true">&#9664;</span>';
		}
		$html .= '</div>';
		return $html;
	}

	// ── Upcoming game card ────────────────────────────────────────────────────

	private function renderUpcomingCard( array $game, array $records ): string {
		$time = ! empty( $game['game_time'] ) ? $game['game_time'] : 'TBD';

		$html  = '<div class="csb-card csb-card--upcoming">';
		$html .= '<div class="csb-card__left">';
		$html .= '<span class="csb-game-time">' . esc_html( $time ) . '</span>';
		$html .= $this->renderTeamMatchupRow( $game['away_team_logo'], $game['away_team_name'], $this->format_record( $records, $game['away_team_name'] ), false );
		$html .= '<div class="csb-at-divider">at</div>';
		$html .= $this->renderTeamMatchupRow( $game['home_team_logo'], $game['home_team_name'], $this->format_record( $records, $game['home_team_name'] ), true );

		if ( ! empty( $game['venue'] ) ) {
			$html .= '<div class="csb-venue">';
			$html .= '<svg class="csb-icon-pin" xmlns="http://www.w3.org/2000/svg" width="12" height="12" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M12 2C8.13 2 5 5.13 5 9c0 5.25 7 13 7 13s7-7.75 7-13c0-3.87-3.13-7-7-7zm0 9.5c-1.38 0-2.5-1.12-2.5-2.5s1.12-2.5 2.5-2.5 2.5 1.12 2.5 2.5-1.12 2.5-2.5 2.5z"/></svg>';
			$html .= esc_html( $game['venue'] );
			$html .= '</div>';
		}

		$html .= '</div>';

		if ( ! empty( $game['promo_header'] ) || ! empty( $game['promo_text'] ) || ! empty( $game['promo_img_url'] ) ) {
			$html .= '<div class="csb-promo">';
			if ( ! empty( $game['promo_img_url'] ) ) {
				$html .= '<img class="csb-promo__img" src="' . esc_url( $game['promo_img_url'] ) . '" loading="lazy" alt="">';
			}
			$html .= '<div class="csb-promo__body">';
			if ( ! empty( $game['promo_header'] ) ) {
				$html .= '<p class="csb-promo__header">' . esc_html( $game['promo_header'] ) . '</p>';
			}
			if ( ! empty( $game['promo_text'] ) ) {
				$html .= '<p class="csb-promo__text">' . nl2br( esc_html( $game['promo_text'] ?? '' ) ) . '</p>';
			}
			$html .= '</div>';
			$html .= '</div>';
		}

		$html .= '<div class="csb-card__right">';
		if ( ! empty( $game['promo_ticket_link'] ) ) {
			$html .= '<a class="csb-ticket-btn" href="' . esc_url( $game['promo_ticket_link'] ) . '" target="_blank" rel="noopener">Buy Tickets</a>';
		}
		$html .= '</div>';

		$html .= '</div>';
		return $html;
	}

	private function renderTeamMatchupRow( string $logo, string $name, string $record, bool $is_home ): string {
		$html = '<div class="csb-matchup-row">';
		if ( ! empty( $logo ) ) {
			$html .= '<img class="csb-team-logo" src="' . esc_url( $logo ) . '" loading="lazy" decoding="async" alt="' . esc_attr( $name ) . '">';
		}
		$html .= '<span class="csb-team-name">' . esc_html( $name ) . '</span>';
		if ( ! empty( $record ) && isset( $this->conference_teams[ $name ] ) ) {
			$html .= '<span class="csb-team-record">(' . esc_html( $record ) . ')</span>';
		}
		if ( $is_home ) {
			$html .= '<span class="csb-home-badge">Home</span>';
		}
		$html .= '</div>';
		return $html;
	}

	// ── Helpers ───────────────────────────────────────────────────────────────

	private function is_final( string $status ): bool {
		return in_array( $status, array( 'Final', 'Final OT', 'Final/OT', 'Final SO', 'Final/SO' ), true );
	}

	private function status_label( string $status ): string {
		$map = array(
			'Final'    => 'FINAL',
			'Final OT' => 'FINAL/OT',
			'Final/OT' => 'FINAL/OT',
			'Final SO' => 'FINAL/SO',
			'Final/SO' => 'FINAL/SO',
		);
		return $map[ $status ] ?? strtoupper( $status );
	}

	// ── Phase 2: Data normalization ───────────────────────────────────────────

	private function normalize_game( array $game ): array {
		$is_home = ( $game['home_or_away'] === 'home' );
		return array(
			'game_timestamp'    => $game['game_timestamp'] ?? '',
			'game_date_day'     => $game['game_date_day'] ?? '',
			'game_time'         => $game['game_time'] ?? '',
			'venue'             => $game['venue'] ?? '',
			'game_status'       => $game['game_status'] ?? '',
			'promo_ticket_link' => $game['promo_ticket_link'] ?? '',
			'promo_header'      => $game['promo_header'] ?? '',
			'promo_text'        => $game['promo_text'] ?? '',
			'promo_img_url'     => $game['promo_img_url'] ?? '',
			'post_link'         => $game['post_link'] ?? '',
			'home_team_name'    => $is_home ? ( $game['target_team_name'] ?? '' ) : ( $game['opponent_team_name'] ?? '' ),
			'home_team_logo'    => $is_home ? ( $game['target_team_logo'] ?? '' ) : ( $game['opponent_team_logo'] ?? '' ),
			'home_score'        => $is_home ? (int) ( $game['target_score'] ?? 0 ) : (int) ( $game['opponent_score'] ?? 0 ),
			'away_team_name'    => $is_home ? ( $game['opponent_team_name'] ?? '' ) : ( $game['target_team_name'] ?? '' ),
			'away_team_logo'    => $is_home ? ( $game['opponent_team_logo'] ?? '' ) : ( $game['target_team_logo'] ?? '' ),
			'away_score'        => $is_home ? (int) ( $game['opponent_score'] ?? 0 ) : (int) ( $game['target_score'] ?? 0 ),
		);
	}

	private function dedup_key( array $normalized ): string {
		$teams = array( $normalized['home_team_name'], $normalized['away_team_name'] );
		sort( $teams );
		$date_only = ! empty( $normalized['game_timestamp'] )
			? ( new DateTime( $normalized['game_timestamp'] ) )->format( 'Y-m-d' )
			: $normalized['game_date_day'];
		return implode( '|', $teams ) . '|' . $date_only;
	}

	private function normalize_and_deduplicate( array $raw_games ): array {
		$seen   = array();
		$result = array();

		foreach ( $raw_games as $row ) {
			$normalized = $this->normalize_game( $row );
			$key        = $this->dedup_key( $normalized );

			if ( ! isset( $seen[ $key ] ) ) {
				$seen[ $key ]   = true;
				$result[ $key ] = $normalized;
			} else {
				if ( empty( $result[ $key ]['post_link'] ) && ! empty( $normalized['post_link'] ) ) {
					$result[ $key ]['post_link'] = $normalized['post_link'];
				}
				if ( empty( $result[ $key ]['promo_ticket_link'] ) && ! empty( $normalized['promo_ticket_link'] ) ) {
					$result[ $key ]['promo_ticket_link'] = $normalized['promo_ticket_link'];
				}
				foreach ( array( 'promo_header', 'promo_text', 'promo_img_url' ) as $f ) {
					if ( empty( $result[ $key ][ $f ] ) && ! empty( $normalized[ $f ] ) ) {
						$result[ $key ][ $f ] = $normalized[ $f ];
					}
				}
			}
		}

		return array_values( $result );
	}

	// ── Phase 3: Record computation ───────────────────────────────────────────

	private function compute_records( array $games ): array {
		$records        = array();
		$final_statuses = array( 'Final', 'Final OT', 'Final/OT', 'Final SO', 'Final/SO' );

		foreach ( $games as $game ) {
			if ( ! in_array( $game['game_status'], $final_statuses, true ) ) {
				continue;
			}

			foreach ( array( $game['home_team_name'], $game['away_team_name'] ) as $team ) {
				if ( ! isset( $records[ $team ] ) ) {
					$records[ $team ] = array(
						'w' => 0,
						'l' => 0,
						't' => 0,
					);
				}
			}

			$hs = $game['home_score'];
			$as = $game['away_score'];

			if ( $hs > $as ) {
				++$records[ $game['home_team_name'] ]['w'];
				++$records[ $game['away_team_name'] ]['l'];
			} elseif ( $as > $hs ) {
				++$records[ $game['away_team_name'] ]['w'];
				++$records[ $game['home_team_name'] ]['l'];
			} else {
				++$records[ $game['home_team_name'] ]['t'];
				++$records[ $game['away_team_name'] ]['t'];
			}
		}

		return $records;
	}

	private function format_record( array $records, string $team ): string {
		if ( ! isset( $records[ $team ] ) ) {
			return '';
		}
		$r = $records[ $team ];
		return $r['w'] . '-' . $r['l'] . '-' . $r['t'];
	}

	// ── Phase 4: Date grouping ────────────────────────────────────────────────

	private function group_by_date( array $games ): array {
		$grouped = array();

		foreach ( $games as $game ) {
			if ( empty( $game['game_timestamp'] ) ) {
				continue;
			}
			try {
				$date_key = ( new DateTime( $game['game_timestamp'] ) )->format( 'Y-m-d' );
			} catch ( Exception $e ) {
				continue;
			}
			$grouped[ $date_key ][] = $game;
		}

		ksort( $grouped );

		foreach ( $grouped as &$day_games ) {
			usort(
				$day_games,
				function ( array $a, array $b ): int {
					return strtotime( $a['game_timestamp'] ) <=> strtotime( $b['game_timestamp'] );
				}
			);
		}
		unset( $day_games );

		return $grouped;
	}

	private function format_date_heading( string $ymd ): string {
		try {
			return ( new DateTime( $ymd ) )->format( 'l, F j, Y' );
		} catch ( Exception $e ) {
			return $ymd;
		}
	}
}
