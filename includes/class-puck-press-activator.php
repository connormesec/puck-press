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

	public static function maybe_run_teams_migration(): void {
		$db_version = get_option( 'pp_db_version', '1.0' );
		if ( version_compare( $db_version, '4.0', '>=' ) ) {
			return;
		}

		require_once plugin_dir_path( __FILE__ ) . 'class-puck-press-wpdb-utils-base-abstract.php';
		require_once plugin_dir_path( __FILE__ ) . 'teams/class-puck-press-teams-wpdb-utils.php';
		require_once plugin_dir_path( __FILE__ ) . 'schedule/class-puck-press-schedules-wpdb-utils.php';
		require_once plugin_dir_path( __FILE__ ) . 'archive/class-puck-press-archive-manager.php';

		global $wpdb;

		$legacy_table = $wpdb->prefix . 'pp_schedules_legacy';
		$old_table    = $wpdb->prefix . 'pp_schedules';

		$legacy_exists = $wpdb->get_var( "SHOW TABLES LIKE '$legacy_table'" ) === $legacy_table;
		$old_exists    = $wpdb->get_var( "SHOW TABLES LIKE '$old_table'" ) === $old_table;

		if ( ! $legacy_exists && $old_exists ) {
			$wpdb->query( "RENAME TABLE $old_table TO $legacy_table" );
		}

		$teams_utils    = new Puck_Press_Teams_Wpdb_Utils();
		$schedules_utils = new Puck_Press_Schedules_Wpdb_Utils();
		$archive_manager = new Puck_Press_Archive_Manager();

		$teams_utils->maybe_create_or_update_tables();
		$schedules_utils->maybe_create_or_update_tables();
		$archive_manager->maybe_create_or_update_tables();

		update_option( 'pp_db_version', '4.0' );
	}

	public static function wipe_and_recreate_tables(): array {
		global $wpdb;
		$log = array();

		$like    = $wpdb->prefix . 'pp_%';
		$tables  = $wpdb->get_col( $wpdb->prepare( 'SHOW TABLES LIKE %s', $like ) );

		foreach ( $tables as $table ) {
			$wpdb->query( "DROP TABLE IF EXISTS `{$table}`" );
			$log[] = "Dropped: {$table}";
		}

		require_once plugin_dir_path( __FILE__ ) . 'class-puck-press-wpdb-utils-base-abstract.php';
		require_once plugin_dir_path( __FILE__ ) . 'teams/class-puck-press-teams-wpdb-utils.php';
		require_once plugin_dir_path( __FILE__ ) . 'schedule/class-puck-press-schedules-wpdb-utils.php';
		require_once plugin_dir_path( __FILE__ ) . 'archive/class-puck-press-archive-manager.php';
		require_once plugin_dir_path( __FILE__ ) . 'class-puck-press-group-aware-wpdb-utils-abstract.php';
		require_once plugin_dir_path( __FILE__ ) . 'roster/class-puck-press-roster-wpdb-utils.php';
		require_once plugin_dir_path( __FILE__ ) . 'roster/class-puck-press-roster-registry-wpdb-utils.php';

		$teams_utils     = new Puck_Press_Teams_Wpdb_Utils();
		$schedules_utils = new Puck_Press_Schedules_Wpdb_Utils();
		$archive_manager = new Puck_Press_Archive_Manager();
		$roster_utils    = new Puck_Press_Roster_Wpdb_Utils();
		$registry_utils  = new Puck_Press_Roster_Registry_Wpdb_Utils();

		$teams_utils->maybe_create_or_update_tables();
		$log[] = 'Created: pp_teams, pp_team_sources, pp_team_games_raw, pp_team_game_mods, pp_team_games_display, pp_team_roster_sources, pp_team_players_raw, pp_team_player_mods, pp_team_players_display, pp_team_player_stats, pp_team_player_goalie_stats';

		$schedules_utils->maybe_create_or_update_tables();
		$log[] = 'Created: pp_schedules, pp_schedule_teams, pp_schedule_games_display';

		$archive_manager->maybe_create_or_update_tables();
		$log[] = 'Created: pp_archive_seasons, pp_team_games_archive';

		$roster_utils->maybe_create_or_update_table( 'pp_rosters' );
		$log[] = 'Created: pp_rosters';

		$registry_utils->maybe_create_or_update_tables();
		$log[] = 'Created: pp_roster_teams';

		require_once plugin_dir_path( __FILE__ ) . 'awards/class-puck-press-awards-wpdb-utils.php';
		$awards_utils = new Puck_Press_Awards_Wpdb_Utils();
		$awards_utils->maybe_create_or_update_tables();
		$log[] = 'Created: pp_awards, pp_award_players';

		$schedules_utils->seed_main_schedule( 'default', 'Main Schedule' );
		$log[] = 'Seeded default Main Schedule';

		$registry_utils->seed_main_roster();
		$log[] = 'Seeded default Main Roster';

		update_option( 'pp_db_version', '6.0' );
		$log[] = 'Set pp_db_version to 6.0';

		return $log;
	}

	public static function maybe_run_roster_registry_migration(): void {
		$db_version = get_option( 'pp_db_version', '1.0' );
		if ( version_compare( $db_version, '5.0', '>=' ) ) {
			return;
		}

		require_once plugin_dir_path( __FILE__ ) . 'class-puck-press-wpdb-utils-base-abstract.php';
		require_once plugin_dir_path( __FILE__ ) . 'class-puck-press-group-aware-wpdb-utils-abstract.php';
		require_once plugin_dir_path( __FILE__ ) . 'roster/class-puck-press-roster-wpdb-utils.php';
		require_once plugin_dir_path( __FILE__ ) . 'roster/class-puck-press-roster-registry-wpdb-utils.php';
		require_once plugin_dir_path( __FILE__ ) . 'teams/class-puck-press-teams-wpdb-utils.php';

		$roster_utils   = new Puck_Press_Roster_Wpdb_Utils();
		$registry_utils = new Puck_Press_Roster_Registry_Wpdb_Utils();
		$teams_utils    = new Puck_Press_Teams_Wpdb_Utils();

		$roster_utils->maybe_create_or_update_table( 'pp_rosters' );
		$registry_utils->maybe_create_or_update_tables();
		$teams_utils->maybe_create_or_update_table( 'pp_team_roster_sources' );
		$teams_utils->maybe_create_or_update_table( 'pp_team_players_raw' );
		$teams_utils->maybe_create_or_update_table( 'pp_team_player_mods' );
		$teams_utils->maybe_create_or_update_table( 'pp_team_players_display' );
		$teams_utils->maybe_create_or_update_table( 'pp_team_player_stats' );
		$teams_utils->maybe_create_or_update_table( 'pp_team_player_goalie_stats' );

		$registry_utils->seed_main_roster();

		update_option( 'pp_db_version', '5.0' );
	}

	public static function maybe_run_cleanup_migration(): void {
		$db_version = get_option( 'pp_db_version', '1.0' );
		if ( version_compare( $db_version, '6.0', '>=' ) ) {
			return;
		}

		global $wpdb;
		$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}pp_roster_player_stats" );
		$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}pp_roster_player_goalie_stats" );
		$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}pp_roster_archives" );
		$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}pp_roster_stats_archive" );
		$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}pp_roster_goalie_stats_archive" );

		require_once plugin_dir_path( __FILE__ ) . 'class-puck-press-wpdb-utils-base-abstract.php';
		require_once plugin_dir_path( __FILE__ ) . 'archive/class-puck-press-archive-manager.php';

		$archive_manager = new Puck_Press_Archive_Manager();
		$archive_manager->maybe_create_or_update_tables();

		update_option( 'pp_db_version', '6.0' );
	}

	public static function maybe_run_league_news_options_migration(): void {
		if ( get_option( 'pp_league_news_options_migrated' ) ) {
			return;
		}

		$count = get_option( 'pp_acha_news_count' );
		if ( false !== $count ) {
			update_option( 'pp_league_news_count', $count );
		}

		$category = get_option( 'pp_acha_news_category' );
		if ( false !== $category ) {
			update_option( 'pp_league_news_acha_category', $category );
		}

		$colors = get_option( 'pp_acha-news_template_colors_card' );
		if ( false !== $colors ) {
			update_option( 'pp_league-news_template_colors_card', $colors );
		}

		$template = get_option( 'pp_current_acha_news_template' );
		if ( false !== $template ) {
			update_option( 'pp_current_league_news_template', $template );
		}

		update_option( 'pp_league_news_options_migrated', true );
	}

	public static function maybe_run_promo_columns_migration(): void {
		$db_version = get_option( 'pp_db_version', '1.0' );
		if ( version_compare( $db_version, '7.0', '>=' ) ) {
			return;
		}

		require_once plugin_dir_path( __FILE__ ) . 'class-puck-press-wpdb-utils-base-abstract.php';
		require_once plugin_dir_path( __FILE__ ) . 'teams/class-puck-press-teams-wpdb-utils.php';
		require_once plugin_dir_path( __FILE__ ) . 'schedule/class-puck-press-schedules-wpdb-utils.php';

		$teams_utils     = new Puck_Press_Teams_Wpdb_Utils();
		$schedules_utils = new Puck_Press_Schedules_Wpdb_Utils();

		$teams_utils->maybe_create_or_update_table( 'pp_team_games_display' );
		$schedules_utils->maybe_create_or_update_table( 'pp_schedule_games_display' );

		update_option( 'pp_db_version', '7.0' );
	}

	public static function activate() {
		if ( ! get_option( 'pp_insta_loopback_secret' ) ) {
			update_option( 'pp_insta_loopback_secret', wp_generate_password( 32, false ) );
		}

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
