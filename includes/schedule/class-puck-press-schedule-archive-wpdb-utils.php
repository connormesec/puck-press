<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Puck_Press_Schedule_Archive_Wpdb_Utils extends Puck_Press_Wpdb_Utils_Base {

	protected $table_schemas = array(
		'pp_schedule_archives'        => '
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            archive_key VARCHAR(150) NOT NULL,
            season VARCHAR(50) NOT NULL,
            game_count INT UNSIGNED NOT NULL DEFAULT 0,
            date_min DATE DEFAULT NULL,
            date_max DATE DEFAULT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY archive_key (archive_key),
            UNIQUE KEY season (season)
        ',
		'pp_schedule_archive_games'   => "
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            archive_key VARCHAR(150) NOT NULL,
            source VARCHAR(100) NOT NULL,
            source_type VARCHAR(100) NOT NULL,
            game_id VARCHAR(50) NOT NULL,
            target_team_id VARCHAR(50) NOT NULL,
            target_team_name VARCHAR(100) NOT NULL,
            target_team_nickname VARCHAR(50) DEFAULT NULL,
            target_team_logo TEXT DEFAULT NULL,
            opponent_team_id VARCHAR(50) NOT NULL,
            opponent_team_name VARCHAR(100) NOT NULL,
            opponent_team_nickname VARCHAR(50) DEFAULT NULL,
            opponent_team_logo TEXT DEFAULT NULL,
            target_score TINYINT DEFAULT NULL,
            opponent_score TINYINT DEFAULT NULL,
            game_status VARCHAR(50) DEFAULT NULL,
            promo_header VARCHAR(100) DEFAULT NULL,
            promo_text TEXT DEFAULT NULL,
            promo_img_url TEXT DEFAULT NULL,
            promo_ticket_link TEXT DEFAULT NULL,
            post_link TEXT DEFAULT NULL,
            game_date_day VARCHAR(50) NOT NULL,
            game_time VARCHAR(50) DEFAULT NULL,
            game_timestamp DATETIME NULL DEFAULT NULL,
            home_or_away ENUM('home', 'away') NOT NULL DEFAULT 'home',
            venue VARCHAR(150) DEFAULT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY archive_key (archive_key)
        ",
		'pp_schedule_archive_sources' => "
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            archive_key VARCHAR(150) NOT NULL,
            original_id BIGINT(20) UNSIGNED DEFAULT NULL,
            name VARCHAR(100) NOT NULL,
            type TEXT NOT NULL,
            season VARCHAR(50) DEFAULT NULL,
            source_url_or_path TEXT DEFAULT NULL,
            last_updated DATETIME DEFAULT NULL,
            status ENUM('active', 'inactive') DEFAULT 'active',
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            csv_data LONGTEXT NULL,
            other_data LONGTEXT NULL,
            PRIMARY KEY (id),
            KEY archive_key (archive_key)
        ",
	);

	public function init_tables(): void {
		foreach ( array_keys( $this->table_schemas ) as $table_name ) {
			$this->maybe_create_or_update_table( $table_name );
		}
	}

	public function season_exists( string $season ): bool {
		global $wpdb;
		$table = $this->get_full_table_name( 'pp_schedule_archives' );
		$count = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM $table WHERE season = %s",
				$season
			)
		);
		return (int) $count > 0;
	}

	public function get_display_game_count(): int {
		global $wpdb;
		$table = $wpdb->prefix . 'pp_game_schedule_for_display';
		return (int) $wpdb->get_var( "SELECT COUNT(*) FROM $table" );
	}

	public function create_archive( string $archive_key, string $season ): bool {
		global $wpdb;

		$display_table   = $wpdb->prefix . 'pp_game_schedule_for_display';
		$sources_table   = $wpdb->prefix . 'pp_schedule_data_sources';
		$archive_games   = $this->get_full_table_name( 'pp_schedule_archive_games' );
		$archive_sources = $this->get_full_table_name( 'pp_schedule_archive_sources' );
		$archive_meta    = $this->get_full_table_name( 'pp_schedule_archives' );

		$games = $wpdb->get_results( "SELECT * FROM $display_table", ARRAY_A );
		if ( empty( $games ) ) {
			return false;
		}

		foreach ( $games as $game ) {
			unset( $game['id'] );
			$game['archive_key'] = $archive_key;
			$wpdb->insert( $archive_games, $game, $this->get_format_array_for_insert( $game ) );
		}

		$sources = $wpdb->get_results( "SELECT * FROM $sources_table", ARRAY_A );
		foreach ( $sources as $source ) {
			$original_id = $source['id'];
			unset( $source['id'] );
			$source['archive_key'] = $archive_key;
			$source['original_id'] = $original_id;
			$wpdb->insert( $archive_sources, $source, $this->get_format_array_for_insert( $source ) );
		}

		$date_min = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT MIN(game_timestamp) FROM $archive_games WHERE archive_key = %s",
				$archive_key
			)
		);
		$date_max = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT MAX(game_timestamp) FROM $archive_games WHERE archive_key = %s",
				$archive_key
			)
		);

		$wpdb->insert(
			$archive_meta,
			array(
				'archive_key' => $archive_key,
				'season'      => $season,
				'game_count'  => count( $games ),
				'date_min'    => $date_min ? date( 'Y-m-d', strtotime( $date_min ) ) : null,
				'date_max'    => $date_max ? date( 'Y-m-d', strtotime( $date_max ) ) : null,
				'created_at'  => current_time( 'mysql' ),
			)
		);

		return true;
	}

	public function get_all_archives(): array {
		global $wpdb;
		$table   = $this->get_full_table_name( 'pp_schedule_archives' );
		$results = $wpdb->get_results( "SELECT * FROM $table ORDER BY created_at DESC", ARRAY_A );
		return $results ?: array();
	}

	public function delete_archive( string $archive_key ): void {
		global $wpdb;
		$wpdb->delete( $this->get_full_table_name( 'pp_schedule_archive_games' ), array( 'archive_key' => $archive_key ) );
		$wpdb->delete( $this->get_full_table_name( 'pp_schedule_archive_sources' ), array( 'archive_key' => $archive_key ) );
		$wpdb->delete( $this->get_full_table_name( 'pp_schedule_archives' ), array( 'archive_key' => $archive_key ) );
	}
}
