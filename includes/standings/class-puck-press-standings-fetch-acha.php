<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Puck_Press_Standings_Fetch_Acha {

	private const API_BASE        = 'https://lscluster.hockeytech.com/feed/index.php?feed=statviewfeed&key=333f159b7bfd73f4&client_code=acha&site_id=3';
	private const API_BASE_LEGACY = 'https://lscluster.hockeytech.com/feed/index.php?feed=statviewfeed&key=e6867b36742a0c9d&client_code=acha&site_id=2';

	private string $acha_team_id;
	private string $season_id;
	public array $fetch_errors = array();

	public function __construct( string $acha_team_id, string $season_id ) {
		$this->acha_team_id = $acha_team_id;
		$this->season_id    = $season_id;
	}

	public function fetch(): array {
		$overall_sections = $this->fetch_standings_context( 'overall' );
		if ( $overall_sections === null ) {
			return array();
		}

		$section = $this->find_team_section( $overall_sections );
		if ( $section === null ) {
			$this->fetch_errors['section_not_found'] = "Team ID {$this->acha_team_id} not found in season {$this->season_id}.";
			return array();
		}

		$division_name = $this->extract_division_name( $section );
		$home_map      = $this->build_split_map( 'home' );
		$logos         = $this->fetch_logos();

		// Overall standings — all games.
		$overall_standings = array();
		foreach ( $section['data'] ?? array() as $entry ) {
			$row = $this->normalize_row( $entry, $logos, $home_map );
			if ( $row !== null ) {
				$overall_standings[] = $row;
			}
		}

		// Division standings — computed from game-level data, intradivisional games only.
		$games              = $this->fetch_season_games();
		$division_standings = $this->compute_division_standings( $section, $logos, $games, $overall_standings );

		return array(
			'division_name'      => $division_name,
			'standings'          => $overall_standings,
			'division_standings' => $division_standings,
		);
	}

	private function fetch_season_games(): array {
		$url = self::API_BASE_LEGACY
			. '&view=schedule'
			. '&team=-1'
			. "&season={$this->season_id}"
			. '&month=-1'
			. '&location=homeaway'
			. '&league_id=1'
			. '&division_id=-1'
			. '&lang=en';

		$raw = $this->fetch_jsonp( $url );
		if ( $raw === null ) {
			return array();
		}

		$games = array();
		foreach ( $raw[0]['sections'] ?? array() as $section ) {
			foreach ( $section['data'] ?? array() as $entry ) {
				$games[] = $entry;
			}
		}
		return $games;
	}

	private function compute_division_standings( array $division_section, array $logos, array $games, array $overall_standings = array() ): array {
		if ( empty( $games ) ) {
			return array();
		}

		// Build set of team IDs in this division.
		$division_tids = array();
		foreach ( $division_section['data'] ?? array() as $entry ) {
			$tid = (string) ( $entry['prop']['name']['playerStatsLink'] ?? '' );
			if ( $tid !== '' ) {
				$division_tids[ $tid ] = true;
			}
		}

		// Accumulate per-team stats from intradivisional games.
		$stats = array();
		foreach ( $games as $game ) {
			$row      = $game['row'] ?? array();
			$prop     = $game['prop'] ?? array();
			$home_tid = (string) ( $prop['home_team_city']['teamLink'] ?? '' );
			$away_tid = (string) ( $prop['visiting_team_city']['teamLink'] ?? '' );

			// Skip if either team is outside this division.
			if ( ! isset( $division_tids[ $home_tid ], $division_tids[ $away_tid ] ) ) {
				continue;
			}

			$home_goals = $row['home_goal_count'] ?? '';
			$away_goals = $row['visiting_goal_count'] ?? '';

			// Skip unplayed games (empty scores).
			if ( $home_goals === '' || $away_goals === '' ) {
				continue;
			}

			$status = (string) ( $row['game_status'] ?? '' );

			// Skip any game that is not a final result.
			if ( stripos( $status, 'final' ) === false ) {
				continue;
			}

			$home_goals = (int) $home_goals;
			$away_goals = (int) $away_goals;
			$is_ot      = stripos( $status, 'OT' ) !== false || stripos( $status, 'SO' ) !== false;

			// Initialise stat buckets on first encounter.
			foreach ( array( $home_tid, $away_tid ) as $tid ) {
				if ( ! isset( $stats[ $tid ] ) ) {
					$stats[ $tid ] = array(
						'gp'       => 0, 'w' => 0, 'l' => 0, 'otl' => 0, 't' => 0,
						'pts'      => 0, 'gf' => 0, 'ga' => 0,
						'home_w'   => 0, 'home_l' => 0, 'home_otl' => 0,
						'away_w'   => 0, 'away_l' => 0, 'away_otl' => 0,
					);
				}
			}

			$stats[ $home_tid ]['gp']++;
			$stats[ $away_tid ]['gp']++;
			$stats[ $home_tid ]['gf'] += $home_goals;
			$stats[ $home_tid ]['ga'] += $away_goals;
			$stats[ $away_tid ]['gf'] += $away_goals;
			$stats[ $away_tid ]['ga'] += $home_goals;

			if ( $home_goals === $away_goals ) {
				// Tie — both earn 1 point.
				$stats[ $home_tid ]['t']++;
				$stats[ $away_tid ]['t']++;
				$stats[ $home_tid ]['pts']++;
				$stats[ $away_tid ]['pts']++;
			} elseif ( $home_goals > $away_goals ) {
				// Home team wins.
				$stats[ $home_tid ]['w']++;
				$stats[ $home_tid ]['pts'] += 2;
				$stats[ $home_tid ]['home_w']++;
				if ( $is_ot ) {
					$stats[ $away_tid ]['otl']++;
					$stats[ $away_tid ]['pts']++;
					$stats[ $away_tid ]['away_otl']++;
				} else {
					$stats[ $away_tid ]['l']++;
					$stats[ $away_tid ]['away_l']++;
				}
			} else {
				// Away team wins.
				$stats[ $away_tid ]['w']++;
				$stats[ $away_tid ]['pts'] += 2;
				$stats[ $away_tid ]['away_w']++;
				if ( $is_ot ) {
					$stats[ $home_tid ]['otl']++;
					$stats[ $home_tid ]['pts']++;
					$stats[ $home_tid ]['home_otl']++;
				} else {
					$stats[ $home_tid ]['l']++;
					$stats[ $home_tid ]['home_l']++;
				}
			}
		}

		// Build a streak/last_10 lookup from overall standings.
		$overall_map = array();
		foreach ( $overall_standings as $row ) {
			$overall_map[ $row['team_id'] ] = $row;
		}

		// Build standings rows using team info from the division section.
		$standings = array();
		foreach ( $division_section['data'] ?? array() as $entry ) {
			$tid = (string) ( $entry['prop']['name']['playerStatsLink'] ?? '' );
			if ( $tid === '' ) {
				continue;
			}

			$s        = $stats[ $tid ] ?? array(
				'gp' => 0, 'w' => 0, 'l' => 0, 'otl' => 0, 't' => 0,
				'pts' => 0, 'gf' => 0, 'ga' => 0,
				'home_w' => 0, 'home_l' => 0, 'home_otl' => 0,
				'away_w' => 0, 'away_l' => 0, 'away_otl' => 0,
			);
			$raw_name = (string) ( $entry['row']['name'] ?? '' );

			$standings[] = array(
				'team_id'       => $tid,
				'team_name'     => (string) preg_replace( '/^(?:MD[1-3]|WD[1-3]|M[1-3]|W[1-3])\s+/', '', $raw_name ),
				'team_nickname' => $entry['row']['nickname'] ?? '',
				'team_logo'     => $logos[ $tid ] ?? '',
				'gp'            => $s['gp'],
				'w'             => $s['w'],
				'l'             => $s['l'],
				'otl'           => $s['otl'],
				'sol'           => 0,
				't'             => $s['t'],
				'pts'           => $s['pts'],
				'gf'            => $s['gf'],
				'ga'            => $s['ga'],
				'diff'          => $s['gf'] - $s['ga'],
				'home_w'        => $s['home_w'],
				'home_l'        => $s['home_l'],
				'home_otl'      => $s['home_otl'],
				'away_w'        => $s['away_w'],
				'away_l'        => $s['away_l'],
				'away_otl'      => $s['away_otl'],
				'home_gf'       => 0,
				'home_ga'       => 0,
				'away_gf'       => 0,
				'away_ga'       => 0,
				'streak'        => $overall_map[ $tid ]['streak'] ?? '',
				'last_10'       => $overall_map[ $tid ]['last_10'] ?? '',
				'is_target'     => false,
			);
		}

		// Sort: pts DESC → wins DESC → goal diff DESC.
		usort( $standings, function ( $a, $b ) {
			if ( $b['pts'] !== $a['pts'] ) {
				return $b['pts'] - $a['pts'];
			}
			if ( $b['w'] !== $a['w'] ) {
				return $b['w'] - $a['w'];
			}
			return ( $b['gf'] - $b['ga'] ) - ( $a['gf'] - $a['ga'] );
		} );

		return $standings;
	}

	private function fetch_standings_context( string $context ): ?array {
		$url = self::API_BASE
			. '&view=teams'
			. '&groupTeamsBy=division'
			. "&context={$context}"
			. "&season={$this->season_id}"
			. '&special=false'
			. '&league_id=1'
			. '&conference=-1'
			. '&division=-1'
			. '&sort=points'
			. '&lang=en';

		$raw = $this->fetch_jsonp( $url );
		if ( $raw === null ) {
			$this->fetch_errors[ "standings_{$context}" ] = "Failed to fetch standings (context={$context}).";
			return null;
		}

		return $raw[0]['sections'] ?? null;
	}

	private function find_team_section( array $sections ): ?array {
		foreach ( $sections as $section ) {
			foreach ( $section['data'] ?? array() as $entry ) {
				$link = (string) ( $entry['prop']['name']['playerStatsLink'] ?? '' );
				if ( $link === $this->acha_team_id ) {
					return $section;
				}
			}
		}
		return null;
	}

	private function extract_division_name( array $section ): string {
		$label = $section['headers']['name']['properties']['label'] ?? '';
		return (string) preg_replace( '/^(?:MD[1-3]|WD[1-3]|M[1-3]|W[1-3])\s+/', '', $label );
	}

	private function build_split_map( string $context ): array {
		$sections = $this->fetch_standings_context( $context );
		if ( $sections === null ) {
			return array();
		}

		$map = array();
		foreach ( $sections as $section ) {
			foreach ( $section['data'] ?? array() as $entry ) {
				$tid = (string) ( $entry['prop']['name']['playerStatsLink'] ?? '' );
				if ( $tid === '' ) {
					continue;
				}
				$row         = $entry['row'] ?? array();
				$map[ $tid ] = array(
					'w'   => (int) ( $row['wins'] ?? 0 ),
					'l'   => (int) ( $row['losses'] ?? 0 ),
					'otl' => (int) ( $row['ot_losses'] ?? 0 ),
					'gf'  => (int) ( $row['goals_for'] ?? 0 ),
					'ga'  => (int) ( $row['goals_against'] ?? 0 ),
				);
			}
		}

		return $map;
	}

	private function fetch_logos(): array {
		$url = self::API_BASE_LEGACY . "&view=teamsForSeason&season={$this->season_id}&division=-1";
		$raw = $this->fetch_jsonp( $url );
		if ( $raw === null ) {
			return array();
		}

		$logos = array();
		foreach ( $raw['teams'] ?? array() as $team ) {
			$tid = (string) ( $team['id'] ?? '' );
			if ( $tid !== '' && $tid !== '-1' ) {
				$logos[ $tid ] = $team['logo'] ?? '';
			}
		}

		return $logos;
	}

	private function normalize_row( array $entry, array $logos, array $home_map ): ?array {
		$tid = (string) ( $entry['prop']['name']['playerStatsLink'] ?? '' );
		if ( $tid === '' ) {
			return null;
		}

		$row = $entry['row'] ?? array();

		$overall_w   = (int) ( $row['wins'] ?? 0 );
		$overall_l   = (int) ( $row['losses'] ?? 0 );
		$overall_otl = (int) ( $row['ot_losses'] ?? 0 );
		$overall_gf  = (int) ( $row['goals_for'] ?? 0 );
		$overall_ga  = (int) ( $row['goals_against'] ?? 0 );

		$home     = $home_map[ $tid ] ?? array();
		$home_w   = (int) ( $home['w'] ?? 0 );
		$home_l   = (int) ( $home['l'] ?? 0 );
		$home_otl = (int) ( $home['otl'] ?? 0 );
		$home_gf  = (int) ( $home['gf'] ?? 0 );
		$home_ga  = (int) ( $home['ga'] ?? 0 );

		return array(
			'team_id'       => $tid,
			'team_name'     => (string) preg_replace( '/^(?:MD[1-3]|WD[1-3]|M[1-3]|W[1-3])\s+/', '', $row['name'] ?? '' ),
			'team_nickname' => $row['nickname'] ?? '',
			'team_logo'     => $logos[ $tid ] ?? '',
			'gp'            => (int) ( $row['games_played'] ?? 0 ),
			'w'             => $overall_w,
			'l'             => $overall_l,
			'otl'           => $overall_otl,
			't'             => (int) ( $row['ties'] ?? 0 ),
			'pts'           => (int) ( $row['points'] ?? 0 ),
			'gf'            => $overall_gf,
			'ga'            => $overall_ga,
			'diff'          => (int) ( $row['goals_diff'] ?? 0 ),
			'home_w'        => $home_w,
			'home_l'        => $home_l,
			'home_otl'      => $home_otl,
			'away_w'        => $overall_w   - $home_w,
			'away_l'        => $overall_l   - $home_l,
			'away_otl'      => $overall_otl - $home_otl,
			'home_gf'       => $home_gf,
			'home_ga'       => $home_ga,
			'away_gf'       => $overall_gf  - $home_gf,
			'away_ga'       => $overall_ga  - $home_ga,
			'streak'        => $this->convert_streak( (string) ( $row['streak'] ?? '' ) ),
			'last_10'       => $row['past_10'] ?? '',
			'is_target'     => false,
		);
	}

	private function convert_streak( string $raw ): string {
		$parts = explode( '-', $raw );
		if ( count( $parts ) !== 4 ) {
			return '';
		}

		$labels   = array( 'W', 'L', 'OTL', 'T' );
		$non_zero = array();
		foreach ( $labels as $i => $label ) {
			$val = (int) $parts[ $i ];
			if ( $val > 0 ) {
				$non_zero[] = $label . $val;
			}
		}

		if ( empty( $non_zero ) ) {
			return '';
		}

		if ( count( $non_zero ) > 1 ) {
			$this->fetch_errors['streak_ambiguous'] = "Ambiguous streak value: {$raw}";
		}

		return $non_zero[0];
	}

	private function fetch_jsonp( string $url ): ?array {
		$response = wp_remote_get( $url, array( 'timeout' => 20 ) );
		if ( is_wp_error( $response ) ) {
			return null;
		}

		$code = wp_remote_retrieve_response_code( $response );
		if ( $code !== 200 ) {
			return null;
		}

		$body = wp_remote_retrieve_body( $response );
		$body = trim( $body );
		if ( isset( $body[0] ) && $body[0] === '(' && substr( $body, -1 ) === ')' ) {
			$body = substr( $body, 1, -1 );
		}

		$decoded = json_decode( $body, true );
		if ( json_last_error() !== JSON_ERROR_NONE ) {
			return null;
		}

		return $decoded;
	}
}
