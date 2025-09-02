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
		$utils = new Puck_Press_Schedule_Wpdb_Utils;
		$utils->reset_table('pp_game_schedule_raw');
		$utils->reset_table('pp_game_schedule_for_display');

		$importer = new Puck_Press_Schedule_Source_Importer();
		$raw_table_results = $importer->populate_raw_schedule_table_from_sources();
		$importer->sanitize_raw_games_table();
		$display_game_table_results = $importer->apply_edits_and_save_to_display_table();

		$refresh_game_table_ui = new Puck_Press_Schedule_Admin_Games_Table_Card;
		$refreshed_game_table_ui = $refresh_game_table_ui->render_game_schedule_admin_preview();

		$refresh_game_preview = Puck_Press_Schedule_Admin_Preview_Card::create_and_init();
		$refresh_game_preview_html = $refresh_game_preview->get_all_templates_html();

		$refresh_slider_preview = Puck_Press_Schedule_Admin_Slider_Preview_Card::create_and_init();
		$refresh_slider_preview_html = $refresh_slider_preview->get_all_templates_html();


		if ($raw_table_results !== false && $display_game_table_results !== false) {
			wp_send_json_success(array(
				'raw_game_table_results' => $raw_table_results,
				'display_game_table_results' => $display_game_table_results,
				'refreshed_game_table_ui' => $refreshed_game_table_ui,
				'refreshed_game_preview_html' => $refresh_game_preview_html,
				'refreshed_slider_preview_html' => $refresh_slider_preview_html
			));
		} else if ($raw_table_results['messages'][0] === 'No active sources to import.') {
			wp_send_json_error(array('message' => 'No active sources to import.', 'raw_table_results' => $raw_table_results, 'display_game_table_results' => $display_game_table_results));
			wp_die(); // Properly end the AJAX request
		} else {
			wp_send_json_error(array('message' => 'Failed to refresh data sources from database', 'error' => $wpdb->last_error, 'raw_table_results' => $raw_table_results, 'display_game_table_results' => $display_game_table_results));
			wp_die(); // Properly end the AJAX request
		}
	}

	public function ajax_refresh_all_roster_sources_callback()
	{
		global $wpdb;
		$utils = new Puck_Press_Roster_Wpdb_Utils;
		$utils->reset_table('pp_roster_raw');
		$utils->reset_table('pp_roster_for_display');

		$importer = new Puck_Press_Roster_Source_Importer();
		$raw_table_results = $importer->populate_raw_roster_table_from_sources();
		$display_roster_table_results = $importer->apply_edits_and_save_to_display_table();
		$importer->sanitize_roster_display_table();

		$refresh_roster_table_ui = new Puck_Press_Raw_Roster_Table_Card;
		$refreshed_roster_table_ui = $refresh_roster_table_ui->render_roster_admin_preview();

		$refresh_roster_preview = Puck_Press_Roster_Admin_Preview_Card::create_and_init();
		$refresh_roster_preview_html = $refresh_roster_preview->get_all_templates_html();

		if ($raw_table_results !== false && $display_roster_table_results !== false) {
			wp_send_json_success(array(
				'raw_roster_table_results' => $raw_table_results,
				'display_roster_table_results' => $display_roster_table_results,
				'refreshed_roster_table_ui' => $refreshed_roster_table_ui,
				'refreshed_roster_preview_html' => $refresh_roster_preview_html
			));
		} else if ($raw_table_results['messages'][0] === 'No active sources to import.') {
			wp_send_json_error(array('message' => 'No active sources to import.', 'raw_table_results' => $raw_table_results, 'display_game_table_results' => $display_roster_table_results));
			wp_die(); // Properly end the AJAX request
		} else {
			wp_send_json_error(array(
				'message' => 'Failed to refresh roster data sources from database',
				'error' => $wpdb->last_error,
				'raw_roster_table_results' => $raw_table_results,
				'display_roster_table_results' => $display_roster_table_results
			));
			wp_die(); // Properly end the AJAX request
		}
	}


	private static function update_template_colors($template_manager)
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

		$colors_updated = $template_manager->save_template_colors($template_key, $colors);
		$template_key_changed = $template_manager->set_current_template_key($template_key);

		if ($colors_updated || $template_key_changed) {
			wp_send_json_success([
				'message' => 'Template updated successfully.',
				'colors'  => $colors
			]);
		} else {
			wp_send_json_error([
				'message' => 'No changes were made to the template or colors.'
			]);
		}
	}

	public static function pp_ajax_update_schedule_template_colors()
	{
		self::update_template_colors(new Puck_Press_Schedule_Template_Manager());
	}

	public static function pp_ajax_update_slider_template_colors()
	{
		self::update_template_colors(new Puck_Press_Slider_Template_Manager());
	}

	public static function pp_ajax_update_roster_template_colors()
	{
		self::update_template_colors(new Puck_Press_Roster_Template_Manager());
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
			case 'roster':
				wp_enqueue_script('puck-press-roster-sources', plugin_dir_url(__FILE__) . 'js/roster/puck-press-roster-sources.js', array('jquery', 'puck-press-admin-shared'), $this->version, false);
				wp_enqueue_script('puck-press-roster-edits', plugin_dir_url(__FILE__) . 'js/roster/puck-press-roster-edits.js', array('jquery', 'puck-press-admin-shared'), $this->version, false);
				wp_enqueue_script('puck-press-roster-color-picker', plugin_dir_url(__FILE__) . 'js/roster/puck-press-roster-color-picker.js', array('jquery'), $this->version, false);
				wp_enqueue_script('puck-press-roster-preview', plugin_dir_url(__FILE__) . 'js/roster/puck-press-roster-preview.js', array('jquery'), $this->version, false);
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
				wp_enqueue_script('puck-press-color-picker', plugin_dir_url(__FILE__) . 'js/schedule/puck-press-schedule-color-picker.js', array('jquery'), $this->version, false);
				wp_enqueue_script('puck-press-slider-color-picker', plugin_dir_url(__FILE__) . 'js/schedule/puck-press-schedule-slider-color-picker.js', array('jquery'), $this->version, false);
				wp_enqueue_script('puck-press-schedule-preview', plugin_dir_url(__FILE__) . 'js/schedule/puck-press-schedule-preview.js', array('jquery'), $this->version, false);
				break;
		}
	}

	public function localize_scripts()
	{
		$template_manager = new Puck_Press_Schedule_Template_Manager;
		$scheduleTemplates = $template_manager->get_all_template_colors();
		$selected_template =  $template_manager->get_current_template_key();
		$templates = [
			'scheduleTemplates' => $scheduleTemplates,
			'selected_template' => $selected_template
		];
		wp_localize_script('puck-press-color-picker', 'ppScheduleTemplates', $templates);

		$slider_template_manager = new Puck_Press_Slider_Template_Manager;
		$sliderTemplates = $slider_template_manager->get_all_template_colors();
		$selected_slider_template =  $slider_template_manager->get_current_template_key();
		$templates = [
			'sliderTemplates' => $sliderTemplates,
			'selected_template' => $selected_slider_template
		];
		wp_localize_script('puck-press-slider-color-picker', 'ppSliderTemplates', $templates);

		$roster_template_manager = new Puck_Press_Roster_Template_Manager;
		$rosterTemplates = $roster_template_manager->get_all_template_colors();
		$selected_roster_template =  $roster_template_manager->get_current_template_key();
		$roster_templates = [
			'rosterTemplates' => $rosterTemplates,
			'selected_template' => $selected_roster_template
		];
		wp_localize_script('puck-press-roster-color-picker', 'ppRosterTemplates', $roster_templates);

		wp_localize_script('pp-game-summary-admin', 'ppGameSummary', [
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce'    => wp_create_nonce('pp_game_summary_nonce'),
        ]);

		wp_localize_script('pp-insta-post-admin', 'ppInstaPost', [
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce'    => wp_create_nonce('pp_insta_post_nonce'),
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

		$roster_sources_card = new Puck_Press_Roster_Admin_Data_Sources_Card();
		add_action('wp_ajax_pp_add_roster_source', [$roster_sources_card, 'ajax_add_roster_source']);
		add_action('wp_ajax_pp_update_roster_source_status', [$roster_sources_card, 'ajax_update_roster_source_status_callback']);
		add_action('wp_ajax_pp_delete_roster_data_source', [$roster_sources_card, 'ajax_delete_roster_source_callback']);

		$roster_edits_card = new Puck_Press_Roster_Admin_Edits_Table_Card;
		add_action('wp_ajax_pp_update_player_edits', [$roster_edits_card, 'ajax_save_player_edit_callback']);
		add_action('wp_ajax_ajax_delete_player_edit', [$roster_edits_card, 'ajax_delete_player_edit_callback']);
		add_action('wp_ajax_ajax_refresh_roster_edits_table_card', [$roster_edits_card, 'ajax_refresh_roster_edits_table_card_callback']);

		$game_summary_post_display = new Puck_Press_Admin_Game_Summary_Post_Display();
		add_action('wp_ajax_pp_test_openai_api', [$game_summary_post_display, 'ajax_test_openai']);
		add_action('wp_ajax_pp_test_image_api', [$game_summary_post_display, 'ajax_test_image']);
		add_action('wp_ajax_pp_create_game_summary', [$game_summary_post_display, 'pp_create_game_summary']);

		$insta_post_display = new Puck_Press_Admin_Instagram_Post_Importer_Display();
		add_action('wp_ajax_pp_get_example_posts', [$insta_post_display, 'ajax_get_example_posts']);
		add_action('wp_ajax_pp_get_example_posts_and_create', [$insta_post_display, 'ajax_get_example_posts_and_create']);

		// Register the AJAX action for refreshing all sources
		add_action('wp_ajax_pp_refresh_all_sources', [$this, 'ajax_refresh_all_sources_callback']);
		add_action('wp_ajax_pp_refresh_all_roster_sources', [$this, 'ajax_refresh_all_roster_sources_callback']);

		// Register the AJAX action for saving colors in color picker
		add_action('wp_ajax_puck_press_update_schedule_colors', [self::class, 'pp_ajax_update_schedule_template_colors']);
		add_action('wp_ajax_puck_press_update_slider_colors', [self::class, 'pp_ajax_update_slider_template_colors']);
		add_action('wp_ajax_puck_press_update_roster_colors', [self::class, 'pp_ajax_update_roster_template_colors']);
	}
}
