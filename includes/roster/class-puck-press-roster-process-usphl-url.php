<?php

/**
 * Class Puck_Press_Roster_Process_Usphl_Url
 *
 * Fetches and normalizes roster and stats data from the USPHL TimeToScore API
 * using HMAC-SHA256 signed requests.
 *
 * Three endpoints are used:
 *
 *   GET /get_roster   — Player bio data + inline skater stats (GP, G, A, PTS, PPG, SHG, GWG, PIM).
 *                       Params: auth_key, auth_timestamp, body_md5, league_id,
 *                               stat_class, season_id, team_id
 *
 *   GET /get_skaters  — Dedicated skater stats endpoint.
 *                       Params: auth_key, auth_timestamp, body_md5, team_id, league_id
 *
 *   GET /get_goalies  — Goalie stats (GP, W, L, OTL, SOL, SOW, SA, SV, SV%, GAA, GA, G, A, PIM).
 *                       Params: auth_key, auth_timestamp, body_md5, team_id, league_id
 *
 * Skater stats strategy: try /get_skaters first; if the endpoint returns no usable
 * data, fall back to the inline stats embedded in the /get_roster response.
 *
 * @package    Puck_Press
 * @subpackage Puck_Press/includes/roster
 */
class Puck_Press_Roster_Process_Usphl_Url {

	/** Normalized player records ready for pp_roster_raw. @var array */
	public $raw_roster_data = array();

	/** Skater stat rows ready for pp_roster_stats. @var array */
	public $raw_stats_data = array();

	/** Goalie stat rows ready for pp_roster_goalie_stats. @var array */
	public $raw_goalie_stats_data = array();

	/**
	 * Errors keyed by endpoint name. Populated when a fetch or parse fails.
	 * The source importer logs these in its results messages.
	 *
	 * @var array
	 */
	public $fetch_errors = array();

	/** @var string */
	private $team_id = '';

	/** @var string */
	private $season_id = '';

	/**
	 * @param string $team_id   USPHL league-assigned team ID (required).
	 * @param string $season_id Season ID — leave empty for the current season.
	 */
	public function __construct( string $team_id, string $season_id = '' ) {
		$this->team_id   = $team_id;
		$this->season_id = $season_id;

		// 1. Roster bio data + inline stats (fallback for skaters).
		$roster_json = $this->fetch_endpoint( 'get_roster', $this->build_roster_params() );
		$this->extract_roster( $roster_json );

		// 2. Dedicated skater stats — try first; fall back to inline if empty.
		$skater_json = $this->fetch_endpoint( 'get_skaters', $this->build_stats_params() );
		$this->extract_skater_stats( $skater_json );

		if ( empty( $this->raw_stats_data ) ) {
			$this->extract_inline_skater_stats( $roster_json );
		}

		// 3. Goalie stats.
		$goalie_json = $this->fetch_endpoint( 'get_goalies', $this->build_stats_params() );
		$this->extract_goalie_stats( $goalie_json );
	}

	// =========================================================================
	// Fetch helper
	// =========================================================================

	/**
	 * Builds a signed URL for $endpoint, makes the GET request, and returns the
	 * decoded JSON body. On any failure stores a message in $this->fetch_errors
	 * and returns an empty array so callers can check `isset($result['error'])`.
	 */
	private function fetch_endpoint( string $endpoint, array $params ): array {
		if ( empty( $this->team_id ) ) {
			$this->fetch_errors[ $endpoint ] = 'Team ID is required.';
			return array();
		}

		$signed_url = Puck_Press_Tts_Api::build_signed_url( $endpoint, $params );
		$response   = wp_remote_get( $signed_url, array( 'timeout' => 15 ) );

		if ( is_wp_error( $response ) ) {
			$this->fetch_errors[ $endpoint ] = 'HTTP error: ' . $response->get_error_message();
			return array();
		}

		$http_code = wp_remote_retrieve_response_code( $response );
		$raw_body  = wp_remote_retrieve_body( $response );

		if ( $http_code !== 200 ) {
			$this->fetch_errors[ $endpoint ] = "HTTP {$http_code}: " . wp_strip_all_tags( $raw_body );
			return array();
		}

		$decoded = json_decode( $raw_body, true );

		if ( json_last_error() !== JSON_ERROR_NONE ) {
			$this->fetch_errors[ $endpoint ] = 'JSON parse error: ' . json_last_error_msg();
			return array();
		}

		if ( ! is_array( $decoded ) ) {
			$this->fetch_errors[ $endpoint ] = 'Unexpected non-array response.';
			return array();
		}

		return $decoded;
	}

	// =========================================================================
	// Param builders
	// =========================================================================

	private function build_roster_params(): array {
		return array(
			'auth_key'       => Puck_Press_Tts_Api::TTS_AUTH_KEY,
			'auth_timestamp' => (string) time(),
			'body_md5'       => Puck_Press_Tts_Api::TTS_BODY_MD5,
			'league_id'      => Puck_Press_Tts_Api::TTS_LEAGUE_ID,
			'stat_class'     => Puck_Press_Tts_Api::TTS_STAT_CLASS,
			'season_id'      => $this->season_id,
			'team_id'        => $this->team_id,
		);
	}

	/** Params for /get_skaters and /get_goalies. */
	private function build_stats_params(): array {
		$params = array(
			'auth_key'       => Puck_Press_Tts_Api::TTS_AUTH_KEY,
			'auth_timestamp' => (string) time(),
			'body_md5'       => Puck_Press_Tts_Api::TTS_BODY_MD5,
			'team_id'        => $this->team_id,
			'league_id'      => Puck_Press_Tts_Api::TTS_LEAGUE_ID,
		);

		if ( ! empty( $this->season_id ) ) {
			$params['season_id'] = $this->season_id;
		}

		return $params;
	}

	// =========================================================================
	// Data extractors
	// =========================================================================

	/**
	 * Populates $raw_roster_data from /get_roster. Skips coaches.
	 */
	private function extract_roster( array $json ): void {
		$players = $json['players'] ?? null;
		if ( ! is_array( $players ) ) {
			return;
		}

		foreach ( $players as $player ) {
			if ( ( $player['coach'] ?? '0' ) === '1' ) {
				continue;
			}

			$this->raw_roster_data[] = array(
				'player_id'      => $player['player_id'],
				'name'           => $player['player_name'],
				'number'         => $player['jersey'],
				'pos'            => $player['plays'],
				'shoots'         => $player['shoots'],
				'ht'             => $player['height'],
				'wt'             => $player['weight'],
				'hometown'       => $player['display_hometown'],
				'headshot_link'  => $player['player_image'],
				'last_team'      => '',
				'year_in_school' => '',
				'major'          => '',
			);
		}
	}

	/**
	 * Populates $raw_stats_data from /get_skaters.
	 * Tries the response keys: 'players', 'skaters'.
	 */
	private function extract_skater_stats( array $json ): void {
		$players = $json['players'] ?? $json['skaters'] ?? null;
		if ( ! is_array( $players ) || empty( $players ) ) {
			return;
		}

		foreach ( $players as $player ) {
			$this->raw_stats_data[] = array(
				'player_id'              => $player['player_id'],
				'games_played'           => intval( $player['games_played'] ?? 0 ),
				'goals'                  => intval( $player['goals'] ?? 0 ),
				'assists'                => intval( $player['assists'] ?? 0 ),
				'points'                 => intval( $player['points'] ?? 0 ),
				'points_per_game'        => isset( $player['point_per_game'] ) ? floatval( $player['point_per_game'] ) : null,
				'power_play_goals'       => intval( $player['ppg'] ?? 0 ),
				'short_handed_goals'     => intval( $player['shg'] ?? 0 ),
				'game_winning_goals'     => intval( $player['gwg'] ?? 0 ),
				'penalty_minutes'        => intval( $player['pims'] ?? 0 ),
				'shooting_percentage'    => isset( $player['shooting_pct'] ) ? floatval( $player['shooting_pct'] ) : null,
				'shootout_winning_goals' => intval( $player['shootout_gwgw'] ?? 0 ),
			);
		}
	}

	/**
	 * Fallback: extracts inline skater stats from the /get_roster response.
	 * Skips players whose position is 'G' (goalies).
	 * Only called when /get_skaters returns no data.
	 */
	private function extract_inline_skater_stats( array $roster_json ): void {
		$players = $roster_json['players'] ?? null;
		if ( ! is_array( $players ) ) {
			return;
		}

		foreach ( $players as $player ) {
			if ( ( $player['coach'] ?? '0' ) === '1' ) {
				continue;
			}

			// Skip goalies — they go in the goalie stats table.
			$pos = strtoupper( trim( $player['plays'] ?? '' ) );
			if ( $pos === 'G' ) {
				continue;
			}

			$this->raw_stats_data[] = array(
				'player_id'              => $player['player_id'],
				'games_played'           => intval( $player['games_played'] ?? 0 ),
				'goals'                  => intval( $player['goals'] ?? 0 ),
				'assists'                => intval( $player['assists'] ?? 0 ),
				'points'                 => intval( $player['points'] ?? 0 ),
				'points_per_game'        => isset( $player['point_per_game'] ) ? floatval( $player['point_per_game'] ) : null,
				'power_play_goals'       => intval( $player['ppg'] ?? 0 ),
				'short_handed_goals'     => intval( $player['shg'] ?? 0 ),
				'game_winning_goals'     => intval( $player['gwg'] ?? 0 ),
				'penalty_minutes'        => intval( $player['pims'] ?? 0 ),
				'shooting_percentage'    => isset( $player['shooting_pct'] ) ? floatval( $player['shooting_pct'] ) : null,
				'shootout_winning_goals' => intval( $player['shootout_gwgw'] ?? 0 ),
			);
		}
	}

	/**
	 * Populates $raw_goalie_stats_data from /get_goalies.
	 * Tries the response keys: 'players', 'goalies'.
	 */
	private function extract_goalie_stats( array $json ): void {
		$players = $json['players'] ?? $json['goalies'] ?? null;
		if ( ! is_array( $players ) || empty( $players ) ) {
			return;
		}

		foreach ( $players as $player ) {
			$this->raw_goalie_stats_data[] = array(
				'player_id'             => $player['player_id'],
				'games_played'          => intval( $player['games_played'] ?? 0 ),
				'wins'                  => intval( $player['wins'] ?? 0 ),
				'losses'                => intval( $player['losses'] ?? 0 ),
				'overtime_losses'       => intval( $player['ot_losses'] ?? 0 ),
				'shootout_losses'       => intval( $player['so_losses'] ?? 0 ),
				'shootout_wins'         => intval( $player['so_wins'] ?? 0 ),
				'shots_against'         => intval( $player['shots_against'] ?? 0 ),
				'saves'                 => intval( $player['saves'] ?? 0 ),
				'save_percentage'       => isset( $player['save_pct'] ) ? floatval( $player['save_pct'] ) : null,
				'goals_against_average' => isset( $player['goals_against_ave'] ) ? floatval( $player['goals_against_ave'] ) : null,
				'goals_against'         => intval( $player['goals_against'] ?? 0 ),
				'goals'                 => intval( $player['goals'] ?? 0 ),
				'assists'               => intval( $player['assists'] ?? 0 ),
				'penalty_minutes'       => intval( $player['pims'] ?? 0 ),
			);
		}
	}
}
