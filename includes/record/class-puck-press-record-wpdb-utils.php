<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Puck_Press_Record_Wpdb_Utils {

	/**
	 * Compute season record stats from pp_game_schedule_for_display.
	 * Only counts games where both target_score and opponent_score are populated.
	 *
	 * @return array Flat associative array of stats.
	 */
	public function get_record_stats( int $schedule_id = 1 ): array {
		global $wpdb;
		$table = $wpdb->prefix . 'pp_schedule_games_display';

		$games = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT target_score, opponent_score, home_or_away, game_status
                   FROM {$table}
                  WHERE schedule_id = %d
                    AND target_score IS NOT NULL
                    AND opponent_score IS NOT NULL
                    AND game_status IS NOT NULL
                    AND game_status NOT IN ('', 'null')",
				$schedule_id
			),
			ARRAY_A
		);

		if ( empty( $games ) ) {
			return $this->empty_stats();
		}

		$stats = $this->empty_stats();

		foreach ( $games as $game ) {
			$ts      = (int) $game['target_score'];
			$os      = (int) $game['opponent_score'];
			$is_home = ( $game['home_or_away'] === 'home' );
			$status  = strtoupper( trim( $game['game_status'] ?? '' ) );
			if ( empty( $status ) || $status === 'NULL' ) {
				continue; // skip unplayed games that slipped through
			}
			// Split status on whitespace, slashes, hyphens, and underscores,
			// then check for an exact "OT" or "SO" token.
			// Handles: "FINAL OT", "FINAL SO", "FINAL/OT", "FINAL/SO",
			// and USPHL-style strings like "W 3-2 OT", "L 2-2 SO".
			$status_tokens = preg_split( '/[\s\/\-_]+/', $status );
			$is_ot_so      = in_array( 'OT', $status_tokens, true ) || in_array( 'SO', $status_tokens, true );

			// Overall goals
			$stats['gf'] += $ts;
			$stats['ga'] += $os;

			// Split goals
			if ( $is_home ) {
				$stats['home_gf'] += $ts;
				$stats['home_ga'] += $os;
			} else {
				$stats['away_gf'] += $ts;
				$stats['away_ga'] += $os;
			}

			// W / L / OTL / T
			if ( $ts > $os ) {
				++$stats['wins'];
				$is_home ? $stats['home_wins']++ : $stats['away_wins']++;
			} elseif ( $ts < $os ) {
				if ( $is_ot_so ) {
					// Overtime or shootout loss counts as OTL
					++$stats['otl'];
					$is_home ? $stats['home_otl']++ : $stats['away_otl']++;
				} else {
					++$stats['losses'];
					$is_home ? $stats['home_losses']++ : $stats['away_losses']++;
				}
			} else {
				// Equal scores (ts === os).
				if ( $is_ot_so ) {
					// OT/SO games can't end in a true tie. Some sources (e.g. USPHL)
					// store SO games at the tied regulation score without adding the
					// shootout goal. Detect win vs. OTL from the status prefix
					// ("W 2-2 SO" = win, "L 2-2 SO" = OTL).
					if ( preg_match( '/^W\b/i', $status ) ) {
						++$stats['wins'];
						$is_home ? $stats['home_wins']++ : $stats['away_wins']++;
					} else {
						// "L 2-2 SO", "FINAL SO" with no score difference, etc. → OTL.
						++$stats['otl'];
						$is_home ? $stats['home_otl']++ : $stats['away_otl']++;
					}
				} else {
					// Regulation tie (rare in modern hockey, but supported)
					++$stats['ties'];
					$is_home ? $stats['home_ties']++ : $stats['away_ties']++;
				}
			}
		}

		$stats['diff'] = $stats['gf'] - $stats['ga'];

		return $stats;
	}

	public function get_multi_source_stats( int $schedule_id = 1 ): array {
		global $wpdb;
		$table = $wpdb->prefix . 'pp_schedule_games_display';

		$games = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT target_team_id, target_team_name, target_team_logo,
                        opponent_team_id, opponent_team_name, opponent_team_logo,
                        target_score, opponent_score, home_or_away, game_status
                   FROM {$table}
                  WHERE schedule_id = %d
                    AND target_score IS NOT NULL
                    AND opponent_score IS NOT NULL
                    AND game_status IS NOT NULL
                    AND game_status NOT IN ('', 'null')",
				$schedule_id
			),
			ARRAY_A
		);

		if ( empty( $games ) ) {
			return array();
		}

		$teams       = array();
		$target_keys = array();

		foreach ( $games as $game ) {
			$ts      = (int) $game['target_score'];
			$os      = (int) $game['opponent_score'];
			$is_home = ( $game['home_or_away'] === 'home' );
			$status  = strtoupper( trim( $game['game_status'] ?? '' ) );

			if ( empty( $status ) || $status === 'NULL' ) {
				continue;
			}

			$status_tokens = preg_split( '/[\s\/\-_]+/', $status );
			$is_ot_so      = in_array( 'OT', $status_tokens, true ) || in_array( 'SO', $status_tokens, true );

			$target_id  = $game['target_team_id'] ?? '';
			$target_key = ( $target_id && $target_id !== '0' )
				? "id:{$target_id}"
				: 'name:' . strtolower( trim( $game['target_team_name'] ) );
			$target_keys[ $target_key ] = true;

			// Process from both perspectives so a game stored under one team's source
			// is still counted for the opponent team (avoids undercounting GP due to
			// the unique (schedule_id, game_id) constraint blocking duplicate imports).
			$this->apply_game_to_team(
				$teams,
				$target_id,
				$game['target_team_name'],
				$game['target_team_logo'] ?? null,
				$ts,
				$os,
				$is_home,
				$is_ot_so,
				$status,
				false
			);

			$this->apply_game_to_team(
				$teams,
				$game['opponent_team_id'] ?? '',
				$game['opponent_team_name'],
				$game['opponent_team_logo'] ?? null,
				$os,
				$ts,
				! $is_home,
				$is_ot_so,
				$status,
				true
			);
		}

		// Only show teams that have their own data source (appeared as target_team).
		// Opponent-side data was computed above to ensure accurate GP/GF/GA splits,
		// but external (non-conference) teams should not appear as standings rows.
		$teams = array_intersect_key( $teams, $target_keys );

		foreach ( $teams as &$team ) {
			$team['diff'] = $team['gf'] - $team['ga'];
		}
		unset( $team );

		usort(
			$teams,
			function ( $a, $b ) {
				$pts_a = ( $a['wins'] * 2 ) + $a['otl'] + $a['ties'];
				$pts_b = ( $b['wins'] * 2 ) + $b['otl'] + $b['ties'];
				if ( $pts_b !== $pts_a ) {
					return $pts_b <=> $pts_a;
				}
				return $b['wins'] <=> $a['wins'];
			}
		);

		return array_values( $teams );
	}

	private function apply_game_to_team(
		array &$teams,
		string $team_id,
		string $team_name,
		?string $team_logo,
		int $team_score,
		int $opp_score,
		bool $is_home,
		bool $is_ot_so,
		string $status,
		bool $is_opponent_perspective
	): void {
		$key = ( $team_id && $team_id !== '0' )
			? "id:{$team_id}"
			: 'name:' . strtolower( trim( $team_name ) );

		if ( ! isset( $teams[ $key ] ) ) {
			$teams[ $key ]              = $this->empty_stats();
			$teams[ $key ]['team_name'] = $team_name;
			$teams[ $key ]['team_logo'] = $team_logo;
		}

		if ( empty( $teams[ $key ]['team_logo'] ) && ! empty( $team_logo ) ) {
			$teams[ $key ]['team_logo'] = $team_logo;
		}

		$teams[ $key ]['gf'] += $team_score;
		$teams[ $key ]['ga'] += $opp_score;

		if ( $is_home ) {
			$teams[ $key ]['home_gf'] += $team_score;
			$teams[ $key ]['home_ga'] += $opp_score;
		} else {
			$teams[ $key ]['away_gf'] += $team_score;
			$teams[ $key ]['away_ga'] += $opp_score;
		}

		if ( $team_score > $opp_score ) {
			++$teams[ $key ]['wins'];
			$is_home ? $teams[ $key ]['home_wins']++ : $teams[ $key ]['away_wins']++;
		} elseif ( $team_score < $opp_score ) {
			if ( $is_ot_so ) {
				++$teams[ $key ]['otl'];
				$is_home ? $teams[ $key ]['home_otl']++ : $teams[ $key ]['away_otl']++;
			} else {
				++$teams[ $key ]['losses'];
				$is_home ? $teams[ $key ]['home_losses']++ : $teams[ $key ]['away_losses']++;
			}
		} else {
			if ( $is_ot_so ) {
				// The "W" prefix in status always refers to the TARGET team's result.
				// When processing from the opponent's perspective, W = they lost (OTL).
				$target_won   = (bool) preg_match( '/^W\b/i', $status );
				$this_team_won = $is_opponent_perspective ? ! $target_won : $target_won;

				if ( $this_team_won ) {
					++$teams[ $key ]['wins'];
					$is_home ? $teams[ $key ]['home_wins']++ : $teams[ $key ]['away_wins']++;
				} else {
					++$teams[ $key ]['otl'];
					$is_home ? $teams[ $key ]['home_otl']++ : $teams[ $key ]['away_otl']++;
				}
			} else {
				++$teams[ $key ]['ties'];
				$is_home ? $teams[ $key ]['home_ties']++ : $teams[ $key ]['away_ties']++;
			}
		}
	}

	public function get_multi_source_stats_with_overall( int $schedule_id = 1 ): array {
		global $wpdb;
		$table = $wpdb->prefix . 'pp_schedule_games_display';

		$games = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT target_team_id, target_team_name, target_team_logo,
                        opponent_team_id, opponent_team_name, opponent_team_logo,
                        target_score, opponent_score, home_or_away, game_status
                   FROM {$table}
                  WHERE schedule_id = %d
                    AND target_score IS NOT NULL
                    AND opponent_score IS NOT NULL
                    AND game_status IS NOT NULL
                    AND game_status NOT IN ('', 'null')",
				$schedule_id
			),
			ARRAY_A
		);

		if ( empty( $games ) ) {
			return array();
		}

		// First pass: identify all conference teams (those with their own data source).
		$conf_keys   = array();
		$target_keys = array();
		foreach ( $games as $game ) {
			$tid = $game['target_team_id'] ?? '';
			$k   = ( $tid && $tid !== '0' )
				? "id:{$tid}"
				: 'name:' . strtolower( trim( $game['target_team_name'] ) );
			$conf_keys[ $k ]   = true;
			$target_keys[ $k ] = true;
		}

		$teams = array();

		foreach ( $games as $game ) {
			$ts      = (int) $game['target_score'];
			$os      = (int) $game['opponent_score'];
			$is_home = ( $game['home_or_away'] === 'home' );
			$status  = strtoupper( trim( $game['game_status'] ?? '' ) );

			if ( empty( $status ) || $status === 'NULL' ) {
				continue;
			}

			$status_tokens = preg_split( '/[\s\/\-_]+/', $status );
			$is_ot_so      = in_array( 'OT', $status_tokens, true ) || in_array( 'SO', $status_tokens, true );

			$opp_id  = $game['opponent_team_id'] ?? '';
			$opp_key = ( $opp_id && $opp_id !== '0' )
				? "id:{$opp_id}"
				: 'name:' . strtolower( trim( $game['opponent_team_name'] ) );

			$is_conf_game = isset( $conf_keys[ $opp_key ] );

			$this->apply_game_to_team_with_overall(
				$teams,
				$game['target_team_id'] ?? '',
				$game['target_team_name'],
				$game['target_team_logo'] ?? null,
				$ts, $os, $is_home, $is_ot_so, $status, false, $is_conf_game
			);

			$this->apply_game_to_team_with_overall(
				$teams,
				$opp_id,
				$game['opponent_team_name'],
				$game['opponent_team_logo'] ?? null,
				$os, $ts, ! $is_home, $is_ot_so, $status, true, $is_conf_game
			);
		}

		$teams = array_intersect_key( $teams, $target_keys );

		foreach ( $teams as &$team ) {
			$team['diff'] = $team['gf'] - $team['ga'];
		}
		unset( $team );

		usort(
			$teams,
			function ( $a, $b ) {
				$pts_a = ( $a['overall_wins'] * 2 ) + $a['overall_otl'] + $a['overall_ties'];
				$pts_b = ( $b['overall_wins'] * 2 ) + $b['overall_otl'] + $b['overall_ties'];
				if ( $pts_b !== $pts_a ) {
					return $pts_b <=> $pts_a;
				}
				return $b['overall_wins'] <=> $a['overall_wins'];
			}
		);

		return array_values( $teams );
	}

	private function apply_game_to_team_with_overall(
		array &$teams,
		string $team_id,
		string $team_name,
		?string $team_logo,
		int $team_score,
		int $opp_score,
		bool $is_home,
		bool $is_ot_so,
		string $status,
		bool $is_opponent_perspective,
		bool $is_conf_game
	): void {
		$key = ( $team_id && $team_id !== '0' )
			? "id:{$team_id}"
			: 'name:' . strtolower( trim( $team_name ) );

		if ( ! isset( $teams[ $key ] ) ) {
			$teams[ $key ]              = $this->empty_stats_with_overall();
			$teams[ $key ]['team_name'] = $team_name;
			$teams[ $key ]['team_logo'] = $team_logo;
		}

		if ( empty( $teams[ $key ]['team_logo'] ) && ! empty( $team_logo ) ) {
			$teams[ $key ]['team_logo'] = $team_logo;
		}

		$teams[ $key ]['gf'] += $team_score;
		$teams[ $key ]['ga'] += $opp_score;

		if ( $team_score > $opp_score ) {
			$result = 'win';
		} elseif ( $team_score < $opp_score ) {
			$result = $is_ot_so ? 'otl' : 'loss';
		} else {
			if ( $is_ot_so ) {
				$target_won    = (bool) preg_match( '/^W\b/i', $status );
				$this_team_won = $is_opponent_perspective ? ! $target_won : $target_won;
				$result        = $this_team_won ? 'win' : 'otl';
			} else {
				$result = 'tie';
			}
		}

		switch ( $result ) {
			case 'win':
				++$teams[ $key ]['overall_wins'];
				break;
			case 'loss':
				++$teams[ $key ]['overall_losses'];
				break;
			case 'otl':
				++$teams[ $key ]['overall_otl'];
				break;
			case 'tie':
				++$teams[ $key ]['overall_ties'];
				break;
		}

		if ( $is_conf_game ) {
			switch ( $result ) {
				case 'win':
					++$teams[ $key ]['wins'];
					break;
				case 'loss':
					++$teams[ $key ]['losses'];
					break;
				case 'otl':
					++$teams[ $key ]['otl'];
					break;
				case 'tie':
					++$teams[ $key ]['ties'];
					break;
			}
		}
	}

	private function empty_stats_with_overall(): array {
		return array(
			'wins'           => 0,
			'losses'         => 0,
			'otl'            => 0,
			'ties'           => 0,
			'gf'             => 0,
			'ga'             => 0,
			'diff'           => 0,
			'overall_wins'   => 0,
			'overall_losses' => 0,
			'overall_otl'    => 0,
			'overall_ties'   => 0,
		);
	}

	private function empty_stats(): array {
		return array(
			'wins'        => 0,
			'losses'      => 0,
			'otl'         => 0,
			'ties'        => 0,
			'gf'          => 0,
			'ga'          => 0,
			'diff'        => 0,
			'home_wins'   => 0,
			'home_losses' => 0,
			'home_otl'    => 0,
			'home_ties'   => 0,
			'home_gf'     => 0,
			'home_ga'     => 0,
			'away_wins'   => 0,
			'away_losses' => 0,
			'away_otl'    => 0,
			'away_ties'   => 0,
			'away_gf'     => 0,
			'away_ga'     => 0,
		);
	}
}
