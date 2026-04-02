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
	 * @param      string $plugin_name       The name of the plugin.
	 * @param      string $version    The version of this plugin.
	 */
	public function __construct( $plugin_name, $version ) {

		$this->plugin_name = $plugin_name;
		$this->version     = $version;
	}

	public function schedule_builder_shortcode( $atts ) {
		$atts        = shortcode_atts(
			array(
				'archive'  => '',
				'schedule' => '',
			),
			$atts
		);
		$archive_key = sanitize_title( $atts['archive'] );
		$slug        = sanitize_title( $atts['schedule'] );

		require_once plugin_dir_path( __FILE__ ) . '../includes/class-puck-press-group-resolver.php';
		require_once plugin_dir_path( __FILE__ ) . '../includes/schedule/class-puck-press-schedule-render-utils.php';

		$schedule_id              = Puck_Press_Group_Resolver::resolve( $slug, 'pp_schedules' );
		$render_schedule          = new Puck_Press_Schedule_Render_Utils( $archive_key, $schedule_id );
		$options                  = $archive_key !== '' ? array( 'is_archive' => true ) : array();
		$options['schedule_slug'] = $slug !== '' ? $slug : 'default';
		$options['schedule_id']   = $schedule_id;

		return $render_schedule->get_current_template_html( $options );
	}

	public function slider_builder_shortcode( $atts = array() ) {
		$atts = shortcode_atts( array( 'schedule' => '' ), $atts );
		$slug = sanitize_title( $atts['schedule'] );

		require_once plugin_dir_path( __FILE__ ) . '../includes/class-puck-press-group-resolver.php';
		require_once plugin_dir_path( __FILE__ ) . '../includes/schedule/class-puck-press-slider-render-utils.php';

		$schedule_id     = Puck_Press_Group_Resolver::resolve( $slug, 'pp_schedules' );
		$render_schedule = new Puck_Press_Slider_Render_Utils( $schedule_id );
		return $render_schedule->get_current_template_html();
	}

	public function roster_builder_shortcode( $atts = array() ) {
		$atts = shortcode_atts( array( 'roster' => '' ), $atts );
		$slug = sanitize_title( $atts['roster'] );

		require_once plugin_dir_path( __FILE__ ) . '../includes/class-puck-press-group-resolver.php';
		require_once plugin_dir_path( __FILE__ ) . '../includes/roster/class-puck-press-roster-render-utils.php';

		$roster_id = Puck_Press_Group_Resolver::resolve( $slug, 'pp_rosters' );
		$render    = new Puck_Press_Roster_Render_Utils( $roster_id );
		return $render->get_current_template_html();
	}

	public function stats_builder_shortcode( $atts = array() ) {
		$atts = shortcode_atts(
			array(
				'team'      => '',
				'show_team' => '',
			),
			$atts
		);

		$teams     = array_values( array_filter( array_map( 'intval', explode( ',', $atts['team'] ) ) ) );
		$show_team = '' !== $atts['show_team'] ? filter_var( $atts['show_team'], FILTER_VALIDATE_BOOLEAN ) : null;

		require_once plugin_dir_path( __FILE__ ) . '../includes/stats/class-puck-press-stats-render-utils.php';

		$render = new Puck_Press_Stats_Render_Utils( $teams, $show_team );
		return $render->get_current_template_html();
	}

	public function record_builder_shortcode( $atts ) {
		$atts = shortcode_atts(
			array(
				'show_home_away' => 'true',
				'show_goals'     => 'true',
				'show_diff'      => 'true',
				'show_pct'       => 'true',
				'title'          => 'Team Record',
				'schedule'       => '',
				'team'           => '',
				'division_only'  => 'false',
			),
			$atts
		);

		require_once plugin_dir_path( __FILE__ ) . '../includes/class-puck-press-group-resolver.php';
		require_once plugin_dir_path( __FILE__ ) . '../includes/record/class-puck-press-record-render-utils.php';

		if ( ! empty( $atts['team'] ) ) {
			$atts['team'] = self::resolve_team_attr( $atts['team'] );
		}

		$schedule_id = Puck_Press_Group_Resolver::resolve( sanitize_title( $atts['schedule'] ), 'pp_schedules' );
		$render      = new Puck_Press_Record_Render_Utils( $schedule_id );
		return $render->get_current_template_html( $atts );
	}

	private static function resolve_team_attr( string $value ): string {
		if ( ctype_digit( $value ) && (int) $value > 0 ) {
			global $wpdb;
			$name = $wpdb->get_var( $wpdb->prepare( "SELECT name FROM {$wpdb->prefix}pp_teams WHERE id = %d LIMIT 1", (int) $value ) );
			return $name ?: $value;
		}
		return $value;
	}

	/**
	 * When the pp_player query var is set, load the plugin's player detail template
	 * instead of the active WordPress theme template.
	 *
	 * @param string $template Default template path.
	 * @return string
	 */
	public function maybe_load_player_template( string $template ): string {
		if ( ! get_query_var( 'pp_player' ) ) {
			return $template;
		}
		return plugin_dir_path( __FILE__ ) . '../public/templates/player-page.php';
	}

	/**
	 * Enqueue the active roster template's CSS vars (inline style) on pages that
	 * need them: the [pp-roster] shortcode page AND the /player/ detail page.
	 */
	public function enqueue_roster_assets(): void {
		global $post;
		$is_roster_page = is_a( $post, 'WP_Post' ) && has_shortcode( $post->post_content, 'pp-roster' );
		$is_player_page = (bool) get_query_var( 'pp_player' );
		if ( ! $is_roster_page && ! $is_player_page ) {
			return;
		}

		require_once plugin_dir_path( __FILE__ ) . '../public/templates/class-puck-press-template-manager-abstract.php';
		require_once plugin_dir_path( __FILE__ ) . '../public/templates/class-puck-press-roster-template-manager.php';
		new Puck_Press_Roster_Template_Manager();

		if ( $is_player_page ) {
			require_once plugin_dir_path( __FILE__ ) . '../includes/class-puck-press-player-detail-colors.php';
			wp_add_inline_style( $this->plugin_name, Puck_Press_Player_Detail_Colors::get_inline_css() );
		}
	}

	/**
	 * Injects a player-specific document title when /player/{slug} is active.
	 * "John Doe – Forward #42" replaces the generic page title.
	 *
	 * @param array $title_parts WordPress title parts array.
	 * @return array
	 */
	public function filter_player_page_title( array $title_parts ): array {
		$player_slug = sanitize_text_field( get_query_var( 'pp_player' ) );
		if ( empty( $player_slug ) ) {
			return $title_parts;
		}

		require_once plugin_dir_path( __FILE__ ) . '../includes/roster/class-puck-press-roster-player-detail.php';
		$player = Puck_Press_Roster_Player_Detail::find_by_slug( $player_slug );

		if ( ! $player ) {
			return $title_parts;
		}

		$position_labels = array(
			'F'  => 'Forward',
			'C'  => 'Center',
			'LW' => 'Left Wing',
			'RW' => 'Right Wing',
			'D'  => 'Defenseman',
			'LD' => 'Left Defense',
			'RD' => 'Right Defense',
			'G'  => 'Goalie',
		);

		$pos_code  = strtoupper( $player['pos'] ?? '' );
		$pos_label = $position_labels[ $pos_code ] ?? $pos_code;
		$number    = ! empty( $player['number'] ) ? '#' . $player['number'] : '';
		$suffix    = implode( ' ', array_filter( array( $pos_label, $number ) ) );

		$title_parts['title'] = $player['name'] . ( $suffix ? ' – ' . $suffix : '' );
		return $title_parts;
	}

	public function register_ajax_hooks() {
		// Player detail is now handled by native WP routing (/player/{slug}).
		// No AJAX hooks needed.
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

	public function enqueue_post_slider_assets() {
		global $post;
		if ( ! is_a( $post, 'WP_Post' ) || ! has_shortcode( $post->post_content, 'pp-post-slider' ) ) {
			return;
		}
		require_once plugin_dir_path( __FILE__ ) . '../public/templates/class-puck-press-template-manager-abstract.php';
		require_once plugin_dir_path( __FILE__ ) . '../public/templates/class-puck-press-post-slider-template-manager.php';
		new Puck_Press_Post_Slider_Template_Manager();
	}

	public function post_slider_shortcode( $atts = array() ) {
		$atts = shortcode_atts(
			array(
				'post_type' => 'post',
				'count'     => '6',
				'more_url'  => '#',
				'more_text' => 'More Posts',
				'team'      => '',
			),
			$atts
		);

		require_once plugin_dir_path( __FILE__ ) . '../includes/post-slider/class-puck-press-post-slider-render-utils.php';

		$render = new Puck_Press_Post_Slider_Render_Utils(
			sanitize_text_field( $atts['post_type'] ),
			max( 1, (int) $atts['count'] ),
			esc_url_raw( $atts['more_url'] ),
			sanitize_text_field( $atts['more_text'] ),
			sanitize_text_field( $atts['team'] )
		);
		return $render->get_html();
	}

	public function enqueue_league_news_assets() {
		global $post;
		if ( ! is_a( $post, 'WP_Post' ) || ! has_shortcode( $post->post_content, 'pp-league-news' ) ) {
			return;
		}
		require_once plugin_dir_path( __FILE__ ) . '../public/templates/class-puck-press-template-manager-abstract.php';
		require_once plugin_dir_path( __FILE__ ) . '../public/templates/class-puck-press-template-abstract.php';
		require_once plugin_dir_path( __FILE__ ) . '../public/templates/class-puck-press-league-news-template-manager.php';
		new Puck_Press_League_News_Template_Manager();
	}

	public function league_news_shortcode( $atts = array() ) {
		require_once plugin_dir_path( __FILE__ ) . '../includes/league-news/class-puck-press-league-news-api.php';
		require_once plugin_dir_path( __FILE__ ) . '../includes/league-news/class-puck-press-league-news-render-utils.php';

		$render = new Puck_Press_League_News_Render_Utils();
		return $render->get_html();
	}

	public function awards_shortcode( $atts = array() ) {
		require_once plugin_dir_path( __FILE__ ) . '../includes/awards/class-puck-press-awards-wpdb-utils.php';
		require_once plugin_dir_path( __FILE__ ) . '../includes/awards/class-puck-press-awards-render-utils.php';
		require_once plugin_dir_path( __FILE__ ) . 'templates/class-puck-press-template-abstract.php';
		require_once plugin_dir_path( __FILE__ ) . 'templates/class-puck-press-template-manager-abstract.php';
		require_once plugin_dir_path( __FILE__ ) . 'templates/class-puck-press-awards-template-manager.php';

		$awards_tm  = new Puck_Press_Awards_Template_Manager();
		$template   = $awards_tm->get_current_template();
		$css_block  = '';
		if ( $template ) {
			$inline_css = $template::get_inline_css();
			if ( $inline_css ) {
				$css_block = '<style>' . $inline_css . '</style>';
			}
			$fonts = $template::get_template_fonts();
			foreach ( $fonts as $font_key => $font_name ) {
				if ( ! empty( $font_name ) ) {
					$font_url   = 'https://fonts.googleapis.com/css2?family=' . urlencode( $font_name ) . ':wght@400;600;700;800&display=swap';
					$css_block .= '<link rel="stylesheet" href="' . esc_url( $font_url ) . '">';
				}
			}
		}

		wp_enqueue_script( 'pp-awards-year-filter', plugin_dir_url( __FILE__ ) . 'js/pp-awards-year-filter.js', array( 'jquery' ), $this->version, true );
		wp_localize_script( 'pp-awards-year-filter', 'ppAwardsFilter', array( 'ajaxUrl' => admin_url( 'admin-ajax.php' ) ) );

		$render = new Puck_Press_Awards_Render_Utils();
		return $css_block . $render->render_shortcode( $atts );
	}

	public function stat_leaders_skaters_shortcode( $atts = array() ) {
		$atts        = shortcode_atts( array( 'roster' => '', 'show_header' => 'true' ), $atts );
		$slug        = sanitize_title( $atts['roster'] );
		$show_header = 'false' !== strtolower( trim( $atts['show_header'] ) );

		require_once plugin_dir_path( __FILE__ ) . '../includes/stat-leaders/class-puck-press-stat-leaders-render-utils.php';

		$teams  = $this->resolve_stat_leaders_teams( $slug );
		$render = new Puck_Press_Stat_Leaders_Render_Utils( 'skaters', $teams, $show_header );
		return $render->get_current_template_html();
	}

	public function stat_leaders_goalies_shortcode( $atts = array() ) {
		$atts        = shortcode_atts( array( 'roster' => '', 'show_header' => 'true' ), $atts );
		$slug        = sanitize_title( $atts['roster'] );
		$show_header = 'false' !== strtolower( trim( $atts['show_header'] ) );

		require_once plugin_dir_path( __FILE__ ) . '../includes/stat-leaders/class-puck-press-stat-leaders-render-utils.php';

		$teams  = $this->resolve_stat_leaders_teams( $slug );
		$render = new Puck_Press_Stat_Leaders_Render_Utils( 'goalies', $teams, $show_header );
		return $render->get_current_template_html();
	}

	private function resolve_stat_leaders_teams( string $slug ): array {
		if ( $slug === '' ) {
			return array();
		}
		require_once plugin_dir_path( __FILE__ ) . '../includes/roster/class-puck-press-roster-registry-wpdb-utils.php';
		$registry  = new Puck_Press_Roster_Registry_Wpdb_Utils();
		$roster    = $registry->get_roster_by_slug( $slug );
		$roster_id = $roster ? (int) $roster['id'] : 0;
		if ( $roster_id <= 0 ) {
			return array();
		}
		$team_ids = $registry->get_roster_team_ids( $roster_id );
		if ( empty( $team_ids ) ) {
			return array();
		}
		global $wpdb;
		$placeholders = implode( ', ', array_fill( 0, count( $team_ids ), '%d' ) );
		return $wpdb->get_col(
			$wpdb->prepare( "SELECT name FROM {$wpdb->prefix}pp_teams WHERE id IN ($placeholders)", $team_ids )
		) ?: array();
	}

	public function enqueue_stat_leaders_assets() {
		global $post;
		if ( ! is_a( $post, 'WP_Post' ) ) {
			return;
		}
		if ( ! has_shortcode( $post->post_content, 'pp-stat-leaders-skaters' )
			&& ! has_shortcode( $post->post_content, 'pp-stat-leaders-goalies' ) ) {
			return;
		}
		require_once plugin_dir_path( __FILE__ ) . '../public/templates/class-puck-press-template-manager-abstract.php';
		require_once plugin_dir_path( __FILE__ ) . '../public/templates/class-puck-press-stat-leaders-template-manager.php';
		new Puck_Press_Stat_Leaders_Template_Manager();
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
