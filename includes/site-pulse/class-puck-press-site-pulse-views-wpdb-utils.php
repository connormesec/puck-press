<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once plugin_dir_path( __DIR__ ) . 'class-puck-press-wpdb-utils-base-abstract.php';

/**
 * Storage + roll-up queries for the Site Pulse first-party visit counter.
 *
 * The counter is privacy-friendly by construction: it stores ONLY a daily,
 * per-page aggregate. No IP addresses, no user IDs, no cookies, no user-agents —
 * nothing that identifies a visitor is persisted. A long URL path is keyed by its
 * sha1 (a fixed 40 chars) so the UNIQUE index stays within MySQL's index-length
 * limits while the human-readable path is kept alongside for the "top pages" list.
 *
 * @package    Puck_Press
 * @subpackage Puck_Press/includes/site-pulse
 */
class Puck_Press_Site_Pulse_Views_Wpdb_Utils extends Puck_Press_Wpdb_Utils_Base {

	protected $table_schemas = array(
		'pp_site_views' => '
			id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			view_date DATE NOT NULL,
			url_hash CHAR(40) NOT NULL,
			url_path VARCHAR(255) NOT NULL,
			view_count INT UNSIGNED NOT NULL DEFAULT 0,
			visit_count INT UNSIGNED NOT NULL DEFAULT 0,
			PRIMARY KEY (id),
			UNIQUE KEY day_page (view_date, url_hash),
			KEY view_date (view_date)
		',
	);

	private function table(): string {
		global $wpdb;
		return $wpdb->prefix . 'pp_site_views';
	}

	/**
	 * Record one page view (and optionally one new session) for a path on a date.
	 *
	 * Single atomic upsert keyed on (view_date, url_hash) — no read-then-write, so
	 * it is safe and cheap under concurrent load.
	 *
	 * @param string $path        Front-end path, e.g. "/schedule".
	 * @param bool   $new_session Whether this beacon began a new browser session.
	 */
	public function record_hit( string $path, bool $new_session ): void {
		global $wpdb;

		$path = $this->normalize_path( $path );
		$hash = sha1( $path );
		$date = current_time( 'Y-m-d' );
		$add_visit = $new_session ? 1 : 0;

		$wpdb->query(
			$wpdb->prepare(
				"INSERT INTO {$this->table()} (view_date, url_hash, url_path, view_count, visit_count)
				 VALUES (%s, %s, %s, 1, %d)
				 ON DUPLICATE KEY UPDATE view_count = view_count + 1, visit_count = visit_count + %d",
				$date,
				$hash,
				$path,
				$add_visit,
				$add_visit
			)
		);
	}

	/**
	 * Total page views in a calendar month.
	 */
	public function get_month_views( int $year, int $month ): int {
		global $wpdb;
		list( $start, $end ) = $this->month_bounds( $year, $month );
		return (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COALESCE(SUM(view_count), 0) FROM {$this->table()}
				 WHERE view_date >= %s AND view_date < %s",
				$start,
				$end
			)
		);
	}

	/**
	 * Total visits (sessions) in a calendar month.
	 */
	public function get_month_visits( int $year, int $month ): int {
		global $wpdb;
		list( $start, $end ) = $this->month_bounds( $year, $month );
		return (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COALESCE(SUM(visit_count), 0) FROM {$this->table()}
				 WHERE view_date >= %s AND view_date < %s",
				$start,
				$end
			)
		);
	}

	/**
	 * Most-viewed paths in a calendar month.
	 *
	 * @return array<int, array{path:string, views:int}>
	 */
	public function get_top_pages( int $year, int $month, int $limit = 5 ): array {
		global $wpdb;
		list( $start, $end ) = $this->month_bounds( $year, $month );
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT url_path AS path, SUM(view_count) AS views FROM {$this->table()}
				 WHERE view_date >= %s AND view_date < %s
				 GROUP BY url_hash, url_path
				 ORDER BY views DESC
				 LIMIT %d",
				$start,
				$end,
				$limit
			),
			ARRAY_A
		) ?: array();

		return array_map(
			fn( $r ) => array(
				'path'  => (string) $r['path'],
				'views' => (int) $r['views'],
			),
			$rows
		);
	}

	/**
	 * Page-view totals for a month and the month before it, with a percent change.
	 *
	 * @return array{current:int, previous:int, percent:?int, is_new:bool}
	 */
	public function get_month_comparison( int $year, int $month ): array {
		$current = $this->get_month_views( $year, $month );

		$prev_month = $month - 1;
		$prev_year  = $year;
		if ( $prev_month < 1 ) {
			$prev_month = 12;
			--$prev_year;
		}
		$previous = $this->get_month_views( $prev_year, $prev_month );

		$percent = null;
		$is_new  = false;
		if ( $previous > 0 ) {
			$percent = (int) round( ( ( $current - $previous ) / $previous ) * 100 );
		} elseif ( $current > 0 ) {
			$is_new = true;
		}

		return array(
			'current'  => $current,
			'previous' => $previous,
			'percent'  => $percent,
			'is_new'   => $is_new,
		);
	}

	/**
	 * Drop aggregate rows older than $months months to cap table growth.
	 */
	public function prune_old( int $months = 14 ): void {
		global $wpdb;
		$cutoff = gmdate( 'Y-m-d', strtotime( "-{$months} months", current_time( 'timestamp' ) ) );
		$wpdb->query(
			$wpdb->prepare( "DELETE FROM {$this->table()} WHERE view_date < %s", $cutoff )
		);
	}

	/**
	 * Reduce an arbitrary request path to a stored, bounded, query-free path.
	 */
	private function normalize_path( string $path ): string {
		$only_path = wp_parse_url( $path, PHP_URL_PATH );
		if ( ! is_string( $only_path ) || $only_path === '' ) {
			$only_path = '/';
		}
		$only_path = '/' . ltrim( $only_path, '/' );
		if ( strlen( $only_path ) > 1 ) {
			$only_path = untrailingslashit( $only_path );
		}
		return substr( sanitize_text_field( $only_path ), 0, 255 );
	}

	/**
	 * @return array{0:string, 1:string} [first day of month, first day of next month]
	 */
	private function month_bounds( int $year, int $month ): array {
		$start = sprintf( '%04d-%02d-01', $year, $month );
		$next_month = $month + 1;
		$next_year  = $year;
		if ( $next_month > 12 ) {
			$next_month = 1;
			++$next_year;
		}
		$end = sprintf( '%04d-%02d-01', $next_year, $next_month );
		return array( $start, $end );
	}
}
