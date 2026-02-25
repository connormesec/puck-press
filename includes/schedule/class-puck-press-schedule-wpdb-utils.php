<?php

/**
 * Class Puck_Press_Schedule_Admin_Wpdb_Utils
 *
 * Utility class for managing custom database tables used in the Puck Press plugin.
 *
 * This class provides methods to create, reset, and conditionally create WordPress
 * database tables using custom schemas. It is designed to handle various tables
 * involved in storing schedule data, API raw data, or other plugin-specific structures.
 *
 * Key Features:
 * - create_schedule_table(): Creates a custom table based on the provided schema.
 * - maybe_create_or_update_table(): Only creates the table if it doesn't already exist.
 * - reset_schedule_table(): Drops and recreates the table.
 *
 * Intended for internal plugin use within admin or installation workflows.
 *
 * @package Puck_Press
 */
class Puck_Press_Schedule_Wpdb_Utils extends Puck_Press_Wpdb_Utils_Base
{
    //no inline comments in this array, as it is used to create the tables in the database
    protected $table_schemas = [
        'pp_schedule_data_sources' =>  "
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            name VARCHAR(100) NOT NULL,
            type TEXT NOT NULL,
            season VARCHAR(50) DEFAULT NULL,
            source_url_or_path TEXT DEFAULT NULL,
            last_updated DATETIME DEFAULT NULL,
            status ENUM('active', 'inactive') DEFAULT 'active',
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            csv_data LONGTEXT NULL,
            other_data LONGTEXT NULL,
            PRIMARY KEY (id)
        ",
        'pp_game_schedule_raw' => "
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
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
            game_date_day VARCHAR(50) NOT NULL,
            game_time VARCHAR(50) DEFAULT NULL,
            game_timestamp DATETIME NULL DEFAULT NULL,
            home_or_away ENUM('home', 'away') NOT NULL DEFAULT 'home',
            venue VARCHAR(150) DEFAULT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY game_id (game_id)
        ",
        'pp_game_schedule_mods' => "
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            external_id VARCHAR(50) DEFAULT NULL,
	        edit_action VARCHAR(50),
	        edit_data LONGTEXT NOT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
	        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id)
        ",
        'pp_game_schedule_for_display' => "
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
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
            PRIMARY KEY (id)
        "
    ];

    public function get_active_schedule_sources()
    {
        global $wpdb;

        $table_name = 'pp_schedule_data_sources';
        $full_table_name = $this->get_full_table_name($table_name);

        $active_sources = $wpdb->get_results(
            $wpdb->prepare("SELECT * FROM $full_table_name WHERE status = %s", 'active'),
            OBJECT
        );

        if (empty($active_sources)) {
            return;
        }
        return $active_sources;
    }

    public function insert_multiple_game_schedule_rows($game_rows = [])
    {
        return $this->insert_multiple_rows(
            'pp_game_schedule_raw',
            $game_rows,
            'game_rows',
            function ($row, $field) { return !isset($row[$field]) || $row[$field] === ''; }
        );
    }

    public function delete_row_by_game_id($table_name, $game_id)
    {
        global $wpdb;
        $full_table = $wpdb->prefix . $table_name;

        return $wpdb->delete($full_table, ['game_id' => $game_id]);
    }


    function console_log($output, $with_script_tags = true)
    {
        $js_code = 'console.log(' . json_encode($output, JSON_HEX_TAG) .
            ');';
        if ($with_script_tags) {
            $js_code = '<script>' . $js_code . '</script>';
        }
        echo $js_code;
    }
}
