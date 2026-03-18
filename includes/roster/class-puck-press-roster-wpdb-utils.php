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
		return array();
	}

	// no inline comments in this array, as it is used to create the tables in the database
	protected $table_schemas = array(
		'pp_rosters' => '
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            slug VARCHAR(100) NOT NULL,
            name VARCHAR(200) NOT NULL,
            description TEXT DEFAULT NULL,
            is_main TINYINT(1) NOT NULL DEFAULT 0,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY slug (slug)
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

	public function aggregate_roster_into_group( int $target_id, array $source_ids ): void {
		global $wpdb;

		if ( empty( $source_ids ) ) {
			return;
		}

		$placeholders = implode( ', ', array_fill( 0, count( $source_ids ), '%d' ) );

		$tfd = $wpdb->prefix . 'pp_roster_for_display';
		$wpdb->delete( $tfd, array( 'roster_id' => $target_id ), array( '%d' ) );
		$wpdb->query(
			$wpdb->prepare(
				"INSERT INTO {$tfd}
                    (roster_id, source, player_id, headshot_link, number, name, pos, ht, wt,
                     shoots, hometown, team_id, team_name, last_team, year_in_school, major, hero_image_url)
                SELECT %d, source, player_id, headshot_link, number, name, pos, ht, wt,
                       shoots, hometown, team_id, team_name, last_team, year_in_school, major, hero_image_url
                FROM {$tfd}
                WHERE roster_id IN ({$placeholders})",
				array_merge( array( $target_id ), $source_ids )
			)
		);

		$ts = $wpdb->prefix . 'pp_roster_stats';
		$wpdb->delete( $ts, array( 'roster_id' => $target_id ), array( '%d' ) );
		$wpdb->query(
			$wpdb->prepare(
				"INSERT INTO {$ts}
                    (roster_id, player_id, source, games_played, goals, assists, points,
                     points_per_game, power_play_goals, short_handed_goals, game_winning_goals,
                     shootout_winning_goals, penalty_minutes, shooting_percentage, stat_rank)
                SELECT %d, player_id, source, games_played, goals, assists, points,
                       points_per_game, power_play_goals, short_handed_goals, game_winning_goals,
                       shootout_winning_goals, penalty_minutes, shooting_percentage, stat_rank
                FROM {$ts}
                WHERE roster_id IN ({$placeholders})",
				array_merge( array( $target_id ), $source_ids )
			)
		);

		$tg = $wpdb->prefix . 'pp_roster_goalie_stats';
		$wpdb->delete( $tg, array( 'roster_id' => $target_id ), array( '%d' ) );
		$wpdb->query(
			$wpdb->prepare(
				"INSERT INTO {$tg}
                    (roster_id, player_id, source, games_played, wins, losses, overtime_losses,
                     shootout_losses, shootout_wins, shots_against, saves, save_percentage,
                     goals_against_average, goals_against, goals, assists, penalty_minutes, stat_rank)
                SELECT %d, player_id, source, games_played, wins, losses, overtime_losses,
                       shootout_losses, shootout_wins, shots_against, saves, save_percentage,
                       goals_against_average, goals_against, goals, assists, penalty_minutes, stat_rank
                FROM {$tg}
                WHERE roster_id IN ({$placeholders})",
				array_merge( array( $target_id ), $source_ids )
			)
		);
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
