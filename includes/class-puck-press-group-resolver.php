<?php

class Puck_Press_Group_Resolver
{
    private static array $cache = [];

    public static function resolve(string $slug, string $registry_table): int
    {
        if ($slug === '' || $slug === 'default') return 1;

        $cache_key = $registry_table . ':' . $slug;
        if (isset(self::$cache[$cache_key])) return self::$cache[$cache_key];

        global $wpdb;
        $full = $wpdb->prefix . $registry_table;
        $row  = $wpdb->get_row(
            $wpdb->prepare("SELECT id FROM $full WHERE slug = %s", $slug),
            ARRAY_A
        );

        $id = $row ? (int) $row['id'] : 1;
        self::$cache[$cache_key] = $id;
        return $id;
    }
}
