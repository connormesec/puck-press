<?php

/**
 * Fetches and parses player stats from the ACHA/HockeyTech stats API.
 *
 * Accepts a team_id and season_id (extracted from the ACHA roster URL) and
 * constructs the lscluster.hockeytech.com API call directly.
 *
 * For skater stats: $raw_stats_data is populated (for pp_roster_stats).
 * For goalie stats: $raw_goalie_stats_data is populated (for pp_roster_goalie_stats).
 *
 * @package    Puck_Press
 * @subpackage Puck_Press/includes/roster
 */
class Puck_Press_Roster_Process_Acha_Stats {

	public $team_id;
	public $season_id;

	/**
	 * Skater stat rows ready for pp_roster_stats.
	 * Populated only when $is_goalie is false.
	 *
	 * @var array
	 */
	public $raw_stats_data = array();

	/**
	 * Goalie stat rows ready for pp_roster_goalie_stats.
	 * Populated only when $is_goalie is true.
	 *
	 * @var array
	 */
	public $raw_goalie_stats_data = array();

	public function __construct( string $team_id, string $season_id, bool $is_goalie = false ) {
		$this->team_id   = $team_id;
		$this->season_id = $season_id;
		$json_data       = $this->fetch_stats_data( $is_goalie );

		if ( $is_goalie ) {
			$this->raw_goalie_stats_data = $this->extract_goalie_stats( $json_data );
		} else {
			$this->raw_stats_data = $this->extract_skater_stats( $json_data );
		}
	}

	public static function from_url( string $raw_stats_url, bool $force_goalie = false ): self {
		$parsed     = parse_url( $raw_stats_url );
		$path_parts = explode( '/', trim( $parsed['path'] ?? '', '/' ) );
		$season_id  = array_pop( $path_parts );
		$team_id    = array_pop( $path_parts );
		$is_goalie  = $force_goalie || strpos( $parsed['path'] ?? '', '/goalie-stats/' ) !== false;
		return new self( $team_id, $season_id, $is_goalie );
	}

	private function fetch_stats_data( bool $is_goalie ) {
		$position = $is_goalie ? 'goalies' : 'skaters';
		$sort     = $is_goalie ? 'wins' : 'points';

		$api_url = 'https://lscluster.hockeytech.com/feed/index.php?' . http_build_query(
			array(
				'feed'         => 'statviewfeed',
				'view'         => 'players',
				'season'       => $this->season_id,
				'team'         => $this->team_id,
				'position'     => $position,
				'rookies'      => 0,
				'statsType'    => 'standard',
				'rosterstatus' => 'undefined',
				'site_id'      => '2',
				'first'        => '0',
				'limit'        => '200',
				'sort'         => $sort,
				'league_id'    => '1',
				'lang'         => 'en',
				'division'     => '-1',
				'conference'   => '-1',
				'qualified'    => 'all',
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
