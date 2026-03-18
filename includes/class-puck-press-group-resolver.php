<?php

class Puck_Press_Group_Resolver {

	private static array $cache = array();

	public static function resolve( string $slug, string $registry_table ): int {
		$cache_key = $registry_table . ':' . ( $slug ?: '__default__' );
		if ( isset( self::$cache[ $cache_key ] ) ) {
			return self::$cache[ $cache_key ];
		}

		global $wpdb;
		$full = $wpdb->prefix . $registry_table;

		if ( $slug === '' || $slug === 'default' ) {
			if ( $registry_table === 'pp_schedules' || $registry_table === 'pp_rosters' ) {
				$main_id = (int) ( $wpdb->get_var( "SELECT id FROM $full WHERE is_main = 1 LIMIT 1" ) ?? 1 );
			} else {
				$main_id = 1;
			}
			self::$cache[ $cache_key ] = $main_id;
			return $main_id;
		}

		$row = $wpdb->get_row(
			$wpdb->prepare( "SELECT id FROM $full WHERE slug = %s", $slug ),
			ARRAY_A
		);

		$id                        = $row ? (int) $row['id'] : 1;
		self::$cache[ $cache_key ] = $id;
		return $id;
	}
}
