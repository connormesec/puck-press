<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Single source of truth for "the current season".
 *
 * Season keys use the YYYY-YYYY format produced by
 * Puck_Press_Acha_Season_Discoverer::derive_season_year() (e.g. 2026-2027).
 *
 * The current season is the pp_current_season_key option. When it is not
 * set, each team falls back to the newest season among its active roster
 * sources, so live stats never go blank on sites that haven't configured it.
 */
class Puck_Press_Season {

	const OPTION_CURRENT  = 'pp_current_season_key';
	const OPTION_LABEL    = 'puck_press_current_season_label';
	const OPTION_ACHA_MAP = 'pp_acha_season_years';

	private static ?array $team_newest_cache = null;

	public static function get_current_key(): string {
		return self::normalize_key( (string) get_option( self::OPTION_CURRENT, '' ) );
	}

	public static function set_current_key( string $key ): bool {
		$key = self::normalize_key( $key );
		if ( $key === '' ) {
			delete_option( self::OPTION_CURRENT );
		} else {
			update_option( self::OPTION_CURRENT, $key );
		}
		self::$team_newest_cache = null;
		return true;
	}

	/**
	 * Label for the "current season" entry in the stats season dropdown.
	 */
	public static function get_label(): string {
		$label = (string) get_option( self::OPTION_LABEL, '' );
		if ( $label !== '' ) {
			return $label;
		}
		$key = self::get_current_key();
		return $key !== '' ? $key : 'Current Season';
	}

	/**
	 * Normalizes "2025-2026", "2025-26" and "2025/26" to "2025-2026".
	 * Returns '' for anything that isn't a season.
	 */
	public static function normalize_key( string $value ): string {
		if ( ! preg_match( '/(\d{4})\s*[-\/]\s*(\d{2,4})/', $value, $m ) ) {
			return '';
		}
		$start = (int) $m[1];
		$end   = strlen( $m[2] ) === 2 ? (int) ( substr( $m[1], 0, 2 ) . $m[2] ) : (int) $m[2];
		if ( $end !== $start + 1 ) {
			return '';
		}
		return $start . '-' . $end;
	}

	/**
	 * Season key for an ACHA season ID, via the bootstrap feed's start dates.
	 * ACHA season IDs are not chronological across divisions, so the ID alone
	 * can't be compared. Results are kept in an option so page renders never
	 * hit the API; pass $allow_fetch only from imports and admin requests.
	 */
	public static function acha_season_key( string $season_id, bool $allow_fetch = false ): string {
		if ( $season_id === '' ) {
			return '';
		}

		$map = get_option( self::OPTION_ACHA_MAP, array() );
		$map = is_array( $map ) ? $map : array();
		if ( isset( $map[ $season_id ] ) ) {
			return (string) $map[ $season_id ];
		}
		if ( ! $allow_fetch ) {
			return '';
		}

		require_once plugin_dir_path( __FILE__ ) . 'schedule/class-puck-press-acha-season-discoverer.php';
		$bootstrap = Puck_Press_Acha_Season_Discoverer::get_bootstrap();
		$seasons   = array_merge(
			$bootstrap['seasons'] ?? array(),
			$bootstrap['regularSeasons'] ?? array(),
			$bootstrap['playoffSeasons'] ?? array()
		);
		foreach ( $seasons as $season ) {
			if ( ! empty( $season['id'] ) && ! empty( $season['start_date'] ) ) {
				$map[ (string) $season['id'] ] = Puck_Press_Acha_Season_Discoverer::derive_season_year( $season['start_date'] );
			}
		}
		if ( ! empty( $seasons ) ) {
			update_option( self::OPTION_ACHA_MAP, $map, false );
		}

		return (string) ( $map[ $season_id ] ?? '' );
	}

	/**
	 * Season of a pp_team_roster_sources row, or '' if unknown.
	 * For ACHA the season ID decides what is fetched, so it wins over the
	 * admin-entered season_year when it can be resolved.
	 */
	public static function roster_source_season( array $source, bool $allow_fetch = false ): string {
		if ( ( $source['type'] ?? '' ) === 'achaRosterUrl' ) {
			$other = json_decode( $source['other_data'] ?? '', true );
			$key   = self::acha_season_key( (string) ( $other['season_id'] ?? '' ), $allow_fetch );
			if ( $key !== '' ) {
				return $key;
			}
		}
		return self::normalize_key( (string) ( $source['season_year'] ?? '' ) );
	}

	/**
	 * Season of a pp_team_sources (schedule) row, or '' if unknown.
	 */
	public static function schedule_source_season( array $source, bool $allow_fetch = false ): string {
		if ( ( $source['type'] ?? '' ) === 'achaGameScheduleUrl' ) {
			$other = json_decode( $source['other_data'] ?? '', true );
			$key   = self::acha_season_key( (string) ( $other['season_id'] ?? '' ), $allow_fetch );
			if ( $key !== '' ) {
				return $key;
			}
		}
		return self::normalize_key( (string) ( $source['season'] ?? '' ) );
	}

	/**
	 * The stats label the roster importer stores in the `source` column for a
	 * roster source. Kept here so the migration can match existing rows.
	 */
	public static function stat_label( array $source ): string {
		$other = json_decode( $source['other_data'] ?? '', true );
		$label = ! empty( $other['stat_period'] ) ? $other['stat_period'] : $source['name'];
		return ! empty( $source['season_year'] ) ? $label . ' ' . $source['season_year'] : $label;
	}

	/**
	 * Newest known season per team among its active roster sources.
	 *
	 * @return array<int, string> team_id => season key
	 */
	public static function get_team_newest_seasons(): array {
		if ( self::$team_newest_cache !== null ) {
			return self::$team_newest_cache;
		}
		global $wpdb;
		$rows = $wpdb->get_results(
			"SELECT team_id, type, season_year, other_data FROM {$wpdb->prefix}pp_team_roster_sources WHERE status = 'active'",
			ARRAY_A
		) ?: array();

		$newest = array();
		foreach ( $rows as $row ) {
			$key = self::roster_source_season( $row );
			$tid = (int) $row['team_id'];
			if ( $key !== '' && ( ! isset( $newest[ $tid ] ) || strcmp( $key, $newest[ $tid ] ) > 0 ) ) {
				$newest[ $tid ] = $key;
			}
		}
		self::$team_newest_cache = $newest;
		return $newest;
	}

	/**
	 * The season key a team's live data should show: the current season, or
	 * the team's newest season when no current season is configured.
	 */
	public static function get_team_live_key( int $team_id ): string {
		$current = self::get_current_key();
		if ( $current !== '' ) {
			return $current;
		}
		return self::get_team_newest_seasons()[ $team_id ] ?? '';
	}

	/**
	 * SQL condition limiting a live stats table (alias $alias, with team_id
	 * and season_key columns) to the current season. Rows with an unknown
	 * season (NULL) are always kept so nothing goes blank; the admin season
	 * health panel flags their sources instead.
	 */
	public static function stats_scope_sql( string $alias ): string {
		global $wpdb;

		$current = self::get_current_key();
		if ( $current !== '' ) {
			return $wpdb->prepare( "( {$alias}.season_key IS NULL OR {$alias}.season_key = %s )", $current );
		}

		$newest = self::get_team_newest_seasons();
		if ( empty( $newest ) ) {
			return '1=1';
		}

		$parts = array( "{$alias}.season_key IS NULL" );
		foreach ( $newest as $team_id => $key ) {
			$parts[] = $wpdb->prepare( "( {$alias}.team_id = %d AND {$alias}.season_key = %s )", $team_id, $key );
		}
		$known   = implode( ', ', array_map( 'intval', array_keys( $newest ) ) );
		$parts[] = "{$alias}.team_id NOT IN ( {$known} )";

		return '( ' . implode( ' OR ', $parts ) . ' )';
	}

	/**
	 * The most common newest season across teams, offered as a one-click
	 * choice when no current season is set.
	 */
	public static function suggest_current_key(): string {
		$counts = array_count_values( self::get_team_newest_seasons() );
		if ( empty( $counts ) ) {
			return '';
		}
		arsort( $counts );
		$top = array_keys( $counts, max( $counts ), true );
		rsort( $top );
		return (string) $top[0];
	}

	/**
	 * Season keys worth offering in admin selects: every season seen on a
	 * source or in live stats, plus last, this and next season.
	 *
	 * @return string[] newest first
	 */
	public static function get_known_keys(): array {
		global $wpdb;
		$keys = array();

		foreach ( $wpdb->get_results( "SELECT type, season_year, other_data FROM {$wpdb->prefix}pp_team_roster_sources", ARRAY_A ) ?: array() as $row ) {
			$keys[] = self::roster_source_season( $row );
		}
		foreach ( $wpdb->get_results( "SELECT type, season, other_data FROM {$wpdb->prefix}pp_team_sources", ARRAY_A ) ?: array() as $row ) {
			$keys[] = self::schedule_source_season( $row );
		}

		$year  = (int) wp_date( 'Y' );
		$start = (int) wp_date( 'n' ) >= 8 ? $year : $year - 1;
		for ( $y = $start - 1; $y <= $start + 1; $y++ ) {
			$keys[] = $y . '-' . ( $y + 1 );
		}
		$keys[] = self::get_current_key();

		$keys = array_values( array_unique( array_filter( $keys ) ) );
		rsort( $keys );
		return $keys;
	}

	/**
	 * Problems an admin should fix, for the season health panel and notices.
	 *
	 * @return array{
	 *   no_current: bool,
	 *   suggested: string,
	 *   off_season_sources: array<int, array>,
	 *   unknown_season_sources: array<int, array>,
	 *   duplicate_external_ids: array<int, array>,
	 *   stale_live_seasons: array<int, array>
	 * }
	 */
	public static function get_health_issues(): array {
		global $wpdb;
		$p = $wpdb->prefix;

		$current    = self::get_current_key();
		$team_names = array();
		foreach ( $wpdb->get_results( "SELECT id, name FROM {$p}pp_teams", ARRAY_A ) ?: array() as $t ) {
			$team_names[ (int) $t['id'] ] = $t['name'];
		}

		$issues = array(
			'no_current'             => $current === '',
			'suggested'              => $current === '' ? self::suggest_current_key() : '',
			'off_season_sources'     => array(),
			'unknown_season_sources' => array(),
			'duplicate_external_ids' => array(),
			'stale_live_seasons'     => array(),
		);

		$sources = array();
		foreach ( $wpdb->get_results( "SELECT id, team_id, name, type, season_year, source_url_or_path, other_data FROM {$p}pp_team_roster_sources WHERE status = 'active'", ARRAY_A ) ?: array() as $row ) {
			$row['kind']   = 'roster';
			$row['season'] = self::roster_source_season( $row );
			$sources[]     = $row;
		}
		foreach ( $wpdb->get_results( "SELECT id, team_id, name, type, season, source_url_or_path, other_data FROM {$p}pp_team_sources WHERE status = 'active'", ARRAY_A ) ?: array() as $row ) {
			$row['kind']   = 'schedule';
			$row['season'] = self::schedule_source_season( $row );
			$sources[]     = $row;
		}

		$by_external = array();
		foreach ( $sources as $src ) {
			$tid   = (int) $src['team_id'];
			$entry = array(
				'team_id'   => $tid,
				'team_name' => $team_names[ $tid ] ?? "Team {$tid}",
				'kind'      => $src['kind'],
				'source'    => $src['name'],
				'season'    => $src['season'],
			);

			$is_api = in_array( $src['type'], array( 'achaRosterUrl', 'achaGameScheduleUrl', 'usphlRosterUrl', 'usphlGameScheduleUrl' ), true );

			if ( $src['season'] === '' ) {
				if ( $src['kind'] === 'roster' || $is_api ) {
					$issues['unknown_season_sources'][] = $entry;
				}
			} else {
				$expected = self::get_team_live_key( $tid );
				if ( $expected !== '' && $src['season'] !== $expected ) {
					$entry['expected']              = $expected;
					$issues['off_season_sources'][] = $entry;
				}
			}

			// League team IDs are stable across seasons, so the same ID on two
			// different teams is always a mistake, whatever the seasons.
			if ( $is_api && ! empty( $src['source_url_or_path'] ) ) {
				$league = strpos( $src['type'], 'acha' ) === 0 ? 'ACHA' : 'USPHL';
				$by_external[ $league . ':' . $src['source_url_or_path'] ][ $tid ] = $entry['team_name'];
			}
		}

		foreach ( $by_external as $ext => $teams ) {
			if ( count( $teams ) > 1 ) {
				list( $league, $id ) = explode( ':', $ext, 2 );
				$issues['duplicate_external_ids'][] = array(
					'league'      => $league,
					'external_id' => $id,
					'teams'       => $teams,
				);
			}
		}

		$live = $wpdb->get_results(
			"SELECT team_id, season_key, COUNT(*) AS n FROM (
                SELECT team_id, season_key FROM {$p}pp_team_player_stats
                UNION ALL
                SELECT team_id, season_key FROM {$p}pp_team_player_goalie_stats
             ) s WHERE season_key IS NOT NULL GROUP BY team_id, season_key",
			ARRAY_A
		) ?: array();
		foreach ( $live as $row ) {
			$tid      = (int) $row['team_id'];
			$expected = self::get_team_live_key( $tid );
			if ( $expected !== '' && $row['season_key'] !== $expected ) {
				$issues['stale_live_seasons'][] = array(
					'team_id'   => $tid,
					'team_name' => $team_names[ $tid ] ?? "Team {$tid}",
					'season'    => $row['season_key'],
					'rows'      => (int) $row['n'],
				);
			}
		}

		return $issues;
	}

	public static function count_health_issues( array $issues ): int {
		return (int) $issues['no_current']
			+ count( $issues['off_season_sources'] )
			+ count( $issues['unknown_season_sources'] )
			+ count( $issues['duplicate_external_ids'] )
			+ count( $issues['stale_live_seasons'] );
	}

	/**
	 * Tags live stats rows that have no season_key yet by matching their
	 * `source` label to the roster source that produced it. Used by the
	 * upgrade migration and before archiving, so rows imported before the
	 * season_key column existed are scoped without a re-import.
	 */
	public static function tag_untagged_stats( ?int $team_id = null ): int {
		global $wpdb;
		$p = $wpdb->prefix;

		$where   = $team_id !== null ? $wpdb->prepare( 'WHERE team_id = %d', $team_id ) : '';
		$sources = $wpdb->get_results( "SELECT team_id, name, type, season_year, other_data FROM {$p}pp_team_roster_sources {$where}", ARRAY_A ) ?: array();

		$tagged = 0;
		foreach ( $sources as $src ) {
			$key = self::roster_source_season( $src, true );
			if ( $key === '' ) {
				continue;
			}
			foreach ( array( 'pp_team_player_stats', 'pp_team_player_goalie_stats' ) as $table ) {
				$tagged += (int) $wpdb->query(
					$wpdb->prepare(
						"UPDATE {$p}{$table} SET season_key = %s WHERE team_id = %d AND source = %s AND season_key IS NULL",
						$key,
						(int) $src['team_id'],
						self::stat_label( $src )
					)
				);
			}
		}
		self::$team_newest_cache = null;
		return $tagged;
	}
}
