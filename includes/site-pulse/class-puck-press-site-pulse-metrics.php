<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Collects the metrics shown in a Site Pulse digest for a given month.
 *
 * Every sub-collector degrades gracefully to zero/empty if a subsystem is
 * unconfigured or its table is missing, so a digest never fatals — a client
 * without awards or standings still gets a coherent email.
 *
 * @package    Puck_Press
 * @subpackage Puck_Press/includes/site-pulse
 */
class Puck_Press_Site_Pulse_Metrics {

	/**
	 * Build the full metrics payload consumed by the email renderer.
	 *
	 * @param int $year  Four-digit year of the reporting month.
	 * @param int $month 1-12 reporting month.
	 * @return array
	 */
	public function collect( int $year, int $month ): array {
		return array(
			'site_name'   => wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES ),
			'month_label' => date_i18n( 'F Y', mktime( 0, 0, 0, $month, 1, $year ) ),
			'generated'   => date_i18n( 'M j, Y' ),
			'traffic'     => $this->collect_traffic( $year, $month ),
			'content'     => $this->collect_content( $year, $month ),
			'schedule'    => $this->collect_schedule( $year, $month ),
			'season'      => $this->collect_season( $year, $month ),
		);
	}

	private function collect_traffic( int $year, int $month ): array {
		try {
			require_once plugin_dir_path( __FILE__ ) . 'class-puck-press-site-pulse-views-wpdb-utils.php';
			$utils      = new Puck_Press_Site_Pulse_Views_Wpdb_Utils();
			$comparison = $utils->get_month_comparison( $year, $month );

			$top = array();
			foreach ( $utils->get_top_pages( $year, $month, 5 ) as $page ) {
				$top[] = array(
					'label' => $this->path_to_label( $page['path'] ),
					'path'  => $page['path'],
					'views' => $page['views'],
				);
			}

			return array(
				'views'      => $utils->get_month_views( $year, $month ),
				'visits'     => $utils->get_month_visits( $year, $month ),
				'comparison' => $comparison,
				'top_pages'  => $top,
			);
		} catch ( \Throwable $e ) {
			return array(
				'views'      => 0,
				'visits'     => 0,
				'comparison' => array( 'current' => 0, 'previous' => 0, 'percent' => null, 'is_new' => false ),
				'top_pages'  => array(),
			);
		}
	}

	private function collect_content( int $year, int $month ): array {
		return array(
			'recaps' => $this->count_posts_in_month( 'pp_game_summary', $year, $month ),
			'insta'  => $this->count_posts_in_month( 'pp_insta_post', $year, $month ),
			'forms'  => $this->count_form_submissions( $year, $month ),
		);
	}

	/**
	 * Contact form submissions tallied for the month by Puck_Press_Site_Pulse.
	 */
	private function count_form_submissions( int $year, int $month ): int {
		$counts = get_option( Puck_Press_Site_Pulse::OPTION_FORM_COUNTS, array() );
		if ( ! is_array( $counts ) ) {
			return 0;
		}
		$key = sprintf( '%04d-%02d', $year, $month );
		return isset( $counts[ $key ] ) ? (int) $counts[ $key ] : 0;
	}

	private function count_posts_in_month( string $post_type, int $year, int $month ): int {
		global $wpdb;
		list( $start, $end ) = $this->month_bounds( $year, $month );
		return (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$wpdb->posts}
				 WHERE post_type = %s AND post_status = 'publish'
				   AND post_date >= %s AND post_date < %s",
				$post_type,
				$start,
				$end
			)
		);
	}

	/**
	 * Games whose game_timestamp falls in the month, and how many have a final
	 * score recorded. Uses game_timestamp (not created_at) because the schedule
	 * display table is re-materialized on every refresh, which resets created_at.
	 */
	private function collect_schedule( int $year, int $month ): array {
		global $wpdb;

		$schedule_id = $this->main_schedule_id();
		$table       = $wpdb->prefix . 'pp_schedule_games_display';
		list( $start, $end ) = $this->month_bounds( $year, $month );

		$tracked = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$table}
				 WHERE schedule_id = %d AND game_timestamp >= %s AND game_timestamp < %s",
				$schedule_id,
				$start,
				$end
			)
		);

		$scored = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$table}
				 WHERE schedule_id = %d AND game_timestamp >= %s AND game_timestamp < %s
				   AND target_score IS NOT NULL AND opponent_score IS NOT NULL
				   AND game_status IS NOT NULL AND game_status NOT IN ('', 'null')",
				$schedule_id,
				$start,
				$end
			)
		);

		return array(
			'tracked' => $tracked,
			'scored'  => $scored,
		);
	}

	private function collect_season( int $year, int $month ): array {
		$record = array( 'wins' => 0, 'losses' => 0, 'otl' => 0, 'ties' => 0, 'gf' => 0, 'ga' => 0 );
		try {
			require_once plugin_dir_path( __DIR__ ) . 'record/class-puck-press-record-wpdb-utils.php';
			$stats  = ( new Puck_Press_Record_Wpdb_Utils() )->get_record_stats( $this->main_schedule_id() );
			$record = array(
				'wins'   => (int) ( $stats['wins'] ?? 0 ),
				'losses' => (int) ( $stats['losses'] ?? 0 ),
				'otl'    => (int) ( $stats['otl'] ?? 0 ),
				'ties'   => (int) ( $stats['ties'] ?? 0 ),
				'gf'     => (int) ( $stats['gf'] ?? 0 ),
				'ga'     => (int) ( $stats['ga'] ?? 0 ),
			);
		} catch ( \Throwable $e ) {
			// keep zeroes
		}

		return array(
			'record'       => $record,
			'standings'    => $this->collect_standings(),
			'awards_added' => $this->count_awards_in_month( $year, $month ),
		);
	}

	/**
	 * Per-team standing (division + rank derived from the cached, sorted table).
	 *
	 * @return array<int, array{team:string, division:string, rank:?int, total:int}>
	 */
	private function collect_standings(): array {
		$out = array();
		try {
			require_once plugin_dir_path( __DIR__ ) . 'teams/class-puck-press-teams-wpdb-utils.php';
			require_once plugin_dir_path( __DIR__ ) . 'standings/class-puck-press-standings-wpdb-utils.php';
			$teams_utils     = new Puck_Press_Teams_Wpdb_Utils();
			$standings_utils = new Puck_Press_Standings_Wpdb_Utils();

			foreach ( $teams_utils->get_all_teams() as $team ) {
				$cache = $standings_utils->get_standings_for_team( (int) $team['id'] );
				if ( ! $cache || empty( $cache['standings_data'] ) || ! is_array( $cache['standings_data'] ) ) {
					continue;
				}

				$rank = null;
				$i    = 0;
				foreach ( $cache['standings_data'] as $row ) {
					++$i;
					if ( ! empty( $row['is_target'] ) ) {
						$rank = $i;
						break;
					}
				}

				$out[] = array(
					'team'     => (string) ( $team['name'] ?? '' ),
					'division' => (string) ( $cache['division_name'] ?? '' ),
					'rank'     => $rank,
					'total'    => count( $cache['standings_data'] ),
				);
			}
		} catch ( \Throwable $e ) {
			return array();
		}
		return $out;
	}

	private function count_awards_in_month( int $year, int $month ): int {
		global $wpdb;
		$table = $wpdb->prefix . 'pp_awards';
		if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) ) !== $table ) {
			return 0;
		}
		list( $start, $end ) = $this->month_bounds( $year, $month );
		return (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$table} WHERE created_at >= %s AND created_at < %s",
				$start,
				$end
			)
		);
	}

	private function main_schedule_id(): int {
		try {
			require_once plugin_dir_path( __DIR__ ) . 'schedule/class-puck-press-schedules-wpdb-utils.php';
			$id = ( new Puck_Press_Schedules_Wpdb_Utils() )->get_main_schedule_id();
			return $id > 0 ? $id : 1;
		} catch ( \Throwable $e ) {
			return 1;
		}
	}

	/**
	 * Turn a stored path into a friendly label: real post/page title when the path
	 * resolves, otherwise a prettified version of the path.
	 */
	private function path_to_label( string $path ): string {
		if ( $path === '/' || $path === '' ) {
			return 'Home';
		}
		$post_id = url_to_postid( home_url( $path ) );
		if ( $post_id ) {
			$title = get_the_title( $post_id );
			if ( $title !== '' ) {
				return $title;
			}
		}
		$slug = trim( $path, '/' );
		$slug = explode( '/', $slug );
		$slug = end( $slug );
		$slug = str_replace( array( '-', '_' ), ' ', $slug );
		return ucwords( $slug );
	}

	/**
	 * @return array{0:string, 1:string} MySQL datetime bounds [month start, next month start]
	 */
	private function month_bounds( int $year, int $month ): array {
		$start = sprintf( '%04d-%02d-01 00:00:00', $year, $month );
		$next_month = $month + 1;
		$next_year  = $year;
		if ( $next_month > 12 ) {
			$next_month = 1;
			++$next_year;
		}
		$end = sprintf( '%04d-%02d-01 00:00:00', $next_year, $next_month );
		return array( $start, $end );
	}
}
