<?php

/**
 * Fetches and parses player stats from the ACHA/HockeyTech stats API.
 *
 * Accepts either an ACHA skater stats URL (e.g. achahockey.org/stats/player-stats/...)
 * or an ACHA goalie stats URL (e.g. achahockey.org/stats/goalie-stats/...) and translates
 * it into an lscluster.hockeytech.com API call.
 *
 * For skater URLs: $raw_stats_data is populated (for pp_roster_stats).
 * For goalie URLs: $raw_goalie_stats_data is populated (for pp_roster_goalie_stats).
 *
 * @package    Puck_Press
 * @subpackage Puck_Press/includes/roster
 */
class Puck_Press_Roster_Process_Acha_Stats {

	private $raw_stats_url;

	/**
	 * Skater stat rows ready for pp_roster_stats.
	 * Populated only when the URL is a skater stats URL.
	 *
	 * @var array
	 */
	public $raw_stats_data = array();

	/**
	 * Goalie stat rows ready for pp_roster_goalie_stats.
	 * Populated only when the URL is a goalie stats URL.
	 *
	 * @var array
	 */
	public $raw_goalie_stats_data = array();

	public function __construct( $raw_stats_url, bool $force_goalie = false ) {
		$this->raw_stats_url = $raw_stats_url;
		$is_goalie_url       = $force_goalie || $this->is_goalie_url( $raw_stats_url );
		$json_data           = $this->fetch_stats_data( $is_goalie_url );

		if ( $is_goalie_url ) {
			$this->raw_goalie_stats_data = $this->extract_goalie_stats( $json_data );
		} else {
			$this->raw_stats_data = $this->extract_skater_stats( $json_data );
		}
	}

	/**
	 * Returns true if the URL is an ACHA goalie stats page URL
	 * (path contains /goalie-stats/ rather than /player-stats/).
	 */
	private function is_goalie_url( string $url ): bool {
		$parsed = parse_url( $url );
		$path   = $parsed['path'] ?? '';
		return strpos( $path, '/goalie-stats/' ) !== false;
	}

	private function fetch_stats_data( bool $is_goalie ) {
		$parsed = parse_url( $this->raw_stats_url );

		// Path looks like: /stats/player-stats/{team_id}/{season_id}
		// or: /stats/goalie-stats/{team_id}/{season_id}
		$path_parts = explode( '/', trim( $parsed['path'] ?? '', '/' ) );
		$season_id  = array_pop( $path_parts );
		$team_id    = array_pop( $path_parts );

		parse_str( $parsed['query'] ?? '', $query_params );

		$conference = $query_params['conference'] ?? '-1';
		$division   = $query_params['division'] ?? '-1';
		$rookie_raw = strtolower( $query_params['rookie'] ?? 'no' );
		$rookies    = ( $rookie_raw === 'yes' ) ? 1 : 0;
		$sort       = $query_params['sort'] ?? ( $is_goalie ? 'wins' : 'points' );
		$stats_type = $query_params['statstype'] ?? 'standard';
		$league_id  = $query_params['league'] ?? '1';
		$qualified  = $query_params['qualified'] ?? 'all';

		// Goalie URLs use position=goalies; skater URLs use the position query param (default: skaters).
		$position = $is_goalie ? 'goalies' : ( $query_params['position'] ?? 'skaters' );

		$api_url = 'https://lscluster.hockeytech.com/feed/index.php?' . http_build_query(
			array(
				'feed'         => 'statviewfeed',
				'view'         => 'players',
				'season'       => $season_id,
				'team'         => $team_id,
				'position'     => $position,
				'rookies'      => $rookies,
				'statsType'    => $stats_type,
				'rosterstatus' => 'undefined',
				'site_id'      => '2',
				'first'        => '0',
				'limit'        => '200',
				'sort'         => $sort,
				'league_id'    => $league_id,
				'lang'         => 'en',
				'division'     => $division,
				'conference'   => $conference,
				'qualified'    => $qualified,
				'key'          => 'e6867b36742a0c9d',
				'client_code'  => 'acha',
			)
		);

		$response = @file_get_contents( $api_url );
		if ( $response === false ) {
			return array( 'error' => 'Failed to fetch stats data from API.' );
		}

		// Response is wrapped in parens: ([...])
		$json_str = trim( $response );
		if ( substr( $json_str, 0, 1 ) === '(' && substr( $json_str, -1 ) === ')' ) {
			$json_str = substr( $json_str, 1, -1 );
		}

		$data = json_decode( $json_str, true );
		if ( json_last_error() !== JSON_ERROR_NONE ) {
			return array( 'error' => 'Failed to parse stats JSON: ' . json_last_error_msg() );
		}

		return $data;
	}

	private function extract_skater_stats( $json_data ) {
		if ( isset( $json_data['error'] ) ) {
			return $json_data;
		}

		if ( ! isset( $json_data[0]['sections'][0]['data'] ) ) {
			return array( 'error' => 'Expected stats data structure not found.' );
		}

		$stats = array();

		foreach ( $json_data[0]['sections'] as $section ) {
			foreach ( $section['data'] as $player ) {
				$row = $player['row'];

				$pm = isset( $row['penalty_minutes'] ) && $row['penalty_minutes'] !== ''
					? intval( $row['penalty_minutes'] )
					: null;

				$stats[] = array(
					'player_id'              => $row['player_id'] ?? '',
					'games_played'           => isset( $row['games_played'] ) ? intval( $row['games_played'] ) : null,
					'goals'                  => isset( $row['goals'] ) ? intval( $row['goals'] ) : null,
					'assists'                => isset( $row['assists'] ) ? intval( $row['assists'] ) : null,
					'points'                 => isset( $row['points'] ) ? intval( $row['points'] ) : null,
					'points_per_game'        => isset( $row['points_per_game'] ) ? floatval( $row['points_per_game'] ) : null,
					'power_play_goals'       => isset( $row['power_play_goals'] ) ? intval( $row['power_play_goals'] ) : null,
					'short_handed_goals'     => isset( $row['short_handed_goals'] ) ? intval( $row['short_handed_goals'] ) : null,
					'game_winning_goals'     => isset( $row['game_winning_goals'] ) ? intval( $row['game_winning_goals'] ) : null,
					'shootout_winning_goals' => isset( $row['shootout_winning_goals'] ) ? intval( $row['shootout_winning_goals'] ) : null,
					'penalty_minutes'        => $pm,
					'shooting_percentage'    => isset( $row['shooting_percentage'] ) ? floatval( $row['shooting_percentage'] ) : null,
					'stat_rank'              => isset( $row['rank'] ) ? intval( $row['rank'] ) : null,
				);
			}
		}

		return $stats;
	}

	private function extract_goalie_stats( $json_data ) {
		if ( isset( $json_data['error'] ) ) {
			return $json_data;
		}

		if ( ! isset( $json_data[0]['sections'][0]['data'] ) ) {
			return array( 'error' => 'Expected goalie stats data structure not found.' );
		}

		$stats = array();

		foreach ( $json_data[0]['sections'] as $section ) {
			foreach ( $section['data'] as $player ) {
				$row = $player['row'];

				$stats[] = array(
					'player_id'             => $row['player_id'] ?? '',
					'games_played'          => isset( $row['games_played'] ) ? intval( $row['games_played'] ) : null,
					'wins'                  => isset( $row['wins'] ) ? intval( $row['wins'] ) : null,
					'losses'                => isset( $row['losses'] ) ? intval( $row['losses'] ) : null,
					'overtime_losses'       => isset( $row['overtime_losses'] ) ? intval( $row['overtime_losses'] ) : null,
					'shootout_losses'       => isset( $row['shootout_losses'] ) ? intval( $row['shootout_losses'] ) : null,
					'shootout_wins'         => isset( $row['shootout_wins'] ) ? intval( $row['shootout_wins'] ) : null,
					'shots_against'         => isset( $row['shots_against'] ) ? intval( $row['shots_against'] ) : null,
					'saves'                 => isset( $row['saves'] ) ? intval( $row['saves'] ) : null,
					'save_percentage'       => isset( $row['save_percentage'] ) ? floatval( $row['save_percentage'] ) : null,
					'goals_against_average' => isset( $row['goals_against_average'] ) ? floatval( $row['goals_against_average'] ) : null,
					'goals_against'         => isset( $row['goals_against'] ) ? intval( $row['goals_against'] ) : null,
					'goals'                 => isset( $row['goals'] ) ? intval( $row['goals'] ) : null,
					'assists'               => isset( $row['assists'] ) ? intval( $row['assists'] ) : null,
					'penalty_minutes'       => isset( $row['penalty_minutes'] ) && $row['penalty_minutes'] !== ''
												? intval( $row['penalty_minutes'] ) : null,
					'stat_rank'             => isset( $row['rank'] ) ? intval( $row['rank'] ) : null,
				);
			}
		}

		return $stats;
	}
}
