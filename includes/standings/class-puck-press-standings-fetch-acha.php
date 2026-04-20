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

		$standings = array();
		foreach ( $section['data'] ?? array() as $entry ) {
			$row = $this->normalize_row( $entry, $logos, $home_map );
			if ( $row !== null ) {
				$standings[] = $row;
			}
		}

		return array(
			'division_name' => $division_name,
			'standings'     => $standings,
		);
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
