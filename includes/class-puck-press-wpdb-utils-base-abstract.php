<?php

/**
 * Class Puck_Press_Wpdb_Utils_Base
 *
 * Base utility class for managing custom database tables in the Puck Press plugin.
 * Provides shared functionality for creating, resetting, and interacting with tables.
 *
 * @package Puck_Press
 */
abstract class Puck_Press_Wpdb_Utils_Base
{
    protected $table_schemas = [];

    public function maybe_create_or_update_table($table_name)
    {
        global $wpdb;
        $full_table_name = $this->get_full_table_name($table_name);

        $exists = $wpdb->get_var($wpdb->prepare(
            "SHOW TABLES LIKE %s",
            $full_table_name
        ));

        if ($exists !== $full_table_name) {
            $this->create_table($table_name);
            return;
        }

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        $charset_collate  = $wpdb->get_charset_collate();
        $table_definition = $this->table_schemas[$table_name];

        // Build full CREATE TABLE SQL for dbDelta
        $sql = "
		CREATE TABLE $full_table_name (
			$table_definition
		) $charset_collate;
	";

        dbDelta($sql);
    }

    public function get_table_schema($table_name)
    {
        return $this->table_schemas[$table_name] ?? null;
    }

    public function get_full_table_name($table_name)
    {
        global $wpdb;
        return $wpdb->prefix . $table_name;
    }

    public function table_exists($full_table_name)
    {
        global $wpdb;
        return $wpdb->get_var("SHOW TABLES LIKE '{$full_table_name}'") === $full_table_name;
    }

    public function create_table($table_name)
    {
        global $wpdb;
        $charset_collate = $wpdb->get_charset_collate();
        $full_table_name = $this->get_full_table_name($table_name);
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        if (!$this->table_exists($full_table_name)) {
            $sql = "CREATE TABLE $full_table_name (
                {$this->table_schemas[$table_name]}
            ) $charset_collate;";
            dbDelta($sql);
        }
    }

    public function create_all_tables()
    {
        foreach (array_keys($this->table_schemas) as $table_name) {
            $this->create_table($table_name);
        }
    }

    public function reset_table($table_name)
    {
        global $wpdb;
        $full_table_name = $this->get_full_table_name($table_name);

        $wpdb->query("DROP TABLE IF EXISTS $full_table_name");

        $this->create_table($table_name);
    }

    public function get_all_table_data($table_name, $data_struct = 'OBJECT')
    {
        global $wpdb;
        $full_table_name = $this->get_full_table_name($table_name);

        if ($data_struct === 'OBJECT') {
            return $wpdb->get_results("SELECT * FROM $full_table_name", OBJECT);
        } elseif ($data_struct === 'ARRAY_A') {
            return $wpdb->get_results("SELECT * FROM $full_table_name", ARRAY_A);
        } elseif ($data_struct === 'ARRAY_N') {
            return $wpdb->get_results("SELECT * FROM $full_table_name", ARRAY_N);
        }

        return null;
    }

    public function insert_or_replace_row($table_name, $data)
    {
        global $wpdb;

        $full_table = $this->get_full_table_name($table_name);
        $columns = $this->get_columns_from_schema($table_name);

        $filtered_data = array_filter($data, function ($key) use ($columns) {
            return in_array($key, $columns);
        }, ARRAY_FILTER_USE_KEY);

        if (empty($filtered_data)) {
            return new WP_Error('no_valid_data', 'No valid data provided for insert or replace.');
        }

        $format = $this->get_wpdb_format_from_schema($this->table_schemas[$table_name], array_keys($filtered_data));

        $result = $wpdb->replace($full_table, $filtered_data, $format);

        if ($result === false) {
            return new WP_Error('db_replace_error', 'Database replace failed.', $wpdb->last_error);
        }

        return $wpdb->insert_id;
    }

    protected function get_columns_from_schema($short_table_name)
    {
        if (!isset($this->table_schemas[$short_table_name])) {
            return [];
        }

        global $wpdb;

        $table_name = $wpdb->prefix . $short_table_name;

        $cols = $wpdb->get_col("DESC " . $table_name, 0);
        if (empty($cols)) {
            return [];
        }

        return $cols;
    }

    protected function get_wpdb_format_from_schema($schema_string, $columns)
    {
        $formats = [];

        foreach ($columns as $column) {
            if (preg_match('/\b' . preg_quote($column, '/') . '\b\s+([A-Z]+)(?:\s*\([^)]+\))?/i', $schema_string, $matches)) {
                $type = strtoupper($matches[1]);

                switch ($type) {
                    case 'INT':
                    case 'BIGINT':
                    case 'TINYINT':
                    case 'SMALLINT':
                    case 'MEDIUMINT':
                        $formats[] = '%d';
                        break;
                    case 'FLOAT':
                    case 'DOUBLE':
                    case 'DECIMAL':
                        $formats[] = '%f';
                        break;
                    default:
                        $formats[] = '%s';
                }
            } else {
                $formats[] = '%s';
            }
        }

        return $formats;
    }
}
