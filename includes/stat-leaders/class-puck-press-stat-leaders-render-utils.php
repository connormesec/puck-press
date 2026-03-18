<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Puck_Press_Stat_Leaders_Render_Utils {

	private $template_manager;
	private $wpdb_utils;
	private array $teams;
	private string $type;
	private bool $show_header;

	public function __construct( string $type, array $teams = array(), bool $show_header = true ) {
		$this->type        = $type;
		$this->teams       = $teams;
		$this->show_header = $show_header;
		$this->load_dependencies();
		$this->template_manager = new Puck_Press_Stat_Leaders_Template_Manager();
		$this->wpdb_utils       = new Puck_Press_Stat_Leaders_Wpdb_Utils();
	}

	public function get_current_template_html(): string {
		$template = $this->template_manager->get_current_template();
		if ( ! $template ) {
			return '';
		}

		$rows = $this->type === 'goalies'
			? $this->wpdb_utils->get_goalie_leaders( $this->teams )
			: $this->wpdb_utils->get_skater_leaders( $this->teams );

		$team_colors = get_option( 'pp_stat_leaders_team_colors', array() );
		$more_link   = get_option( 'pp_stat_leaders_more_link', '' );
		$show_team   = (bool) get_option( 'pp_stat_leaders_show_team', 1 );

		$data = array(
			'rows'        => $rows,
			'show_team'   => $show_team,
			'show_header' => $this->show_header,
			'more_link'   => is_string( $more_link ) ? $more_link : '',
			'team_colors' => is_array( $team_colors ) ? $team_colors : array(),
		);

		return $template->render( $data );
	}

	private function load_dependencies(): void {
		require_once plugin_dir_path( __FILE__ ) . '../../public/templates/class-puck-press-template-manager-abstract.php';
		require_once plugin_dir_path( __FILE__ ) . '../../public/templates/class-puck-press-stat-leaders-template-manager.php';
		require_once plugin_dir_path( __FILE__ ) . 'class-puck-press-stat-leaders-wpdb-utils.php';
	}
}
