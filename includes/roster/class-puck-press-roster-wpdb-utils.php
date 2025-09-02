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
            PRIMARY KEY  (id)
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

    public function insert_multiple_roster_rows( $roster_rows = [] ) {
        global $wpdb;
    
        if ( empty( $roster_rows ) || ! is_array( $roster_rows ) ) {
            return new WP_Error( 'no_data', 'No roster rows provided.' );
        }
    
        $table_name        = 'pp_roster_raw';
        $full_table_name   = $this->get_full_table_name( $table_name );
        $required_fields   = $this->get_required_fields_from_schema( $table_name );
        $valid_columns     = $this->get_column_names_from_schema( $table_name );
        $inserted_ids      = [];
        $insert_errors     = [];
        $missing_fields_all = [];
    
        foreach ( $roster_rows as $index => $row ) {
    
            $missing_fields = [];
    
            foreach ( $required_fields as $field ) {
                if ( ! array_key_exists( $field, $row ) ) {
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
            'roster_rows'     => $roster_rows
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

    public function delete_row_by_player_id($table_name, $player_id)
    {
        global $wpdb;
        $full_table = $wpdb->prefix . $table_name;

        return $wpdb->delete($full_table, ['player_id' => $player_id]);
    }
}
