<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Puck_Press_Acha_Season_Discoverer {

	const BOOTSTRAP_TRANSIENT = 'pp_acha_bootstrap';
	const BOOTSTRAP_TTL       = 43200; // 12 hours
	const API_BASE            = 'https://lscluster.hockeytech.com/feed/index.php?feed=statviewfeed&key=e6867b36742a0c9d&client_code=acha&site_id=2';

	private static function fetch_url( string $url ): string|false {
		$context = stream_context_create( array(
			'http' => array( 'timeout' => 30 ),
		) );
		return @file_get_contents( $url, false, $context ); // phpcs:ignore
	}

	public static function get_bootstrap(): array {
		$cached = get_transient( self::BOOTSTRAP_TRANSIENT );
		if ( $cached !== false ) {
			return $cached;
		}
		$raw  = self::fetch_url( self::API_BASE . '&view=bootstrap' );
		if ( $raw === false ) {
			return array();
		}
		$data = json_decode( substr( $raw, 1, -1 ), true );
		if ( json_last_error() !== JSON_ERROR_NONE || empty( $data['seasons'] ) ) {
			return array();
		}
		set_transient( self::BOOTSTRAP_TRANSIENT, $data, self::BOOTSTRAP_TTL );
		return $data;
	}

	public static function derive_season_year( string $start_date ): string {
		try {
			$dt    = new DateTime( $start_date );
			$month = (int) $dt->format( 'n' );
			$year  = (int) $dt->format( 'Y' );
		} catch ( Exception $e ) {
			return '';
		}
		if ( $month >= 8 ) {
			return $year . '-' . ( $year + 1 );
		}
		return ( $year - 1 ) . '-' . $year;
	}

	public static function get_team_in_season( string $acha_team_id, string $season_id ): ?array {
		$url  = self::API_BASE . "&view=teamsForSeason&season={$season_id}&division=-1";
		$raw  = self::fetch_url( $url );
		if ( $raw === false ) {
			return null;
		}
		$data = json_decode( substr( $raw, 1, -1 ), true );
		if ( json_last_error() !== JSON_ERROR_NONE ) {
			return null;
		}
		foreach ( $data['teams'] ?? array() as $team ) {
			if ( (string) $team['id'] === $acha_team_id ) {
				return $team;
			}
		}
		return null;
	}

	public static function maybe_create_roster_seed( int $wp_team_id, string $acha_team_id, string $season_id, array $season_meta ): bool {
		global $wpdb;
		$roster_table = $wpdb->prefix . 'pp_team_roster_sources';

		$existing = $wpdb->get_var( $wpdb->prepare(
			"SELECT id FROM {$roster_table}
			 WHERE source_url_or_path = %s AND type = 'achaRosterUrl'
			 AND other_data LIKE %s",
			$acha_team_id,
			'%"season_id":"' . $wpdb->esc_like( $season_id ) . '"%'
		) );

		if ( $existing ) {
			return false;
		}

		$url  = 'https://lscluster.hockeytech.com/feed/index.php?feed=statviewfeed&view=roster'
			. "&team_id={$acha_team_id}&season_id={$season_id}"
			. '&key=e6867b36742a0c9d&client_code=acha&site_id=2&league_id=-1&lang=en';
		$raw  = self::fetch_url( $url );
		if ( $raw === false ) {
			return false;
		}
		$data = json_decode( substr( $raw, 1, -1 ), true );
		if ( json_last_error() !== JSON_ERROR_NONE ) {
			return false;
		}

		$has_players = false;
		foreach ( $data['roster'][0]['sections'] ?? array() as $section ) {
			if ( $section['title'] !== 'Coaches' && ! empty( $section['data'] ) ) {
				$has_players = true;
				break;
			}
		}
		if ( ! $has_players ) {
			return false;
		}

		$wpdb->insert(
			$roster_table,
			array(
				'team_id'            => $wp_team_id,
				'name'               => $season_meta['season_name'],
				'type'               => 'achaRosterUrl',
				'source_url_or_path' => $acha_team_id,
				'status'             => 'active',
				'other_data'         => wp_json_encode( array(
					'season_id'       => $season_id,
					'include_stats'   => true,
					'auto_discover'   => true,
					'seed_season_id'  => $season_id,
					'seed_start_date' => $season_meta['start_date'],
				) ),
				'created_at'         => current_time( 'mysql' ),
			)
		);

		return (bool) $wpdb->insert_id;
	}

	/**
	 * @return array{division_id: string, season_year: string, start_date: string, season_name: string}|WP_Error
	 */
	public static function get_team_season_meta( string $acha_team_id, string $season_id, bool $require_regular_season = false ) {
		$bootstrap = self::get_bootstrap();
		if ( empty( $bootstrap ) ) {
			return new WP_Error( 'bootstrap_failed', 'Could not reach the ACHA API. Please try again.' );
		}

		if ( $require_regular_season ) {
			$regular_ids = array_column( $bootstrap['regularSeasons'] ?? array(), 'id' );
			if ( ! in_array( $season_id, $regular_ids, true ) ) {
				return new WP_Error(
					'playoff_season',
					'This season ID belongs to a playoff event. Uncheck "Auto-discover future seasons" to add it as a standalone source.'
				);
			}
		}

		$season_meta = null;
		foreach ( $bootstrap['seasons'] ?? array() as $s ) {
			if ( (string) $s['id'] === (string) $season_id ) {
				$season_meta = $s;
				break;
			}
		}
		if ( $season_meta === null ) {
			return new WP_Error( 'season_not_found', "Season ID {$season_id} was not found in the ACHA system." );
		}

		$team_entry = self::get_team_in_season( $acha_team_id, $season_id );
		if ( $team_entry === null ) {
			return new WP_Error(
				'team_not_found',
				"Team ID {$acha_team_id} was not found in season {$season_id}. Verify both IDs from the ACHA schedule URL."
			);
		}

		return array(
			'division_id'  => (string) $team_entry['division_id'],
			'season_year'  => self::derive_season_year( $season_meta['start_date'] ),
			'start_date'   => $season_meta['start_date'],
			'season_name'  => $season_meta['name'],
		);
	}

	public function discover_all( ?int $wp_team_id = null ): array {
		if ( ! get_option( 'puck_press_acha_auto_discover_enabled', true ) ) {
			return array();
		}

		$bootstrap = self::get_bootstrap();
		if ( empty( $bootstrap ) ) {
			return array();
		}

		$playoff_ids = array_flip( array_column( $bootstrap['playoffSeasons'] ?? array(), 'id' ) );
		$all_seasons = $bootstrap['seasons'] ?? array();

		global $wpdb;
		$sources_table = $wpdb->prefix . 'pp_team_sources';

		$where = "type = 'achaGameScheduleUrl' AND other_data LIKE '%\"auto_discover\":true%' AND status = 'active'";
		if ( $wp_team_id !== null ) {
			$where .= $wpdb->prepare( ' AND team_id = %d', $wp_team_id );
		}
		$seed_sources = $wpdb->get_results( "SELECT * FROM {$sources_table} WHERE {$where}", ARRAY_A ) ?? array(); // phpcs:ignore

		$log = array();
		foreach ( $seed_sources as $seed ) {
			$result = $this->discover_for_seed( $seed, $playoff_ids, $all_seasons );
			if ( ! empty( $result ) ) {
				$log[] = $result;
			}
		}
		return $log;
	}

	private function discover_for_seed( array $seed, array $playoff_ids, array $all_seasons ): array {
		$other      = json_decode( $seed['other_data'] ?? '{}', true );
		$acha_team  = (string) $seed['source_url_or_path'];
		$wp_team_id = (int) $seed['team_id'];
		$seed_start = $other['seed_start_date'] ?? '';

		if ( empty( $acha_team ) || empty( $seed_start ) ) {
			return array();
		}

		try {
			$seed_year   = (int) ( new DateTime( $seed_start ) )->format( 'Y' );
		} catch ( Exception $e ) {
			return array();
		}
		$year_cutoff = ( $seed_year + 1 ) . '-08-01';

		global $wpdb;
		$sources_table = $wpdb->prefix . 'pp_team_sources';
		$existing_rows = $wpdb->get_col( $wpdb->prepare(
			"SELECT other_data FROM {$sources_table} WHERE source_url_or_path = %s AND type = 'achaGameScheduleUrl'",
			$acha_team
		) ) ?? array();

		$tracked_ids = array();
		foreach ( $existing_rows as $json ) {
			$od = json_decode( $json, true );
			if ( ! empty( $od['season_id'] ) ) {
				$tracked_ids[] = (string) $od['season_id'];
			}
		}

		$candidates = array_filter( $all_seasons, function( array $s ) use ( $playoff_ids, $seed_start, $year_cutoff, $tracked_ids ): bool {
			return isset( $playoff_ids[ $s['id'] ] )
				&& $s['start_date'] >= $seed_start
				&& $s['start_date'] < $year_cutoff
				&& ! in_array( (string) $s['id'], $tracked_ids, true );
		} );

		$discovered        = array();
		$confirmed_seasons = array();

		foreach ( $candidates as $season ) {
			$team_entry = self::get_team_in_season( $acha_team, $season['id'] );
			if ( $team_entry === null ) {
				continue;
			}

			$division_id = (string) $team_entry['division_id'];
			$season_year = self::derive_season_year( $season['start_date'] );

			$wpdb->insert(
				$sources_table,
				array(
					'team_id'            => $wp_team_id,
					'name'               => $season['name'],
					'type'               => 'achaGameScheduleUrl',
					'source_url_or_path' => $acha_team,
					'season'             => $season_year,
					'status'             => 'active',
					'other_data'         => wp_json_encode( array(
						'season_id'      => $season['id'],
						'division_id'    => $division_id,
						'auto_discovered' => true,
					) ),
					'created_at'         => current_time( 'mysql' ),
				)
			);

			$confirmed_seasons[] = $season;
			$discovered[]        = $season['name'];
		}

		foreach ( $confirmed_seasons as $confirmed ) {
			$season_meta_for_roster = array(
				'season_name' => $confirmed['name'],
				'start_date'  => $confirmed['start_date'],
			);
			self::maybe_create_roster_seed( $wp_team_id, $acha_team, $confirmed['id'], $season_meta_for_roster );
		}

		if ( empty( $discovered ) ) {
			return array();
		}

		return array(
			'team_id'    => $wp_team_id,
			'acha_team'  => $acha_team,
			'discovered' => $discovered,
		);
	}

}
