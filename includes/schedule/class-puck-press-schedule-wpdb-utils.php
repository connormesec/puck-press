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

    public function insert_multiple_game_schedule_rows( $game_rows = [] ) {
        global $wpdb;
        if ( empty( $game_rows ) || ! is_array( $game_rows ) ) {
            return new WP_Error( 'no_data', 'No game rows provided.' );
        }
    
        $table_name        = 'pp_game_schedule_raw';
        $full_table_name   = $this->get_full_table_name( $table_name );
        $required_fields   = $this->get_required_fields_from_schema( $table_name );
        $valid_columns     = $this->get_column_names_from_schema( $table_name );
        $inserted_ids      = [];
        $insert_errors     = [];
        $missing_fields_all = [];
    
        foreach ( $game_rows as $index => $row ) {
    
            $missing_fields = [];
    
            foreach ( $required_fields as $field ) {
                if ( ! isset( $row[ $field ] ) || $row[ $field ] === '' ) {
                    $missing_fields[] = $field;
                }
            }
    
            if ( ! empty( $missing_fields ) ) {
                $missing_fields_all = array_merge( $missing_fields_all, $missing_fields );
                continue;
            }
    
            if ( empty( $row['created_at'] ) ) {
                $row['created_at'] = current_time( 'mysql' );
            }
    
            $filtered_row = array_intersect_key( $row, array_flip( $valid_columns ) );
    
            $inserted = $wpdb->insert(
                $full_table_name,
                $filtered_row,
                $this->get_format_array_for_insert( $filtered_row )
            );
    
            if ( $inserted !== false ) {
                $inserted_ids[] = $wpdb->insert_id;
            } else {
                $insert_errors[] = [
                    'row_index' => $index,
                    'row_data'  => $filtered_row,
                    'db_error'  => $wpdb->last_error
                ];
            }
        }
    
        return [
            'inserted_ids'    => $inserted_ids,
            'missing_fields'  => array_values( array_unique( $missing_fields_all ) ),
            'insert_errors'   => $insert_errors,
            'game_rows'     => $game_rows
        ];
    }

    protected function get_required_fields_from_schema($short_table_name)
    {
        if (!isset($this->table_schemas[$short_table_name])) {
            return [];
        }

        $schema = $this->table_schemas[$short_table_name];
        $required_fields = [];

        // Split schema by lines
        $lines = explode("\n", $schema);

        foreach ($lines as $line) {
            $line = trim($line);

            // Match lines that define a column (ignore PRIMARY KEY, UNIQUE, etc.)
            if (preg_match('/^(\w+)\s+[\w\(\)]+.*NOT NULL/i', $line, $matches)) {
                $column = $matches[1];

                // Skip auto-increment columns or those with DEFAULT
                if (stripos($line, 'AUTO_INCREMENT') !== false) continue;
                if (stripos($line, 'DEFAULT') !== false) continue;

                $required_fields[] = $column;
            }
        }

        return $required_fields;
    }

    private function get_column_names_from_schema($table_name)
    {
        if (!isset($this->table_schemas[$table_name])) {
            return [];
        }

        $schema = $this->table_schemas[$table_name];
        preg_match_all('/^\s*(\w+)\s+/m', $schema, $matches);
        return $matches[1] ?? [];
    }

    protected function get_format_array_for_insert($data)
    {
        $formats = [];

        foreach ($data as $value) {
            if (is_int($value)) {
                $formats[] = '%d';
            } elseif (is_float($value)) {
                $formats[] = '%f';
            } else {
                $formats[] = '%s';
            }
        }

        return $formats;
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
