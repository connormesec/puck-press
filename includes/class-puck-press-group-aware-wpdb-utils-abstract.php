<?php

abstract class Puck_Press_Group_Aware_Wpdb_Utils extends Puck_Press_Wpdb_Utils_Base {

	abstract protected function get_registry_table_name(): string;
	abstract protected function get_group_id_column(): string;
	abstract protected function get_domain_tables(): array;

	public function get_all_groups(): array {
		global $wpdb;
		$table = $wpdb->prefix . $this->get_registry_table_name();
		return $wpdb->get_results( "SELECT * FROM $table ORDER BY id ASC", ARRAY_A ) ?? array();
	}

	public function get_group_by_slug( string $slug ): ?array {
		global $wpdb;
		$table = $wpdb->prefix . $this->get_registry_table_name();
		return $wpdb->get_row(
			$wpdb->prepare( "SELECT * FROM $table WHERE slug = %s", $slug ),
			ARRAY_A
		);
	}

	public function create_group( string $slug, string $name, string $description = '' ): int|false {
		global $wpdb;
		$table  = $wpdb->prefix . $this->get_registry_table_name();
		$result = $wpdb->insert(
			$table,
			array(
				'slug'        => sanitize_title( $slug ),
				'name'        => sanitize_text_field( $name ),
				'description' => sanitize_textarea_field( $description ),
				'created_at'  => current_time( 'mysql' ),
			)
		);
		return $result ? $wpdb->insert_id : false;
	}

	public function delete_group( int $group_id ): void {
		global $wpdb;
		if ( $group_id <= 1 ) {
			return;
		}

		$col = $this->get_group_id_column();

		foreach ( $this->get_domain_tables() as $table ) {
			$wpdb->delete( $wpdb->prefix . $table, array( $col => $group_id ) );
		}

		$wpdb->delete( $wpdb->prefix . $this->get_registry_table_name(), array( 'id' => $group_id ) );
	}

	public function seed_default_group( string $name = 'Main' ): void {
		global $wpdb;
		$table  = $wpdb->prefix . $this->get_registry_table_name();
		$exists = $wpdb->get_var( "SELECT id FROM $table WHERE id = 1" );
		if ( ! $exists ) {
			$wpdb->insert(
				$table,
				array(
					'id'         => 1,
					'slug'       => 'default',
					'name'       => $name,
					'created_at' => current_time( 'mysql' ),
				)
			);
		}
	}
}
