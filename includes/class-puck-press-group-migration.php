<?php

class Puck_Press_Group_Migration
{
    public static function maybe_add_group_id_column(
        array  $tables,
        string $column,
        array  $unique_key_fix = []
    ): void {
        global $wpdb;

        foreach ($tables as $table) {
            $full = $wpdb->prefix . $table;
            $col  = $wpdb->get_results("SHOW COLUMNS FROM `$full` LIKE '$column'");
            if (empty($col)) {
                $wpdb->query("ALTER TABLE `$full` ADD COLUMN $column BIGINT(20) UNSIGNED NOT NULL DEFAULT 1 AFTER id");
                $wpdb->query("ALTER TABLE `$full` ADD KEY $column ($column)");
                $wpdb->query("UPDATE `$full` SET $column = 1 WHERE $column IS NULL OR $column = 0");
            }
        }

        if (!empty($unique_key_fix)) {
            $raw     = $wpdb->prefix . $unique_key_fix['table'];
            $old_key = $unique_key_fix['old_key'];
            $new_key = $unique_key_fix['new_key'];
            $cols    = $unique_key_fix['columns'];

            $old_exists = $wpdb->get_results("SHOW INDEX FROM `$raw` WHERE Key_name = '$old_key'");
            if (!empty($old_exists)) {
                $wpdb->query("ALTER TABLE `$raw` DROP INDEX `$old_key`");
                $wpdb->query("ALTER TABLE `$raw` ADD UNIQUE KEY `$new_key` $cols");
            }
        }
    }
}
