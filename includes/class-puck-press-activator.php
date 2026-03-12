<?php

/**
 * Fired during plugin activation
 *
 * @link       https://https://github.com/connormesec/
 * @since      1.0.0
 *
 * @package    Puck_Press
 * @subpackage Puck_Press/includes
 */

/**
 * Fired during plugin activation.
 *
 * This class defines all code necessary to run during the plugin's activation.
 *
 * @since      1.0.0
 * @package    Puck_Press
 * @subpackage Puck_Press/includes
 * @author     Connor Mesec <connormesec@gmail.com>
 */
class Puck_Press_Activator {

	public static function maybe_run_migrations(): void {
		$db_version = get_option( 'pp_db_version', '1.0' );
		if ( version_compare( $db_version, '2.0', '>=' ) ) {
			return;
		}

		require_once plugin_dir_path( __FILE__ ) . 'class-puck-press-wpdb-utils-base-abstract.php';
		require_once plugin_dir_path( __FILE__ ) . 'class-puck-press-group-aware-wpdb-utils-abstract.php';
		require_once plugin_dir_path( __FILE__ ) . 'class-puck-press-group-migration.php';
		require_once plugin_dir_path( __FILE__ ) . 'schedule/class-puck-press-schedule-wpdb-utils.php';

		$schedule_utils = new Puck_Press_Schedule_Wpdb_Utils();
		$schedule_utils->maybe_create_or_update_table( 'pp_schedules' );
		$schedule_utils->seed_default_group( 'Main Schedule' );

		Puck_Press_Group_Migration::maybe_add_group_id_column(
			array(
				'pp_schedule_data_sources',
				'pp_game_schedule_raw',
				'pp_game_schedule_mods',
				'pp_game_schedule_for_display',
			),
			'schedule_id',
			array(
				'table'   => 'pp_game_schedule_raw',
				'old_key' => 'game_id',
				'new_key' => 'schedule_game',
				'columns' => '(schedule_id, game_id)',
			)
		);

		update_option( 'pp_db_version', '2.0' );
	}

	public static function maybe_run_roster_group_migration(): void {
		$db_version = get_option( 'pp_db_version', '1.0' );
		if ( version_compare( $db_version, '3.0', '>=' ) ) {
			return;
		}

		require_once plugin_dir_path( __FILE__ ) . 'class-puck-press-wpdb-utils-base-abstract.php';
		require_once plugin_dir_path( __FILE__ ) . 'class-puck-press-group-aware-wpdb-utils-abstract.php';
		require_once plugin_dir_path( __FILE__ ) . 'class-puck-press-group-migration.php';
		require_once plugin_dir_path( __FILE__ ) . 'roster/class-puck-press-roster-wpdb-utils.php';

		$roster_utils = new Puck_Press_Roster_Wpdb_Utils();
		$roster_utils->maybe_create_or_update_table( 'pp_rosters' );
		$roster_utils->seed_default_group( 'Main Roster' );

		Puck_Press_Group_Migration::maybe_add_group_id_column(
			array(
				'pp_roster_data_sources',
				'pp_roster_raw',
				'pp_roster_mods',
				'pp_roster_for_display',
				'pp_roster_stats',
				'pp_roster_goalie_stats',
			),
			'roster_id'
		);

		update_option( 'pp_db_version', '3.0' );
	}

	public static function activate() {
		require_once plugin_dir_path( __FILE__ ) . 'class-puck-press-cron.php';
		$cron = new Puck_Press_Cron();
		$cron->schedule_cron();

		require_once plugin_dir_path( __FILE__ ) . 'class-puck-press-rewrite-manager.php';
		Puck_Press_Rewrite_Manager::add_rules();

		// Register CPTs before flushing so their rewrite rules are included.
		register_post_type(
			'pp_insta_post',
			array(
				'public'  => true,
				'rewrite' => array(
					'slug'       => 'instagram',
					'with_front' => false,
				),
			)
		);
		register_post_type(
			'pp_game_summary',
			array(
				'public'  => true,
				'rewrite' => array(
					'slug'       => 'game-recap',
					'with_front' => false,
				),
			)
		);

		flush_rewrite_rules();
	}
}
