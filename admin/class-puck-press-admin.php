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
class Puck_Press_Admin
{

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
	 * @param      string    $plugin_name       The name of this plugin.
	 * @param      string    $version    The version of this plugin.
	 */
	public function __construct($plugin_name, $version)
	{
		$this->plugin_name = $plugin_name;
		$this->version = $version;

		add_action('admin_menu', [$this, 'add_admin_menu']);
		$this->check_for_updates();
	}

	// Add Admin Menu Page
	public function add_admin_menu()
	{
		add_menu_page(
			__('Puck Press Settings', 'puck-press'),
			__('Puck Press', 'puck-press'),
			'manage_options',
			'puck-press',
			[$this, 'display_admin_page'],
			'dashicons-admin-generic',
			80
		);

		add_submenu_page(
			'puck-press',
			__('Puck Press Settings', 'puck-press'),
			__('Settings', 'puck-press'),
			'manage_options',
			'puck-press',
			[$this, 'display_admin_page']
		);

		add_submenu_page(
			'puck-press',
			__('Instagram Posts', 'puck-press'),
			__('Instagram Posts', 'puck-press'),
			'manage_options',
			'edit.php?post_type=pp_insta_post',
			''
		);

		add_submenu_page(
			'puck-press',
			__('Game Recaps', 'puck-press'),
			__('Game Recaps', 'puck-press'),
			'manage_options',
			'edit.php?post_type=pp_game_summary',
			''
		);
	}

	// Display Admin Page
	public function display_admin_page()
	{
		if (!current_user_can('manage_options')) {
			return;
		}

		include plugin_dir_path(dirname(__FILE__)) . 'admin/components/puck-press-admin-display.php';
	}

	public function ajax_refresh_all_sources_callback()
	{
		global $wpdb;
		$schedule_id = (int) ($_POST['schedule_id'] ?? 1);
		$utils = new Puck_Press_Schedule_Wpdb_Utils;
		$utils->delete_rows_for_schedule('pp_game_schedule_raw', $schedule_id);
		$utils->delete_rows_for_schedule('pp_game_schedule_for_display', $schedule_id);

		$importer = new Puck_Press_Schedule_Source_Importer($schedule_id);
		$raw_table_results = $importer->populate_raw_schedule_table_from_sources();
		$display_game_table_results = $importer->apply_edits_and_save_to_display_table();

		$refresh_game_table_ui = new Puck_Press_Schedule_Admin_Games_Table_Card([], $schedule_id);
		$refreshed_game_table_ui = $refresh_game_table_ui->render_game_schedule_admin_preview();

		$refresh_game_preview = Puck_Press_Schedule_Admin_Preview_Card::create_and_init($schedule_id);
		$refresh_game_preview_html = $refresh_game_preview->get_all_templates_html();

		$refresh_slider_preview = Puck_Press_Schedule_Admin_Slider_Preview_Card::create_and_init($schedule_id);
		$refresh_slider_preview_html = $refresh_slider_preview->get_all_templates_html();


		$response_data = array(
			'raw_game_table_results'      => $raw_table_results,
			'display_game_table_results'  => $display_game_table_results,
			'refreshed_game_table_ui'     => $refreshed_game_table_ui,
			'refreshed_game_preview_html' => $refresh_game_preview_html,
			'refreshed_slider_preview_html' => $refresh_slider_preview_html,
		);

		// "No active sources" is a valid state (all sources toggled off). Send success
		// so the JS updates the DOM with the now-empty preview HTML.
		$no_active_sources = is_array( $raw_table_results )
			&& in_array( 'No active sources to import.', $raw_table_results['messages'] ?? [] );

		if ( $no_active_sources || ( $raw_table_results !== false && $display_game_table_results !== false ) ) {
			if ( $no_active_sources ) {
				$response_data['message'] = 'No active sources to import.';
			}
			wp_send_json_success( $response_data );
		} else {
			wp_send_json_error( array(
				'message'                    => 'Failed to refresh data sources from database',
				'error'                      => $wpdb->last_error,
				'raw_game_table_results'     => $raw_table_results,
				'display_game_table_results' => $display_game_table_results,
			) );
			wp_die();
		}
	}

	public function ajax_refresh_all_roster_sources_callback()
	{
		global $wpdb;
		$utils = new Puck_Press_Roster_Wpdb_Utils;
		$utils->reset_table('pp_roster_raw');
		$utils->reset_table('pp_roster_for_display');
		$utils->reset_table('pp_roster_stats');
		$utils->reset_table('pp_roster_goalie_stats');

		$importer = new Puck_Press_Roster_Source_Importer();
		$raw_table_results = $importer->populate_raw_roster_table_from_sources();
		$display_roster_table_results = $importer->apply_edits_and_save_to_display_table();
		$importer->sanitize_roster_display_table();

		$refresh_roster_preview = Puck_Press_Roster_Admin_Preview_Card::create_and_init();
		$refresh_roster_preview_html = $refresh_roster_preview->get_all_templates_html();

		$refresh_edits_table = new Puck_Press_Roster_Admin_Edits_Table_Card();
		$refreshed_edits_table_html = $refresh_edits_table->render_edits_table();

		$response_data = array(
			'raw_roster_table_results'     => $raw_table_results,
			'display_roster_table_results' => $display_roster_table_results,
			'refreshed_roster_preview_html' => $refresh_roster_preview_html,
			'refreshed_edits_table_html'   => $refreshed_edits_table_html,
		);

		// "No active sources" is a valid state. Send success so the JS updates
		// the DOM with the now-empty preview HTML.
		$no_active_sources = is_array( $raw_table_results )
			&& in_array( 'No active sources to import.', $raw_table_results['messages'] ?? [] );

		if ( $no_active_sources || ( $raw_table_results !== false && $display_roster_table_results !== false ) ) {
			if ( $no_active_sources ) {
				$response_data['message'] = 'No active sources to import.';
			}
			wp_send_json_success( $response_data );
		} else {
			wp_send_json_error( array(
				'message'                      => 'Failed to refresh roster data sources from database',
				'error'                        => $wpdb->last_error,
				'raw_roster_table_results'     => $raw_table_results,
				'display_roster_table_results' => $display_roster_table_results,
			) );
			wp_die();
		}
	}


	public function ajax_fix_roster_databases_callback()
	{
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( [ 'message' => 'Unauthorized' ] );
		}

		global $wpdb;
		$log = [];

		// ── Step 1: Rename reserved-word column `rank` → `stat_rank` ─────────
		$rank_migration_tables = [
			$wpdb->prefix . 'pp_roster_stats',
			$wpdb->prefix . 'pp_roster_goalie_stats',
			$wpdb->prefix . 'pp_roster_stats_archive',
			$wpdb->prefix . 'pp_roster_goalie_stats_archive',
		];

		foreach ( $rank_migration_tables as $table ) {
			$table_exists = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) );
			if ( $table_exists !== $table ) {
				$log[] = "$table: does not exist yet — will be created by schema sync.";
				continue;
			}

			$old_col = $wpdb->get_row( $wpdb->prepare(
				"SELECT COLUMN_NAME, COLUMN_TYPE FROM INFORMATION_SCHEMA.COLUMNS
				 WHERE TABLE_SCHEMA = %s AND TABLE_NAME = %s AND COLUMN_NAME = 'rank'",
				DB_NAME,
				$table
			) );

			if ( $old_col ) {
				$col_type = $old_col->COLUMN_TYPE;
				$result   = $wpdb->query( "ALTER TABLE `$table` CHANGE `rank` `stat_rank` {$col_type} DEFAULT NULL" );
				if ( $result !== false ) {
					$log[] = "$table: renamed column `rank` → `stat_rank`.";
				} else {
					$log[] = "$table: ERROR renaming `rank` — " . $wpdb->last_error;
				}
			} else {
				$new_col = $wpdb->get_var( $wpdb->prepare(
					"SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS
					 WHERE TABLE_SCHEMA = %s AND TABLE_NAME = %s AND COLUMN_NAME = 'stat_rank'",
					DB_NAME,
					$table
				) );
				if ( $new_col ) {
					$log[] = "$table: `stat_rank` already correct. No change.";
				} else {
					$log[] = "$table: neither `rank` nor `stat_rank` found — schema sync will add it.";
				}
			}
		}

		// ── Step 2: Normalize collation across all roster tables ──────────────
		// WordPress upgrades can change the default collation (e.g. utf8mb4_general_ci
		// → utf8mb4_unicode_520_ci). Tables created under different defaults end up
		// with mismatched collations, breaking JOINs on VARCHAR columns.
		$charset = $wpdb->charset ?: 'utf8mb4';
		$collate = $wpdb->collate  ?: 'utf8mb4_unicode_520_ci';

		$all_roster_tables = [
			$wpdb->prefix . 'pp_roster_data_sources',
			$wpdb->prefix . 'pp_roster_raw',
			$wpdb->prefix . 'pp_roster_mods',
			$wpdb->prefix . 'pp_roster_for_display',
			$wpdb->prefix . 'pp_roster_stats',
			$wpdb->prefix . 'pp_roster_goalie_stats',
			$wpdb->prefix . 'pp_roster_archives',
			$wpdb->prefix . 'pp_roster_stats_archive',
			$wpdb->prefix . 'pp_roster_goalie_stats_archive',
		];

		foreach ( $all_roster_tables as $table ) {
			$table_exists = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) );
			if ( $table_exists !== $table ) {
				continue;
			}

			$current_collation = $wpdb->get_var( $wpdb->prepare(
				"SELECT TABLE_COLLATION FROM INFORMATION_SCHEMA.TABLES
				 WHERE TABLE_SCHEMA = %s AND TABLE_NAME = %s",
				DB_NAME,
				$table
			) );

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

		// ── Step 3: Schema sync via dbDelta ───────────────────────────────────
		$roster_utils  = new Puck_Press_Roster_Wpdb_Utils();
		$archive_utils = new Puck_Press_Roster_Archive_Wpdb_Utils();

		$roster_utils->create_all_tables();
		$log[] = 'Schema sync complete: pp_roster_* tables checked via dbDelta.';

		$archive_utils->init_tables();
		$log[] = 'Schema sync complete: pp_roster_*_archive tables checked via dbDelta.';

		wp_send_json_success( [
			'message' => 'Database fix complete.',
			'log'     => $log,
		] );
	}

	private static function update_template_colors($template_manager, $extra_updated = false)
	{
		// Check permissions
		if (!current_user_can('manage_options')) {
			wp_send_json_error(['message' => 'Insufficient permissions.']);
		}

		if (!isset($_POST['template_key'], $_POST['colors']) || !is_array($_POST['colors'])) {
			wp_send_json_error(['message' => 'Missing or invalid parameters.']);
		}

		$template_key = sanitize_key($_POST['template_key']);

		$colors = [];
		foreach ($_POST['colors'] as $key => $value) {
			$color_key = sanitize_key($key);
			$color_value = sanitize_hex_color($value);
			if ($color_value) {
				$colors[$color_key] = $color_value;
			}
		}

		// Fonts — optional payload alongside colors, sanitized as text (not hex).
		$fonts_updated = false;
		if (!empty($_POST['fonts']) && is_array($_POST['fonts'])) {
			$fonts = [];
			foreach ($_POST['fonts'] as $key => $value) {
				$fonts[sanitize_key($key)] = sanitize_text_field($value);
			}
			$fonts_updated = $template_manager->save_template_fonts($template_key, $fonts);
		}

		$colors_updated = $template_manager->save_template_colors($template_key, $colors);
		$template_key_changed = $template_manager->set_current_template_key($template_key);

		if ($colors_updated || $template_key_changed || $fonts_updated || $extra_updated) {
			$response = [
				'message' => 'Template updated successfully.',
				'colors'  => $colors,
			];
			if ( isset( $_POST['cal_url'] ) ) {
				$response['cal_url'] = get_option( 'pp_slider_cal_url', '' );
			}
			wp_send_json_success( $response );
		} else {
			wp_send_json_error([
				'message' => 'No changes were made to the template or colors.'
			]);
		}
	}

	public static function pp_ajax_update_schedule_template_colors()
	{
		$schedule_id = (int) ($_POST['schedule_id'] ?? 1);
		self::update_template_colors(new Puck_Press_Schedule_Template_Manager($schedule_id));
	}

	public static function pp_ajax_update_slider_template_colors()
	{
		$url_updated = false;
		if ( isset( $_POST['cal_url'] ) ) {
			$url_updated = update_option( 'pp_slider_cal_url', esc_url_raw( wp_strip_all_tags( $_POST['cal_url'] ) ) );
		}
		self::update_template_colors(new Puck_Press_Slider_Template_Manager(), $url_updated);
	}

	public static function pp_ajax_update_roster_template_colors()
	{
		self::update_template_colors(new Puck_Press_Roster_Template_Manager());
	}

	public static function pp_ajax_update_record_template_colors()
	{
		$schedule_id = isset($_POST['schedule_id']) ? (int) $_POST['schedule_id'] : 1;
		self::update_template_colors(new Puck_Press_Record_Template_Manager($schedule_id));
	}

	public static function pp_ajax_update_stats_template_colors()
	{
		self::update_template_colors(new Puck_Press_Stats_Template_Manager());
	}

	public static function pp_ajax_update_player_detail_colors(): void
	{
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( [ 'message' => 'Insufficient permissions.' ] );
			return;
		}
		$colors = isset( $_POST['colors'] ) && is_array( $_POST['colors'] ) ? $_POST['colors'] : [];
		$fonts  = isset( $_POST['fonts'] )  && is_array( $_POST['fonts'] )  ? $_POST['fonts']  : [];
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/class-puck-press-player-detail-colors.php';
		Puck_Press_Player_Detail_Colors::save( $colors, $fonts );
		wp_send_json_success( [ 'message' => 'Player page colors updated.' ] );
	}

	public static function pp_ajax_save_stats_column_settings()
	{
		if (!current_user_can('manage_options')) {
			wp_send_json_error(['message' => 'Insufficient permissions.']);
		}

		check_ajax_referer('pp_stats_columns_nonce', 'nonce');

		$allowed = [
			'show_pim', 'show_ppg', 'show_shg', 'show_gwg', 'show_pts_per_game', 'show_sh_pct',
			'show_goalie_otl', 'show_goalie_gaa', 'show_goalie_svpct', 'show_goalie_sa', 'show_goalie_saves',
		];

		$settings = [];
		foreach ($allowed as $key) {
			$settings[$key] = !empty($_POST[$key]) ? 1 : 0;
		}

		update_option('pp_stats_column_settings', $settings);

		$preview_card = new Puck_Press_Stats_Admin_Preview_Card();
		$preview_card->init();

		wp_send_json_success([
			'message'      => 'Column settings saved.',
			'preview_html' => $preview_card->get_current_template_html(),
		]);
	}

	/**
	 * Register the stylesheets for the admin area.
	 *
	 * @since    1.0.0
	 */
	public function enqueue_styles()
	{

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
		wp_enqueue_style($this->plugin_name, plugin_dir_url(__FILE__) . 'css/puck-press-admin.css', array(), $this->version, 'all');
		// Directory containing module CSS files
		$css_dir = plugin_dir_path(__FILE__) . 'css/modules/';

		// Get all CSS files from the modules folder
		foreach (glob($css_dir . '*.css') as $file) {
			$file_name = basename($file, '.css'); // Extract file name without extension

			wp_enqueue_style(
				$this->plugin_name . '-' . $file_name, // Unique handle
				plugin_dir_url(__FILE__) . 'css/modules/' . basename($file),
				array(),
				$this->version,
				'all'
			);
		}

		wp_enqueue_style('select2-css',	'https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css',	array(), '4.1.0');
	}

	/**
	 * Register the JavaScript for the admin area.
	 *
	 * @since    1.0.0
	 */
	public function enqueue_scripts()
	{
		wp_enqueue_script('puck-press-data-source-utils', plugin_dir_url(__FILE__) . 'js/puck-press-data-source-utils.js', array('jquery'), $this->version, false);
		wp_enqueue_script('puck-press-admin-shared', plugin_dir_url(__FILE__) . 'js/puck-press-admin-shared.js', array('jquery'), $this->version, false);
		wp_enqueue_script('puck-press-admin', plugin_dir_url(__FILE__) . 'js/puck-press-admin.js', array('jquery', 'puck-press-admin-shared'), $this->version, false);

		wp_enqueue_script('select2-js', 'https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js', array('jquery'), '4.1.0', true);

		// Get the current tab (sanitize as needed)
		$current_tab = isset($_GET['tab']) ? sanitize_key($_GET['tab']) : null;
		// Tab-specific scripts
		switch ($current_tab) {
			case 'record':
				wp_enqueue_script('puck-press-color-picker-shared', plugin_dir_url(__FILE__) . 'js/puck-press-color-picker-shared.js', array('jquery'), $this->version, false);
				wp_enqueue_script('puck-press-record-color-picker', plugin_dir_url(__FILE__) . 'js/record/puck-press-record-color-picker.js', array('jquery', 'select2-js', 'puck-press-color-picker-shared'), $this->version, false);
				// Enqueue saved Google Fonts and CSS vars for record templates in head to avoid FOUT on admin preview.
				$record_tm = new Puck_Press_Record_Template_Manager(isset($_GET['schedule_id']) ? (int) $_GET['schedule_id'] : 1);
				$font_vars_css = ':root {';
				foreach ( $record_tm->get_all_template_fonts() as $tpl_key => $font_set ) {
					foreach ( $font_set as $font_key => $font_name ) {
						if ( ! empty( $font_name ) ) {
							wp_enqueue_style(
								"pp-admin-gf-{$tpl_key}-{$font_key}",
								'https://fonts.googleapis.com/css2?family=' . urlencode( $font_name ) . ':wght@400;600;700;800&display=swap',
								[], null
							);
							$safe = str_replace( [ "'", '"', ';', '}' ], '', $font_name );
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
				wp_register_script('pp-player-detail', plugin_dir_url(dirname(__FILE__)) . 'public/js/pp-player-detail.js', array('jquery'), $this->version, true);
				wp_enqueue_script('puck-press-color-picker-shared', plugin_dir_url(__FILE__) . 'js/puck-press-color-picker-shared.js', array('jquery'), $this->version, false);
				wp_enqueue_script('puck-press-stats-color-picker', plugin_dir_url(__FILE__) . 'js/stats/puck-press-stats-color-picker.js', array('jquery', 'select2-js', 'puck-press-color-picker-shared'), $this->version, false);
				// Enqueue saved Google Fonts and CSS vars for stats templates in head to avoid FOUT on admin preview.
				$stats_tm = new Puck_Press_Stats_Template_Manager();
				$stats_font_vars_css = ':root {';
				foreach ( $stats_tm->get_all_template_fonts() as $tpl_key => $font_set ) {
					foreach ( $font_set as $font_key => $font_name ) {
						if ( ! empty( $font_name ) ) {
							wp_enqueue_style(
								"pp-admin-gf-{$tpl_key}-{$font_key}",
								'https://fonts.googleapis.com/css2?family=' . urlencode( $font_name ) . ':wght@400;600;700;800&display=swap',
								[], null
							);
							$safe = str_replace( [ "'", '"', ';', '}' ], '', $font_name );
							$stats_font_vars_css .= "--pp-{$tpl_key}-{$font_key}: '{$safe}', sans-serif;";
						}
					}
				}
				$stats_font_vars_css .= '}';
				if ( $stats_font_vars_css !== ':root {}' ) {
					wp_add_inline_style( 'puck-press', $stats_font_vars_css );
				}
				break;
			case 'roster':
				wp_register_script('pp-player-detail', plugin_dir_url(dirname(__FILE__)) . 'public/js/pp-player-detail.js', array('jquery'), $this->version, true);
				wp_enqueue_media();
				$roster_db_utils = new Puck_Press_Roster_Wpdb_Utils();
				$roster_db_utils->maybe_create_or_update_table('pp_roster_for_display');
				wp_enqueue_script('puck-press-roster-sources', plugin_dir_url(__FILE__) . 'js/roster/puck-press-roster-sources.js', array('jquery', 'select2-js', 'puck-press-admin-shared'), $this->version, false);
				wp_enqueue_script('puck-press-roster-edits', plugin_dir_url(__FILE__) . 'js/roster/puck-press-roster-edits.js', array('jquery', 'puck-press-admin-shared'), $this->version, false);
				wp_enqueue_script('puck-press-add-player', plugin_dir_url(__FILE__) . 'js/roster/puck-press-add-player.js', array('jquery', 'puck-press-roster-edits'), $this->version, false);
				wp_enqueue_script('puck-press-color-picker-shared', plugin_dir_url(__FILE__) . 'js/puck-press-color-picker-shared.js', array('jquery'), $this->version, false);
			wp_enqueue_script('puck-press-roster-color-picker', plugin_dir_url(__FILE__) . 'js/roster/puck-press-roster-color-picker.js', array('jquery', 'select2-js', 'puck-press-color-picker-shared'), $this->version, false);
				wp_enqueue_script('puck-press-roster-preview', plugin_dir_url(__FILE__) . 'js/roster/puck-press-roster-preview.js', array('jquery'), $this->version, false);
				wp_enqueue_script('puck-press-roster-archive', plugin_dir_url(__FILE__) . 'js/roster/puck-press-roster-archive.js', array('jquery', 'puck-press-admin-shared'), $this->version, false);
				wp_enqueue_script('puck-press-bulk-edit-roster', plugin_dir_url(__FILE__) . 'js/roster/puck-press-bulk-edit-roster.js', array('jquery', 'puck-press-admin-shared'), $this->version, false);
				break;
			case 'player-page':
				wp_enqueue_script('puck-press-color-picker-shared', plugin_dir_url(__FILE__) . 'js/puck-press-color-picker-shared.js', array('jquery'), $this->version, false);
				wp_enqueue_script('puck-press-player-page-admin', plugin_dir_url(__FILE__) . 'js/player-page/puck-press-player-page-admin.js', array('jquery', 'select2-js', 'puck-press-color-picker-shared'), filemtime(plugin_dir_path(__FILE__) . 'js/player-page/puck-press-player-page-admin.js'), true);
				break;
			case 'game-summary':
				wp_enqueue_script('pp-game-summary-admin', plugin_dir_url(__FILE__) . 'js/game-summary/puck-press-game-summary-admin.js', array('jquery'), '1.0', true);
				break;
			case 'insta-post':
				wp_enqueue_script('pp-insta-post-admin', plugin_dir_url(__FILE__) . 'js/insta-post/puck-press-insta-post-admin.js', array('jquery'), '1.0', true);
				break;
			case 'upcoming_games_table':
				//wp_enqueue_script('pp-admin-upcoming', plugin_dir_url(__FILE__) . 'admin/js/upcoming-games.js', ['jquery'], null, true);
				break;
			default: // Schedule tab (null or unspecified)
				wp_enqueue_script('puck-press-schedule-sources', plugin_dir_url(__FILE__) . 'js/schedule/puck-press-schedule-sources.js', array('jquery', 'puck-press-admin-shared'), $this->version, false);
				wp_enqueue_script('puck-press-edits-table', plugin_dir_url(__FILE__) . 'js/schedule/puck-press-edits-table.js', array('jquery', 'puck-press-admin-shared'), $this->version, false);
				wp_enqueue_script('puck-press-add-game', plugin_dir_url(__FILE__) . 'js/schedule/puck-press-add-game.js', array('jquery', 'puck-press-admin-shared', 'select2-js'), $this->version, false);
				wp_enqueue_script('puck-press-color-picker-shared', plugin_dir_url(__FILE__) . 'js/puck-press-color-picker-shared.js', array('jquery'), $this->version, false);
			wp_enqueue_script('puck-press-color-picker', plugin_dir_url(__FILE__) . 'js/schedule/puck-press-schedule-color-picker.js', array('jquery', 'puck-press-color-picker-shared'), $this->version, false);
				wp_enqueue_script('puck-press-slider-color-picker', plugin_dir_url(__FILE__) . 'js/schedule/puck-press-schedule-slider-color-picker.js', array('jquery', 'puck-press-color-picker-shared'), $this->version, false);
				wp_enqueue_script('puck-press-schedule-preview', plugin_dir_url(__FILE__) . 'js/schedule/puck-press-schedule-preview.js', array('jquery'), $this->version, false);
				wp_enqueue_script('puck-press-schedule-archive', plugin_dir_url(__FILE__) . 'js/schedule/puck-press-schedule-archive.js', array('jquery', 'puck-press-admin-shared'), $this->version, false);
				wp_enqueue_script('puck-press-bulk-edit-schedule', plugin_dir_url(__FILE__) . 'js/schedule/puck-press-bulk-edit-schedule.js', array('jquery', 'puck-press-admin-shared'), $this->version, false);
				wp_enqueue_script('puck-press-schedule-groups', plugin_dir_url(__FILE__) . 'js/schedule/puck-press-schedule-groups.js', array('jquery', 'puck-press-schedule-sources'), $this->version, false);
				break;
		}
	}

	public function localize_scripts()
	{
		$active_schedule_id   = (int) get_option('pp_admin_active_schedule_id', 1);
		$schedule_wpdb        = new Puck_Press_Schedule_Wpdb_Utils();
		$all_schedule_groups  = $schedule_wpdb->get_all_groups();
		$active_group_arr     = array_values(array_filter($all_schedule_groups, fn($g) => (int) $g['id'] === $active_schedule_id));
		$active_schedule_slug = $active_group_arr[0]['slug'] ?? 'default';

		wp_localize_script('puck-press-schedule-groups', 'ppScheduleAdmin', [
			'activeScheduleId'   => $active_schedule_id,
			'activeScheduleSlug' => $active_schedule_slug,
			'scheduleGroups'     => $all_schedule_groups,
			'nonce'              => wp_create_nonce('pp_schedule_group_nonce'),
		]);

		$template_manager = new Puck_Press_Schedule_Template_Manager($active_schedule_id);
		$scheduleTemplates = $template_manager->get_all_template_colors();
		$selected_template =  $template_manager->get_current_template_key();
		$templates = [
			'scheduleTemplates' => $scheduleTemplates,
			'colorLabels'       => $template_manager->get_all_template_color_labels(),
			'fontSettings'      => $template_manager->get_all_template_fonts(),
			'fontLabels'        => $template_manager->get_all_template_font_labels(),
			'selected_template' => $selected_template
		];
		wp_localize_script('puck-press-color-picker', 'ppScheduleTemplates', $templates);

		$slider_template_manager = new Puck_Press_Slider_Template_Manager;
		$sliderTemplates = $slider_template_manager->get_all_template_colors();
		$selected_slider_template =  $slider_template_manager->get_current_template_key();
		$templates = [
			'sliderTemplates'   => $sliderTemplates,
			'selected_template' => $selected_slider_template,
			'cal_url'           => get_option( 'pp_slider_cal_url', '' ),
		];
		wp_localize_script('puck-press-slider-color-picker', 'ppSliderTemplates', $templates);

		$roster_template_manager = new Puck_Press_Roster_Template_Manager;
		$rosterTemplates = $roster_template_manager->get_all_template_colors();
		$selected_roster_template =  $roster_template_manager->get_current_template_key();
		$roster_templates = [
			'rosterTemplates' => $rosterTemplates,
			'colorLabels'     => $roster_template_manager->get_all_template_color_labels(),
			'fontSettings'    => $roster_template_manager->get_all_template_fonts(),
			'fontLabels'      => $roster_template_manager->get_all_template_font_labels(),
			'selected_template' => $selected_roster_template
		];
		wp_localize_script('puck-press-roster-color-picker', 'ppRosterTemplates', $roster_templates);

		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/class-puck-press-player-detail-colors.php';
		global $wpdb;
		$pp_players_raw = $wpdb->get_results( "SELECT name FROM {$wpdb->prefix}pp_roster_for_display ORDER BY name ASC", ARRAY_A );
		$pp_players_list = [];
		foreach ( $pp_players_raw as $pp_p ) {
			$pp_players_list[] = [
				'name' => $pp_p['name'],
				'url'  => home_url( '/player/' . sanitize_title( $pp_p['name'] ) ),
			];
		}
		wp_localize_script( 'puck-press-player-page-admin', 'ppPlayerPageAdmin', [
			'colors'      => Puck_Press_Player_Detail_Colors::get_colors(),
			'colorLabels' => Puck_Press_Player_Detail_Colors::get_color_labels(),
			'font'        => Puck_Press_Player_Detail_Colors::get_fonts()['player-font'] ?? '',
			'fontLabel'   => 'Player Page Font',
			'players'     => $pp_players_list,
			'ajaxUrl'     => admin_url( 'admin-ajax.php' ),
		] );

		$record_schedule_id       = isset($_GET['schedule_id']) ? (int) $_GET['schedule_id'] : 1;
		$record_template_manager  = new Puck_Press_Record_Template_Manager($record_schedule_id);
		$selected_record_template = $record_template_manager->get_current_template_key();
		$record_templates = [
			'recordTemplates'   => $record_template_manager->get_all_template_colors(),
			'colorLabels'       => $record_template_manager->get_all_template_color_labels(),
			'fontSettings'      => $record_template_manager->get_all_template_fonts(),
			'fontLabels'        => $record_template_manager->get_all_template_font_labels(),
			'selected_template' => $selected_record_template,
		];
		wp_localize_script('puck-press-record-color-picker', 'ppRecordTemplates', $record_templates);
		wp_localize_script('puck-press-record-color-picker', 'ppRecordAdmin', [
			'scheduleId' => $record_schedule_id,
		]);

		$stats_template_manager  = new Puck_Press_Stats_Template_Manager();
		$selected_stats_template = $stats_template_manager->get_current_template_key();
		$stats_templates_data = [
			'statsTemplates'   => $stats_template_manager->get_all_template_colors(),
			'colorLabels'      => $stats_template_manager->get_all_template_color_labels(),
			'fontSettings'     => $stats_template_manager->get_all_template_fonts(),
			'fontLabels'       => $stats_template_manager->get_all_template_font_labels(),
			'selected_template' => $selected_stats_template,
			'columns_nonce'    => wp_create_nonce( 'pp_stats_columns_nonce' ),
		];
		wp_localize_script( 'puck-press-stats-color-picker', 'ppStatsTemplates', $stats_templates_data );

		wp_localize_script('pp-game-summary-admin', 'ppGameSummary', [
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce'    => wp_create_nonce('pp_game_summary_nonce'),
        ]);

		wp_localize_script('pp-insta-post-admin', 'ppInstaPost', [
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce'    => wp_create_nonce('pp_insta_post_nonce'),
		]);

		wp_localize_script('puck-press-bulk-edit-schedule', 'ppBulkSchedule', [
			'nonce' => wp_create_nonce('pp_bulk_schedule_nonce'),
		]);

		wp_localize_script('puck-press-bulk-edit-roster', 'ppBulkRoster', [
			'nonce' => wp_create_nonce('pp_bulk_roster_nonce'),
		]);
	}

	/**
	 * The code that runs to check for updates
	 * This action is documented in includes/update.php
	 */
	function check_for_updates()
	{
		/**
		 * The class responsible for updating the plugin
		 */
		$updater = new PDUpdater(PLUGIN_DIR_PATH . '/puck-press.php');
		$updater->set_username('connormesec');
		$updater->set_repository('puck-press');

		$updater->initialize();
	}

	public static function pp_ajax_create_schedule_group(): void
	{
		if (!current_user_can('manage_options')) {
			wp_send_json_error(['message' => 'Insufficient permissions.']);
		}
		check_ajax_referer('pp_schedule_group_nonce', 'nonce');

		$name = sanitize_text_field($_POST['name'] ?? '');
		$slug = sanitize_title($_POST['slug'] ?? $name);
		$desc = sanitize_textarea_field($_POST['description'] ?? '');

		if (empty($name) || empty($slug)) {
			wp_send_json_error(['message' => 'Name and slug are required.']);
		}

		$wpdb_utils = new Puck_Press_Schedule_Wpdb_Utils();
		$id = $wpdb_utils->create_group($slug, $name, $desc);

		if (!$id) {
			wp_send_json_error(['message' => 'Failed to create schedule group. Slug may already exist.']);
		}

		wp_send_json_success([
			'message' => "Schedule group '{$name}' created.",
			'id'      => $id,
			'slug'    => $slug,
			'name'    => $name,
		]);
	}

	public static function pp_ajax_delete_schedule_group(): void
	{
		if (!current_user_can('manage_options')) {
			wp_send_json_error(['message' => 'Insufficient permissions.']);
		}
		check_ajax_referer('pp_schedule_group_nonce', 'nonce');

		$group_id = (int) ($_POST['group_id'] ?? 0);
		if ($group_id <= 1) {
			wp_send_json_error(['message' => 'Cannot delete the default schedule group.']);
		}

		$wpdb_utils = new Puck_Press_Schedule_Wpdb_Utils();
		$wpdb_utils->delete_group($group_id);

		wp_send_json_success(['message' => 'Schedule group deleted.']);
	}

	public static function pp_ajax_set_active_schedule_id(): void
	{
		if (!current_user_can('manage_options')) {
			wp_send_json_error(['message' => 'Insufficient permissions.']);
		}
		$schedule_id = (int) ($_POST['schedule_id'] ?? 1);
		update_option('pp_admin_active_schedule_id', $schedule_id);
		wp_send_json_success(['message' => 'Active schedule updated.', 'schedule_id' => $schedule_id]);
	}

	public function register_ajax_hooks()
	{
		$data_sources_card = new Puck_Press_Schedule_Admin_Data_Sources_Card();
		add_action('wp_ajax_pp_add_data_source', [$data_sources_card, 'ajax_add_data_source']);
		add_action('wp_ajax_pp_update_schedule_source_status', [$data_sources_card, 'ajax_update_source_status_callback']);
		add_action('wp_ajax_pp_delete_schedule_data_source', [$data_sources_card, 'ajax_delete_schedule_source_callback']);

		$data_edits_card = new Puck_Press_Schedule_Admin_Edits_Table_Card;
		add_action('wp_ajax_pp_update_game_promos', [$data_edits_card, 'ajax_save_game_edit_callback']);
		add_action('wp_ajax_ajax_delete_game_edit', [$data_edits_card, 'ajax_delete_game_edit_callback']);
		add_action('wp_ajax_ajax_refresh_edits_table_card', [$data_edits_card, 'ajax_refresh_edits_table_card_callback']);
		add_action('wp_ajax_pp_get_game_data', [$data_edits_card, 'ajax_get_game_data_callback']);
		add_action('wp_ajax_pp_revert_game_field', [$data_edits_card, 'ajax_revert_game_field_callback']);
		add_action('wp_ajax_pp_reset_all_game_edits', [$data_edits_card, 'ajax_reset_all_edits_callback']);
		add_action('wp_ajax_pp_bulk_update_schedule_field', [$data_edits_card, 'ajax_bulk_update_schedule_field_callback']);

		$roster_sources_card = new Puck_Press_Roster_Admin_Data_Sources_Card();
		add_action('wp_ajax_pp_add_roster_source', [$roster_sources_card, 'ajax_add_roster_source']);
		add_action('wp_ajax_pp_update_roster_source_status', [$roster_sources_card, 'ajax_update_roster_source_status_callback']);
		add_action('wp_ajax_pp_delete_roster_data_source', [$roster_sources_card, 'ajax_delete_roster_source_callback']);

		$roster_edits_card = new Puck_Press_Roster_Admin_Edits_Table_Card;
		add_action('wp_ajax_pp_update_player_edits', [$roster_edits_card, 'ajax_save_player_edit_callback']);
		add_action('wp_ajax_ajax_delete_player_edit', [$roster_edits_card, 'ajax_delete_player_edit_callback']);
		add_action('wp_ajax_ajax_refresh_roster_edits_table_card', [$roster_edits_card, 'ajax_refresh_roster_edits_table_card_callback']);
		add_action('wp_ajax_pp_get_player_data',      [$roster_edits_card, 'ajax_get_player_data_callback']);
		add_action('wp_ajax_pp_revert_player_field',  [$roster_edits_card, 'ajax_revert_player_field_callback']);
		add_action('wp_ajax_pp_add_manual_player',    [$roster_edits_card, 'ajax_add_manual_player_callback']);
		add_action('wp_ajax_pp_delete_manual_player', [$roster_edits_card, 'ajax_delete_manual_player_callback']);
		add_action('wp_ajax_pp_reset_all_roster_edits', [$roster_edits_card, 'ajax_reset_all_roster_edits_callback']);
		add_action('wp_ajax_pp_bulk_update_roster_field',  [$roster_edits_card, 'ajax_bulk_update_roster_field_callback']);
		add_action('wp_ajax_pp_bulk_revert_roster_edits',  [$roster_edits_card, 'ajax_bulk_revert_roster_edits_callback']);

		$game_summary_post_display = new Puck_Press_Admin_Game_Summary_Post_Display();
		add_action('wp_ajax_pp_test_openai_api', [$game_summary_post_display, 'ajax_test_openai']);
		add_action('wp_ajax_pp_test_image_api', [$game_summary_post_display, 'ajax_test_image']);
		add_action('wp_ajax_pp_create_game_summary', [$game_summary_post_display, 'pp_create_game_summary']);

		$insta_post_display = new Puck_Press_Admin_Instagram_Post_Importer_Display();
		add_action('wp_ajax_pp_get_example_posts', [$insta_post_display, 'ajax_get_example_posts']);
		add_action('wp_ajax_pp_get_example_posts_and_create', [$insta_post_display, 'ajax_get_example_posts_and_create']);

		$games_table_card = new Puck_Press_Schedule_Admin_Games_Table_Card();
		add_action('wp_ajax_pp_add_manual_game',    [$games_table_card, 'ajax_add_manual_game_callback']);
		add_action('wp_ajax_pp_delete_manual_game', [$games_table_card, 'ajax_delete_manual_game_callback']);

		$archive_card = new Puck_Press_Schedule_Admin_Archive_Card();
		add_action('wp_ajax_pp_get_archive_game_count',  [$archive_card, 'ajax_get_game_count']);
		add_action('wp_ajax_pp_create_schedule_archive', [$archive_card, 'ajax_create_archive']);
		add_action('wp_ajax_pp_delete_schedule_archive', [$archive_card, 'ajax_delete_archive']);

		$roster_archive_card = new Puck_Press_Roster_Admin_Archive_Card();
		add_action('wp_ajax_pp_get_roster_stats_count',  [$roster_archive_card, 'ajax_get_stats_count']);
		add_action('wp_ajax_pp_create_roster_archive',   [$roster_archive_card, 'ajax_create_roster_archive']);
		add_action('wp_ajax_pp_delete_roster_archive',   [$roster_archive_card, 'ajax_delete_roster_archive']);

		// Register the AJAX action for refreshing all sources
		add_action('wp_ajax_pp_refresh_all_sources', [$this, 'ajax_refresh_all_sources_callback']);
		add_action('wp_ajax_pp_refresh_all_roster_sources', [$this, 'ajax_refresh_all_roster_sources_callback']);
		add_action('wp_ajax_pp_fix_roster_databases', [$this, 'ajax_fix_roster_databases_callback']);

		// Schedule group CRUD and active group selection
		add_action('wp_ajax_pp_create_schedule_group', [self::class, 'pp_ajax_create_schedule_group']);
		add_action('wp_ajax_pp_delete_schedule_group', [self::class, 'pp_ajax_delete_schedule_group']);
		add_action('wp_ajax_pp_set_active_schedule_id', [self::class, 'pp_ajax_set_active_schedule_id']);

		// Register the AJAX action for saving colors in color picker
		add_action('wp_ajax_puck_press_update_schedule_colors', [self::class, 'pp_ajax_update_schedule_template_colors']);
		add_action('wp_ajax_puck_press_update_slider_colors', [self::class, 'pp_ajax_update_slider_template_colors']);
		add_action('wp_ajax_puck_press_update_roster_colors', [self::class, 'pp_ajax_update_roster_template_colors']);
		add_action('wp_ajax_puck_press_update_record_colors', [self::class, 'pp_ajax_update_record_template_colors']);
		add_action('wp_ajax_puck_press_update_stats_colors', [self::class, 'pp_ajax_update_stats_template_colors']);
		add_action('wp_ajax_pp_save_stats_column_settings', [self::class, 'pp_ajax_save_stats_column_settings']);
		add_action('wp_ajax_puck_press_update_player_detail_colors', [self::class, 'pp_ajax_update_player_detail_colors']);
	}
}
