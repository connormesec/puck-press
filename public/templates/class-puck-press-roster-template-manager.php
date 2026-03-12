<?php
class Puck_Press_Roster_Template_Manager extends Puck_Press_Template_Manager {

	private int $roster_id;

	public function __construct( int $roster_id = 1 ) {
		$this->roster_id = $roster_id;
		parent::__construct();
	}

	protected function get_template_dir(): string {
		return plugin_dir_path( __FILE__ ) . 'roster-templates';
	}

	protected function get_option_prefix(): string {
		return "pp_roster_{$this->roster_id}_template_colors_";
	}

	protected function get_current_template_option(): string {
		return "pp_roster_{$this->roster_id}_current_template";
	}

	public function get_current_template_key() {
		$key = get_option( $this->get_current_template_option(), '' );
		if ( $this->roster_id === 1 && ( empty( $key ) || ! isset( $this->templates[ $key ] ) ) ) {
			$key = get_option( 'pp_current_roster_template', '' );
		}
		return $key;
	}

	public function get_all_template_colors(): array {
		$colors = array();
		foreach ( $this->templates as $key => $class_name ) {
			$colors[ $key ] = $class_name::get_roster_colors( $this->roster_id );
		}
		return $colors;
	}

	public function get_all_template_fonts(): array {
		$fonts = array();
		foreach ( $this->templates as $key => $class_name ) {
			$fonts[ $key ] = $class_name::get_roster_fonts( $this->roster_id );
		}
		return $fonts;
	}

	public function get_roster_id(): int {
		return $this->roster_id;
	}
}
