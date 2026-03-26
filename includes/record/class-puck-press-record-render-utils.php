<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Puck_Press_Record_Render_Utils {

	private $template_manager;
	private $wpdb_utils;
	private int $schedule_id;

	public function __construct( int $schedule_id = 1 ) {
		$this->load_dependencies();
		$this->template_manager = new Puck_Press_Record_Template_Manager( $schedule_id );
		$this->wpdb_utils       = new Puck_Press_Record_Wpdb_Utils();
		$this->schedule_id      = $schedule_id;
	}

	private function load_dependencies(): void {
		require_once plugin_dir_path( __FILE__ ) . '../../public/templates/class-puck-press-template-manager-abstract.php';
		require_once plugin_dir_path( __FILE__ ) . '../../public/templates/class-puck-press-record-template-manager.php';
		require_once plugin_dir_path( __FILE__ ) . 'class-puck-press-record-wpdb-utils.php';
	}

	/**
	 * Render the currently-active record template, merging shortcode attributes.
	 *
	 * Supported $atts keys (all optional, defaults shown):
	 *   show_home_away  'true'  — Show home/away split rows
	 *   show_goals      'true'  — Show GF / GA statistics
	 *   show_diff       'true'  — Show goal differential
	 *   title           'Team Record'
	 *
	 * @param array $atts Shortcode attributes (string values from shortcode_atts).
	 * @return string HTML output.
	 */
	public function get_current_template_html( array $atts = array() ): string {
		$template = $this->template_manager->get_current_template();
		if ( ! $template ) {
			return '';
		}

		$common = array(
			'show_home_away' => isset( $atts['show_home_away'] ) ? $atts['show_home_away'] : 'true',
			'show_goals'     => isset( $atts['show_goals'] )     ? $atts['show_goals']     : 'true',
			'show_diff'      => isset( $atts['show_diff'] )      ? $atts['show_diff']      : 'true',
			'show_pct'       => isset( $atts['show_pct'] )       ? $atts['show_pct']       : 'true',
			'title'          => isset( $atts['title'] )          ? $atts['title']          : '',
		);

		if ( $template->get_key() === 'conference' ) {
			$data = array_merge(
				$common,
				array( 'rows' => $this->wpdb_utils->get_multi_source_stats( $this->schedule_id ) )
			);
		} else {
			$stats = $this->wpdb_utils->get_record_stats( $this->schedule_id );
			$data  = array_merge(
				$stats,
				$common,
				array(
					'title' => isset( $atts['title'] ) ? $atts['title'] : 'Team Record',
					'team'  => isset( $atts['team'] )  ? $atts['team']  : '',
				)
			);
		}

		return $template->render_with_options( $data, array( 'schedule_id' => $this->schedule_id ) );
	}
}
