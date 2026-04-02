<?php

/**
 * The admin-specific functionality of the plugin.
 *
 * @link       https://https://github.com/connormesec/
 * @since      1.0.0
 *
 * @package    Puck_Press
 * @subpackage Puck_Press/admin
 */

/**
 * The admin-specific functionality of the plugin.
 *
 * Defines the plugin name, version, and two examples hooks for how to
 * enqueue the admin-specific stylesheet and JavaScript.
 *
 * @package    Puck_Press
 * @subpackage Puck_Press/admin
 * @author     Connor Mesec <connormesec@gmail.com>
 */
class Puck_Press_Admin {


	/**
	 * The ID of this plugin.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      string    $plugin_name    The ID of this plugin.
	 */
	private $plugin_name;

	/**
	 * The version of this plugin.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      string    $version    The current version of this plugin.
	 */
	private $version;

	/**
	 * Initialize the class and set its properties.
	 *
	 * @since    1.0.0
	 * @param      string $plugin_name       The name of this plugin.
	 * @param      string $version    The version of this plugin.
	 */
	public function __construct( $plugin_name, $version ) {
		$this->plugin_name = $plugin_name;
		$this->version     = $version;

		add_action( 'admin_menu', array( $this, 'add_admin_menu' ) );
		$this->check_for_updates();
	}

	// Add Admin Menu Page
	public function add_admin_menu() {
		add_menu_page(
			__( 'Puck Press Settings', 'puck-press' ),
			__( 'Puck Press', 'puck-press' ),
			'manage_options',
			'puck-press',
			array( $this, 'display_admin_page' ),
			'dashicons-admin-generic',
			80
		);

		add_submenu_page(
			'puck-press',
			__( 'Puck Press Settings', 'puck-press' ),
			__( 'Settings', 'puck-press' ),
			'manage_options',
			'puck-press',
			array( $this, 'display_admin_page' )
		);

		add_submenu_page(
			'puck-press',
			__( 'Instagram Posts', 'puck-press' ),
			__( 'Instagram Posts', 'puck-press' ),
			'manage_options',
			'edit.php?post_type=pp_insta_post',
			''
		);

		add_submenu_page(
			'puck-press',
			__( 'Game Recaps', 'puck-press' ),
			__( 'Game Recaps', 'puck-press' ),
			'manage_options',
			'edit.php?post_type=pp_game_summary',
			''
		);
	}

	// Display Admin Page
	public function display_admin_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		include plugin_dir_path( __DIR__ ) . 'admin/components/puck-press-admin-display.php';
	}



	public function pp_ajax_refresh_schedule_sources(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => 'Unauthorized' ) );
			wp_die();
		}

		$scope       = sanitize_text_field( $_POST['scope'] ?? 'schedule' );
		$schedule_id = (int) ( $_POST['schedule_id'] ?? 0 );

		require_once plugin_dir_path( __DIR__ ) . 'includes/teams/class-puck-press-teams-wpdb-utils.php';
		require_once plugin_dir_path( __DIR__ ) . 'includes/schedule/class-puck-press-schedules-wpdb-utils.php';
		require_once plugin_dir_path( __DIR__ ) . 'includes/schedule/class-puck-press-schedule-materializer.php';
		require_once plugin_dir_path( __DIR__ ) . 'includes/schedule/class-puck-press-team-source-importer.php';

		$team_ids = array();

		if ( $scope === 'all' ) {
			$teams_utils = new Puck_Press_Teams_Wpdb_Utils();
			foreach ( $teams_utils->get_all_teams() as $team ) {
				$team_ids[] = (int) $team['id'];
			}
		} elseif ( $schedule_id > 0 ) {
			$schedules_utils = new Puck_Press_Schedules_Wpdb_Utils();
			$schedule_obj    = $schedules_utils->get_schedule_by_id( $schedule_id );
			if ( $schedule_obj && (int) $schedule_obj['is_main'] === 1 ) {
				$teams_utils = new Puck_Press_Teams_Wpdb_Utils();
				foreach ( $teams_utils->get_all_teams() as $team ) {
					$team_ids[] = (int) $team['id'];
				}
			} else {
				$team_ids = $schedules_utils->get_schedule_team_ids( $schedule_id );
			}
		}

		$results = array();
		foreach ( $team_ids as $team_id ) {
			$importer  = new Puck_Press_Team_Source_Importer( $team_id );
			$r         = $importer->rebuild_team_and_cascade();
			$results[] = array(
				'team_id'       => $team_id,
				'success_count' => $r['success_count'] ?? 0,
				'messages'      => $r['messages'] ?? array(),
				'errors'        => $r['errors'] ?? array(),
			);
		}

		if ( ! isset( $schedules_utils ) ) {
			$schedules_utils = new Puck_Press_Schedules_Wpdb_Utils();
		}
		$debug_schedule_id = $schedule_id > 0 ? $schedule_id : $schedules_utils->get_main_schedule_id();
		$games_debug       = $schedules_utils->get_schedule_games_display( $debug_schedule_id );

		$active_team_id = (int) ( $_POST['active_team_id'] ?? 0 );
		$game_table_ui  = '';
		if ( $active_team_id > 0 ) {
			$games_card    = new Puck_Press_Teams_Admin_Games_Table_Card( array(), $active_team_id );
			$game_table_ui = $games_card->render_team_games_admin_preview();
		}

		$refresh_game_preview   = Puck_Press_Schedule_Admin_Preview_Card::create_and_init( $debug_schedule_id );
		$refresh_slider_preview = Puck_Press_Schedule_Admin_Slider_Preview_Card::create_and_init( $debug_schedule_id );

		$preview_options = array( 'schedule_id' => $debug_schedule_id );

		wp_send_json_success( array(
			'results'                       => $results,
			'refreshed_game_table_ui'       => $game_table_ui,
			'refreshed_game_preview_html'   => $refresh_game_preview->get_all_templates_html(),
			'refreshed_slider_preview_html' => $refresh_slider_preview->get_all_templates_html(),
			'refreshed_active_preview_html' => $refresh_game_preview->get_current_template_html( $preview_options ),
			'refreshed_active_slider_html'  => $refresh_slider_preview->get_current_template_html( $preview_options ),
			'games_debug'                   => $games_debug,
			'debug_schedule_id'             => $debug_schedule_id,
		) );
	}


	public function ajax_fix_roster_databases_callback() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => 'Unauthorized' ) );
		}

		global $wpdb;
		$log = array();

		// ── Step 1: Normalize collation across all active plugin tables ────────
		// WordPress upgrades can change the default collation (e.g. utf8mb4_general_ci
		// → utf8mb4_unicode_520_ci). Tables created under different defaults end up
		// with mismatched collations, breaking JOINs on VARCHAR columns.
		$charset = $wpdb->charset ?: 'utf8mb4';
		$collate = $wpdb->collate ?: 'utf8mb4_unicode_520_ci';

		$all_plugin_tables = array(
			$wpdb->prefix . 'pp_teams',
			$wpdb->prefix . 'pp_team_sources',
			$wpdb->prefix . 'pp_team_games_raw',
			$wpdb->prefix . 'pp_team_game_mods',
			$wpdb->prefix . 'pp_team_games_display',
			$wpdb->prefix . 'pp_team_roster_sources',
			$wpdb->prefix . 'pp_team_players_raw',
			$wpdb->prefix . 'pp_team_player_mods',
			$wpdb->prefix . 'pp_team_players_display',
			$wpdb->prefix . 'pp_team_player_stats',
			$wpdb->prefix . 'pp_team_player_goalie_stats',
			$wpdb->prefix . 'pp_schedules',
			$wpdb->prefix . 'pp_schedule_teams',
			$wpdb->prefix . 'pp_schedule_games_display',
			$wpdb->prefix . 'pp_rosters',
		);

		foreach ( $all_plugin_tables as $table ) {
			$table_exists = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) );
			if ( $table_exists !== $table ) {
				continue;
			}

			$current_collation = $wpdb->get_var(
				$wpdb->prepare(
					'SELECT TABLE_COLLATION FROM INFORMATION_SCHEMA.TABLES
				 WHERE TABLE_SCHEMA = %s AND TABLE_NAME = %s',
					DB_NAME,
					$table
				)
			);

			if ( $current_collation === $collate ) {
				$log[] = "$table: collation already $collate. No change.";
				continue;
			}

			$result = $wpdb->query( "ALTER TABLE `$table` CONVERT TO CHARACTER SET $charset COLLATE $collate" );
			if ( $result !== false ) {
				$log[] = "$table: collation converted from $current_collation to $collate.";
			} else {
				$log[] = "$table: ERROR converting collation — " . $wpdb->last_error;
			}
		}

		// ── Step 2: Schema sync via dbDelta ───────────────────────────────────
		require_once plugin_dir_path( __DIR__ ) . 'includes/teams/class-puck-press-teams-wpdb-utils.php';
		require_once plugin_dir_path( __DIR__ ) . 'includes/schedule/class-puck-press-schedules-wpdb-utils.php';
		require_once plugin_dir_path( __DIR__ ) . 'includes/roster/class-puck-press-roster-registry-wpdb-utils.php';

		$teams_utils    = new Puck_Press_Teams_Wpdb_Utils();
		$schedule_utils = new Puck_Press_Schedules_Wpdb_Utils();
		$registry_utils = new Puck_Press_Roster_Registry_Wpdb_Utils();
		$roster_utils   = new Puck_Press_Roster_Wpdb_Utils();

		$teams_utils->create_all_tables();
		$log[] = 'Schema sync complete: pp_team_* tables checked via dbDelta.';

		$schedule_utils->create_all_tables();
		$log[] = 'Schema sync complete: pp_schedule* tables checked via dbDelta.';

		$registry_utils->create_all_tables();
		$log[] = 'Schema sync complete: pp_roster_teams table checked via dbDelta.';

		$roster_utils->create_all_tables();
		$log[] = 'Schema sync complete: pp_rosters table checked via dbDelta.';

		wp_send_json_success(
			array(
				'message' => 'Database fix complete.',
				'log'     => $log,
			)
		);
	}

	private static function update_template_colors( $template_manager, $extra_updated = false, $extra_data_fn = null ) {
		// Check permissions
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => 'Insufficient permissions.' ) );
		}

		if ( ! isset( $_POST['template_key'], $_POST['colors'] ) || ! is_array( $_POST['colors'] ) ) {
			wp_send_json_error( array( 'message' => 'Missing or invalid parameters.' ) );
		}

		$template_key = sanitize_key( $_POST['template_key'] );

		$colors = array();
		foreach ( $_POST['colors'] as $key => $value ) {
			$color_key   = sanitize_key( $key );
			$color_value = sanitize_hex_color( $value );
			if ( $color_value ) {
				$colors[ $color_key ] = $color_value;
			}
		}

		// Fonts — optional payload alongside colors, sanitized as text (not hex).
		$fonts_updated = false;
		if ( ! empty( $_POST['fonts'] ) && is_array( $_POST['fonts'] ) ) {
			$fonts = array();
			foreach ( $_POST['fonts'] as $key => $value ) {
				$fonts[ sanitize_key( $key ) ] = sanitize_text_field( $value );
			}
			$fonts_updated = $template_manager->save_template_fonts( $template_key, $fonts );
		}

		$colors_updated       = $template_manager->save_template_colors( $template_key, $colors );
		$template_key_changed = $template_manager->set_current_template_key( $template_key );

		if ( $colors_updated || $template_key_changed || $fonts_updated || $extra_updated ) {
			$response = array(
				'message' => 'Template updated successfully.',
				'colors'  => $colors,
			);
			if ( isset( $_POST['cal_url'] ) ) {
				$sid                 = (int) ( $_POST['schedule_id'] ?? 0 );
				$response['cal_url'] = $sid > 0
					? get_option( "pp_slider_{$sid}_cal_url", '' )
					: get_option( 'pp_slider_cal_url', '' );
			}
			if ( is_callable( $extra_data_fn ) ) {
				$response = array_merge( $response, call_user_func( $extra_data_fn ) );
			}
			wp_send_json_success( $response );
		} else {
			wp_send_json_error(
				array(
					'message' => 'No changes were made to the template or colors.',
				)
			);
		}
	}

	public static function pp_ajax_update_schedule_template_colors() {
		$schedule_id = (int) ( $_POST['schedule_id'] ?? 1 );
		self::update_template_colors(
			new Puck_Press_Schedule_Template_Manager( $schedule_id ),
			false,
			function () use ( $schedule_id ) {
				$preview_card = Puck_Press_Schedule_Admin_Preview_Card::create_and_init( $schedule_id );
				$slider_card  = Puck_Press_Schedule_Admin_Slider_Preview_Card::create_and_init( $schedule_id );
				return array(
					'active_preview_html' => $preview_card->get_current_template_html(),
					'active_slider_html'  => $slider_card->get_current_template_html(),
				);
			}
		);
	}

	public static function pp_ajax_update_slider_template_colors() {
		$schedule_id = (int) ( $_POST['schedule_id'] ?? 1 );
		$url_updated = false;
		if ( isset( $_POST['cal_url'] ) ) {
			$cal_url_key = $schedule_id > 0 ? "pp_slider_{$schedule_id}_cal_url" : 'pp_slider_cal_url';
			$url_updated = update_option( $cal_url_key, esc_url_raw( wp_strip_all_tags( $_POST['cal_url'] ) ) );
		}
		self::update_template_colors(
			new Puck_Press_Slider_Template_Manager( $schedule_id ),
			$url_updated,
			function () use ( $schedule_id ) {
				$slider_card = Puck_Press_Schedule_Admin_Slider_Preview_Card::create_and_init( $schedule_id );
				return array( 'active_slider_html' => $slider_card->get_current_template_html() );
			}
		);
	}

	public static function pp_ajax_update_roster_template_colors() {
		$roster_id = isset( $_POST['roster_id'] ) ? (int) $_POST['roster_id'] : 1;
		self::update_template_colors( new Puck_Press_Roster_Template_Manager( $roster_id ) );
	}

	public static function pp_ajax_set_active_roster_id(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => 'Insufficient permissions.' ) );
		}
		$roster_id = (int) ( $_POST['roster_id'] ?? 1 );
		update_option( 'pp_admin_active_roster_id', $roster_id );
		wp_send_json_success(
			array(
				'message'   => 'Active roster updated.',
				'roster_id' => $roster_id,
			)
		);
	}

	public static function pp_ajax_set_active_stats_roster_id(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => 'Insufficient permissions.' ) );
		}
		$roster_id = (int) ( $_POST['roster_id'] ?? 0 );
		update_option( 'pp_admin_active_stats_roster_id', $roster_id );
		wp_send_json_success(
			array(
				'message'   => 'Active stats roster updated.',
				'roster_id' => $roster_id,
			)
		);
	}

	public static function pp_ajax_update_record_template_colors() {
		$schedule_id = isset( $_POST['schedule_id'] ) ? (int) $_POST['schedule_id'] : 1;
		self::update_template_colors( new Puck_Press_Record_Template_Manager( $schedule_id ) );
	}

	public static function pp_ajax_update_stats_template_colors() {
		self::update_template_colors( new Puck_Press_Stats_Template_Manager() );
	}

	public static function pp_ajax_update_awards_template_colors() {
		require_once plugin_dir_path( __DIR__ ) . 'public/templates/class-puck-press-template-manager-abstract.php';
		require_once plugin_dir_path( __DIR__ ) . 'public/templates/class-puck-press-awards-template-manager.php';
		self::update_template_colors( new Puck_Press_Awards_Template_Manager() );
	}

	public static function pp_ajax_update_post_slider_template_colors() {
		self::update_template_colors( new Puck_Press_Post_Slider_Template_Manager() );
	}

	public static function pp_ajax_update_league_news_template_colors() {
		self::update_template_colors(
			new Puck_Press_League_News_Template_Manager(),
			false,
			function () {
				return array(
					'preview_html' => Puck_Press_League_News_Admin_Preview_Card::get_current_template_html(),
				);
			}
		);
	}

	public static function pp_ajax_save_league_news_settings() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => 'Insufficient permissions.' ) );
		}
		check_ajax_referer( 'pp_league_news_nonce', 'nonce' );

		$source      = sanitize_key( $_POST['source'] ?? 'acha' );
		$category_id = (int) ( $_POST['category'] ?? 1 );
		$count       = max( 1, min( 20, (int) ( $_POST['count'] ?? 8 ) ) );

		$valid_sources = array( 'acha', 'usphl' );
		if ( ! in_array( $source, $valid_sources, true ) ) {
			$source = 'acha';
		}

		$valid_categories = array_keys( Puck_Press_League_News_Api::get_categories( $source ) );
		if ( ! in_array( $category_id, $valid_categories, true ) ) {
			$category_id = $valid_categories[0] ?? 1;
		}

		$old_source   = get_option( 'pp_league_news_source', 'acha' );
		$old_cat_key  = 'pp_league_news_' . $old_source . '_category';
		$old_category = (int) get_option( $old_cat_key, 1 );

		update_option( 'pp_league_news_source', $source );
		update_option( 'pp_league_news_' . $source . '_category', $category_id );
		update_option( 'pp_league_news_count', $count );

		// Bust old and new caches so preview refreshes immediately.
		Puck_Press_League_News_Api::bust_cache( $old_source, $old_category );
		Puck_Press_League_News_Api::bust_cache( $source, $category_id );

		wp_send_json_success( array(
			'preview_html' => Puck_Press_League_News_Admin_Preview_Card::get_current_template_html(),
		) );
	}

	public static function pp_ajax_get_league_news_preview() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => 'Insufficient permissions.' ) );
		}
		wp_send_json_success( array(
			'preview_html' => Puck_Press_League_News_Admin_Preview_Card::get_all_templates_html(),
		) );
	}

	public static function pp_ajax_update_stat_leaders_colors(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => 'Insufficient permissions.' ) );
			return;
		}

		if ( ! isset( $_POST['template_key'], $_POST['colors'] ) || ! is_array( $_POST['colors'] ) ) {
			wp_send_json_error( array( 'message' => 'Missing or invalid parameters.' ) );
			return;
		}

		// Save team colors.
		if ( ! empty( $_POST['team_colors'] ) && is_array( $_POST['team_colors'] ) ) {
			$team_colors = array();
			foreach ( $_POST['team_colors'] as $team => $hex ) {
				$safe = sanitize_hex_color( $hex );
				if ( $safe ) {
					$team_colors[ sanitize_text_field( $team ) ] = $safe;
				}
			}
			update_option( 'pp_stat_leaders_team_colors', $team_colors );
		}

		// Save more_link and show_team.
		if ( isset( $_POST['more_link'] ) ) {
			update_option( 'pp_stat_leaders_more_link', esc_url_raw( wp_strip_all_tags( wp_unslash( $_POST['more_link'] ) ) ) );
		}
		if ( isset( $_POST['show_team'] ) ) {
			update_option( 'pp_stat_leaders_show_team', ! empty( $_POST['show_team'] ) ? 1 : 0 );
		}

		// Save template colors via shared helper (sends JSON response).
		self::update_template_colors( new Puck_Press_Stat_Leaders_Template_Manager() );
	}

	public static function pp_ajax_save_stat_leaders_settings(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => 'Insufficient permissions.' ) );
			return;
		}

		check_ajax_referer( 'pp_stat_leaders_settings_nonce', 'nonce' );

		$skater_allowed = array( 'show_goals', 'show_assists', 'show_points', 'show_pim' );
		$goalie_allowed = array( 'show_gaa', 'show_saves', 'show_sv_pct', 'show_wins' );

		$skater_settings = array();
		foreach ( $skater_allowed as $key ) {
			$skater_settings[ $key ] = ! empty( $_POST[ $key ] ) ? 1 : 0;
		}
		$goalie_settings = array();
		foreach ( $goalie_allowed as $key ) {
			$goalie_settings[ $key ] = ! empty( $_POST[ $key ] ) ? 1 : 0;
		}

		update_option( 'pp_stat_leaders_skater_settings', $skater_settings );
		update_option( 'pp_stat_leaders_goalie_settings', $goalie_settings );
		update_option( 'pp_stat_leaders_show_team', ! empty( $_POST['show_team'] ) ? 1 : 0 );

		$preview_card = new Puck_Press_Stat_Leaders_Admin_Preview_Card( array( 'id' => 'stat-leaders-preview' ) );
		$preview_card->init();

		wp_send_json_success(
			array(
				'message'      => 'Settings saved.',
				'preview_html' => $preview_card->get_current_template_html(),
			)
		);
	}

	public static function pp_ajax_update_player_detail_colors(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => 'Insufficient permissions.' ) );
			return;
		}
		$colors = isset( $_POST['colors'] ) && is_array( $_POST['colors'] ) ? $_POST['colors'] : array();
		$fonts  = isset( $_POST['fonts'] ) && is_array( $_POST['fonts'] ) ? $_POST['fonts'] : array();
		require_once plugin_dir_path( __DIR__ ) . 'includes/class-puck-press-player-detail-colors.php';
		Puck_Press_Player_Detail_Colors::save( $colors, $fonts );
		wp_send_json_success( array( 'message' => 'Player page colors updated.' ) );
	}

	public static function pp_ajax_save_stats_column_settings() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => 'Insufficient permissions.' ) );
		}

		check_ajax_referer( 'pp_stats_columns_nonce', 'nonce' );

		$allowed = array(
			'show_team',
			'show_pim',
			'show_ppg',
			'show_shg',
			'show_gwg',
			'show_pts_per_game',
			'show_sh_pct',
			'show_goalie_otl',
			'show_goalie_gaa',
			'show_goalie_svpct',
			'show_goalie_sa',
			'show_goalie_saves',
		);

		$settings = array();
		foreach ( $allowed as $key ) {
			$settings[ $key ] = ! empty( $_POST[ $key ] ) ? 1 : 0;
		}

		update_option( 'pp_stats_column_settings', $settings );

		$season_label = sanitize_text_field( wp_unslash( $_POST['current_season_label'] ?? '' ) );
		update_option( 'puck_press_current_season_label', $season_label );

		$preview_card = new Puck_Press_Stats_Admin_Preview_Card();
		$preview_card->init();

		wp_send_json_success(
			array(
				'message'      => 'Column settings saved.',
				'preview_html' => $preview_card->get_current_template_html(),
			)
		);
	}

	public static function pp_ajax_get_archive_stats(): void {
		check_ajax_referer( 'pp_player_detail_nonce', 'nonce' );

		$archive_key = sanitize_text_field( wp_unslash( $_POST['archive_key'] ?? '' ) );
		if ( ! $archive_key ) {
			wp_send_json_error( array( 'message' => 'Missing archive_key.' ) );
		}

		require_once plugin_dir_path( __FILE__ ) . '../includes/stats/class-puck-press-stats-wpdb-utils.php';
		require_once plugin_dir_path( __FILE__ ) . '../includes/stats/class-puck-press-stats-render-utils.php';

		$render        = new Puck_Press_Stats_Render_Utils();
		$sections_html = $render->get_archive_sections_html( $archive_key );
		$sources       = $render->get_archive_sources( $archive_key );

		wp_send_json_success(
			array(
				'sections_html' => $sections_html,
				'sources'       => $sources,
			)
		);
	}

	/**
	 * Register the stylesheets for the admin area.
	 *
	 * @since    1.0.0
	 */
	public function enqueue_styles() {

		/**
		 * This function is provided for demonstration purposes only.
		 *
		 * An instance of this class should be passed to the run() function
		 * defined in Puck_Press_Loader as all of the hooks are defined
		 * in that particular class.
		 *
		 * The Puck_Press_Loader will then create the relationship
		 * between the defined hooks and the functions defined in this
		 * class.
		 */

		// Enqueue main admin stylesheet
		wp_enqueue_style( $this->plugin_name, plugin_dir_url( __FILE__ ) . 'css/puck-press-admin.css', array(), $this->version, 'all' );
		// Directory containing module CSS files
		$css_dir = plugin_dir_path( __FILE__ ) . 'css/modules/';

		// Get all CSS files from the modules folder
		foreach ( glob( $css_dir . '*.css' ) as $file ) {
			$file_name = basename( $file, '.css' ); // Extract file name without extension

			wp_enqueue_style(
				$this->plugin_name . '-' . $file_name, // Unique handle
				plugin_dir_url( __FILE__ ) . 'css/modules/' . basename( $file ),
				array(),
				$this->version,
				'all'
			);
		}

		wp_enqueue_style( 'select2-css', 'https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css', array(), '4.1.0' );
	}

	/**
	 * Register the JavaScript for the admin area.
	 *
	 * @since    1.0.0
	 */
	public function enqueue_scripts() {
		wp_enqueue_script( 'puck-press-data-source-utils', plugin_dir_url( __FILE__ ) . 'js/puck-press-data-source-utils.js', array( 'jquery' ), $this->version, false );
		wp_enqueue_script( 'puck-press-admin-shared', plugin_dir_url( __FILE__ ) . 'js/puck-press-admin-shared.js', array( 'jquery' ), $this->version, false );
		wp_enqueue_script( 'puck-press-admin', plugin_dir_url( __FILE__ ) . 'js/puck-press-admin.js', array( 'jquery', 'puck-press-admin-shared' ), $this->version, false );

		wp_enqueue_script( 'select2-js', 'https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js', array( 'jquery' ), '4.1.0', true );

		// Get the current tab (sanitize as needed)
		$current_tab = isset( $_GET['tab'] ) ? sanitize_key( $_GET['tab'] ) : null;
		// Tab-specific scripts
		switch ( $current_tab ) {
			case 'teams':
				wp_enqueue_script( 'puck-press-teams', plugin_dir_url( __FILE__ ) . 'js/schedule/puck-press-teams.js', array( 'jquery' ), $this->version, false );
				wp_enqueue_script( 'puck-press-add-game', plugin_dir_url( __FILE__ ) . 'js/schedule/puck-press-add-game.js', array( 'jquery', 'puck-press-admin-shared', 'select2-js' ), $this->version, false );
				wp_enqueue_script( 'puck-press-bulk-edit-schedule', plugin_dir_url( __FILE__ ) . 'js/schedule/puck-press-bulk-edit-schedule.js', array( 'jquery', 'puck-press-admin-shared' ), $this->version, false );
				break;
			case 'record':
				wp_enqueue_script( 'puck-press-color-picker-shared', plugin_dir_url( __FILE__ ) . 'js/puck-press-color-picker-shared.js', array( 'jquery' ), $this->version, false );
				wp_enqueue_script( 'puck-press-record-color-picker', plugin_dir_url( __FILE__ ) . 'js/record/puck-press-record-color-picker.js', array( 'jquery', 'select2-js', 'puck-press-color-picker-shared' ), $this->version, false );
				// Enqueue saved Google Fonts and CSS vars for record templates in head to avoid FOUT on admin preview.
				$record_tm     = new Puck_Press_Record_Template_Manager( isset( $_GET['schedule_id'] ) ? (int) $_GET['schedule_id'] : 1 );
				$font_vars_css = ':root {';
				foreach ( $record_tm->get_all_template_fonts() as $tpl_key => $font_set ) {
					foreach ( $font_set as $font_key => $font_name ) {
						if ( ! empty( $font_name ) ) {
							wp_enqueue_style(
								"pp-admin-gf-{$tpl_key}-{$font_key}",
								'https://fonts.googleapis.com/css2?family=' . urlencode( $font_name ) . ':wght@400;600;700;800&display=swap',
								array(),
								null
							);
							$safe           = str_replace( array( "'", '"', ';', '}' ), '', $font_name );
							$font_vars_css .= "--pp-{$tpl_key}-{$font_key}: '{$safe}', sans-serif;";
						}
					}
				}
				$font_vars_css .= '}';
				if ( $font_vars_css !== ':root {}' ) {
					wp_add_inline_style( 'puck-press', $font_vars_css );
				}
				break;
			case 'stats':
				wp_register_script( 'pp-player-detail', plugin_dir_url( __DIR__ ) . 'public/js/pp-player-detail.js', array( 'jquery' ), $this->version, true );
				wp_enqueue_script( 'puck-press-color-picker-shared', plugin_dir_url( __FILE__ ) . 'js/puck-press-color-picker-shared.js', array( 'jquery' ), $this->version, false );
				wp_enqueue_script( 'puck-press-stats-color-picker', plugin_dir_url( __FILE__ ) . 'js/stats/puck-press-stats-color-picker.js', array( 'jquery', 'select2-js', 'puck-press-color-picker-shared' ), $this->version, false );
				wp_enqueue_script( 'puck-press-stat-leaders-color-picker', plugin_dir_url( __FILE__ ) . 'js/stats/puck-press-stat-leaders-color-picker.js', array( 'jquery', 'select2-js', 'puck-press-color-picker-shared' ), $this->version, false );
				// Enqueue saved Google Fonts and CSS vars for stats templates in head to avoid FOUT on admin preview.
				$stats_tm            = new Puck_Press_Stats_Template_Manager();
				$stats_font_vars_css = ':root {';
				foreach ( $stats_tm->get_all_template_fonts() as $tpl_key => $font_set ) {
					foreach ( $font_set as $font_key => $font_name ) {
						if ( ! empty( $font_name ) ) {
							wp_enqueue_style(
								"pp-admin-gf-{$tpl_key}-{$font_key}",
								'https://fonts.googleapis.com/css2?family=' . urlencode( $font_name ) . ':wght@400;600;700;800&display=swap',
								array(),
								null
							);
							$safe                 = str_replace( array( "'", '"', ';', '}' ), '', $font_name );
							$stats_font_vars_css .= "--pp-{$tpl_key}-{$font_key}: '{$safe}', sans-serif;";
						}
					}
				}
				$stats_font_vars_css .= '}';
				if ( $stats_font_vars_css !== ':root {}' ) {
					wp_add_inline_style( 'puck-press', $stats_font_vars_css );
				}
				$sl_tm            = new Puck_Press_Stat_Leaders_Template_Manager();
				$sl_font_vars_css = ':root {';
				foreach ( $sl_tm->get_all_template_fonts() as $tpl_key => $font_set ) {
					foreach ( $font_set as $font_key => $font_name ) {
						if ( ! empty( $font_name ) ) {
							wp_enqueue_style(
								"pp-admin-gf-{$tpl_key}-{$font_key}",
								'https://fonts.googleapis.com/css2?family=' . urlencode( $font_name ) . ':wght@400;600;700;800&display=swap',
								array(),
								null
							);
							$safe              = str_replace( array( "'", '"', ';', '}' ), '', $font_name );
							$sl_font_vars_css .= "--pp-{$tpl_key}-{$font_key}: '{$safe}', sans-serif;";
						}
					}
				}
				$sl_font_vars_css .= '}';
				if ( $sl_font_vars_css !== ':root {}' ) {
					wp_add_inline_style( 'puck-press', $sl_font_vars_css );
				}
				wp_add_inline_script(
					'puck-press-stats-color-picker',
					'jQuery(document).ready(function($){$("#pp-stats-roster-selector").on("change",function(){var id=parseInt($(this).val(),10);$.post(ajaxurl,{action:"pp_set_active_stats_roster_id",roster_id:id});});});'
				);
				break;
			case 'roster':
				wp_register_script( 'pp-player-detail', plugin_dir_url( __DIR__ ) . 'public/js/pp-player-detail.js', array( 'jquery' ), $this->version, true );
				wp_enqueue_media();
				( new Puck_Press_Roster_Registry_Wpdb_Utils() )->maybe_create_or_update_tables();
				wp_enqueue_script( 'puck-press-roster-sources', plugin_dir_url( __FILE__ ) . 'js/roster/puck-press-roster-sources.js', array( 'jquery', 'select2-js', 'puck-press-admin-shared' ), $this->version, false );
				wp_enqueue_script( 'puck-press-roster-edits', plugin_dir_url( __FILE__ ) . 'js/roster/puck-press-roster-edits.js', array( 'jquery', 'puck-press-admin-shared' ), $this->version, false );
				wp_enqueue_script( 'puck-press-add-player', plugin_dir_url( __FILE__ ) . 'js/roster/puck-press-add-player.js', array( 'jquery', 'puck-press-roster-edits' ), $this->version, false );
				wp_enqueue_script( 'puck-press-color-picker-shared', plugin_dir_url( __FILE__ ) . 'js/puck-press-color-picker-shared.js', array( 'jquery' ), $this->version, false );
				wp_enqueue_script( 'puck-press-roster-color-picker', plugin_dir_url( __FILE__ ) . 'js/roster/puck-press-roster-color-picker.js', array( 'jquery', 'select2-js', 'puck-press-color-picker-shared' ), $this->version, false );
				wp_enqueue_script( 'puck-press-roster-preview', plugin_dir_url( __FILE__ ) . 'js/roster/puck-press-roster-preview.js', array( 'jquery' ), $this->version, false );
				wp_enqueue_script( 'puck-press-roster-archive', plugin_dir_url( __FILE__ ) . 'js/roster/puck-press-roster-archive.js', array( 'jquery', 'puck-press-admin-shared' ), $this->version, false );
				wp_enqueue_script( 'puck-press-bulk-edit-roster', plugin_dir_url( __FILE__ ) . 'js/roster/puck-press-bulk-edit-roster.js', array( 'jquery', 'puck-press-admin-shared' ), $this->version, false );
			wp_enqueue_script( 'puck-press-roster-teams', plugin_dir_url( __FILE__ ) . 'js/roster/puck-press-roster-teams.js', array( 'jquery' ), $this->version, false );
				break;
			case 'player-page':
				wp_enqueue_script( 'puck-press-color-picker-shared', plugin_dir_url( __FILE__ ) . 'js/puck-press-color-picker-shared.js', array( 'jquery' ), $this->version, false );
				wp_enqueue_script( 'puck-press-player-page-admin', plugin_dir_url( __FILE__ ) . 'js/player-page/puck-press-player-page-admin.js', array( 'jquery', 'select2-js', 'puck-press-color-picker-shared' ), filemtime( plugin_dir_path( __FILE__ ) . 'js/player-page/puck-press-player-page-admin.js' ), true );
				break;
			case 'game-summary':
				wp_enqueue_script( 'pp-game-summary-admin', plugin_dir_url( __FILE__ ) . 'js/game-summary/puck-press-game-summary-admin.js', array( 'jquery' ), '1.0', true );
				break;
			case 'insta-post':
				wp_enqueue_script( 'pp-insta-post-admin', plugin_dir_url( __FILE__ ) . 'js/insta-post/puck-press-insta-post-admin.js', array( 'jquery' ), '1.0', true );
				break;
			case 'post-slider':
				wp_enqueue_script( 'puck-press-color-picker-shared', plugin_dir_url( __FILE__ ) . 'js/puck-press-color-picker-shared.js', array( 'jquery' ), $this->version, false );
				wp_enqueue_script( 'puck-press-post-slider-color-picker', plugin_dir_url( __FILE__ ) . 'js/post-slider/puck-press-post-slider-color-picker.js', array( 'jquery', 'select2-js', 'puck-press-color-picker-shared' ), $this->version, false );
				break;
			case 'league-news':
				wp_enqueue_script( 'puck-press-color-picker-shared', plugin_dir_url( __FILE__ ) . 'js/puck-press-color-picker-shared.js', array( 'jquery' ), $this->version, false );
				wp_enqueue_script( 'puck-press-league-news-admin', plugin_dir_url( __FILE__ ) . 'js/league-news/puck-press-league-news-admin.js', array( 'jquery' ), $this->version, true );
				wp_enqueue_script( 'puck-press-league-news-color-picker', plugin_dir_url( __FILE__ ) . 'js/league-news/puck-press-league-news-color-picker.js', array( 'jquery', 'select2-js', 'puck-press-color-picker-shared' ), $this->version, false );
				break;
			case 'upcoming_games_table':
				// wp_enqueue_script('pp-admin-upcoming', plugin_dir_url(__FILE__) . 'admin/js/upcoming-games.js', ['jquery'], null, true);
				break;
			case 'awards':
				wp_enqueue_media();
				wp_enqueue_script( 'puck-press-awards-admin', plugin_dir_url( __FILE__ ) . 'js/awards/puck-press-awards-admin.js', array( 'jquery', 'puck-press-admin-shared', 'select2-js' ), $this->version, false );
				wp_enqueue_script( 'puck-press-awards-add-player', plugin_dir_url( __FILE__ ) . 'js/awards/puck-press-awards-add-player.js', array( 'jquery', 'select2-js' ), $this->version, false );
				wp_enqueue_script( 'puck-press-color-picker-shared', plugin_dir_url( __FILE__ ) . 'js/puck-press-color-picker-shared.js', array( 'jquery' ), $this->version, false );
				wp_enqueue_script( 'puck-press-awards-color-picker', plugin_dir_url( __FILE__ ) . 'js/awards/puck-press-awards-color-picker.js', array( 'jquery', 'select2-js', 'puck-press-color-picker-shared' ), $this->version, false );
				break;
			default: // Schedule tab (null or unspecified)
				wp_enqueue_script( 'puck-press-add-game', plugin_dir_url( __FILE__ ) . 'js/schedule/puck-press-add-game.js', array( 'jquery', 'puck-press-admin-shared', 'select2-js' ), $this->version, false );
				wp_enqueue_script( 'puck-press-color-picker-shared', plugin_dir_url( __FILE__ ) . 'js/puck-press-color-picker-shared.js', array( 'jquery' ), $this->version, false );
				wp_enqueue_script( 'puck-press-color-picker', plugin_dir_url( __FILE__ ) . 'js/schedule/puck-press-schedule-color-picker.js', array( 'jquery', 'puck-press-color-picker-shared' ), $this->version, false );
				wp_enqueue_script( 'puck-press-slider-color-picker', plugin_dir_url( __FILE__ ) . 'js/schedule/puck-press-schedule-slider-color-picker.js', array( 'jquery', 'puck-press-color-picker-shared' ), $this->version, false );
				wp_enqueue_script( 'puck-press-schedule-preview', plugin_dir_url( __FILE__ ) . 'js/schedule/puck-press-schedule-preview.js', array( 'jquery' ), $this->version, false );
				wp_enqueue_script( 'puck-press-schedule-archive', plugin_dir_url( __FILE__ ) . 'js/schedule/puck-press-schedule-archive.js', array( 'jquery', 'puck-press-admin-shared' ), $this->version, false );
				wp_enqueue_script( 'puck-press-bulk-edit-schedule', plugin_dir_url( __FILE__ ) . 'js/schedule/puck-press-bulk-edit-schedule.js', array( 'jquery', 'puck-press-admin-shared' ), $this->version, false );
				wp_enqueue_script( 'puck-press-teams', plugin_dir_url( __FILE__ ) . 'js/schedule/puck-press-teams.js', array( 'jquery' ), $this->version, false );
				break;
		}
	}

	public function localize_scripts() {
		$active_schedule_id   = (int) get_option( 'pp_admin_active_new_schedule_id', 1 );
		$template_manager  = new Puck_Press_Schedule_Template_Manager( $active_schedule_id );
		$scheduleTemplates = $template_manager->get_all_template_colors();
		$selected_template = $template_manager->get_current_template_key();
		$templates         = array(
			'scheduleTemplates' => $scheduleTemplates,
			'colorLabels'       => $template_manager->get_all_template_color_labels(),
			'fontSettings'      => $template_manager->get_all_template_fonts(),
			'fontLabels'        => $template_manager->get_all_template_font_labels(),
			'selected_template' => $selected_template,
		);
		wp_localize_script( 'puck-press-color-picker', 'ppScheduleTemplates', $templates );

		$slider_template_manager  = new Puck_Press_Slider_Template_Manager( $active_schedule_id );
		$sliderTemplates          = $slider_template_manager->get_all_template_colors();
		$selected_slider_template = $slider_template_manager->get_current_template_key();
		$templates                = array(
			'sliderTemplates'   => $sliderTemplates,
			'selected_template' => $selected_slider_template,
			'cal_url'           => get_option( "pp_slider_{$active_schedule_id}_cal_url", get_option( 'pp_slider_cal_url', '' ) ),
		);
		wp_localize_script( 'puck-press-slider-color-picker', 'ppSliderTemplates', $templates );

		$active_roster_id         = (int) get_option( 'pp_admin_active_new_roster_id', 1 );

		$roster_template_manager  = new Puck_Press_Roster_Template_Manager( $active_roster_id );
		$rosterTemplates          = $roster_template_manager->get_all_template_colors();
		$selected_roster_template = $roster_template_manager->get_current_template_key();
		$roster_templates         = array(
			'rosterTemplates'   => $rosterTemplates,
			'colorLabels'       => $roster_template_manager->get_all_template_color_labels(),
			'fontSettings'      => $roster_template_manager->get_all_template_fonts(),
			'fontLabels'        => $roster_template_manager->get_all_template_font_labels(),
			'selected_template' => $selected_roster_template,
		);
		wp_localize_script( 'puck-press-roster-color-picker', 'ppRosterTemplates', $roster_templates );
		wp_localize_script(
			'puck-press-roster-color-picker',
			'ppRosterAdmin',
			array(
				'activeRosterId' => $active_roster_id,
				'ajaxUrl'        => admin_url( 'admin-ajax.php' ),
			)
		);

		require_once plugin_dir_path( __DIR__ ) . 'includes/class-puck-press-player-detail-colors.php';
		global $wpdb;
		$pp_players_raw  = $wpdb->get_results( "SELECT name FROM {$wpdb->prefix}pp_team_players_display ORDER BY name ASC", ARRAY_A );
		$pp_players_list = array();
		foreach ( $pp_players_raw as $pp_p ) {
			$pp_players_list[] = array(
				'name' => $pp_p['name'],
				'url'  => home_url( '/player/' . sanitize_title( $pp_p['name'] ) ),
			);
		}
		wp_localize_script(
			'puck-press-player-page-admin',
			'ppPlayerPageAdmin',
			array(
				'colors'      => Puck_Press_Player_Detail_Colors::get_colors(),
				'colorLabels' => Puck_Press_Player_Detail_Colors::get_color_labels(),
				'font'        => Puck_Press_Player_Detail_Colors::get_fonts()['player-font'] ?? '',
				'fontLabel'   => 'Player Page Font',
				'players'     => $pp_players_list,
				'ajaxUrl'     => admin_url( 'admin-ajax.php' ),
			)
		);

		$record_schedule_id       = isset( $_GET['schedule_id'] ) ? (int) $_GET['schedule_id'] : 1;
		$record_template_manager  = new Puck_Press_Record_Template_Manager( $record_schedule_id );
		$selected_record_template = $record_template_manager->get_current_template_key();
		$record_templates         = array(
			'recordTemplates'   => $record_template_manager->get_all_template_colors(),
			'colorLabels'       => $record_template_manager->get_all_template_color_labels(),
			'fontSettings'      => $record_template_manager->get_all_template_fonts(),
			'fontLabels'        => $record_template_manager->get_all_template_font_labels(),
			'selected_template' => $selected_record_template,
		);
		wp_localize_script( 'puck-press-record-color-picker', 'ppRecordTemplates', $record_templates );
		wp_localize_script(
			'puck-press-record-color-picker',
			'ppRecordAdmin',
			array(
				'scheduleId' => $record_schedule_id,
			)
		);

		$stats_template_manager  = new Puck_Press_Stats_Template_Manager();
		$selected_stats_template = $stats_template_manager->get_current_template_key();
		$stats_templates_data    = array(
			'statsTemplates'    => $stats_template_manager->get_all_template_colors(),
			'colorLabels'       => $stats_template_manager->get_all_template_color_labels(),
			'fontSettings'      => $stats_template_manager->get_all_template_fonts(),
			'fontLabels'        => $stats_template_manager->get_all_template_font_labels(),
			'selected_template' => $selected_stats_template,
			'columns_nonce'     => wp_create_nonce( 'pp_stats_columns_nonce' ),
		);
		wp_localize_script( 'puck-press-stats-color-picker', 'ppStatsTemplates', $stats_templates_data );

		$sl_template_manager  = new Puck_Press_Stat_Leaders_Template_Manager();
		$selected_sl_template = $sl_template_manager->get_current_template_key();
		$sl_team_colors       = get_option( 'pp_stat_leaders_team_colors', array() );
		$sl_skater_settings   = get_option( 'pp_stat_leaders_skater_settings', Puck_Press_Stat_Leaders_Wpdb_Utils::get_default_skater_settings() );
		$sl_goalie_settings   = get_option( 'pp_stat_leaders_goalie_settings', Puck_Press_Stat_Leaders_Wpdb_Utils::get_default_goalie_settings() );
		$sl_team_names        = Puck_Press_Stat_Leaders_Wpdb_Utils::get_all_team_names();
		$sl_templates_data    = array(
			'leadersTemplates'  => $sl_template_manager->get_all_template_colors(),
			'colorLabels'       => $sl_template_manager->get_all_template_color_labels(),
			'fontSettings'      => $sl_template_manager->get_all_template_fonts(),
			'fontLabels'        => $sl_template_manager->get_all_template_font_labels(),
			'selected_template' => $selected_sl_template,
			'team_colors'       => $sl_team_colors,
			'team_names'        => $sl_team_names,
			'skater_settings'   => $sl_skater_settings,
			'goalie_settings'   => $sl_goalie_settings,
			'settings_nonce'    => wp_create_nonce( 'pp_stat_leaders_settings_nonce' ),
			'more_link'         => get_option( 'pp_stat_leaders_more_link', '' ),
			'show_team'         => get_option( 'pp_stat_leaders_show_team', '1' ),
		);
		wp_localize_script( 'puck-press-stat-leaders-color-picker', 'ppStatLeadersData', $sl_templates_data );

		wp_localize_script(
			'pp-game-summary-admin',
			'ppGameSummary',
			array(
				'ajax_url' => admin_url( 'admin-ajax.php' ),
				'nonce'    => wp_create_nonce( 'pp_game_summary_nonce' ),
			)
		);

		wp_localize_script(
			'pp-insta-post-admin',
			'ppInstaPost',
			array(
				'ajax_url' => admin_url( 'admin-ajax.php' ),
				'nonce'    => wp_create_nonce( 'pp_insta_post_nonce' ),
			)
		);

		$ps_template_manager  = new Puck_Press_Post_Slider_Template_Manager();
		$ps_selected_template = $ps_template_manager->get_current_template_key();
		$ps_templates_data    = array(
			'postSliderTemplates' => $ps_template_manager->get_all_template_colors(),
			'colorLabels'         => $ps_template_manager->get_all_template_color_labels(),
			'selected_template'   => $ps_selected_template,
		);
		wp_localize_script( 'puck-press-post-slider-color-picker', 'ppPostSliderData', $ps_templates_data );

		$ln_template_manager  = new Puck_Press_League_News_Template_Manager();
		$ln_selected_template = $ln_template_manager->get_current_template_key();
		$ln_templates_data    = array(
			'leagueNewsTemplates' => $ln_template_manager->get_all_template_colors(),
			'colorLabels'         => $ln_template_manager->get_all_template_color_labels(),
			'selected_template'   => $ln_selected_template,
		);
		wp_localize_script( 'puck-press-league-news-color-picker', 'ppLeagueNewsTemplates', $ln_templates_data );
		wp_localize_script(
			'puck-press-league-news-admin',
			'ppLeagueNewsData',
			array(
				'ajax_url'   => admin_url( 'admin-ajax.php' ),
				'nonce'      => wp_create_nonce( 'pp_league_news_nonce' ),
				'categories' => array(
					'acha'  => Puck_Press_League_News_Api::get_categories( 'acha' ),
					'usphl' => Puck_Press_League_News_Api::get_categories( 'usphl' ),
				),
			)
		);

		wp_localize_script(
			'puck-press-bulk-edit-schedule',
			'ppBulkSchedule',
			array(
				'nonce' => wp_create_nonce( 'pp_bulk_schedule_nonce' ),
			)
		);

		wp_localize_script(
			'puck-press-bulk-edit-roster',
			'ppBulkRoster',
			array(
				'nonce' => wp_create_nonce( 'pp_bulk_roster_nonce' ),
			)
		);

		wp_localize_script(
			'puck-press-teams',
			'ppTeamPlayers',
			array(
				'nonce' => wp_create_nonce( 'pp_team_players_nonce' ),
			)
		);

		wp_localize_script(
			'puck-press-awards-admin',
			'ppAwardsAdmin',
			array(
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'nonce'   => wp_create_nonce( 'pp_awards_nonce' ),
			)
		);

		require_once plugin_dir_path( __DIR__ ) . 'public/templates/class-puck-press-template-manager-abstract.php';
		require_once plugin_dir_path( __DIR__ ) . 'public/templates/class-puck-press-awards-template-manager.php';
		$awards_tm            = new Puck_Press_Awards_Template_Manager();
		$awards_templates_data = array(
			'awardsTemplates'   => $awards_tm->get_all_template_colors(),
			'colorLabels'       => $awards_tm->get_all_template_color_labels(),
			'fontSettings'      => $awards_tm->get_all_template_fonts(),
			'fontLabels'        => $awards_tm->get_all_template_font_labels(),
			'selected_template' => $awards_tm->get_current_template_key(),
		);
		wp_localize_script( 'puck-press-awards-color-picker', 'ppAwardsTemplates', $awards_templates_data );
	}

	/**
	 * The code that runs to check for updates
	 * This action is documented in includes/update.php
	 */
	function check_for_updates() {
		/**
		 * The class responsible for updating the plugin
		 */
		$updater = new PDUpdater( PLUGIN_DIR_PATH . '/puck-press.php' );
		$updater->set_username( 'connormesec' );
		$updater->set_repository( 'puck-press' );

		$updater->initialize();
	}




	public static function pp_ajax_create_team(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => 'Insufficient permissions.' ) );
		}

		$name = sanitize_text_field( $_POST['name'] ?? '' );
		$slug = sanitize_title( $_POST['slug'] ?? $name );

		if ( empty( $name ) || empty( $slug ) ) {
			wp_send_json_error( array( 'message' => 'Name and slug are required.' ) );
		}

		$utils = new Puck_Press_Teams_Wpdb_Utils();
		$id    = $utils->create_team( $slug, $name );

		if ( ! $id ) {
			wp_send_json_error( array( 'message' => 'Failed to create team. Slug may already exist.' ) );
		}

		$schedules_utils = new Puck_Press_Schedules_Wpdb_Utils();
		$schedule_id     = self::auto_create_schedule_for_team( $schedules_utils, $slug, $name, $id );

		$roster_registry = new Puck_Press_Roster_Registry_Wpdb_Utils();
		$roster_id       = self::auto_create_roster_for_team( $roster_registry, $slug, $name, $id );

		update_option( 'pp_admin_active_team_id', $id );

		$data_sources_card  = new Puck_Press_Teams_Admin_Data_Sources_Card(
			array(
				'title'    => 'Data Sources',
				'subtitle' => 'Manage external data sources for games',
				'id'       => 'data-sources-table',
			),
			$id
		);
		$games_card         = new Puck_Press_Teams_Admin_Games_Table_Card(
			array(
				'title'    => 'Games',
				'subtitle' => '0 games scheduled',
				'id'       => 'team-game-list',
			),
			$id
		);
		$roster_sources_card = new Puck_Press_Teams_Admin_Roster_Sources_Card( $id );
		$players_card        = new Puck_Press_Teams_Admin_Players_Table_Card( $id );

		wp_send_json_success(
			array(
				'message'             => "Team '{$name}' created.",
				'id'                  => $id,
				'slug'                => $slug,
				'name'                => $name,
				'schedule_id'         => $schedule_id,
				'roster_id'           => $roster_id,
				'data_sources_html'   => $data_sources_card->render(),
				'games_html'          => $games_card->render(),
				'roster_sources_html' => $roster_sources_card->render(),
				'players_html'        => $players_card->render(),
				'pages_card_html'     => ( new Puck_Press_Teams_Admin_Pages_Card( $id ) )->render(),
			)
		);
	}

	private static function auto_create_schedule_for_team( Puck_Press_Schedules_Wpdb_Utils $utils, string $team_slug, string $team_name, int $team_id ): ?int {
		$slug = $team_slug . '-schedule';

		if ( $utils->slug_exists( $slug ) ) {
			error_log( "[PuckPress] auto_create_schedule_for_team: slug '{$slug}' already exists, skipping." );
			return null;
		}

		$schedule_id = $utils->create_schedule( $slug, $team_name . ' Schedule', false );
		if ( ! $schedule_id ) {
			error_log( "[PuckPress] auto_create_schedule_for_team: create_schedule failed for slug '{$slug}'." );
			return null;
		}

		$utils->add_team_to_schedule( $schedule_id, $team_id );
		return $schedule_id;
	}

	private static function auto_create_roster_for_team( Puck_Press_Roster_Registry_Wpdb_Utils $registry, string $team_slug, string $team_name, int $team_id ): ?int {
		$slug   = $team_slug . '-roster';
		$result = $registry->create_roster( $team_name . ' Roster', $slug );

		if ( $result === 'duplicate_slug' ) {
			error_log( "[PuckPress] auto_create_roster_for_team: slug '{$slug}' already exists, skipping." );
			return null;
		}

		if ( ! $result ) {
			error_log( "[PuckPress] auto_create_roster_for_team: create_roster failed for slug '{$slug}'." );
			return null;
		}

		$roster_id = (int) $result;
		$registry->add_team_to_roster( $roster_id, $team_id );
		return $roster_id;
	}

	public static function pp_ajax_update_team(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => 'Insufficient permissions.' ) );
		}

		$team_id = (int) ( $_POST['team_id'] ?? 0 );
		$name    = sanitize_text_field( $_POST['name'] ?? '' );
		$slug    = sanitize_title( $_POST['slug'] ?? '' );

		if ( ! $team_id || empty( $name ) || empty( $slug ) ) {
			wp_send_json_error( array( 'message' => 'Team ID, name, and slug are required.' ) );
		}

		global $wpdb;

		$existing = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT id FROM {$wpdb->prefix}pp_teams WHERE slug = %s AND id != %d LIMIT 1",
				$slug,
				$team_id
			)
		);
		if ( $existing ) {
			wp_send_json_error( array( 'message' => 'That slug is already used by another team.' ) );
		}

		$wpdb->update(
			$wpdb->prefix . 'pp_teams',
			array( 'name' => $name, 'slug' => $slug ),
			array( 'id' => $team_id ),
			array( '%s', '%s' ),
			array( '%d' )
		);

		wp_send_json_success( array( 'name' => $name, 'slug' => $slug ) );
	}

	public static function pp_ajax_delete_team(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => 'Insufficient permissions.' ) );
		}

		$team_id = (int) ( $_POST['team_id'] ?? 0 );
		if ( ! $team_id ) {
			wp_send_json_error( array( 'message' => 'Invalid team ID.' ) );
		}

		$utils   = new Puck_Press_Teams_Wpdb_Utils();
		$deleted = $utils->delete_team( $team_id );

		if ( ! $deleted ) {
			wp_send_json_error( array( 'message' => 'Failed to delete team.' ) );
		}

		( new Puck_Press_Divi_Page_Builder() )->delete_all( $team_id );

		if ( (int) get_option( 'pp_admin_active_team_id', 0 ) === $team_id ) {
			$teams    = $utils->get_all_teams();
			$first_id = ! empty( $teams ) ? (int) $teams[0]['id'] : 0;
			update_option( 'pp_admin_active_team_id', $first_id );
		}

		wp_send_json_success( array( 'message' => 'Team deleted.' ) );
	}

	public static function pp_ajax_set_active_team_id(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => 'Insufficient permissions.' ) );
		}
		$team_id = (int) ( $_POST['team_id'] ?? 0 );
		update_option( 'pp_admin_active_team_id', $team_id );
		wp_send_json_success(
			array(
				'message' => 'Active team updated.',
				'team_id' => $team_id,
			)
		);
	}

	public static function pp_ajax_switch_active_team(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => 'Insufficient permissions.' ) );
		}
		$team_id = (int) ( $_POST['team_id'] ?? 0 );
		if ( ! $team_id ) {
			wp_send_json_error( array( 'message' => 'Invalid team ID.' ) );
		}
		update_option( 'pp_admin_active_team_id', $team_id );

		$data_sources_card  = new Puck_Press_Teams_Admin_Data_Sources_Card(
			array(
				'title'    => 'Data Sources',
				'subtitle' => 'Manage external data sources for games',
				'id'       => 'data-sources-table',
			),
			$team_id
		);
		$games_card         = new Puck_Press_Teams_Admin_Games_Table_Card(
			array(
				'title'    => 'Games',
				'subtitle' => '0 games scheduled',
				'id'       => 'team-game-list',
			),
			$team_id
		);
		$roster_sources_card = new Puck_Press_Teams_Admin_Roster_Sources_Card( $team_id );
		$players_card        = new Puck_Press_Teams_Admin_Players_Table_Card( $team_id );

		wp_send_json_success(
			array(
				'team_id'              => $team_id,
				'data_sources_html'    => $data_sources_card->render(),
				'games_html'           => $games_card->render(),
				'roster_sources_html'  => $roster_sources_card->render(),
				'players_html'         => $players_card->render(),
				'pages_card_html'      => ( new Puck_Press_Teams_Admin_Pages_Card( $team_id ) )->render(),
			)
		);
	}

	public static function pp_ajax_generate_team_pages(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => 'Insufficient permissions.' ) );
		}

		$team_id = (int) ( $_POST['team_id'] ?? 0 );
		if ( ! $team_id ) {
			wp_send_json_error( array( 'message' => 'Invalid team ID.' ) );
		}

		if ( ! class_exists( 'ET_Builder_Module' ) ) {
			wp_send_json_error( array( 'message' => 'Divi is not active. Please activate the Divi theme before generating pages.' ) );
		}

		$max_width          = sanitize_text_field( $_POST['max_width'] ?? '1080px' );
		$padding            = sanitize_text_field( $_POST['padding'] ?? '10px 0px' );
		$header_color       = sanitize_hex_color( $_POST['header_color'] ?? '' ) ?: '';
		$header_font_size   = sanitize_text_field( $_POST['header_font_size'] ?? '1.4rem' );
		$header_font        = sanitize_text_field( $_POST['header_font'] ?? '' );
		$header_text_color  = sanitize_hex_color( $_POST['header_text_color'] ?? '#ffffff' ) ?: '#ffffff';
		$school_url         = esc_url_raw( $_POST['school_url'] ?? '' );

		Puck_Press_Divi_Page_Builder::save_defaults( $max_width, $padding, $header_color, $header_font_size, $header_font, $header_text_color, $school_url );
		Puck_Press_Divi_Page_Builder::save_team_settings( $team_id, $max_width, $padding, $header_color, $header_font_size, $header_font, $header_text_color, $school_url );

		$builder = new Puck_Press_Divi_Page_Builder();
		$result  = $builder->generate_all( $team_id, $max_width, $padding, $header_color, $header_font_size, $header_font, $header_text_color, $school_url );

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ) );
		}

		wp_send_json_success(
			array(
				'pages_card_html' => ( new Puck_Press_Teams_Admin_Pages_Card( $team_id ) )->render(),
			)
		);
	}

	public static function pp_ajax_delete_team_pages(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => 'Insufficient permissions.' ) );
		}

		$team_id = (int) ( $_POST['team_id'] ?? 0 );
		if ( ! $team_id ) {
			wp_send_json_error( array( 'message' => 'Invalid team ID.' ) );
		}

		$builder = new Puck_Press_Divi_Page_Builder();
		$builder->delete_all( $team_id );

		wp_send_json_success(
			array(
				'pages_card_html' => ( new Puck_Press_Teams_Admin_Pages_Card( $team_id ) )->render(),
			)
		);
	}

	public static function pp_ajax_add_team_source(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => 'Insufficient permissions.' ) );
		}

		$team_id = (int) ( $_POST['team_id'] ?? 0 );
		if ( ! $team_id ) {
			wp_send_json_error( array( 'message' => 'Invalid team ID.' ) );
		}

		$name = sanitize_text_field( $_POST['name'] ?? '' );
		$type = sanitize_text_field( $_POST['type'] ?? '' );

		$url = $season = $csv_content = $other_data = null;

		switch ( $type ) {
			case 'achaGameScheduleUrl':
				$acha_team_id   = sanitize_text_field( $_POST['team_id_url'] ?? '' );
				$acha_season_id = sanitize_text_field( $_POST['season_id'] ?? '' );
				$auto_discover  = ! empty( $_POST['auto_discover'] );

				if ( empty( $acha_team_id ) || empty( $acha_season_id ) ) {
					wp_send_json_error( array( 'message' => 'Team ID and Season ID are required.' ) );
				}

				require_once plugin_dir_path( __DIR__ ) . 'includes/schedule/class-puck-press-acha-season-discoverer.php';
				$meta = Puck_Press_Acha_Season_Discoverer::get_team_season_meta( $acha_team_id, $acha_season_id, $auto_discover );
				if ( is_wp_error( $meta ) ) {
					wp_send_json_error( array( 'message' => $meta->get_error_message() ) );
				}

				$name   = $meta['season_name'];
				$url    = $acha_team_id;
				$season = $meta['season_year'];

				$od_arr = array(
					'season_id'   => $acha_season_id,
					'division_id' => $meta['division_id'],
				);
				if ( $auto_discover ) {
					$od_arr['auto_discover']   = true;
					$od_arr['seed_season_id']  = $acha_season_id;
					$od_arr['seed_start_date'] = $meta['start_date'];
				}
				$other_data = wp_json_encode( $od_arr );
				break;

			case 'usphlGameScheduleUrl':
				$url       = sanitize_text_field( $_POST['team_id_url'] ?? '' );
				$season_id = sanitize_text_field( $_POST['season_id'] ?? '' );
				if ( empty( $season_id ) ) {
					wp_send_json_error( array( 'message' => 'Season ID is required for USPHL sources.' ) );
				}
				$other_data = wp_json_encode( array( 'season_id' => $season_id ) );
				break;

			case 'csv':
				if ( ! isset( $_FILES['csv'] ) || $_FILES['csv']['error'] !== UPLOAD_ERR_OK ) {
					wp_send_json_error( array( 'message' => 'CSV upload failed.' ) );
				}
				require_once plugin_dir_path( __DIR__ ) . 'includes/schedule/class-puck-press-schedule-process-csv-data.php';
				$validation = Puck_Press_Schedule_Process_Csv_Data::validate_csv_headers( $_FILES['csv']['tmp_name'] );
				if ( is_wp_error( $validation ) ) {
					wp_send_json_error( array( 'message' => $validation->get_error_message() ) );
				}
				$csv_content = file_get_contents( $_FILES['csv']['tmp_name'] );
				$url         = sanitize_file_name( $_FILES['csv']['name'] );
				break;

			case 'customGame':
				$json    = wp_unslash( $_POST['other_data'] ?? '' );
				$decoded = json_decode( $json, true );
				if ( json_last_error() !== JSON_ERROR_NONE ) {
					wp_send_json_error( array( 'message' => 'Invalid JSON provided.' ) );
				}
				$other_data = wp_json_encode( $decoded );
				break;

			default:
				wp_send_json_error( array( 'message' => 'Invalid source type.' ) );
		}

		$utils = new Puck_Press_Teams_Wpdb_Utils();
		$id    = $utils->add_team_source(
			$team_id,
			array(
				'name'               => $name,
				'type'               => $type,
				'season'             => $season,
				'source_url_or_path' => $url,
				'csv_data'           => $csv_content,
				'other_data'         => $other_data,
				'status'             => 'active',
				'last_updated'       => current_time( 'mysql' ),
			)
		);

		if ( ! $id ) {
			wp_send_json_error( array( 'message' => 'Failed to add source.' ) );
		}

		if ( $type === 'achaGameScheduleUrl' && $auto_discover && isset( $meta ) && is_array( $meta ) ) {
			$roster_created = Puck_Press_Acha_Season_Discoverer::maybe_create_roster_seed( $team_id, $acha_team_id, $acha_season_id, $meta );
			if ( $roster_created ) {
				require_once plugin_dir_path( __DIR__ ) . 'includes/roster/class-puck-press-team-roster-importer.php';
				( new Puck_Press_Team_Roster_Importer( $team_id ) )->rebuild_team_and_cascade();
			}
		}

		wp_send_json_success( array( 'message' => 'Source added.', 'id' => $id ) );
	}

	public static function pp_ajax_update_team_source_status(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => 'Insufficient permissions.' ) );
		}

		$source_id = (int) ( $_POST['source_id'] ?? 0 );
		$team_id   = (int) ( $_POST['team_id'] ?? 0 );
		$status    = sanitize_text_field( $_POST['status'] ?? '' );

		if ( ! $source_id || ! in_array( $status, array( 'active', 'inactive' ), true ) ) {
			wp_send_json_error( array( 'message' => 'Invalid request data.' ) );
		}

		$utils   = new Puck_Press_Teams_Wpdb_Utils();
		$updated = $utils->update_team_source( $source_id, array( 'status' => $status ) );

		if ( false === $updated ) {
			wp_send_json_error( array( 'message' => 'Failed to update status.' ) );
		}

		if ( $team_id ) {
			require_once plugin_dir_path( __DIR__ ) . 'includes/schedule/class-puck-press-schedules-wpdb-utils.php';
			require_once plugin_dir_path( __DIR__ ) . 'includes/schedule/class-puck-press-schedule-materializer.php';
			require_once plugin_dir_path( __DIR__ ) . 'includes/schedule/class-puck-press-team-source-importer.php';

			$importer = new Puck_Press_Team_Source_Importer( $team_id );
			$importer->rebuild_team_and_cascade();

			$games_card = new Puck_Press_Teams_Admin_Games_Table_Card( array(), $team_id );
			wp_send_json_success(
				array(
					'message'    => 'Status updated.',
					'games_html' => $games_card->render_team_games_admin_preview(),
				)
			);
		}

		wp_send_json_success( array( 'message' => 'Status updated.' ) );
	}

	public static function pp_ajax_delete_team_source(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => 'Insufficient permissions.' ) );
		}

		$source_id = (int) ( $_POST['source_id'] ?? 0 );
		if ( ! $source_id ) {
			wp_send_json_error( array( 'message' => 'Invalid source ID.' ) );
		}

		$utils   = new Puck_Press_Teams_Wpdb_Utils();
		$deleted = $utils->delete_team_source( $source_id );

		if ( $deleted ) {
			wp_send_json_success( array( 'message' => 'Source deleted.' ) );
		} else {
			wp_send_json_error( array( 'message' => 'Failed to delete source.' ) );
		}
	}

	public function ajax_refresh_team_callback(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => 'Unauthorized' ) );
			wp_die();
		}

		$team_id = (int) ( $_POST['team_id'] ?? 0 );
		if ( ! $team_id ) {
			wp_send_json_error( array( 'message' => 'Invalid team ID.' ) );
			wp_die();
		}

		require_once plugin_dir_path( __DIR__ ) . 'includes/teams/class-puck-press-teams-wpdb-utils.php';
		require_once plugin_dir_path( __DIR__ ) . 'includes/schedule/class-puck-press-schedules-wpdb-utils.php';
		require_once plugin_dir_path( __DIR__ ) . 'includes/schedule/class-puck-press-schedule-materializer.php';
		require_once plugin_dir_path( __DIR__ ) . 'includes/schedule/class-puck-press-team-source-importer.php';

		$importer = new Puck_Press_Team_Source_Importer( $team_id );
		$results  = $importer->rebuild_team_and_cascade();

		$games_card = new Puck_Press_Teams_Admin_Games_Table_Card( array(), $team_id );

		wp_send_json_success(
			array(
				'message'                 => 'Team refreshed.',
				'results'                 => $results,
				'refreshed_game_table_ui' => $games_card->render_team_games_admin_preview(),
			)
		);
	}

	public static function pp_ajax_create_new_schedule(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => 'Insufficient permissions.' ) );
		}

		$name = sanitize_text_field( $_POST['name'] ?? '' );
		$slug = sanitize_title( $_POST['slug'] ?? $name );

		if ( empty( $name ) || empty( $slug ) ) {
			wp_send_json_error( array( 'message' => 'Name and slug are required.' ) );
		}

		require_once plugin_dir_path( __DIR__ ) . 'includes/schedule/class-puck-press-schedules-wpdb-utils.php';
		$utils = new Puck_Press_Schedules_Wpdb_Utils();
		$id    = $utils->create_schedule( $slug, $name, false );

		if ( ! $id ) {
			wp_send_json_error( array( 'message' => 'Failed to create schedule. Slug may already exist.' ) );
		}

		wp_send_json_success( array( 'message' => "Schedule '{$name}' created.", 'id' => $id, 'slug' => $slug, 'name' => $name ) );
	}

	public static function pp_ajax_delete_new_schedule(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => 'Insufficient permissions.' ) );
		}

		$schedule_id = (int) ( $_POST['schedule_id'] ?? 0 );
		if ( ! $schedule_id ) {
			wp_send_json_error( array( 'message' => 'Invalid schedule ID.' ) );
		}

		require_once plugin_dir_path( __DIR__ ) . 'includes/schedule/class-puck-press-schedules-wpdb-utils.php';
		$utils   = new Puck_Press_Schedules_Wpdb_Utils();
		$deleted = $utils->delete_schedule( $schedule_id );

		if ( ! $deleted ) {
			wp_send_json_error( array( 'message' => 'Cannot delete this schedule (may be the main schedule).' ) );
		}

		wp_send_json_success( array( 'message' => 'Schedule deleted.' ) );
	}

	public static function pp_ajax_set_active_new_schedule_id(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => 'Insufficient permissions.' ) );
		}
		$schedule_id = (int) ( $_POST['schedule_id'] ?? 0 );
		update_option( 'pp_admin_active_new_schedule_id', $schedule_id );
		wp_send_json_success( array( 'message' => 'Active schedule updated.', 'schedule_id' => $schedule_id ) );
	}

	public static function pp_ajax_get_schedule_preview(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => 'Insufficient permissions.' ) );
		}
		$schedule_id = (int) ( $_POST['schedule_id'] ?? 0 );
		if ( $schedule_id <= 0 ) {
			wp_send_json_error( array( 'message' => 'Invalid schedule_id.' ) );
		}

		$preview_card  = Puck_Press_Schedule_Admin_Preview_Card::create_and_init( $schedule_id );
		$slider_card   = Puck_Press_Schedule_Admin_Slider_Preview_Card::create_and_init( $schedule_id );
		$options       = array( 'schedule_id' => $schedule_id );

		$schedule_tm       = new Puck_Press_Schedule_Template_Manager( $schedule_id );
		$slider_tm         = new Puck_Press_Slider_Template_Manager( $schedule_id );
		$selected_template = $schedule_tm->get_current_template_key();
		$selected_slider   = $slider_tm->get_current_template_key();

		// Membership data for the Team Membership card.
		$schedules_utils     = new Puck_Press_Schedules_Wpdb_Utils();
		$schedule_obj        = $schedules_utils->get_schedule_by_id( $schedule_id );
		$is_main             = $schedule_obj ? (int) $schedule_obj['is_main'] === 1 : false;
		$schedule_slug       = $schedule_obj['slug'] ?? 'default';
		$schedule_teams      = $schedules_utils->get_schedule_teams( $schedule_id );
		$existing_team_ids   = array_column( $schedule_teams, 'id' );
		$teams_utils         = new Puck_Press_Teams_Wpdb_Utils();
		$all_teams           = $teams_utils->get_all_teams();
		$available_teams     = array_values( array_filter( $all_teams, fn( $t ) => ! in_array( $t['id'], $existing_team_ids, false ) ) );
		$shortcode           = '[pp-schedule' . ( $schedule_slug !== 'default' ? ' schedule="' . esc_attr( $schedule_slug ) . '"' : '' ) . ']';

		wp_send_json_success( array(
			'schedule_id'              => $schedule_id,
			'game_preview_html'        => $preview_card->get_all_templates_html(),
			'slider_preview_html'      => $slider_card->get_all_templates_html(),
			'active_preview_html'      => $preview_card->get_current_template_html( $options ),
			'active_slider_html'       => $slider_card->get_current_template_html( $options ),
			'selected_template'        => $selected_template,
			'selected_slider_template' => $selected_slider,
			'is_main'                  => $is_main,
			'schedule_slug'            => $schedule_slug,
			'shortcode'                => $shortcode,
			'schedule_teams'           => $schedule_teams,
			'available_teams'          => $available_teams,
			'cal_url'                  => get_option( "pp_slider_{$schedule_id}_cal_url", get_option( 'pp_slider_cal_url', '' ) ),
		) );
	}

	public static function pp_ajax_add_team_to_schedule(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => 'Insufficient permissions.' ) );
		}

		$schedule_id = (int) ( $_POST['schedule_id'] ?? 0 );
		$team_id     = (int) ( $_POST['team_id'] ?? 0 );

		if ( ! $schedule_id || ! $team_id ) {
			wp_send_json_error( array( 'message' => 'Invalid IDs.' ) );
		}

		$utils = new Puck_Press_Schedules_Wpdb_Utils();
		$added = $utils->add_team_to_schedule( $schedule_id, $team_id );

		if ( ! $added ) {
			wp_send_json_error( array( 'message' => 'Could not add team (main schedule auto-includes all teams, or duplicate).' ) );
		}

		$materializer = new Puck_Press_Schedule_Materializer();
		$materializer->materialize_schedule( $schedule_id );

		wp_send_json_success( array( 'message' => 'Team added to schedule.' ) );
	}

	public static function pp_ajax_remove_team_from_schedule(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => 'Insufficient permissions.' ) );
		}

		$schedule_id = (int) ( $_POST['schedule_id'] ?? 0 );
		$team_id     = (int) ( $_POST['team_id'] ?? 0 );

		if ( ! $schedule_id || ! $team_id ) {
			wp_send_json_error( array( 'message' => 'Invalid IDs.' ) );
		}

		$utils   = new Puck_Press_Schedules_Wpdb_Utils();
		$removed = $utils->remove_team_from_schedule( $schedule_id, $team_id );

		if ( ! $removed ) {
			wp_send_json_error( array( 'message' => 'Could not remove team.' ) );
		}

		$materializer = new Puck_Press_Schedule_Materializer();
		$materializer->materialize_schedule( $schedule_id );

		wp_send_json_success( array( 'message' => 'Team removed from schedule.' ) );
	}

	public static function pp_ajax_archive_all_teams_season(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => 'Insufficient permissions.' ) );
		}

		$season_key = sanitize_text_field( $_POST['season_key'] ?? '' );
		$label      = sanitize_text_field( $_POST['label'] ?? $season_key );
		$wipe       = ! empty( $_POST['wipe'] );

		if ( empty( $season_key ) ) {
			wp_send_json_error( array( 'message' => 'season_key is required.' ) );
		}

		require_once plugin_dir_path( __DIR__ ) . 'includes/archive/class-puck-press-archive-manager.php';
		$archive_manager = new Puck_Press_Archive_Manager();

		$result = $archive_manager->archive_all_teams_season( $season_key, $label );

		if ( ! $result['success'] ) {
			wp_send_json_error( $result );
		}

		if ( $wipe ) {
			$archive_manager->clear_all_teams_season_data();
			require_once plugin_dir_path( __DIR__ ) . 'includes/schedule/class-puck-press-schedule-materializer.php';
			( new Puck_Press_Schedule_Materializer() )->materialize_all_schedules();
			$result['reload'] = true;
		}

		$result['archives_html'] = self::build_all_archives_html();
		wp_send_json_success( $result );
	}

	public static function pp_ajax_delete_team_archive(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => 'Insufficient permissions.' ) );
		}

		$season_key = sanitize_text_field( wp_unslash( $_POST['season_key'] ?? '' ) );

		if ( ! $season_key ) {
			wp_send_json_error( array( 'message' => 'season_key is required.' ) );
		}

		require_once plugin_dir_path( __DIR__ ) . 'includes/archive/class-puck-press-archive-manager.php';
		( new Puck_Press_Archive_Manager() )->delete_archive( $season_key );

		wp_send_json_success( array( 'archives_html' => self::build_all_archives_html() ) );
	}

	private static function build_all_archives_html(): string {
		require_once plugin_dir_path( __DIR__ ) . 'includes/archive/class-puck-press-archive-manager.php';
		$archives = ( new Puck_Press_Archive_Manager() )->get_all_archives();

		ob_start();
		?>
		<div id="pp-team-archives-list">
		<?php if ( empty( $archives ) ) : ?>
			<p style="color:#5f6368;font-size:0.875rem;margin:12px 0 0;">No archives yet.</p>
		<?php else : ?>
			<table style="width:100%;border-collapse:collapse;font-size:0.875rem;margin-top:12px;">
				<thead>
					<tr style="background:#f5f5f5;">
						<th style="text-align:left;padding:8px 12px;border:1px solid #e0e0e0;font-weight:600;">Season</th>
						<th style="text-align:left;padding:8px 12px;border:1px solid #e0e0e0;font-weight:600;">Archived</th>
						<th style="text-align:center;padding:8px 12px;border:1px solid #e0e0e0;font-weight:600;">Games</th>
						<th style="text-align:center;padding:8px 12px;border:1px solid #e0e0e0;font-weight:600;">Skaters</th>
						<th style="text-align:center;padding:8px 12px;border:1px solid #e0e0e0;font-weight:600;">Goalies</th>
						<th style="padding:8px 12px;border:1px solid #e0e0e0;"></th>
					</tr>
				</thead>
				<tbody>
				<?php foreach ( $archives as $archive ) : ?>
					<tr>
						<td style="padding:8px 12px;border:1px solid #e0e0e0;"><?php echo esc_html( $archive['label'] ); ?></td>
						<td style="padding:8px 12px;border:1px solid #e0e0e0;"><?php echo esc_html( date_i18n( 'M j, Y', strtotime( $archive['archived_at'] ) ) ); ?></td>
						<td style="padding:8px 12px;border:1px solid #e0e0e0;text-align:center;"><?php echo (int) $archive['game_count']; ?></td>
						<td style="padding:8px 12px;border:1px solid #e0e0e0;text-align:center;"><?php echo (int) $archive['skater_count']; ?></td>
						<td style="padding:8px 12px;border:1px solid #e0e0e0;text-align:center;"><?php echo (int) $archive['goalie_count']; ?></td>
						<td style="padding:8px 12px;border:1px solid #e0e0e0;text-align:right;">
							<button class="pp-button pp-button-danger pp-delete-archive-btn"
								data-season-key="<?php echo esc_attr( $archive['season_key'] ); ?>">
								Delete
							</button>
						</td>
					</tr>
				<?php endforeach; ?>
				</tbody>
			</table>
		<?php endif; ?>
		</div>
		<?php
		return ob_get_clean();
	}

	public static function pp_ajax_wipe_all_teams_season_data(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => 'Insufficient permissions.' ) );
		}

		require_once plugin_dir_path( __DIR__ ) . 'includes/archive/class-puck-press-archive-manager.php';
		( new Puck_Press_Archive_Manager() )->clear_all_teams_season_data();

		require_once plugin_dir_path( __DIR__ ) . 'includes/schedule/class-puck-press-schedule-materializer.php';
		( new Puck_Press_Schedule_Materializer() )->materialize_all_schedules();

		wp_send_json_success( array( 'message' => 'Live season data wiped.', 'reload' => true ) );
	}

	public static function pp_ajax_get_game_data(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => 'Insufficient permissions.' ) );
		}

		global $wpdb;
		$game_id = sanitize_text_field( $_POST['game_id'] ?? '' );
		$team_id = (int) ( $_POST['team_id'] ?? 0 );

		if ( ! $game_id || ! $team_id ) {
			wp_send_json_error( array( 'message' => 'game_id and team_id are required.' ) );
		}

		$game = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$wpdb->prefix}pp_team_games_display WHERE game_id = %s AND team_id = %d",
				$game_id,
				$team_id
			),
			ARRAY_A
		);

		if ( ! $game ) {
			wp_send_json_error( array( 'message' => 'Game not found.' ) );
		}

		$game['game_timestamp'] = ! empty( $game['game_timestamp'] ) ? strtotime( $game['game_timestamp'] ) : null;

		// Convert game_time from 12-hour display format to HH:MM for <input type="time">.
		if ( ! empty( $game['game_time'] ) ) {
			$parsed = strtotime( $game['game_time'] );
			if ( $parsed ) {
				$game['game_time'] = date( 'H:i', $parsed );
			}
		}

		// Normalize game_status to match select option values (e.g. 'FINAL OT' → 'final-ot').
		if ( ! empty( $game['game_status'] ) ) {
			$game['game_status'] = strtolower( str_replace( ' ', '-', $game['game_status'] ) );
		}

		wp_send_json_success( array( 'game' => $game ) );
	}

	public static function pp_ajax_save_game_edit(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => 'Insufficient permissions.' ) );
		}

		global $wpdb;
		$game_id   = sanitize_text_field( $_POST['game_id'] ?? '' );
		$team_id   = (int) ( $_POST['team_id'] ?? 0 );
		$is_manual = strpos( $game_id, 'manual_' ) === 0;

		if ( ! $game_id || ! $team_id ) {
			wp_send_json_error( array( 'message' => 'game_id and team_id are required.' ) );
		}

		$mods_table = $wpdb->prefix . 'pp_team_game_mods';
		// game_date submitted as YYYY-MM-DD (from <input type="date">).
		// game_time submitted as HH:MM (from <input type="time">).
		$submitted_date = sanitize_text_field( $_POST['game_date'] ?? '' );
		$submitted_time = sanitize_text_field( $_POST['game_time'] ?? '' ); // HH:MM or ''

		require_once plugin_dir_path( __DIR__ ) . 'includes/teams/class-puck-press-teams-wpdb-utils.php';
		$teams_utils = new Puck_Press_Teams_Wpdb_Utils();

		if ( $is_manual ) {
			// Manual games: no update mod — just merge into the insert mod so nothing is highlighted.
			$manual_data = array();
			if ( $submitted_date ) {
				require_once plugin_dir_path( __DIR__ ) . 'includes/schedule/class-puck-press-team-source-importer.php';
				$manual_data['game_date_day']  = Puck_Press_Team_Source_Importer::format_game_date_day( $submitted_date );
				$time_for_ts                   = $submitted_time ?: '00:00';
				$manual_data['game_timestamp'] = wp_date( 'Y-m-d', strtotime( $submitted_date ) ) . ' ' . $time_for_ts . ':00';
			}
			if ( $submitted_time !== '' ) {
				$manual_data['game_time'] = date( 'g:i A', strtotime( $submitted_time ) );
				if ( ! isset( $manual_data['game_timestamp'] ) ) {
					$base = substr( $manual_data['game_timestamp'] ?? current_time( 'mysql' ), 0, 10 );
					$manual_data['game_timestamp'] = $base . ' ' . $submitted_time . ':00';
				}
			}
			$home_or_away = sanitize_text_field( $_POST['home_or_away'] ?? '' );
			if ( $home_or_away ) {
				$manual_data['home_or_away'] = $home_or_away;
			}
			$game_status = sanitize_text_field( $_POST['game_status'] ?? '' );
			if ( $game_status !== '' ) {
				$manual_data['game_status'] = $game_status === 'none' ? null : $game_status;
			}
			foreach ( array( 'target_score', 'opponent_score' ) as $score_field ) {
				$val = $_POST[ $score_field ] ?? '';
				if ( $val !== '' ) {
					$manual_data[ $score_field ] = (int) $val;
				}
			}
			foreach ( array( 'venue', 'promo_header', 'promo_text', 'promo_img_url', 'promo_ticket_link', 'post_link' ) as $text_field ) {
				if ( isset( $_POST[ $text_field ] ) ) {
					$manual_data[ $text_field ] = sanitize_text_field( $_POST[ $text_field ] );
				}
			}
			$insert_mod = $wpdb->get_row(
				$wpdb->prepare(
					"SELECT * FROM $mods_table WHERE team_id = %d AND external_id = %s AND edit_action = 'insert' LIMIT 1",
					$team_id,
					$game_id
				),
				ARRAY_A
			);
			if ( $insert_mod ) {
				$mod_data = json_decode( $insert_mod['edit_data'], true ) ?: array();
				$mod_data = array_merge( $mod_data, $manual_data );
				$wpdb->update(
					$mods_table,
					array(
						'edit_data'  => wp_json_encode( $mod_data ),
						'updated_at' => current_time( 'mysql' ),
					),
					array( 'id' => $insert_mod['id'] ),
					array( '%s', '%s' ),
					array( '%d' )
				);
			}
		} else {
			// Sourced games: compare against the raw source row. Only fields that truly
			// differ from the source go into edit_data — those are the real overrides.
			$raw_game = $wpdb->get_row(
				$wpdb->prepare(
					"SELECT * FROM {$wpdb->prefix}pp_team_games_raw WHERE game_id = %s AND team_id = %d LIMIT 1",
					$game_id,
					$team_id
				),
				ARRAY_A
			) ?: array();

			require_once plugin_dir_path( __DIR__ ) . 'includes/schedule/class-puck-press-team-source-importer.php';

			$edit_data = array();

			// --- Date: compare via game_timestamp date portions (format-agnostic). ---
			// game_date_day in raw is a display string like "Fri, Sep 13" and cannot be
			// compared directly against the YYYY-MM-DD value from <input type="date">.
			if ( $submitted_date ) {
				$new_date_part = wp_date( 'Y-m-d', strtotime( $submitted_date ) );
				$raw_date_part = substr( (string) ( $raw_game['game_timestamp'] ?? '' ), 0, 10 );
				if ( $new_date_part !== $raw_date_part ) {
					$time_for_ts                  = $submitted_time ?: substr( (string) ( $raw_game['game_timestamp'] ?? '1970-01-01 00:00:00' ), 11, 5 );
					$edit_data['game_date_day']   = Puck_Press_Team_Source_Importer::format_game_date_day( $submitted_date );
					$edit_data['game_timestamp']  = $new_date_part . ' ' . $time_for_ts . ':00';
				}
			}

			// --- Time: submitted as HH:MM; raw stored as "7:30 PM" or "7:30pm". ---
			// Normalize both to "g:i A" format before comparing so spacing/case differences
			// (e.g. "7:30pm" vs "7:30 PM") don't produce false positives.
			if ( $submitted_time !== '' ) {
				$new_time_fmt    = date( 'g:i A', strtotime( $submitted_time ) ); // e.g. "7:30 PM"
				$raw_time_str    = (string) ( $raw_game['game_time'] ?? '' );
				$raw_time_parsed = strtotime( $raw_time_str );
				$raw_time_fmt    = $raw_time_parsed ? date( 'g:i A', $raw_time_parsed ) : $raw_time_str;
				if ( $new_time_fmt !== $raw_time_fmt ) {
					$edit_data['game_time'] = $new_time_fmt;
					// Keep game_timestamp time portion in sync.
					if ( ! isset( $edit_data['game_timestamp'] ) ) {
						$base = substr( (string) ( $raw_game['game_timestamp'] ?? current_time( 'mysql' ) ), 0, 10 );
						$edit_data['game_timestamp'] = $base . ' ' . $submitted_time . ':00';
					} else {
						$edit_data['game_timestamp'] = substr( $edit_data['game_timestamp'], 0, 11 ) . $submitted_time . ':00';
					}
				}
			}

			// --- home_or_away ---
			$home_or_away = sanitize_text_field( $_POST['home_or_away'] ?? '' );
			if ( $home_or_away && $home_or_away !== ( $raw_game['home_or_away'] ?? '' ) ) {
				$edit_data['home_or_away'] = $home_or_away;
			}

			// --- game_status ---
			// Select values are 'final'/'final-ot'/'final-so'; DB stores 'FINAL'/'FINAL OT'/'FINAL SO'.
			$game_status = sanitize_text_field( $_POST['game_status'] ?? '' );
			if ( $game_status !== '' ) {
				$status_map        = array( 'final' => 'FINAL', 'final-ot' => 'FINAL OT', 'final-so' => 'FINAL SO', 'none' => null );
				$normalized_status = $status_map[ $game_status ] ?? null;
				if ( (string) $normalized_status !== (string) ( $raw_game['game_status'] ?? '' ) ) {
					$edit_data['game_status'] = $normalized_status;
				}
			}

			// --- Scores ---
			foreach ( array( 'target_score', 'opponent_score' ) as $score_field ) {
				$val = $_POST[ $score_field ] ?? '';
				if ( $val !== '' && (string) (int) $val !== (string) ( $raw_game[ $score_field ] ?? '' ) ) {
					$edit_data[ $score_field ] = (int) $val;
				}
			}

			// --- Text fields ---
			foreach ( array( 'venue', 'promo_header', 'promo_text', 'promo_img_url', 'promo_ticket_link', 'post_link' ) as $text_field ) {
				if ( isset( $_POST[ $text_field ] ) ) {
					$val = sanitize_text_field( $_POST[ $text_field ] );
					if ( $val !== (string) ( $raw_game[ $text_field ] ?? '' ) ) {
						$edit_data[ $text_field ] = $val;
					}
				}
			}

			// Remove existing update mod, then re-insert if there are real changes.
			$wpdb->delete(
				$mods_table,
				array( 'team_id' => $team_id, 'external_id' => $game_id, 'edit_action' => 'update' ),
				array( '%d', '%s', '%s' )
			);

			if ( ! empty( $edit_data ) ) {
				$teams_utils->upsert_team_game_mod( $team_id, $game_id, 'update', $edit_data );
			}
		}

		require_once plugin_dir_path( __DIR__ ) . 'includes/schedule/class-puck-press-team-source-importer.php';
		$importer = new Puck_Press_Team_Source_Importer( $team_id );
		$importer->rebuild_display_and_cascade();

		require_once plugin_dir_path( __DIR__ ) . 'admin/components/teams/class-puck-press-teams-admin-games-table-card.php';
		$games_card = new Puck_Press_Teams_Admin_Games_Table_Card( array(), $team_id );

		wp_send_json_success( array( 'games_table_html' => $games_card->render_team_games_admin_preview() ) );
	}

	public static function pp_ajax_delete_game(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => 'Insufficient permissions.' ) );
		}

		global $wpdb;
		$game_id = sanitize_text_field( $_POST['game_id'] ?? '' );
		$team_id = (int) ( $_POST['team_id'] ?? 0 );

		if ( ! $game_id || ! $team_id ) {
			wp_send_json_error( array( 'message' => 'game_id and team_id are required.' ) );
		}

		require_once plugin_dir_path( __DIR__ ) . 'includes/teams/class-puck-press-teams-wpdb-utils.php';
		$teams_utils = new Puck_Press_Teams_Wpdb_Utils();

		if ( strpos( $game_id, 'manual_' ) === 0 ) {
			// Manual game: remove insert mod and any update mods.
			$wpdb->delete(
				$wpdb->prefix . 'pp_team_game_mods',
				array( 'team_id' => $team_id, 'external_id' => $game_id ),
				array( '%d', '%s' )
			);
		} else {
			// Sourced game: add a delete mod.
			$teams_utils->upsert_team_game_mod( $team_id, $game_id, 'delete', array() );
		}

		require_once plugin_dir_path( __DIR__ ) . 'includes/schedule/class-puck-press-team-source-importer.php';
		$importer = new Puck_Press_Team_Source_Importer( $team_id );
		$importer->rebuild_display_and_cascade();

		require_once plugin_dir_path( __DIR__ ) . 'admin/components/teams/class-puck-press-teams-admin-games-table-card.php';
		$games_card = new Puck_Press_Teams_Admin_Games_Table_Card( array(), $team_id );

		wp_send_json_success( array( 'games_table_html' => $games_card->render_team_games_admin_preview() ) );
	}

	public static function pp_ajax_restore_game(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => 'Insufficient permissions.' ) );
		}

		$delete_mod_id = (int) ( $_POST['delete_mod_id'] ?? 0 );
		$team_id       = (int) ( $_POST['team_id'] ?? 0 );

		if ( ! $delete_mod_id || ! $team_id ) {
			wp_send_json_error( array( 'message' => 'delete_mod_id and team_id are required.' ) );
		}

		require_once plugin_dir_path( __DIR__ ) . 'includes/teams/class-puck-press-teams-wpdb-utils.php';
		$teams_utils = new Puck_Press_Teams_Wpdb_Utils();
		$teams_utils->delete_team_game_mod( $delete_mod_id );

		require_once plugin_dir_path( __DIR__ ) . 'includes/schedule/class-puck-press-team-source-importer.php';
		$importer = new Puck_Press_Team_Source_Importer( $team_id );
		$importer->rebuild_display_and_cascade();

		require_once plugin_dir_path( __DIR__ ) . 'admin/components/teams/class-puck-press-teams-admin-games-table-card.php';
		$games_card = new Puck_Press_Teams_Admin_Games_Table_Card( array(), $team_id );

		wp_send_json_success( array( 'games_table_html' => $games_card->render_team_games_admin_preview() ) );
	}

	public static function pp_ajax_revert_game_field(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => 'Insufficient permissions.' ) );
		}

		global $wpdb;
		$mod_id  = (int) ( $_POST['mod_id'] ?? 0 );
		$team_id = (int) ( $_POST['team_id'] ?? 0 );
		$fields  = array_map( 'sanitize_key', (array) ( $_POST['fields'] ?? array() ) );

		if ( ! $mod_id || ! $team_id || empty( $fields ) ) {
			wp_send_json_error( array( 'message' => 'mod_id, team_id, and fields are required.' ) );
		}

		$mods_table = $wpdb->prefix . 'pp_team_game_mods';
		$mod        = $wpdb->get_row(
			$wpdb->prepare( "SELECT * FROM $mods_table WHERE id = %d AND team_id = %d AND edit_action = 'update'", $mod_id, $team_id ),
			ARRAY_A
		);

		if ( ! $mod ) {
			wp_send_json_error( array( 'message' => 'Mod not found.' ) );
		}

		$edit_data = json_decode( $mod['edit_data'], true ) ?: array();

		foreach ( $fields as $field ) {
			// 'game_date' is the virtual td field — the actual stored keys are game_date_day + game_timestamp.
			if ( 'game_date' === $field ) {
				unset( $edit_data['game_date_day'], $edit_data['game_timestamp'] );
			} else {
				unset( $edit_data[ $field ] );
			}
		}

		// If nothing meaningful remains, delete the entire mod.
		if ( empty( array_diff( array_keys( $edit_data ), array( 'external_id' ) ) ) ) {
			$wpdb->delete( $mods_table, array( 'id' => $mod_id ), array( '%d' ) );
		} else {
			$wpdb->update(
				$mods_table,
				array(
					'edit_data'  => wp_json_encode( $edit_data ),
					'updated_at' => current_time( 'mysql' ),
				),
				array( 'id' => $mod_id ),
				array( '%s', '%s' ),
				array( '%d' )
			);
		}

		require_once plugin_dir_path( __DIR__ ) . 'includes/schedule/class-puck-press-team-source-importer.php';
		$importer = new Puck_Press_Team_Source_Importer( $team_id );
		$importer->rebuild_display_and_cascade();

		require_once plugin_dir_path( __DIR__ ) . 'admin/components/teams/class-puck-press-teams-admin-games-table-card.php';
		$games_card = new Puck_Press_Teams_Admin_Games_Table_Card( array(), $team_id );

		wp_send_json_success( array( 'games_table_html' => $games_card->render_team_games_admin_preview() ) );
	}

	public static function pp_ajax_wipe_and_recreate_db(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => 'Insufficient permissions.' ) );
		}

		require_once plugin_dir_path( __DIR__ ) . 'includes/class-puck-press-activator.php';

		$log = Puck_Press_Activator::wipe_and_recreate_tables();

		wp_send_json_success( array( 'log' => $log ) );
	}

	public static function pp_ajax_refresh_roster_sources(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => 'Insufficient permissions.' ) );
		}

		global $wpdb;

		// Ensure all roster tables and the main roster row exist before trying to materialize.
		$roster_utils = new Puck_Press_Roster_Wpdb_Utils();
		$roster_utils->maybe_create_or_update_table( 'pp_rosters' );
		$registry = new Puck_Press_Roster_Registry_Wpdb_Utils();
		$registry->maybe_create_or_update_tables();
		$registry->seed_main_roster();

		$teams_utils = new Puck_Press_Teams_Wpdb_Utils();
		$all_teams   = $teams_utils->get_all_teams();
		$log         = array();
		$log[]       = 'Main roster ID: ' . ( $registry->get_main_roster_id() ?? 'NULL' );
		$log[]       = 'Teams found: ' . count( $all_teams );

		foreach ( $all_teams as $team ) {
			$team_id  = (int) $team['id'];

			$sources = $wpdb->get_results( $wpdb->prepare( "SELECT id, name, type, status FROM {$wpdb->prefix}pp_team_roster_sources WHERE team_id = %d", $team_id ), ARRAY_A ) ?? array();
			$log[]   = "=== Team ID {$team_id} ({$team['name']}) ===";
			$log[]   = "pp_team_roster_sources rows: " . count( $sources ) . ' => ' . wp_json_encode( $sources );

			$importer = new Puck_Press_Team_Roster_Importer( $team_id );
			$result   = $importer->rebuild_team_and_cascade();
			$log[]    = wp_json_encode( $result );

			$raw_count     = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->prefix}pp_team_players_raw WHERE team_id = %d", $team_id ) );
			$display_count = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->prefix}pp_team_players_display WHERE team_id = %d", $team_id ) );
			$log[]         = "pp_team_players_raw rows: {$raw_count}";
			$log[]         = "pp_team_players_display rows: {$display_count}";
		}

		$registry = new Puck_Press_Roster_Registry_Wpdb_Utils();
		$rosters  = $registry->get_all_rosters();
		$log[]    = '=== Roster display counts (from pp_team_players_display) ===';
		foreach ( $rosters as $roster ) {
			$rid      = (int) $roster['id'];
			$team_ids = $registry->get_roster_team_ids( $rid );
			if ( ! empty( $team_ids ) ) {
				$placeholders = implode( ', ', array_fill( 0, count( $team_ids ), '%d' ) );
				$count        = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->prefix}pp_team_players_display WHERE team_id IN ($placeholders)", $team_ids ) );
			} else {
				$count = 0;
			}
			$log[] = "Roster ID {$rid} ({$roster['name']}, is_main={$roster['is_main']}): {$count} rows in pp_team_players_display";
			$log[] = "  Team IDs resolved for this roster: " . ( empty( $team_ids ) ? 'none' : implode( ', ', $team_ids ) );
		}

		wp_send_json_success( array( 'log' => $log ) );
	}

	public static function pp_ajax_refresh_team_roster(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => 'Insufficient permissions.' ) );
		}

		$team_id = (int) ( $_POST['team_id'] ?? 0 );
		if ( ! $team_id ) {
			wp_send_json_error( array( 'message' => 'Invalid team ID.' ) );
		}

		$results = ( new Puck_Press_Team_Roster_Importer( $team_id ) )->rebuild_team_and_cascade();

		wp_send_json_success( array( 'message' => 'Roster refreshed', 'results' => $results ) );
	}

	public static function pp_ajax_create_new_roster(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => 'Insufficient permissions.' ) );
		}

		$name = sanitize_text_field( $_POST['name'] ?? '' );
		$slug = sanitize_title( $_POST['slug'] ?? $name );

		if ( empty( $name ) || empty( $slug ) ) {
			wp_send_json_error( array( 'message' => 'Name and slug are required.' ) );
		}

		$id = ( new Puck_Press_Roster_Registry_Wpdb_Utils() )->create_roster( $name, $slug );

		if ( $id === 'duplicate_slug' ) {
			wp_send_json_error( array( 'message' => "A roster with slug \"{$slug}\" already exists. Choose a different name." ) );
		}

		if ( ! $id ) {
			wp_send_json_error( array( 'message' => 'Failed to create roster.' ) );
		}

		wp_send_json_success( array( 'id' => $id ) );
	}

	public static function pp_ajax_delete_new_roster(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => 'Insufficient permissions.' ) );
		}

		$roster_id = (int) ( $_POST['roster_id'] ?? 0 );
		if ( ! $roster_id ) {
			wp_send_json_error( array( 'message' => 'Invalid roster ID.' ) );
		}

		$deleted = ( new Puck_Press_Roster_Registry_Wpdb_Utils() )->delete_roster( $roster_id );

		if ( ! $deleted ) {
			wp_send_json_error( array( 'message' => 'Cannot delete this roster (may be the main roster).' ) );
		}

		wp_send_json_success();
	}

	public static function pp_ajax_add_team_to_roster(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => 'Insufficient permissions.' ) );
		}

		$roster_id = (int) ( $_POST['roster_id'] ?? 0 );
		$team_id   = (int) ( $_POST['team_id'] ?? 0 );

		if ( ! $roster_id || ! $team_id ) {
			wp_send_json_error( array( 'message' => 'Invalid IDs.' ) );
		}

		( new Puck_Press_Roster_Registry_Wpdb_Utils() )->add_team_to_roster( $roster_id, $team_id );

		wp_send_json_success();
	}

	public static function pp_ajax_remove_team_from_roster(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => 'Insufficient permissions.' ) );
		}

		$roster_id = (int) ( $_POST['roster_id'] ?? 0 );
		$team_id   = (int) ( $_POST['team_id'] ?? 0 );

		if ( ! $roster_id || ! $team_id ) {
			wp_send_json_error( array( 'message' => 'Invalid IDs.' ) );
		}

		( new Puck_Press_Roster_Registry_Wpdb_Utils() )->remove_team_from_roster( $roster_id, $team_id );

		wp_send_json_success();
	}

	public static function pp_ajax_set_active_new_roster_id(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => 'Insufficient permissions.' ) );
		}

		$roster_id = (int) ( $_POST['roster_id'] ?? 0 );
		update_option( 'pp_admin_active_new_roster_id', $roster_id );

		wp_send_json_success();
	}

	public static function pp_ajax_get_roster_preview(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => 'Insufficient permissions.' ) );
		}

		$roster_id = (int) ( $_POST['roster_id'] ?? 0 );
		if ( ! $roster_id ) {
			wp_send_json_error( array( 'message' => 'Invalid roster ID.' ) );
		}

		$registry     = new Puck_Press_Roster_Registry_Wpdb_Utils();
		$roster       = $registry->get_roster_by_id( $roster_id );
		$is_main      = $roster ? (int) $roster['is_main'] === 1 : false;
		$roster_teams = $registry->get_roster_teams( $roster_id );
		$avail_teams  = $is_main ? array() : $registry->get_available_teams_for_roster( $roster_id );
		$roster_slug  = $roster['slug'] ?? 'default';
		$shortcode    = '[pp-roster' . ( $roster_slug !== 'default' ? ' roster="' . esc_attr( $roster_slug ) . '"' : '' ) . ']';

		global $wpdb;

		$team_ids_for_roster          = $registry->get_roster_team_ids( $roster_id );
		$all_teams_in_db              = $wpdb->get_results( "SELECT id, slug FROM {$wpdb->prefix}pp_teams", ARRAY_A ) ?? array();
		$total_display_rows           = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->prefix}pp_team_players_display" );
		$distinct_team_ids_in_display = $wpdb->get_col( "SELECT DISTINCT team_id FROM {$wpdb->prefix}pp_team_players_display" ) ?? array();
		$display_rows_by_team         = array();
		foreach ( $all_teams_in_db as $t ) {
			$display_rows_by_team[ $t['id'] . ':' . $t['slug'] ] = (int) $wpdb->get_var(
				$wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->prefix}pp_team_players_display WHERE team_id = %d", $t['id'] )
			);
		}

		$preview_card       = new Puck_Press_Roster_Admin_Preview_Card( array(), $roster_id );
		$preview_card->init();
		$all_templates_html = $preview_card->get_all_templates_html();

		$display_rows_for_roster_teams = array();
		foreach ( $team_ids_for_roster as $tid ) {
			$display_rows_for_roster_teams[ $tid ] = (int) $wpdb->get_var(
				$wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->prefix}pp_team_players_display WHERE team_id = %d", $tid )
			);
		}

		$template_manager = new Puck_Press_Roster_Template_Manager( $roster_id );
		$template_colors  = array(
			'rosterTemplates'   => $template_manager->get_all_template_colors(),
			'colorLabels'       => $template_manager->get_all_template_color_labels(),
			'fontSettings'      => $template_manager->get_all_template_fonts(),
			'fontLabels'        => $template_manager->get_all_template_font_labels(),
			'selected_template' => $template_manager->get_current_template_key(),
		);

		error_log( '[PP Roster AJAX] roster_id=' . $roster_id . ' is_main=' . ( $is_main ? 'true' : 'false' ) . ' team_ids=[' . implode( ', ', $team_ids_for_roster ) . '] total_display_rows=' . $total_display_rows . ' all_templates_html_length=' . strlen( $all_templates_html ) );

		wp_send_json_success( array(
			'roster'              => $roster,
			'is_main'             => $is_main,
			'roster_teams'        => $roster_teams,
			'available_teams'     => $avail_teams,
			'shortcode'           => $shortcode,
			'all_templates_html'  => $all_templates_html,
			'template_colors'     => $template_colors,
			'debug'               => array(
				'roster_id'                     => $roster_id,
				'is_main'                       => $is_main,
				'team_ids_for_roster'           => $team_ids_for_roster,
				'all_teams_in_db'               => $all_teams_in_db,
				'total_display_rows'            => $total_display_rows,
				'distinct_team_ids_in_display'  => $distinct_team_ids_in_display,
				'display_rows_by_team'          => $display_rows_by_team,
				'display_rows_for_roster_teams' => $display_rows_for_roster_teams,
				'all_templates_html_length'     => strlen( $all_templates_html ),
			),
		) );
	}

	public static function pp_ajax_add_team_roster_source(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => 'Insufficient permissions.' ) );
		}

		$team_id = (int) ( $_POST['team_id'] ?? 0 );
		if ( ! $team_id ) {
			wp_send_json_error( array( 'message' => 'Invalid team ID.' ) );
		}

		$name        = sanitize_text_field( $_POST['name'] ?? '' );
		$type        = sanitize_text_field( $_POST['type'] ?? '' );
		$active      = (int) ( $_POST['active'] ?? 1 );
		$season_year = sanitize_text_field( $_POST['season_year'] ?? '' );

		$url = $csv_content = $other_data = null;

		$stat_period    = sanitize_text_field( $_POST['stat_period'] ?? '' );
		$other_data_arr = array();
		if ( ! empty( $stat_period ) ) {
			$other_data_arr['stat_period'] = $stat_period;
		}

		switch ( $type ) {
			case 'achaRosterUrl':
				$acha_team_id   = sanitize_text_field( $_POST['team_id_url'] ?? '' );
				$acha_season_id = sanitize_text_field( $_POST['season_id'] ?? '' );
				$auto_discover  = ! empty( $_POST['auto_discover'] );

				if ( empty( $acha_team_id ) || empty( $acha_season_id ) ) {
					wp_send_json_error( array( 'message' => 'Team ID and Season ID are required.' ) );
				}

				require_once plugin_dir_path( __DIR__ ) . 'includes/schedule/class-puck-press-acha-season-discoverer.php';
				$meta = Puck_Press_Acha_Season_Discoverer::get_team_season_meta( $acha_team_id, $acha_season_id, $auto_discover );
				if ( is_wp_error( $meta ) ) {
					wp_send_json_error( array( 'message' => $meta->get_error_message() ) );
				}

				$name = $meta['season_name'];
				$url  = $acha_team_id;

				unset( $other_data_arr['stat_period'] );
				$other_data_arr['season_id']     = $acha_season_id;
				$other_data_arr['include_stats'] = ! empty( $_POST['include_stats'] );
				if ( $auto_discover ) {
					$other_data_arr['auto_discover']   = true;
					$other_data_arr['seed_season_id']  = $acha_season_id;
					$other_data_arr['seed_start_date'] = $meta['start_date'];
				}
				break;

			case 'usphlRosterUrl':
				$url       = sanitize_text_field( $_POST['team_id_usphl'] ?? '' );
				$season_id = sanitize_text_field( $_POST['season_id'] ?? '' );
				if ( ! empty( $season_id ) ) {
					$other_data_arr['season_id'] = $season_id;
				}
				break;

			case 'csv':
				if ( ! isset( $_FILES['csv'] ) || $_FILES['csv']['error'] !== UPLOAD_ERR_OK ) {
					wp_send_json_error( array( 'message' => 'CSV upload failed.' ) );
				}
				$csv_content = file_get_contents( $_FILES['csv']['tmp_name'] );
				$url         = sanitize_file_name( $_FILES['csv']['name'] );
				break;

			default:
				wp_send_json_error( array( 'message' => 'Invalid source type.' ) );
		}

		$other_data = ! empty( $other_data_arr ) ? wp_json_encode( $other_data_arr ) : null;

		$importer = new Puck_Press_Team_Roster_Importer( $team_id );
		$row      = array(
			'name'               => $name,
			'type'               => $type,
			'source_url_or_path' => $url,
			'csv_data'           => $csv_content,
			'other_data'         => $other_data,
			'status'             => $active ? 'active' : 'inactive',
			'last_updated'       => current_time( 'mysql' ),
		);
		if ( ! empty( $season_year ) ) {
			$row['season_year'] = $season_year;
		}
		$id = $importer->add_team_roster_source( $row );

		if ( ! $id ) {
			wp_send_json_error( array( 'message' => 'Failed to add roster source.' ) );
		}

		$roster_table_html = '';
		if ( $active ) {
			$importer->rebuild_team_and_cascade();
			$roster_table_html = ( new Puck_Press_Teams_Admin_Players_Table_Card( $team_id ) )->render_players_table();
		}

		wp_send_json_success( array( 'id' => $id, 'roster_table_html' => $roster_table_html ) );
	}

	public static function pp_ajax_run_acha_discovery(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => 'Insufficient permissions.' ) );
		}

		$team_id = isset( $_POST['team_id'] ) ? (int) $_POST['team_id'] : null;

		require_once plugin_dir_path( __DIR__ ) . 'includes/schedule/class-puck-press-acha-season-discoverer.php';
		require_once plugin_dir_path( __DIR__ ) . 'includes/schedule/class-puck-press-team-source-importer.php';
		require_once plugin_dir_path( __DIR__ ) . 'includes/roster/class-puck-press-team-roster-importer.php';

		$discoverer = new Puck_Press_Acha_Season_Discoverer();
		$log        = $discoverer->discover_all( $team_id );

		foreach ( $log as $entry ) {
			if ( empty( $entry['discovered'] ) || empty( $entry['team_id'] ) ) {
				continue;
			}
			$wp_team_id = (int) $entry['team_id'];
			( new Puck_Press_Team_Source_Importer( $wp_team_id ) )->rebuild_team_and_cascade();
			( new Puck_Press_Team_Roster_Importer( $wp_team_id ) )->rebuild_team_and_cascade();
		}

		$all_discovered = array_merge( ...( array_column( $log, 'discovered' ) ?: array( array() ) ) );
		$message        = empty( $all_discovered )
			? 'No new seasons found.'
			: 'Discovered: ' . implode( ', ', $all_discovered );

		wp_send_json_success( array( 'message' => $message, 'log' => $log ) );
	}

	public static function pp_ajax_delete_team_roster_source(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => 'Insufficient permissions.' ) );
		}

		$source_id = (int) ( $_POST['source_id'] ?? 0 );
		$team_id   = (int) ( $_POST['team_id'] ?? 0 );

		if ( ! $source_id || ! $team_id ) {
			wp_send_json_error( array( 'message' => 'Invalid IDs.' ) );
		}

		$importer = new Puck_Press_Team_Roster_Importer( $team_id );
		$deleted  = $importer->delete_team_roster_source( $source_id );

		if ( ! $deleted ) {
			wp_send_json_error( array( 'message' => 'Failed to delete roster source.' ) );
		}

		$importer->rebuild_team_and_cascade();
		$table_html = ( new Puck_Press_Teams_Admin_Players_Table_Card( $team_id ) )->render_players_table();
		wp_send_json_success( array( 'roster_table_html' => $table_html ) );
	}

	public static function pp_ajax_update_team_roster_source_status(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => 'Insufficient permissions.' ) );
		}

		$source_id = (int) ( $_POST['source_id'] ?? 0 );
		$team_id   = (int) ( $_POST['team_id'] ?? 0 );
		$status    = sanitize_text_field( $_POST['status'] ?? '' );

		if ( ! $source_id || ! $team_id || ! in_array( $status, array( 'active', 'inactive' ), true ) ) {
			wp_send_json_error( array( 'message' => 'Invalid request data.' ) );
		}

		global $wpdb;

		$importer = new Puck_Press_Team_Roster_Importer( $team_id );
		$importer->update_team_roster_source_status( $source_id, $status );
		$cascade = $importer->rebuild_team_and_cascade();

		$raw_count     = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->prefix}pp_team_players_raw WHERE team_id = %d", $team_id ) );
		$display_count = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->prefix}pp_team_players_display WHERE team_id = %d", $team_id ) );
		$mods_count    = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->prefix}pp_team_player_mods WHERE team_id = %d", $team_id ) );

		$table_html = ( new Puck_Press_Teams_Admin_Players_Table_Card( $team_id ) )->render_players_table();
		wp_send_json_success(
			array(
				'roster_table_html' => $table_html,
				'log'               => array(
					'cascade'       => $cascade,
					'raw_count'     => $raw_count,
					'display_count' => $display_count,
					'mods_count'    => $mods_count,
				),
			)
		);
	}

	public static function pp_ajax_add_team_player(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => 'Insufficient permissions.' ) );
		}

		global $wpdb;

		$team_id = (int) ( $_POST['team_id'] ?? 0 );
		if ( ! $team_id ) {
			wp_send_json_error( array( 'message' => 'Invalid team ID.' ) );
		}

		$edit_data = array(
			'player_id'      => 'manual_' . uniqid(),
			'number'         => sanitize_text_field( $_POST['number'] ?? '' ),
			'name'           => sanitize_text_field( $_POST['name'] ?? '' ),
			'pos'            => sanitize_text_field( $_POST['pos'] ?? '' ),
			'ht'             => sanitize_text_field( $_POST['ht'] ?? '' ),
			'wt'             => sanitize_text_field( $_POST['wt'] ?? '' ),
			'shoots'         => sanitize_text_field( $_POST['shoots'] ?? '' ),
			'hometown'       => sanitize_text_field( $_POST['hometown'] ?? '' ),
			'last_team'      => sanitize_text_field( $_POST['last_team'] ?? '' ),
			'year_in_school' => sanitize_text_field( $_POST['year_in_school'] ?? '' ),
			'major'          => sanitize_text_field( $_POST['major'] ?? '' ),
			'headshot_link'  => esc_url_raw( $_POST['headshot_link'] ?? '' ),
			'hero_image_url' => esc_url_raw( $_POST['hero_image_url'] ?? '' ),
		);

		$wpdb->insert(
			$wpdb->prefix . 'pp_team_player_mods',
			array(
				'team_id'     => $team_id,
				'edit_action' => 'insert',
				'edit_data'   => wp_json_encode( $edit_data ),
				'created_at'  => current_time( 'mysql' ),
				'updated_at'  => current_time( 'mysql' ),
			)
		);

		$importer = new Puck_Press_Team_Roster_Importer( $team_id );
		$importer->rebuild_display_from_mods();

		$table_html = ( new Puck_Press_Teams_Admin_Players_Table_Card( $team_id ) )->render_players_table();
		wp_send_json_success( array( 'roster_table_html' => $table_html ) );
	}

	public static function pp_ajax_edit_team_player(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => 'Insufficient permissions.' ) );
		}

		global $wpdb;

		$team_id   = (int) ( $_POST['team_id'] ?? 0 );
		$player_id = sanitize_text_field( $_POST['player_id'] ?? '' );

		if ( ! $team_id || ! $player_id ) {
			wp_send_json_error( array( 'message' => 'Invalid team or player ID.' ) );
		}

		$edit_data = array(
			'number'         => sanitize_text_field( $_POST['number'] ?? '' ),
			'name'           => sanitize_text_field( $_POST['name'] ?? '' ),
			'pos'            => sanitize_text_field( $_POST['pos'] ?? '' ),
			'ht'             => sanitize_text_field( $_POST['ht'] ?? '' ),
			'wt'             => sanitize_text_field( $_POST['wt'] ?? '' ),
			'shoots'         => sanitize_text_field( $_POST['shoots'] ?? '' ),
			'hometown'       => sanitize_text_field( $_POST['hometown'] ?? '' ),
			'last_team'      => sanitize_text_field( $_POST['last_team'] ?? '' ),
			'year_in_school' => sanitize_text_field( $_POST['year_in_school'] ?? '' ),
			'major'          => sanitize_text_field( $_POST['major'] ?? '' ),
			'headshot_link'  => esc_url_raw( $_POST['headshot_link'] ?? '' ),
			'hero_image_url' => esc_url_raw( $_POST['hero_image_url'] ?? '' ),
		);

		$wpdb->insert(
			$wpdb->prefix . 'pp_team_player_mods',
			array(
				'team_id'     => $team_id,
				'external_id' => $player_id,
				'edit_action' => 'update',
				'edit_data'   => wp_json_encode( $edit_data ),
				'created_at'  => current_time( 'mysql' ),
				'updated_at'  => current_time( 'mysql' ),
			)
		);

		$importer = new Puck_Press_Team_Roster_Importer( $team_id );
		$importer->rebuild_display_from_mods();

		$table_html = ( new Puck_Press_Teams_Admin_Players_Table_Card( $team_id ) )->render_players_table();
		wp_send_json_success( array( 'roster_table_html' => $table_html ) );
	}

	public static function pp_ajax_delete_team_player(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => 'Insufficient permissions.' ) );
		}

		global $wpdb;

		$team_id   = (int) ( $_POST['team_id'] ?? 0 );
		$player_id = sanitize_text_field( $_POST['player_id'] ?? '' );

		if ( ! $team_id || ! $player_id ) {
			wp_send_json_error( array( 'message' => 'Invalid team or player ID.' ) );
		}

		$wpdb->insert(
			$wpdb->prefix . 'pp_team_player_mods',
			array(
				'team_id'     => $team_id,
				'external_id' => $player_id,
				'edit_action' => 'delete',
				'edit_data'   => '{}',
				'created_at'  => current_time( 'mysql' ),
				'updated_at'  => current_time( 'mysql' ),
			)
		);

		$importer = new Puck_Press_Team_Roster_Importer( $team_id );
		$importer->rebuild_display_from_mods();

		$table_html = ( new Puck_Press_Teams_Admin_Players_Table_Card( $team_id ) )->render_players_table();
		wp_send_json_success( array( 'roster_table_html' => $table_html ) );
	}

	public function register_ajax_hooks() {

		$game_summary_post_display = new Puck_Press_Admin_Game_Summary_Post_Display();
		add_action( 'wp_ajax_pp_test_openai_api', array( $game_summary_post_display, 'ajax_test_openai' ) );
		add_action( 'wp_ajax_pp_test_image_api', array( $game_summary_post_display, 'ajax_test_image' ) );
		add_action( 'wp_ajax_pp_create_game_summary', array( $game_summary_post_display, 'pp_create_game_summary' ) );

		$insta_post_display = new Puck_Press_Admin_Instagram_Post_Importer_Display();
		add_action( 'wp_ajax_pp_save_team_handle', array( $insta_post_display, 'ajax_save_team_handle' ) );
		add_action( 'wp_ajax_pp_get_team_example_posts', array( $insta_post_display, 'ajax_get_team_example_posts' ) );
		add_action( 'wp_ajax_pp_create_team_insta_post', array( $insta_post_display, 'ajax_create_team_insta_post' ) );
		// Registered in register_insta_loopback_hooks() on 'init' instead.

		// Register the AJAX action for refreshing all sources
		add_action( 'wp_ajax_pp_refresh_schedule_sources', array( $this, 'pp_ajax_refresh_schedule_sources' ) );
		add_action( 'wp_ajax_pp_fix_roster_databases', array( $this, 'ajax_fix_roster_databases_callback' ) );

		// Teams CRUD
		add_action( 'wp_ajax_pp_create_team', array( self::class, 'pp_ajax_create_team' ) );
		add_action( 'wp_ajax_pp_update_team', array( self::class, 'pp_ajax_update_team' ) );
		add_action( 'wp_ajax_pp_generate_team_pages', array( self::class, 'pp_ajax_generate_team_pages' ) );
		add_action( 'wp_ajax_pp_delete_team_pages', array( self::class, 'pp_ajax_delete_team_pages' ) );
		add_action( 'wp_ajax_pp_delete_team', array( self::class, 'pp_ajax_delete_team' ) );
		add_action( 'wp_ajax_pp_set_active_team_id', array( self::class, 'pp_ajax_set_active_team_id' ) );
		add_action( 'wp_ajax_pp_switch_active_team', array( self::class, 'pp_ajax_switch_active_team' ) );
		add_action( 'wp_ajax_pp_add_team_source', array( self::class, 'pp_ajax_add_team_source' ) );
		add_action( 'wp_ajax_pp_update_team_source_status', array( self::class, 'pp_ajax_update_team_source_status' ) );
		add_action( 'wp_ajax_pp_delete_team_source', array( self::class, 'pp_ajax_delete_team_source' ) );
		add_action( 'wp_ajax_pp_run_acha_discovery', array( self::class, 'pp_ajax_run_acha_discovery' ) );
		add_action( 'wp_ajax_pp_refresh_team', array( $this, 'ajax_refresh_team_callback' ) );

		// New schedules CRUD (pp_schedules table)
		add_action( 'wp_ajax_pp_create_new_schedule', array( self::class, 'pp_ajax_create_new_schedule' ) );
		add_action( 'wp_ajax_pp_delete_new_schedule', array( self::class, 'pp_ajax_delete_new_schedule' ) );
		add_action( 'wp_ajax_pp_set_active_new_schedule_id', array( self::class, 'pp_ajax_set_active_new_schedule_id' ) );
		add_action( 'wp_ajax_pp_add_team_to_schedule', array( self::class, 'pp_ajax_add_team_to_schedule' ) );
		add_action( 'wp_ajax_pp_remove_team_from_schedule', array( self::class, 'pp_ajax_remove_team_from_schedule' ) );
		add_action( 'wp_ajax_pp_get_schedule_preview', array( self::class, 'pp_ajax_get_schedule_preview' ) );

		// Archive
		add_action( 'wp_ajax_pp_archive_all_teams_season', array( self::class, 'pp_ajax_archive_all_teams_season' ) );
		add_action( 'wp_ajax_pp_wipe_all_teams_season_data', array( self::class, 'pp_ajax_wipe_all_teams_season_data' ) );
		add_action( 'wp_ajax_pp_delete_team_archive', array( self::class, 'pp_ajax_delete_team_archive' ) );

		// Game edits
		add_action( 'wp_ajax_pp_get_game_data', array( self::class, 'pp_ajax_get_game_data' ) );
		add_action( 'wp_ajax_pp_save_game_edit', array( self::class, 'pp_ajax_save_game_edit' ) );
		add_action( 'wp_ajax_pp_delete_game', array( self::class, 'pp_ajax_delete_game' ) );
		add_action( 'wp_ajax_pp_restore_game', array( self::class, 'pp_ajax_restore_game' ) );
		add_action( 'wp_ajax_pp_revert_game_field', array( self::class, 'pp_ajax_revert_game_field' ) );

		// Roster (team-based) CRUD
		add_action( 'wp_ajax_pp_ajax_refresh_roster_sources', array( self::class, 'pp_ajax_refresh_roster_sources' ) );
		add_action( 'wp_ajax_pp_create_new_roster', array( self::class, 'pp_ajax_create_new_roster' ) );
		add_action( 'wp_ajax_pp_delete_new_roster', array( self::class, 'pp_ajax_delete_new_roster' ) );
		add_action( 'wp_ajax_pp_add_team_to_roster', array( self::class, 'pp_ajax_add_team_to_roster' ) );
		add_action( 'wp_ajax_pp_remove_team_from_roster', array( self::class, 'pp_ajax_remove_team_from_roster' ) );
		add_action( 'wp_ajax_pp_set_active_new_roster_id', array( self::class, 'pp_ajax_set_active_new_roster_id' ) );
		add_action( 'wp_ajax_pp_get_roster_preview', array( self::class, 'pp_ajax_get_roster_preview' ) );
		add_action( 'wp_ajax_pp_add_team_roster_source', array( self::class, 'pp_ajax_add_team_roster_source' ) );
		add_action( 'wp_ajax_pp_delete_team_roster_source', array( self::class, 'pp_ajax_delete_team_roster_source' ) );
		add_action( 'wp_ajax_pp_update_team_roster_source_status', array( self::class, 'pp_ajax_update_team_roster_source_status' ) );
		add_action( 'wp_ajax_pp_refresh_team_roster', array( self::class, 'pp_ajax_refresh_team_roster' ) );
		add_action( 'wp_ajax_pp_add_team_player', array( self::class, 'pp_ajax_add_team_player' ) );
		add_action( 'wp_ajax_pp_edit_team_player', array( self::class, 'pp_ajax_edit_team_player' ) );
		add_action( 'wp_ajax_pp_delete_team_player', array( self::class, 'pp_ajax_delete_team_player' ) );

		$players_card = new Puck_Press_Teams_Admin_Players_Table_Card();
		add_action( 'wp_ajax_pp_add_team_manual_player_and_table', array( $players_card, 'ajax_add_team_manual_player_and_table_callback' ) );
		add_action( 'wp_ajax_pp_get_team_player_data', array( $players_card, 'ajax_get_team_player_data_callback' ) );
		add_action( 'wp_ajax_pp_update_team_player_edit', array( $players_card, 'ajax_update_team_player_edit_callback' ) );
		add_action( 'wp_ajax_pp_revert_team_player_field', array( $players_card, 'ajax_revert_team_player_field_callback' ) );
		add_action( 'wp_ajax_pp_restore_team_player', array( $players_card, 'ajax_restore_team_player_callback' ) );
		add_action( 'wp_ajax_pp_delete_team_manual_player', array( $players_card, 'ajax_delete_team_manual_player_callback' ) );
		add_action( 'wp_ajax_pp_reset_all_team_player_edits', array( $players_card, 'ajax_reset_all_team_player_edits_callback' ) );
		add_action( 'wp_ajax_pp_bulk_update_team_player_field', array( $players_card, 'ajax_bulk_update_team_player_field_callback' ) );
		add_action( 'wp_ajax_pp_bulk_revert_team_player_edits', array( $players_card, 'ajax_bulk_revert_team_player_edits_callback' ) );

		// DB management
		add_action( 'wp_ajax_pp_wipe_and_recreate_db', array( self::class, 'pp_ajax_wipe_and_recreate_db' ) );

		add_action( 'wp_ajax_pp_set_active_roster_id', array( self::class, 'pp_ajax_set_active_roster_id' ) );

		// Register the AJAX action for saving colors in color picker
		add_action( 'wp_ajax_puck_press_update_schedule_colors', array( self::class, 'pp_ajax_update_schedule_template_colors' ) );
		add_action( 'wp_ajax_puck_press_update_slider_colors', array( self::class, 'pp_ajax_update_slider_template_colors' ) );
		add_action( 'wp_ajax_puck_press_update_roster_colors', array( self::class, 'pp_ajax_update_roster_template_colors' ) );
		add_action( 'wp_ajax_puck_press_update_record_colors', array( self::class, 'pp_ajax_update_record_template_colors' ) );
		add_action( 'wp_ajax_puck_press_update_stats_colors', array( self::class, 'pp_ajax_update_stats_template_colors' ) );
		add_action( 'wp_ajax_puck_press_update_awards_colors', array( self::class, 'pp_ajax_update_awards_template_colors' ) );
		add_action( 'wp_ajax_puck_press_update_post_slider_colors', array( self::class, 'pp_ajax_update_post_slider_template_colors' ) );
		add_action( 'wp_ajax_puck_press_update_league_news_colors', array( self::class, 'pp_ajax_update_league_news_template_colors' ) );
		add_action( 'wp_ajax_pp_save_league_news_settings', array( self::class, 'pp_ajax_save_league_news_settings' ) );
		add_action( 'wp_ajax_pp_get_league_news_preview', array( self::class, 'pp_ajax_get_league_news_preview' ) );
		add_action( 'wp_ajax_pp_save_stats_column_settings', array( self::class, 'pp_ajax_save_stats_column_settings' ) );
		add_action( 'wp_ajax_pp_get_archive_stats', array( self::class, 'pp_ajax_get_archive_stats' ) );
		add_action( 'wp_ajax_nopriv_pp_get_archive_stats', array( self::class, 'pp_ajax_get_archive_stats' ) );
		add_action( 'wp_ajax_puck_press_update_player_detail_colors', array( self::class, 'pp_ajax_update_player_detail_colors' ) );
		add_action( 'wp_ajax_pp_update_stat_leaders_colors', array( self::class, 'pp_ajax_update_stat_leaders_colors' ) );
		add_action( 'wp_ajax_pp_save_stat_leaders_settings', array( self::class, 'pp_ajax_save_stat_leaders_settings' ) );
		add_action( 'wp_ajax_pp_set_active_stats_roster_id', array( self::class, 'pp_ajax_set_active_stats_roster_id' ) );

		// Awards CRUD
		add_action( 'wp_ajax_pp_create_award', array( self::class, 'pp_ajax_create_award' ) );
		add_action( 'wp_ajax_pp_update_award', array( self::class, 'pp_ajax_update_award' ) );
		add_action( 'wp_ajax_pp_delete_award', array( self::class, 'pp_ajax_delete_award' ) );
		add_action( 'wp_ajax_pp_search_award_players', array( self::class, 'pp_ajax_search_award_players' ) );
		add_action( 'wp_ajax_pp_add_award_player', array( self::class, 'pp_ajax_add_award_player' ) );
		add_action( 'wp_ajax_pp_remove_award_player', array( self::class, 'pp_ajax_remove_award_player' ) );
		add_action( 'wp_ajax_pp_get_parent_names', array( self::class, 'pp_ajax_get_parent_names' ) );
		add_action( 'wp_ajax_pp_bulk_add_team_to_award', array( self::class, 'pp_ajax_bulk_add_team_to_award' ) );
		add_action( 'wp_ajax_pp_toggle_award_visibility', array( self::class, 'pp_ajax_toggle_award_visibility' ) );
		add_action( 'wp_ajax_pp_get_teams_for_awards', array( self::class, 'pp_ajax_get_teams_for_awards' ) );
	}

	public function register_insta_loopback_hooks() {
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/instagram-post-importer/class-puck-press-instagram-post-importer.php';
		add_action( 'wp_ajax_nopriv_pp_run_team_insta_import', array( 'Puck_Press_Instagram_Post_Importer', 'handle_loopback_team_import' ) );
		add_action( 'wp_ajax_pp_run_team_insta_import', array( 'Puck_Press_Instagram_Post_Importer', 'handle_loopback_team_import' ) );
	}

	// ── Awards AJAX Handlers ─────────────────────────────────────────────────

	private static function pp_awards_check(): Puck_Press_Awards_Wpdb_Utils {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => 'Insufficient permissions.' ) );
		}
		check_ajax_referer( 'pp_awards_nonce', 'nonce' );
		return new Puck_Press_Awards_Wpdb_Utils();
	}

	public static function pp_ajax_create_award(): void {
		$utils  = self::pp_awards_check();
		$result = $utils->create_award( $_POST );
		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ) );
		}
		wp_send_json_success( array( 'id' => $result ) );
	}

	public static function pp_ajax_update_award(): void {
		$utils  = self::pp_awards_check();
		$id     = absint( $_POST['id'] ?? 0 );
		$result = $utils->update_award( $id, $_POST );
		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ) );
		}
		wp_send_json_success();
	}

	public static function pp_ajax_delete_award(): void {
		$utils = self::pp_awards_check();
		$id    = absint( $_POST['id'] ?? 0 );
		if ( $utils->delete_award( $id ) ) {
			wp_send_json_success();
		}
		wp_send_json_error( array( 'message' => 'Failed to delete award.' ) );
	}

	public static function pp_ajax_search_award_players(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => 'Insufficient permissions.' ) );
		}
		check_ajax_referer( 'pp_awards_nonce', 'nonce' );

		$q = sanitize_text_field( wp_unslash( $_GET['q'] ?? '' ) );
		if ( mb_strlen( $q ) < 2 ) {
			wp_send_json_success( array( 'results' => array() ) );
		}

		$utils   = new Puck_Press_Awards_Wpdb_Utils();
		$players = $utils->search_players( $q );
		$results = array();

		foreach ( $players as $p ) {
			$team_id  = (int) $p['team_id'];
			$logo_url = $utils->resolve_team_logo( $team_id );
			$results[] = array(
				'id'            => $p['player_id'] . '|' . $team_id,
				'text'          => $p['name'] . ' — ' . $p['team_name'] . ' (' . $p['pos'] . ')',
				'player_id'     => $p['player_id'],
				'team_id'       => $team_id,
				'name'          => $p['name'],
				'team_name'     => $p['team_name'],
				'pos'           => $p['pos'],
				'headshot_url'  => $p['headshot_link'] ?? '',
				'team_logo_url' => $logo_url ?? '',
			);
		}

		wp_send_json_success( array( 'results' => $results ) );
	}

	public static function pp_ajax_add_award_player(): void {
		$utils  = self::pp_awards_check();
		$result = $utils->add_player( $_POST );
		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ) );
		}
		wp_send_json_success( array( 'id' => $result ) );
	}

	public static function pp_ajax_remove_award_player(): void {
		$utils = self::pp_awards_check();
		$id    = absint( $_POST['id'] ?? 0 );
		if ( $utils->remove_player( $id ) ) {
			wp_send_json_success();
		}
		wp_send_json_error( array( 'message' => 'Failed to remove player.' ) );
	}

	public static function pp_ajax_get_parent_names(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => 'Insufficient permissions.' ) );
		}
		check_ajax_referer( 'pp_awards_nonce', 'nonce' );
		$utils = new Puck_Press_Awards_Wpdb_Utils();
		wp_send_json_success( array( 'parents' => $utils->get_distinct_parent_names() ) );
	}

	public static function pp_ajax_bulk_add_team_to_award(): void {
		$utils    = self::pp_awards_check();
		$award_id = absint( $_POST['award_id'] ?? 0 );
		$team_id  = absint( $_POST['team_id'] ?? 0 );

		if ( ! $award_id || ! $team_id ) {
			wp_send_json_error( array( 'message' => 'Award ID and Team ID are required.' ) );
		}

		$result = $utils->bulk_add_team_players( $award_id, $team_id );
		wp_send_json_success( $result );
	}

	public static function pp_ajax_toggle_award_visibility(): void {
		$utils    = self::pp_awards_check();
		$id       = absint( $_POST['id'] ?? 0 );
		$visible  = (int) ( $_POST['show_in_shortcode'] ?? 1 );
		$result   = $utils->update_award( $id, array( 'show_in_shortcode' => $visible ) );

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ) );
		}
		wp_send_json_success();
	}

	public static function pp_ajax_get_teams_for_awards(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => 'Insufficient permissions.' ) );
		}
		check_ajax_referer( 'pp_awards_nonce', 'nonce' );

		$teams_utils = new Puck_Press_Teams_Wpdb_Utils();
		$teams       = $teams_utils->get_all_teams();
		$results     = array();
		foreach ( $teams as $t ) {
			$results[] = array(
				'id'   => (int) $t['id'],
				'text' => $t['name'],
			);
		}
		wp_send_json_success( array( 'teams' => $results ) );
	}
}
