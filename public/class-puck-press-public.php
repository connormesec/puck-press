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

	public function stats_builder_shortcode()
	{
		require_once plugin_dir_path( __FILE__ ) . '../includes/stats/class-puck-press-stats-render-utils.php';
		$render = new Puck_Press_Stats_Render_Utils();
		return $render->get_current_template_html();
	}

	public function record_builder_shortcode( $atts )
	{
		$atts = shortcode_atts( [
			'show_home_away' => 'true',
			'show_goals'     => 'true',
			'show_diff'      => 'true',
			'title'          => 'Team Record',
		], $atts );

		require_once plugin_dir_path( __FILE__ ) . '../includes/record/class-puck-press-record-render-utils.php';
		$render = new Puck_Press_Record_Render_Utils();
		return $render->get_current_template_html( $atts );
	}

	/**
	 * Injects a player-specific document title when ?player= is active.
	 * "John Doe – Forward #42" replaces the generic page title.
	 *
	 * @param array $title_parts WordPress title parts array.
	 * @return array
	 */
	public function filter_player_page_title( array $title_parts ): array
	{
		$player_slug = sanitize_text_field( $_GET['player'] ?? '' );
		if ( empty( $player_slug ) ) return $title_parts;

		global $wpdb;
		$all_players = $wpdb->get_results(
			"SELECT name, pos, number FROM {$wpdb->prefix}pp_roster_for_display",
			ARRAY_A
		);
		$player = null;
		foreach ( $all_players as $row ) {
			if ( sanitize_title( $row['name'] ) === $player_slug ) {
				$player = $row;
				break;
			}
		}

		if ( ! $player ) return $title_parts;

		$position_labels = [
			'F'  => 'Forward',    'C'  => 'Center',
			'LW' => 'Left Wing',  'RW' => 'Right Wing',
			'D'  => 'Defenseman', 'LD' => 'Left Defense',
			'RD' => 'Right Defense', 'G' => 'Goalie',
		];

		$pos_code  = strtoupper( $player['pos'] ?? '' );
		$pos_label = $position_labels[ $pos_code ] ?? $pos_code;
		$number    = ! empty( $player['number'] ) ? '#' . $player['number'] : '';
		$suffix    = implode( ' ', array_filter( [ $pos_label, $number ] ) );

		$title_parts['title'] = $player['name'] . ( $suffix ? ' – ' . $suffix : '' );
		return $title_parts;
	}

	public function register_ajax_hooks()
	{
		add_action( 'wp_ajax_pp_get_player_detail',        [ $this, 'ajax_get_player_detail' ] );
		add_action( 'wp_ajax_nopriv_pp_get_player_detail', [ $this, 'ajax_get_player_detail' ] );
	}

	public function ajax_get_player_detail()
	{
		check_ajax_referer( 'pp_player_detail_nonce', 'nonce' );

		$player_slug = sanitize_text_field( $_POST['player_id'] ?? '' );
		if ( empty( $player_slug ) ) {
			wp_send_json_error( [ 'message' => 'Invalid player ID.' ] );
			return;
		}

		global $wpdb;

		$all_players = $wpdb->get_results(
			"SELECT * FROM {$wpdb->prefix}pp_roster_for_display",
			ARRAY_A
		);
		$player = null;
		foreach ( $all_players as $row ) {
			if ( sanitize_title( $row['name'] ) === $player_slug ) {
				$player = $row;
				break;
			}
		}

		if ( ! $player ) {
			wp_send_json_error( [ 'message' => 'Player not found.' ] );
			return;
		}

		// Query the appropriate stats table based on whether the player is a goalie.
		$is_goalie  = ( strtoupper( $player['pos'] ?? '' ) === 'G' );
		$stats_table = $is_goalie
			? "{$wpdb->prefix}pp_roster_goalie_stats"
			: "{$wpdb->prefix}pp_roster_stats";

		$stats = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$stats_table} WHERE player_id = %s LIMIT 1",
				$player['player_id']
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
	/**
	 * Enqueue the active slider template's CSS and JS early enough for wp_head.
	 * The shortcode fires after wp_head, so CSS enqueued there is missed.
	 */
	public function enqueue_slider_assets() {
		global $post;
		if ( ! is_a( $post, 'WP_Post' ) || ! has_shortcode( $post->post_content, 'pp-slider' ) ) {
			return;
		}
		require_once plugin_dir_path( __FILE__ ) . '../public/templates/class-puck-press-template-manager-abstract.php';
		require_once plugin_dir_path( __FILE__ ) . '../public/templates/class-puck-press-slider-template-manager.php';
		new Puck_Press_Slider_Template_Manager();
	}

	public function enqueue_record_assets() {
		global $post;
		if ( ! is_a( $post, 'WP_Post' ) || ! has_shortcode( $post->post_content, 'pp-record' ) ) {
			return;
		}
		require_once plugin_dir_path( __FILE__ ) . '../public/templates/class-puck-press-template-manager-abstract.php';
		require_once plugin_dir_path( __FILE__ ) . '../public/templates/class-puck-press-record-template-manager.php';
		new Puck_Press_Record_Template_Manager();
	}

	public function enqueue_stats_assets() {
		global $post;
		if ( ! is_a( $post, 'WP_Post' ) || ! has_shortcode( $post->post_content, 'pp-stats' ) ) {
			return;
		}
		require_once plugin_dir_path( __FILE__ ) . '../public/templates/class-puck-press-template-manager-abstract.php';
		require_once plugin_dir_path( __FILE__ ) . '../public/templates/class-puck-press-stats-template-manager.php';
		new Puck_Press_Stats_Template_Manager();
	}

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
