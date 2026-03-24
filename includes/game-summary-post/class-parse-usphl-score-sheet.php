<?php
class Parse_Usphl_Score_Sheet {

	public function parseScoreSheet( $gameId, $seasonId ) {
		$params = array(
			'auth_key'       => Puck_Press_Tts_Api::TTS_AUTH_KEY,
			'auth_timestamp' => time(),
			'body_md5'       => Puck_Press_Tts_Api::TTS_BODY_MD5,
			'game_id'        => intval( $gameId ),
			'league_id'      => Puck_Press_Tts_Api::TTS_LEAGUE_ID,
			'season_id'      => intval( $seasonId ),
			'widget'         => 'gamecenter',
		);

		$url      = Puck_Press_Tts_Api::build_signed_url( 'get_game_center', $params );
		$response = wp_remote_get( $url, array( 'timeout' => 15 ) );

		if ( is_wp_error( $response ) ) {
			throw new Exception( 'Failed to fetch game center: ' . $response->get_error_message() );
		}

		$body = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( empty( $body['game_center'] ) ) {
			throw new Exception( 'Invalid game_center response for game_id ' . $gameId );
		}

		return $this->mapGameCenter( $body['game_center'] );
	}

	private function mapGameCenter( array $gc ): array {
		$info      = $gc['game_info'];
		$live      = $gc['live'];
		$homeGoals = intval( $live['goal_summary']['home_goals']['total'] ?? 0 );
		$awayGoals = intval( $live['goal_summary']['away_goals']['total'] ?? 0 );
		$homeShots = intval( $live['shot_summary']['home_shots']['total'] ?? 0 );
		$awayShots = intval( $live['shot_summary']['away_shots']['total'] ?? 0 );
		$status    = ( $info['status'] === 'Final' ) ? 'completed' : 'scheduled';

		return array(
			'date'        => $info['formatted_date'],
			'time'        => $info['time'],
			'league'      => 'usphl',
			'game_id'     => $info['alias'],
			'venue'       => trim( $info['location'] ),
			'game_status' => $status,
			'visitor'     => array(
				'name'          => $info['away_name'],
				'final_score'   => $awayGoals,
				'players'       => $this->mapSkaters( $live['away_skaters'] ?? array() ),
				'goalies'       => $this->mapGoalies( $live['away_goalies'] ?? array() ),
				'special_teams' => $this->mapSpecialTeams( $live['misc_summary'] ?? array(), 'away' ),
				'total_shots'   => $awayShots,
			),
			'home'        => array(
				'name'          => $info['home_name'],
				'final_score'   => $homeGoals,
				'players'       => $this->mapSkaters( $live['home_skaters'] ?? array() ),
				'goalies'       => $this->mapGoalies( $live['home_goalies'] ?? array() ),
				'special_teams' => $this->mapSpecialTeams( $live['misc_summary'] ?? array(), 'home' ),
				'total_shots'   => $homeShots,
			),
		);
	}

	private function mapSkaters( array $skaters ): array {
		$result = array();
		foreach ( $skaters as $s ) {
			// Skip goalies listed in skaters array
			if ( isset( $s['goalie'] ) && $s['goalie'] === '1' ) {
				continue;
			}
			$goals   = intval( $s['goals'] ?? 0 );
			$assists = intval( $s['assists'] ?? 0 );
			$result[] = array(
				'name'    => $s['name'],
				'number'  => $s['jersey'],
				'GP'      => 1,
				'goals'   => $goals,
				'assists' => $assists,
				'min'     => intval( $s['pims'] ?? 0 ),
				'points'  => $goals + $assists,
				'SOG'     => intval( $s['shots'] ?? 0 ),
			);
		}
		return $result;
	}

	private function mapGoalies( array $goalies ): array {
		$result = array();
		foreach ( $goalies as $g ) {
			$shots_against = intval( $g['shots_against'] ?? 0 );
			$saves         = intval( $g['saves'] ?? 0 );

			// Skip goalies who didn't play
			if ( $shots_against === 0 && $saves === 0 ) {
				continue;
			}

			$ga       = intval( $g['goals_against'] ?? 0 );
			$result[] = array(
				'name'         => $g['name'],
				'number'       => $g['jersey'],
				'GP'           => 1,
				'shots'        => $shots_against,
				'GA'           => $ga,
				'save_percent' => $shots_against > 0 ? round( ( $shots_against - $ga ) / $shots_against, 3 ) : 0,
				'result'       => $g['wlotl'] ?? '',
			);
		}
		return $result;
	}

	private function mapSpecialTeams( array $misc, string $side ): array {
		$other = $side === 'home' ? 'away' : 'home';
		return array(
			'power_play'   => array(
				'goals_for'  => intval( $misc[ $side . '_ppg' ] ?? 0 ),
				'advantages' => intval( $misc[ $side . '_pp' ] ?? 0 ),
			),
			'penalty_kill' => array(
				'times_short_handed'       => intval( $misc[ $side . '_penalties' ] ?? 0 ),
				'power_play_goals_against' => intval( $misc[ $other . '_ppg' ] ?? 0 ),
			),
		);
	}
}
