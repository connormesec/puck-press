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
		$table = $wpdb->prefix . 'pp_game_schedule_for_display';

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
