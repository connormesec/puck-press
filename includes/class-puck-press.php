<?php

/**
 * The file that defines the core plugin class
 *
 * A class definition that includes attributes and functions used across both the
 * public-facing side of the site and the admin area.
 *
 * @link       https://https://github.com/connormesec/
 * @since      1.0.0
 *
 * @package    Puck_Press
 * @subpackage Puck_Press/includes
 */

/**
 * The core plugin class.
 *
 * This is used to define internationalization, admin-specific hooks, and
 * public-facing site hooks.
 *
 * Also maintains the unique identifier of this plugin as well as the current
 * version of the plugin.
 *
 * @since      1.0.0
 * @package    Puck_Press
 * @subpackage Puck_Press/includes
 * @author     Connor Mesec <connormesec@gmail.com>
 */
class Puck_Press {


	/**
	 * The loader that's responsible for maintaining and registering all hooks that power
	 * the plugin.
	 *
	 * @since    1.0.0
	 * @access   protected
	 * @var      Puck_Press_Loader    $loader    Maintains and registers all hooks for the plugin.
	 */
	protected $loader;

	/**
	 * The unique identifier of this plugin.
	 *
	 * @since    1.0.0
	 * @access   protected
	 * @var      string    $plugin_name    The string used to uniquely identify this plugin.
	 */
	protected $plugin_name;

	/**
	 * The current version of the plugin.
	 *
	 * @since    1.0.0
	 * @access   protected
	 * @var      string    $version    The current version of the plugin.
	 */
	protected $version;

	/**
	 * Define the core functionality of the plugin.
	 *
	 * Set the plugin name and the plugin version that can be used throughout the plugin.
	 * Load the dependencies, define the locale, and set the hooks for the admin area and
	 * the public-facing side of the site.
	 *
	 * @since    1.0.0
	 */
	public function __construct() {
		if ( defined( 'PUCK_PRESS_VERSION' ) ) {
			$this->version = PUCK_PRESS_VERSION;
		} else {
			$this->version = '1.0.0';
		}
		$this->plugin_name = 'puck-press';

		$this->load_dependencies();
		$this->set_locale();
		if ( is_admin() ) {
			$this->define_admin_hooks();
		}
		$this->define_public_hooks();
		add_action( 'init', array( $this, 'define_cron_hooks' ) );
		add_action( 'init', array( $this, 'register_post_types' ) );
	}

	/**
	 * Load the required dependencies for this plugin.
	 *
	 * Include the following files that make up the plugin:
	 *
	 * - Puck_Press_Loader. Orchestrates the hooks of the plugin.
	 * - Puck_Press_i18n. Defines internationalization functionality.
	 * - Puck_Press_Admin. Defines all hooks for the admin area.
	 * - Puck_Press_Public. Defines all hooks for the public side of the site.
	 *
	 * Create an instance of the loader which will be used to register the hooks
	 * with WordPress.
	 *
	 * @since    1.0.0
	 * @access   private
	 */
	private function load_dependencies() {

		/**
		 * The class responsible for orchestrating the actions and filters of the
		 * core plugin.
		 */
		require_once plugin_dir_path( __DIR__ ) . 'includes/class-puck-press-loader.php';

		/**
		 * The class responsible for defining internationalization functionality
		 * of the plugin.
		 */
		require_once plugin_dir_path( __DIR__ ) . 'includes/class-puck-press-i18n.php';

		/**
		 * The class responsible for defining all actions that occur in the admin area.
		 */
		// Only load admin code if inside wp-admin
		if ( is_admin() ) {
			// require_once plugin_dir_path( dirname( __FILE__ ) ) . 'admin/class-puck-press-admin.php';
			require_once plugin_dir_path( __DIR__ ) . 'admin/class-puck-press-admin-loader.php';
			Puck_Press_Admin_Loader::load();
		}

		/**
		 * The class responsible for defining all actions that occur in the public-facing
		 * side of the site.
		 */
		require_once plugin_dir_path( __DIR__ ) . 'public/class-puck-press-public.php';

		require_once plugin_dir_path( __DIR__ ) . 'includes/class-puck-press-cron.php';

		require_once plugin_dir_path( __DIR__ ) . 'includes/class-puck-press-rewrite-manager.php';
		Puck_Press_Rewrite_Manager::init();

		require_once plugin_dir_path( __DIR__ ) . 'includes/class-puck-press-yoast-sitemap.php';
		Puck_Press_Yoast_Sitemap::init();

		require_once plugin_dir_path( __DIR__ ) . 'includes/class-puck-press-activator.php';
		add_action( 'plugins_loaded', array( 'Puck_Press_Activator', 'maybe_run_migrations' ) );
		add_action( 'plugins_loaded', array( 'Puck_Press_Activator', 'maybe_run_roster_group_migration' ) );
		add_action( 'plugins_loaded', array( 'Puck_Press_Activator', 'maybe_run_teams_migration' ) );
		add_action( 'plugins_loaded', array( 'Puck_Press_Activator', 'maybe_run_roster_registry_migration' ) );
		add_action( 'plugins_loaded', array( 'Puck_Press_Activator', 'maybe_run_cleanup_migration' ) );

		$this->loader = new Puck_Press_Loader();
	}

	/**
	 * Define the locale for this plugin for internationalization.
	 *
	 * Uses the Puck_Press_i18n class in order to set the domain and to register the hook
	 * with WordPress.
	 *
	 * @since    1.0.0
	 * @access   private
	 */
	private function set_locale() {

		$plugin_i18n = new Puck_Press_i18n();

		$this->loader->add_action( 'plugins_loaded', $plugin_i18n, 'load_plugin_textdomain' );
	}

	/**
	 * Register all of the hooks related to the admin area functionality
	 * of the plugin.
	 *
	 * @since    1.0.0
	 * @access   private
	 */
	private function define_admin_hooks() {

		$plugin_admin = new Puck_Press_Admin( $this->get_plugin_name(), $this->get_version() );

		$this->loader->add_action( 'admin_enqueue_scripts', $plugin_admin, 'enqueue_styles' );
		$this->loader->add_action( 'admin_enqueue_scripts', $plugin_admin, 'enqueue_scripts' );
		$this->loader->add_action( 'admin_enqueue_scripts', $plugin_admin, 'localize_scripts' );
		$this->loader->add_action( 'admin_init', $plugin_admin, 'register_ajax_hooks' );
	}

	public function register_post_types() {
		register_post_type(
			'pp_insta_post',
			array(
				'labels'       => array(
					'name'          => 'Instagram Posts',
					'singular_name' => 'Instagram Post',
				),
				'public'       => true,
				'has_archive'  => true,
				'rewrite'      => array(
					'slug'       => 'instagram',
					'with_front' => false,
				),
				'supports'     => array( 'title', 'editor', 'thumbnail', 'custom-fields' ),
				'show_in_menu' => false,
				'show_in_rest' => true,
			)
		);

		register_post_type(
			'pp_game_summary',
			array(
				'labels'       => array(
					'name'          => 'Game Recaps',
					'singular_name' => 'Game Recap',
				),
				'public'       => true,
				'has_archive'  => true,
				'rewrite'      => array(
					'slug'       => 'game-recap',
					'with_front' => false,
				),
				'supports'     => array( 'title', 'editor', 'thumbnail', 'custom-fields' ),
				'show_in_menu' => false,
				'show_in_rest' => true,
			)
		);
	}

	public function define_cron_hooks() {
		$plugin_cron = new Puck_Press_Cron();

		// Hook into WordPress init to setup cron
		$this->loader->add_action( 'init', $plugin_cron, 'maybe_schedule_cron' );
	}

	/**
	 * Register all of the hooks related to the public-facing functionality
	 * of the plugin.
	 *
	 * @since    1.0.0
	 * @access   private
	 */
	private function define_public_hooks() {

		$plugin_public = new Puck_Press_Public( $this->get_plugin_name(), $this->get_version() );

		$this->loader->add_action( 'wp_enqueue_scripts', $plugin_public, 'enqueue_styles' );
		$this->loader->add_action( 'wp_enqueue_scripts', $plugin_public, 'enqueue_scripts' );
		$this->loader->add_action( 'wp_enqueue_scripts', $plugin_public, 'enqueue_slider_assets' );
		$this->loader->add_action( 'wp_enqueue_scripts', $plugin_public, 'enqueue_record_assets' );
		$this->loader->add_action( 'wp_enqueue_scripts', $plugin_public, 'enqueue_stats_assets' );
		$this->loader->add_action( 'wp_enqueue_scripts', $plugin_public, 'enqueue_stat_leaders_assets' );
		$this->loader->add_action( 'init', $plugin_public, 'register_ajax_hooks' );
		$this->loader->add_filter( 'document_title_parts', $plugin_public, 'filter_player_page_title' );
		$this->loader->add_filter( 'template_include', $plugin_public, 'maybe_load_player_template' );
		$this->loader->add_action( 'wp_enqueue_scripts', $plugin_public, 'enqueue_roster_assets' );
		// Add our Shortcodes
		$this->loader->add_shortcode( 'pp-schedule', $plugin_public, 'schedule_builder_shortcode' );
		$this->loader->add_shortcode( 'pp-slider', $plugin_public, 'slider_builder_shortcode' );
		$this->loader->add_shortcode( 'pp-roster', $plugin_public, 'roster_builder_shortcode' );
		$this->loader->add_shortcode( 'pp-record', $plugin_public, 'record_builder_shortcode' );
		$this->loader->add_shortcode( 'pp-stats', $plugin_public, 'stats_builder_shortcode' );
		$this->loader->add_shortcode( 'pp-stat-leaders-skaters', $plugin_public, 'stat_leaders_skaters_shortcode' );
		$this->loader->add_shortcode( 'pp-stat-leaders-goalies', $plugin_public, 'stat_leaders_goalies_shortcode' );
	}

	/**
	 * Run the loader to execute all of the hooks with WordPress.
	 *
	 * @since    1.0.0
	 */
	public function run() {
		$this->loader->run();
	}

	/**
	 * The name of the plugin used to uniquely identify it within the context of
	 * WordPress and to define internationalization functionality.
	 *
	 * @since     1.0.0
	 * @return    string    The name of the plugin.
	 */
	public function get_plugin_name() {
		return $this->plugin_name;
	}

	/**
	 * The reference to the class that orchestrates the hooks with the plugin.
	 *
	 * @since     1.0.0
	 * @return    Puck_Press_Loader    Orchestrates the hooks of the plugin.
	 */
	public function get_loader() {
		return $this->loader;
	}

	/**
	 * Retrieve the version number of the plugin.
	 *
	 * @since     1.0.0
	 * @return    string    The version number of the plugin.
	 */
	public function get_version() {
		return $this->version;
	}
}
