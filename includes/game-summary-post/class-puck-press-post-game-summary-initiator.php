<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Puck_Press_Post_Game_Summary_Initiator {

	private $game_id;
	private $source_type;
	private $schedule_id;

	public function __construct( $game_id, $source_type, $schedule_id = null ) {
		$this->load_dependencies();
		$this->game_id     = $game_id;
		$this->source_type = $source_type;
		$this->schedule_id = $schedule_id ?? $this->get_main_schedule_id();
	}

	private function load_dependencies() {
		include_once dirname( dirname( __FILE__ ) ) . '/class-puck-press-tts-api.php';
		include_once plugin_dir_path( __FILE__ ) . 'class-parse-usphl-score-sheet.php';
		include_once plugin_dir_path( __FILE__ ) . 'class-parse-acha-score-sheet.php';
		include_once plugin_dir_path( __FILE__ ) . 'class-hockey-game-blog-generator.php';
	}

	private function get_main_schedule_id() {
		global $wpdb;
		$id = $wpdb->get_var(
			"SELECT id FROM {$wpdb->prefix}pp_schedules WHERE is_main = 1 LIMIT 1"
		);
		return $id ? (int) $id : null;
	}

	private function resolve_season_id(): int {
		global $wpdb;
		$row = $wpdb->get_row(
			"SELECT other_data FROM {$wpdb->prefix}pp_team_sources
             WHERE type = 'usphlGameScheduleUrl'
             ORDER BY id DESC LIMIT 1"
		);
		if ( ! $row ) {
			return 0;
		}
		$other = json_decode( $row->other_data ?? '{}', true );
		return intval( $other['season_id'] ?? 0 );
	}

	public function returnGameDataInImageAPIFormat() {
		if ( $this->source_type === 'usphlGameScheduleUrl' ) {
			$season_id = $this->resolve_season_id();
			$parser    = new Parse_Usphl_Score_Sheet();
			$parsed    = $parser->parseScoreSheet( $this->game_id, $season_id );
			return $this->transformUsphlData( $parsed );
		} elseif ( $this->source_type === 'achaGameScheduleUrl' ) {
			$parser = new Parse_Acha_Score_Sheet();
			$parsed = $parser->parseScoreSheet( $this->game_id );
			return $this->transformAchaData( $parsed );
		} else {
			throw new Exception( 'Unsupported source type: ' . $this->source_type );
		}
	}

	public function getGameSummaryFromBlogAPI( $gameData ) {
		$generator              = new Class_Hockey_Game_Blog_Generator( get_option( 'pp_openai_api_key' ) );
		$title_and_body         = $this->extract_title_and_body( $generator->generateGameBlog( $gameData ) );
		$blog_data['body']      = $title_and_body['body'];
		$blog_data['title']     = $title_and_body['title'];
		$blog_data['prompt_players'] = $generator->getPromptPlayers();
		return $blog_data;
	}

	function extract_title_and_body( $input ) {
		$title = '';
		$body  = trim( $input );

		if ( preg_match( '/^\[(.*?)\]\s*(.*)$/s', $input, $matches ) ) {
			$title = trim( $matches[1] );
			$body  = trim( $matches[2] );
		}

		return array(
			'title' => $title,
			'body'  => $body,
		);
	}

	private function transformUsphlData( array $data ): array {
		$mapPlayer = function ( $p ) {
			$parts     = preg_split( '/\s+/', trim( $p['name'] ) );
			$lastName  = array_pop( $parts );
			$firstName = implode( ' ', $parts );

			return array(
				'info'  => array(
					'jerseyNumber' => $p['number'],
					'position'     => '',
					'firstName'    => $firstName,
					'lastName'     => $lastName,
				),
				'stats' => array(
					'goals'          => $p['goals'],
					'assists'        => $p['assists'],
					'points'         => $p['points'],
					'penaltyMinutes' => $p['min'],
				),
			);
		};

		$mapGoalie = function ( $g ) {
			$parts     = preg_split( '/\s+/', trim( $g['name'] ) );
			$lastName  = array_pop( $parts );
			$firstName = implode( ' ', $parts );

			return array(
				'info'  => array(
					'jerseyNumber' => $g['number'],
					'firstName'    => $firstName,
					'lastName'     => $lastName,
				),
				'stats' => array(
					'saves' => $g['shots'] - $g['GA'],
				),
			);
		};

		$logos_ids = $this->getHomeAndAwayTeamLogosAndIds( $this->game_id, $this->source_type );

		$mapTeam = function ( $team, $logo, $team_id, $db_name, $db_nickname ) use ( $mapPlayer, $mapGoalie ) {
			return array(
				'info'      => array(
					'id'       => $team_id,
					'name'     => $db_name,
					'nickname' => $db_nickname,
					'logo'     => $logo,
				),
				'stats'     => array(
					'goals'           => $team['final_score'],
					'shots'           => $team['total_shots'],
					'powerPlayGoals'  => $team['special_teams']['power_play']['goals_for'] ?? null,
					'infractionCount' => $team['special_teams']['penalty_kill']['times_short_handed'] ?? null,
				),
				'skaters'   => array_map( $mapPlayer, $team['players'] ?? array() ),
				'goalieLog' => array_map( $mapGoalie, $team['goalies'] ?? array() ),
			);
		};

		// New API returns "Friday, March 13, 2026" + "08:00 PM"
		$dateTime = \DateTime::createFromFormat(
			'l, F j, Y g:i A',
			$data['date'] . ' ' . $data['time'],
			new \DateTimeZone( 'America/Denver' )
		);
		$isoDate  = $dateTime ? $dateTime->format( DateTimeInterface::ATOM ) : null;

		$next_game = $this->getNextGameInformation( $this->game_id, $this->source_type );
		return array(
			'league'         => 'usphl',
			'targetTeamId'   => $logos_ids['target_team_id'],
			'nextGameInfo'   => $next_game,
			'highLevelStats' => array(
				'league'       => 'usphl',
				'homeTeam'     => $mapTeam( $data['home'], $logos_ids['home_team_logo'], $logos_ids['home_team_id'], $logos_ids['home_team_name'], $logos_ids['home_team_nickname'] ),
				'visitingTeam' => $mapTeam( $data['visitor'], $logos_ids['away_team_logo'], $logos_ids['away_team_id'], $logos_ids['away_team_name'], $logos_ids['away_team_nickname'] ),
				'details'      => array(
					'status'          => $data['game_status'],
					'simpleStatus'    => $data['game_status'],
					'venue'           => $data['venue'],
					'GameDateISO8601' => $isoDate,
				),
			),
		);
	}

	private function split_usphl_team_name( $team ) {
		if ( preg_match( '/^(.+?)\s+(Jr(?:\.|)|Junior\s+\w+.*)$/i', $team, $matches ) ) {
			return array(
				'city' => $matches[1],
				'name' => $matches[2],
			);
		}

		if ( preg_match( '/^(.+)\s+(\S+)$/', $team, $matches ) ) {
			return array(
				'city' => $matches[1],
				'name' => $matches[2],
			);
		}

		return array(
			'city' => $team,
			'name' => $team,
		);
	}

	private function getNextGameInformation( $game_id, $source_type ) {
		global $wpdb;

		$table = $wpdb->prefix . 'pp_schedule_games_display';

		$current = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT game_timestamp
                 FROM {$table}
                 WHERE game_id = %s
                   AND source_type = %s
                 LIMIT 1",
				$game_id,
				$source_type
			)
		);

		if ( ! $current ) {
			return null;
		}

		$schedule_filter = '';
		$args            = array( $source_type, $current->game_timestamp );

		if ( $this->schedule_id ) {
			$schedule_filter = 'AND schedule_id = %d';
			$args[]          = $this->schedule_id;
		}

		$next_game = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT *
                 FROM {$table}
                 WHERE source_type = %s
                   AND game_timestamp > %s
                   {$schedule_filter}
                 ORDER BY game_timestamp ASC
                 LIMIT 1",
				...$args
			)
		);

		if ( $next_game ) {
			return array(
				'date_day'     => $next_game->game_date_day,
				'time'         => $next_game->game_time,
				'venue'        => $next_game->venue,
				'home_or_away' => $next_game->home_or_away,
				'opponent'     => $next_game->opponent_team_name,
				'game_id'      => $next_game->game_id,
			);
		}

		return null;
	}

	private function getHomeAndAwayTeamLogosAndIds( $game_id, $source_type ) {
		global $wpdb;

		$table = $wpdb->prefix . 'pp_schedule_games_display';

		$schedule_filter = '';
		$args            = array( $game_id, $source_type );

		if ( $this->schedule_id ) {
			$schedule_filter = 'AND schedule_id = %d';
			$args[]          = $this->schedule_id;
		}

		$game = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT target_team_logo, opponent_team_logo, home_or_away, target_team_id, opponent_team_id,
                        target_team_name, target_team_nickname, opponent_team_name, opponent_team_nickname
                 FROM {$table}
                 WHERE game_id = %s
                   AND source_type = %s
                   {$schedule_filter}
                 LIMIT 1",
				...$args
			)
		);

		if ( $game ) {
			if ( $game->home_or_away === 'home' ) {
				return array(
					'home_team_logo'     => $game->target_team_logo,
					'away_team_logo'     => $game->opponent_team_logo,
					'home_or_away'       => $game->home_or_away,
					'home_team_id'       => $game->target_team_id,
					'away_team_id'       => $game->opponent_team_id,
					'target_team_id'     => $game->target_team_id,
					'home_team_name'     => $game->target_team_name,
					'home_team_nickname' => $game->target_team_nickname,
					'away_team_name'     => $game->opponent_team_name,
					'away_team_nickname' => $game->opponent_team_nickname,
				);
			} else {
				return array(
					'away_team_logo'     => $game->target_team_logo,
					'home_team_logo'     => $game->opponent_team_logo,
					'home_or_away'       => $game->home_or_away,
					'away_team_id'       => $game->target_team_id,
					'home_team_id'       => $game->opponent_team_id,
					'target_team_id'     => $game->target_team_id,
					'away_team_name'     => $game->target_team_name,
					'away_team_nickname' => $game->target_team_nickname,
					'home_team_name'     => $game->opponent_team_name,
					'home_team_nickname' => $game->opponent_team_nickname,
				);
			}
		} else {
			throw new Exception( 'Game not found in the database' );
		}
	}

	public function getImageFromImageAPI( $bodyData ) {
		$apiUrl = 'https://6bl3vhnaqh.execute-api.us-east-2.amazonaws.com/default/post-game-summary-graphic-api';
		$apiKey = get_option( 'pp_image_api_key' );

		$response = wp_remote_post(
			$apiUrl,
			array(
				'headers' => array(
					'Content-Type'  => 'application/json',
					'Authorization' => 'Bearer ' . $apiKey,
				),
				'body'    => json_encode( $bodyData ),
				'timeout' => 30,
			)
		);

		if ( is_wp_error( $response ) ) {
			error_log( 'Image API request failed: ' . $response->get_error_message() );
			return null;
		}

		$statusCode   = wp_remote_retrieve_response_code( $response );
		$responseBody = wp_remote_retrieve_body( $response );

		if ( $statusCode !== 200 ) {
			error_log( "Image API returned status $statusCode: $responseBody" );
			return "Image API returned status $statusCode: $responseBody";
		}

		return $responseBody;
	}

	private function transformAchaData( $data ) {
		$cleanTeamName = function ( $name ) {
			return preg_replace( '/^(MD[1-3]|WD[1-2])\s+/', '', $name );
		};

		$transformSkaters = function ( $skaters ) {
			$result = array();
			foreach ( $skaters as $skater ) {
				if (
					$skater['stats']['goals'] > 0 ||
					$skater['stats']['assists'] > 0 ||
					$skater['stats']['penaltyMinutes'] > 0 ||
					! empty( $skater['info']['firstName'] ) && ! empty( $skater['info']['lastName'] )
				) {
					$result[] = array(
						'info'  => array(
							'jerseyNumber' => $skater['info']['jerseyNumber'],
							'position'     => $skater['info']['position'],
							'firstName'    => $skater['info']['firstName'],
							'lastName'     => $skater['info']['lastName'],
						),
						'stats' => array(
							'goals'          => $skater['stats']['goals'],
							'assists'        => $skater['stats']['assists'],
							'points'         => $skater['stats']['points'],
							'penaltyMinutes' => $skater['stats']['penaltyMinutes'],
						),
					);
				}
			}
			return $result;
		};

		$transformGoalieLog = function ( $goalieLog ) {
			$result = array();
			foreach ( $goalieLog as $goalie ) {
				$result[] = array(
					'info'  => array(
						'jerseyNumber' => $goalie['info']['jerseyNumber'],
						'firstName'    => $goalie['info']['firstName'],
						'lastName'     => $goalie['info']['lastName'],
					),
					'stats' => array(
						'saves' => $goalie['stats']['saves'],
					),
				);
			}
			return $result;
		};

		$logos_ids = $this->getHomeAndAwayTeamLogosAndIds( $this->game_id, $this->source_type );
		$next_game = $this->getNextGameInformation( $this->game_id, $this->source_type );

		return array(
			'league'         => 'acha',
			'targetTeamId'   => $logos_ids['target_team_id'],
			'nextGameInfo'   => $next_game,
			'highLevelStats' => array(
				'league'       => 'acha',
				'homeTeam'     => array(
					'info'      => array(
						'id'       => $data['homeTeam']['info']['id'],
						'name'     => $cleanTeamName( $data['homeTeam']['info']['name'] ),
						'nickname' => $data['homeTeam']['info']['nickname'],
						'logo'     => $data['homeTeam']['info']['logo'],
					),
					'stats'     => array(
						'goals'           => $data['homeTeam']['stats']['goals'],
						'shots'           => $data['homeTeam']['stats']['shots'],
						'powerPlayGoals'  => $data['homeTeam']['stats']['powerPlayGoals'],
						'infractionCount' => $data['homeTeam']['stats']['infractionCount'],
					),
					'skaters'   => $transformSkaters( $data['homeTeam']['skaters'] ),
					'goalieLog' => $transformGoalieLog( $data['homeTeam']['goalieLog'] ),
				),
				'visitingTeam' => array(
					'info'      => array(
						'id'       => $data['visitingTeam']['info']['id'],
						'name'     => $cleanTeamName( $data['visitingTeam']['info']['name'] ),
						'nickname' => $data['visitingTeam']['info']['nickname'],
						'logo'     => $data['visitingTeam']['info']['logo'],
					),
					'stats'     => array(
						'goals'           => $data['visitingTeam']['stats']['goals'],
						'shots'           => $data['visitingTeam']['stats']['shots'],
						'powerPlayGoals'  => $data['visitingTeam']['stats']['powerPlayGoals'],
						'infractionCount' => $data['visitingTeam']['stats']['infractionCount'],
					),
					'skaters'   => $transformSkaters( $data['visitingTeam']['skaters'] ),
					'goalieLog' => $transformGoalieLog( $data['visitingTeam']['goalieLog'] ),
				),
				'details'      => array(
					'status'          => $data['details']['status'],
					'simpleStatus'    => $this->determineStatus( $data['details']['status'] ),
					'GameDateISO8601' => $data['details']['GameDateISO8601'],
					'venue'           => $data['details']['venue'],
				),
			),
		);
	}

	private function determineStatus( $status ) {
		$status = trim( $status );

		if ( preg_match( '/^Final(\s+.*)?$/i', $status ) ) {
			return 'completed';
		}

		if ( preg_match( '/^\d{1,2}:\d{2}\s?(AM|PM)(\s?[A-Z]{2,4})?$/i', $status ) ) {
			return 'scheduled';
		}

		return 'unknown';
	}
}
