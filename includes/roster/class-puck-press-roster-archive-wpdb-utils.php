<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * DB utilities for snapshotting roster stats by season.
 *
 * Three tables:
 *  - pp_roster_archives           — metadata (one row per archived season)
 *  - pp_roster_stats_archive      — archived skater stats rows
 *  - pp_roster_goalie_stats_archive — archived goalie stats rows
 */
class Puck_Press_Roster_Archive_Wpdb_Utils extends Puck_Press_Wpdb_Utils_Base {

	protected $table_schemas = array(
		'pp_roster_archives'             => '
			id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			archive_key VARCHAR(150) NOT NULL,
			season VARCHAR(50) NOT NULL,
			skater_count INT UNSIGNED NOT NULL DEFAULT 0,
			goalie_count INT UNSIGNED NOT NULL DEFAULT 0,
			created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY (id),
			UNIQUE KEY archive_key (archive_key),
			UNIQUE KEY season (season)
		',
		'pp_roster_stats_archive'        => '
			id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			archive_key VARCHAR(150) NOT NULL,
			season VARCHAR(50) NOT NULL,
			player_id VARCHAR(50) NOT NULL,
			source VARCHAR(100) NOT NULL,
			games_played SMALLINT DEFAULT NULL,
			goals SMALLINT DEFAULT NULL,
			assists SMALLINT DEFAULT NULL,
			points SMALLINT DEFAULT NULL,
			points_per_game DECIMAL(5,2) DEFAULT NULL,
			power_play_goals SMALLINT DEFAULT NULL,
			short_handed_goals SMALLINT DEFAULT NULL,
			game_winning_goals SMALLINT DEFAULT NULL,
			shootout_winning_goals SMALLINT DEFAULT NULL,
			penalty_minutes SMALLINT DEFAULT NULL,
			shooting_percentage DECIMAL(5,2) DEFAULT NULL,
			stat_rank SMALLINT DEFAULT NULL,
			PRIMARY KEY (id),
			KEY archive_key (archive_key),
			KEY player_id (player_id)
		',
		'pp_roster_goalie_stats_archive' => '
			id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			archive_key VARCHAR(150) NOT NULL,
			season VARCHAR(50) NOT NULL,
			player_id VARCHAR(50) NOT NULL,
			source VARCHAR(100) NOT NULL,
			games_played SMALLINT DEFAULT NULL,
			wins SMALLINT DEFAULT NULL,
			losses SMALLINT DEFAULT NULL,
			overtime_losses SMALLINT DEFAULT NULL,
			shootout_losses SMALLINT DEFAULT NULL,
			shootout_wins SMALLINT DEFAULT NULL,
			shots_against SMALLINT DEFAULT NULL,
			saves SMALLINT DEFAULT NULL,
			save_percentage DECIMAL(6,3) DEFAULT NULL,
			goals_against_average DECIMAL(5,2) DEFAULT NULL,
			goals_against SMALLINT DEFAULT NULL,
			goals SMALLINT DEFAULT NULL,
			assists SMALLINT DEFAULT NULL,
			penalty_minutes SMALLINT DEFAULT NULL,
			stat_rank SMALLINT DEFAULT NULL,
			PRIMARY KEY (id),
			KEY archive_key (archive_key),
			KEY player_id (player_id)
		',
	);

	public function init_tables(): void {
		foreach ( array_keys( $this->table_schemas ) as $table_name ) {
			$this->maybe_create_or_update_table( $table_name );
		}
	}

	// ── Archive creation ──────────────────────────────────────────────────────

	/**
	 * Snapshot current pp_roster_stats and pp_roster_goalie_stats rows, tagged
	 * with archive_key and season. Safe when either stats table is empty.
	 */
	public function create_stats_archive( string $archive_key, string $season ): bool {
		global $wpdb;

		$skater_src = $wpdb->prefix . 'pp_roster_stats';
		$goalie_src = $wpdb->prefix . 'pp_roster_goalie_stats';
		$skater_dst = $this->get_full_table_name( 'pp_roster_stats_archive' );
		$goalie_dst = $this->get_full_table_name( 'pp_roster_goalie_stats_archive' );
		$meta_table = $this->get_full_table_name( 'pp_roster_archives' );

		$skaters = $wpdb->get_results( "SELECT * FROM $skater_src", ARRAY_A );
		foreach ( $skaters as $row ) {
			unset( $row['id'] );
			$row['archive_key'] = $archive_key;
			$row['season']      = $season;
			$wpdb->insert( $skater_dst, $row, $this->get_format_array_for_insert( $row ) );
		}

		$goalies = $wpdb->get_results( "SELECT * FROM $goalie_src", ARRAY_A );
		foreach ( $goalies as $row ) {
			unset( $row['id'] );
			$row['archive_key'] = $archive_key;
			$row['season']      = $season;
			$wpdb->insert( $goalie_dst, $row, $this->get_format_array_for_insert( $row ) );
		}

		$wpdb->insert(
			$meta_table,
			array(
				'archive_key'  => $archive_key,
				'season'       => $season,
				'skater_count' => count( $skaters ),
				'goalie_count' => count( $goalies ),
				'created_at'   => current_time( 'mysql' ),
			)
		);

		return true;
	}

	// ── Metadata queries ──────────────────────────────────────────────────────

	public function get_all_roster_archives(): array {
		global $wpdb;
		$table   = $this->get_full_table_name( 'pp_roster_archives' );
		$results = $wpdb->get_results( "SELECT * FROM $table ORDER BY created_at DESC", ARRAY_A );
		return $results ?: array();
	}

	public function roster_archive_season_exists( string $season ): bool {
		global $wpdb;
		$table = $this->get_full_table_name( 'pp_roster_archives' );
		$count = $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM $table WHERE season = %s", $season ) );
		return (int) $count > 0;
	}

	/**
	 * Returns counts of rows currently in the live stats tables.
	 */
	public function get_live_stats_count(): array {
		global $wpdb;
		return array(
			'skater_count' => (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->prefix}pp_roster_stats" ),
			'goalie_count' => (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->prefix}pp_roster_goalie_stats" ),
		);
	}

	// ── Per-player archive queries (used by player-page.php) ──────────────────

	public function get_player_skater_archives( string $player_id ): array {
		global $wpdb;
		$table = $this->get_full_table_name( 'pp_roster_stats_archive' );
		return $wpdb->get_results(
			$wpdb->prepare( "SELECT * FROM $table WHERE player_id = %s ORDER BY season DESC", $player_id ),
			ARRAY_A
		) ?: array();
	}

	public function get_player_goalie_archives( string $player_id ): array {
		global $wpdb;
		$table = $this->get_full_table_name( 'pp_roster_goalie_stats_archive' );
		return $wpdb->get_results(
			$wpdb->prepare( "SELECT * FROM $table WHERE player_id = %s ORDER BY season DESC", $player_id ),
			ARRAY_A
		) ?: array();
	}

	// ── Deletion ──────────────────────────────────────────────────────────────

	public function delete_stats_archive( string $archive_key ): void {
		global $wpdb;
		$wpdb->delete( $this->get_full_table_name( 'pp_roster_stats_archive' ), array( 'archive_key' => $archive_key ) );
		$wpdb->delete( $this->get_full_table_name( 'pp_roster_goalie_stats_archive' ), array( 'archive_key' => $archive_key ) );
		$wpdb->delete( $this->get_full_table_name( 'pp_roster_archives' ), array( 'archive_key' => $archive_key ) );
	}
}
