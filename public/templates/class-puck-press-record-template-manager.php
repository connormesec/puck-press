<?php

class Puck_Press_Record_Template_Manager extends Puck_Press_Template_Manager {

	private int $schedule_id;

	public function __construct( int $schedule_id = 1 ) {
		$this->schedule_id = $schedule_id;
		parent::__construct();
	}

	protected function get_template_dir(): string {
		return plugin_dir_path( __FILE__ ) . 'record-templates';
	}

	protected function get_option_prefix(): string {
		return "pp_record_{$this->schedule_id}_template_colors_";
	}

	protected function get_current_template_option(): string {
		return "pp_record_{$this->schedule_id}_current_template";
	}

	public function get_current_template_key(): string {
		$value = get_option( $this->get_current_template_option(), '' );
		if ( ! empty( $value ) ) {
			return $value;
		}

		if ( $this->schedule_id === 1 ) {
			$legacy = get_option( 'pp_current_record_template', '' );
			if ( ! empty( $legacy ) ) {
				return $legacy;
			}
		}

		return '';
	}

	public function get_all_template_colors(): array {
		$colors = array();
		foreach ( $this->templates as $key => $class_name ) {
			$option = $this->get_option_prefix() . $key;
			$saved  = get_option( $option, null );
			if ( $saved === null && $this->schedule_id === 1 ) {
				$saved = get_option( "pp_record_template_colors_{$key}", null );
			}
			$colors[ $key ] = is_array( $saved ) ? $saved : $class_name::get_default_colors();
		}
		return $colors;
	}

	public function get_all_template_fonts(): array {
		$fonts        = array();
		$fonts_prefix = "pp_record_{$this->schedule_id}_template_fonts_";
		foreach ( $this->templates as $key => $class_name ) {
			$option = $fonts_prefix . $key;
			$saved  = get_option( $option, null );
			if ( $saved === null && $this->schedule_id === 1 ) {
				$saved = get_option( "pp_record_template_fonts_{$key}", null );
			}
			$fonts[ $key ] = is_array( $saved ) ? $saved : $class_name::get_default_fonts();
		}
		return $fonts;
	}
}
