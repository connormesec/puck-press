<?php

require_once plugin_dir_path( __DIR__ ) . 'class-puck-press-wpdb-utils-base-abstract.php';
require_once plugin_dir_path( __DIR__ ) . 'class-puck-press-group-aware-wpdb-utils-abstract.php';

class Puck_Press_Roster_Wpdb_Utils extends Puck_Press_Group_Aware_Wpdb_Utils {

	protected function get_registry_table_name(): string {
		return 'pp_rosters';
	}

	protected function get_group_id_column(): string {
		return 'roster_id';
	}

	protected function get_domain_tables(): array {
		return array(
			'pp_roster_data_sources',
			'pp_roster_raw',
			'pp_roster_mods',
			'pp_roster_for_display',
			'pp_roster_stats',
			'pp_roster_goalie_stats',
		);
	}

	// no inline comments in this array, as it is used to create the tables in the database
	protected $table_schemas = array(
		'pp_rosters'             => '
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            slug VARCHAR(100) NOT NULL,
            name VARCHAR(200) NOT NULL,
            description TEXT DEFAULT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY slug (slug)
        ',
		'pp_roster_data_sources' => "
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            roster_id BIGINT(20) UNSIGNED NOT NULL DEFAULT 1,
            name VARCHAR(100) NOT NULL,
            type TEXT NOT NULL,
            source_url_or_path TEXT DEFAULT NULL,
            stats_url TEXT DEFAULT NULL,
            goalie_stats_url TEXT DEFAULT NULL,
            last_updated DATETIME DEFAULT NULL,
            status ENUM('active', 'inactive') DEFAULT 'active',
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            csv_data LONGTEXT NULL,
            other_data LONGTEXT NULL,
            PRIMARY KEY (id),
            KEY roster_id (roster_id)
        ",
		'pp_roster_raw'          => '
            id MEDIUMINT(9) NOT NULL AUTO_INCREMENT,
            roster_id BIGINT(20) UNSIGNED NOT NULL DEFAULT 1,
            source VARCHAR(100) NOT NULL,
            player_id VARCHAR(50) NOT NULL,
            headshot_link TEXT,
            number SMALLINT(3) NOT NULL,
            name VARCHAR(100) NOT NULL,
            pos VARCHAR(10),
            ht VARCHAR(10),
            wt SMALLINT(3),
            shoots VARCHAR(5),
            hometown VARCHAR(100),
            team_id VARCHAR(20) DEFAULT NULL,
            team_name VARCHAR(200) DEFAULT NULL,
            last_team VARCHAR(100),
            year_in_school VARCHAR(50),
            major VARCHAR(100),
            PRIMARY KEY (id),
            KEY roster_id (roster_id)
        ',
		'pp_roster_mods'         => '
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            roster_id BIGINT(20) UNSIGNED NOT NULL DEFAULT 1,
            external_id VARCHAR(50) DEFAULT NULL,
            edit_action VARCHAR(50),
            edit_data LONGTEXT NOT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY roster_id (roster_id)
        ',
		'pp_roster_for_display'  => '
            id MEDIUMINT(9) NOT NULL AUTO_INCREMENT,
            roster_id BIGINT(20) UNSIGNED NOT NULL DEFAULT 1,
            source VARCHAR(100) NOT NULL,
            player_id VARCHAR(50) NOT NULL,
            headshot_link TEXT,
            number SMALLINT(3) NOT NULL,
            name VARCHAR(100) NOT NULL,
            pos VARCHAR(10),
            ht VARCHAR(10),
            wt SMALLINT(3),
            shoots VARCHAR(5),
            hometown VARCHAR(100),
            team_id VARCHAR(20) DEFAULT NULL,
            team_name VARCHAR(200) DEFAULT NULL,
            last_team VARCHAR(100),
            year_in_school VARCHAR(50),
            major VARCHAR(100),
            hero_image_url TEXT,
            PRIMARY KEY (id),
            KEY roster_id (roster_id)
        ',
		'pp_roster_stats'        => '
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            roster_id BIGINT(20) UNSIGNED NOT NULL DEFAULT 1,
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
            KEY roster_id (roster_id)
        ',
		'pp_roster_goalie_stats' => '
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            roster_id BIGINT(20) UNSIGNED NOT NULL DEFAULT 1,
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
            KEY roster_id (roster_id)
        ',
	);

	public function get_active_roster_sources( int $roster_id = 1 ) {
		global $wpdb;

		$full_table_name = $this->get_full_table_name( 'pp_roster_data_sources' );

		$active_sources = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM $full_table_name WHERE status = %s AND roster_id = %d",
				'active',
				$roster_id
			),
			OBJECT
		);

		if ( empty( $active_sources ) ) {
			return;
		}
		return $active_sources;
	}

	public function delete_rows_for_roster( string $table_name, int $roster_id ): void {
		global $wpdb;
		$full_table = $wpdb->prefix . $table_name;
		$wpdb->delete( $full_table, array( 'roster_id' => $roster_id ), array( '%d' ) );
	}

	public function insert_multiple_roster_rows( array $roster_rows = array(), int $roster_id = 1 ) {
		foreach ( $roster_rows as &$row ) {
			$row['roster_id'] = $roster_id;
		}
		unset( $row );
		return $this->insert_multiple_rows(
			'pp_roster_raw',
			$roster_rows,
			'roster_rows',
			function ( $row, $field ) {
				return ! array_key_exists( $field, $row ); }
		);
	}

	public function delete_row_by_player_id( $table_name, $player_id ) {
		global $wpdb;
		$full_table = $wpdb->prefix . $table_name;

		return $wpdb->delete( $full_table, array( 'player_id' => $player_id ) );
	}

	public function insert_goalie_stats_rows( array $stats_rows = array(), int $roster_id = 1 ) {
		foreach ( $stats_rows as &$row ) {
			$row['roster_id'] = $roster_id;
		}
		unset( $row );
		return $this->insert_stats_rows_into( 'pp_roster_goalie_stats', $stats_rows );
	}

	public function insert_stats_rows( array $stats_rows = array(), int $roster_id = 1 ) {
		foreach ( $stats_rows as &$row ) {
			$row['roster_id'] = $roster_id;
		}
		unset( $row );
		return $this->insert_stats_rows_into( 'pp_roster_stats', $stats_rows );
	}

	private function insert_stats_rows_into( string $table_name, array $stats_rows ) {
		global $wpdb;

		if ( empty( $stats_rows ) || ! is_array( $stats_rows ) ) {
			return new WP_Error( 'no_data', 'No stats rows provided.' );
		}

		$full_table_name = $this->get_full_table_name( $table_name );
		$inserted_ids    = array();
		$insert_errors   = array();

		foreach ( $stats_rows as $index => $row ) {
			$inserted = $wpdb->insert(
				$full_table_name,
				$row,
				$this->get_format_array_for_insert( $row )
			);

			if ( $inserted !== false ) {
				$inserted_ids[] = $wpdb->insert_id;
			} else {
				$insert_errors[] = array(
					'row_index' => $index,
					'row_data'  => $row,
					'db_error'  => $wpdb->last_error,
				);
			}
		}

		return array(
			'inserted_ids'  => $inserted_ids,
			'insert_errors' => $insert_errors,
		);
	}
}
