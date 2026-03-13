<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Puck_Press_Stats_Render_Utils {

	private $template_manager;
	private $wpdb_utils;
	private int $roster_id;
	private bool $show_team;

	public function __construct( int $roster_id = 0, bool $show_team = true ) {
		$this->roster_id = $roster_id;
		$this->show_team = $show_team;
		$this->load_dependencies();
		$this->template_manager = new Puck_Press_Stats_Template_Manager();
		$this->wpdb_utils       = new Puck_Press_Stats_Wpdb_Utils();
	}

	private function load_dependencies(): void {
		require_once plugin_dir_path( __FILE__ ) . '../../public/templates/class-puck-press-template-manager-abstract.php';
		require_once plugin_dir_path( __FILE__ ) . '../../public/templates/class-puck-press-stats-template-manager.php';
		require_once plugin_dir_path( __FILE__ ) . 'class-puck-press-stats-wpdb-utils.php';
	}

	/**
	 * Render the currently-active stats template.
	 *
	 * @return string HTML output.
	 */
	public function get_current_template_html(): string {
		$template = $this->template_manager->get_current_template();
		if ( ! $template ) {
			return '';
		}

		$data = $this->wpdb_utils->get_stats_data( $this->roster_id );
		$data['column_settings']['show_team'] = $this->show_team;
		return $template->render( $data );
	}

	/**
	 * Render just the skater + goalie sections HTML for an archived season.
	 * Used by the archive AJAX handler to swap tbody content on the frontend.
	 *
	 * @return string HTML for both sections (no container wrapper).
	 */
	public function get_archive_sections_html( string $archive_key ): string {
		$template = $this->template_manager->get_current_template();
		if ( ! $template ) {
			return '';
		}

		$defaults = Puck_Press_Stats_Wpdb_Utils::get_default_column_settings();
		$saved    = get_option( 'pp_stats_column_settings', array() );
		$col      = array_merge( $defaults, is_array( $saved ) ? $saved : array() );

		$skaters = $this->wpdb_utils->get_archive_skater_stats( $archive_key );
		$goalies = $this->wpdb_utils->get_archive_goalie_stats( $archive_key );

		return $template->build_archive_sections( $skaters, $goalies, $col );
	}
}
