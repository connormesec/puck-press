<?php

/**
 * Class Puck_Press_Roster_Admin_Wpdb_Utils
 *
 * Utility class for managing custom database tables used in the Puck Press plugin.
 *
 * Intended for internal plugin use within admin or installation workflows.
 *
 * @package Puck_Press
 */
class Puck_Press_Roster_Wpdb_Utils extends Puck_Press_Wpdb_Utils_Base
{
    //no inline comments in this array, as it is used to create the tables in the database
    protected $table_schemas = [
        'pp_roster_data_sources' =>  "
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
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
            PRIMARY KEY (id)
        ",
        'pp_roster_raw' => "
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            source VARCHAR(100) NOT NULL,
            player_id VARCHAR(50) NOT NULL,
            headshot_link text,
            number smallint(3) NOT NULL,
            name varchar(100) NOT NULL,
            pos varchar(10),
            ht varchar(10),
            wt smallint(3),
            shoots varchar(5),
            hometown varchar(100),
            last_team varchar(100),
            year_in_school varchar(50),
            major varchar(100),
            PRIMARY KEY  (id)
        ",
        'pp_roster_mods' => "
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            external_id VARCHAR(50) DEFAULT NULL,
	        edit_action VARCHAR(50),
	        edit_data LONGTEXT NOT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
	        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id)
        ",
        'pp_roster_for_display' => "
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            source VARCHAR(100) NOT NULL,
            player_id VARCHAR(50) NOT NULL,
            headshot_link text,
            number smallint(3) NOT NULL,
            name varchar(100) NOT NULL,
            pos varchar(10),
            ht varchar(10),
            wt smallint(3),
            shoots varchar(5),
            hometown varchar(100),
            last_team varchar(100),
            year_in_school varchar(50),
            major varchar(100),
            hero_image_url text,
            PRIMARY KEY  (id)
        ",
        'pp_roster_stats' => "
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
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
            rank SMALLINT DEFAULT NULL,
            PRIMARY KEY (id)
        ",
        'pp_roster_goalie_stats' => "
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
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
            rank SMALLINT DEFAULT NULL,
            PRIMARY KEY (id)
        "
    ];

    public function get_active_roster_sources() {
        global $wpdb;

        $table_name = 'pp_roster_data_sources';
        $full_table_name = $this->get_full_table_name($table_name);

        return $wpdb->get_results(
            $wpdb->prepare("SELECT * FROM $full_table_name WHERE status = %s", 'active'),
            OBJECT
        );
    }

    public function insert_multiple_roster_rows($roster_rows = [])
    {
        return $this->insert_multiple_rows(
            'pp_roster_raw',
            $roster_rows,
            'roster_rows',
            function ($row, $field) { return !array_key_exists($field, $row); }
        );
    }

    public function delete_row_by_player_id($table_name, $player_id)
    {
        global $wpdb;
        $full_table = $wpdb->prefix . $table_name;

        return $wpdb->delete($full_table, ['player_id' => $player_id]);
    }

    public function truncate_table($table_name)
    {
        global $wpdb;
        $full_table = $wpdb->prefix . $table_name;
        $wpdb->query("TRUNCATE TABLE $full_table");
    }

    public function insert_goalie_stats_rows( $stats_rows = [] ) {
        return $this->insert_stats_rows_into( 'pp_roster_goalie_stats', $stats_rows );
    }

    public function insert_stats_rows( $stats_rows = [] ) {
        return $this->insert_stats_rows_into( 'pp_roster_stats', $stats_rows );
    }

    private function insert_stats_rows_into( string $table_name, array $stats_rows ) {
        global $wpdb;

        if ( empty( $stats_rows ) || ! is_array( $stats_rows ) ) {
            return new WP_Error( 'no_data', 'No stats rows provided.' );
        }

        $full_table_name = $this->get_full_table_name( $table_name );
        $inserted_ids    = [];
        $insert_errors   = [];

        foreach ( $stats_rows as $index => $row ) {
            $inserted = $wpdb->insert(
                $full_table_name,
                $row,
                $this->get_format_array_for_insert( $row )
            );

            if ( $inserted !== false ) {
                $inserted_ids[] = $wpdb->insert_id;
            } else {
                $insert_errors[] = [
                    'row_index' => $index,
                    'row_data'  => $row,
                    'db_error'  => $wpdb->last_error
                ];
            }
        }

        return [
            'inserted_ids'  => $inserted_ids,
            'insert_errors' => $insert_errors,
        ];
    }
}
