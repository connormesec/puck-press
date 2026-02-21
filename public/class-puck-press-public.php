<?php

/**
 * The public-facing functionality of the plugin.
 *
 * @link       https://https://github.com/connormesec/
 * @since      1.0.0
 *
 * @package    Puck_Press
 * @subpackage Puck_Press/public
 */

/**
 * The public-facing functionality of the plugin.
 *
 * Defines the plugin name, version, and two examples hooks for how to
 * enqueue the public-facing stylesheet and JavaScript.
 *
 * @package    Puck_Press
 * @subpackage Puck_Press/public
 * @author     Connor Mesec <connormesec@gmail.com>
 */
class Puck_Press_Public {

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
	 * @param      string    $plugin_name       The name of the plugin.
	 * @param      string    $version    The version of this plugin.
	 */
	public function __construct( $plugin_name, $version ) {

		$this->plugin_name = $plugin_name;
		$this->version = $version;

	}

	public function schedule_builder_shortcode()
	{
		require_once plugin_dir_path( __FILE__ ) . '../includes/schedule/class-puck-press-schedule-render-utils.php';
		$render_schedule = new Puck_Press_Schedule_Render_Utils;
		$output = $render_schedule->get_current_template_html();
		return $output;
	}

	public function slider_builder_shortcode()
	{
		require_once plugin_dir_path( __FILE__ ) . '../includes/schedule/class-puck-press-slider-render-utils.php';
		$render_schedule = new Puck_Press_Slider_Render_Utils;
		$output = $render_schedule->get_current_template_html();
		return $output;
	}

	public function roster_builder_shortcode()
	{
		require_once plugin_dir_path( __FILE__ ) . '../includes/roster/class-puck-press-roster-render-utils.php';
		$render_schedule = new Puck_Press_Roster_Render_Utils;
		$output = $render_schedule->get_current_template_html();

		return $output;
	}

	public function register_ajax_hooks()
	{
		add_action( 'wp_ajax_pp_get_player_detail',        [ $this, 'ajax_get_player_detail' ] );
		add_action( 'wp_ajax_nopriv_pp_get_player_detail', [ $this, 'ajax_get_player_detail' ] );
	}

	public function ajax_get_player_detail()
	{
		check_ajax_referer( 'pp_player_detail_nonce', 'nonce' );

		$player_id = sanitize_text_field( $_POST['player_id'] ?? '' );
		if ( empty( $player_id ) ) {
			wp_send_json_error( [ 'message' => 'Invalid player ID.' ] );
			return;
		}

		global $wpdb;

		$player = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$wpdb->prefix}pp_roster_for_display WHERE player_id = %s LIMIT 1",
				$player_id
			),
			ARRAY_A
		);

		if ( ! $player ) {
			wp_send_json_error( [ 'message' => 'Player not found.' ] );
			return;
		}

		$stats = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$wpdb->prefix}pp_roster_stats WHERE player_id = %s LIMIT 1",
				$player_id
			),
			ARRAY_A
		);

		require_once plugin_dir_path( __FILE__ ) . '../includes/roster/class-puck-press-roster-player-detail.php';
		$html = Puck_Press_Roster_Player_Detail::render( $player, $stats ?? [] );

		wp_send_json_success( [ 'html' => $html ] );
	}


	/**
	 * Register the stylesheets for the public-facing side of the site.
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

		wp_enqueue_style( $this->plugin_name, plugin_dir_url( __FILE__ ) . 'css/puck-press-public.css', array(), $this->version, 'all' );

	}

	/**
	 * Register the JavaScript for the public-facing side of the site.
	 *
	 * @since    1.0.0
	 */
	public function enqueue_scripts() {

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

		wp_enqueue_script( $this->plugin_name, plugin_dir_url( __FILE__ ) . 'js/puck-press-public.js', array( 'jquery' ), $this->version, false );

		wp_enqueue_script(
			'pp-player-detail',
			plugin_dir_url( __FILE__ ) . 'js/pp-player-detail.js',
			array( 'jquery' ),
			filemtime( plugin_dir_path( __FILE__ ) . 'js/pp-player-detail.js' ),
			true
		);

	}

}
