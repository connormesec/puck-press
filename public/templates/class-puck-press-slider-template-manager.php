<?php
class Puck_Press_Slider_Template_Manager extends Puck_Press_Template_Manager {

	private int $schedule_id;

	public function __construct( int $schedule_id = 1 ) {
		$this->schedule_id = $schedule_id;
		parent::__construct();
	}

	protected function get_template_dir(): string {
		return plugin_dir_path( __FILE__ ) . 'slider-templates';
	}

	protected function get_option_prefix(): string {
		return "pp_slider_{$this->schedule_id}_template_colors_";
	}

	protected function get_current_template_option(): string {
		return "pp_slider_{$this->schedule_id}_current_template";
	}

	public function get_current_template_key(): string {
		$value = get_option( $this->get_current_template_option(), '' );
		if ( ! empty( $value ) ) {
			return $value;
		}

		// Legacy: fall back to the global option used before per-schedule support.
		if ( $this->schedule_id === 1 ) {
			$legacy = get_option( 'pp_current_slider_template', '' );
			if ( ! empty( $legacy ) ) {
				return $legacy;
			}
		}

		return '';
	}

	public function enqueue_current_template_assets( $handle_prefix = 'puck-press' ) {
		parent::enqueue_current_template_assets( "puck-press-slider-{$this->schedule_id}" );
	}

	public function enqueue_all_template_assets( $handle_prefix = 'puck-press' ) {
		parent::enqueue_all_template_assets( "puck-press-slider-{$this->schedule_id}" );
	}

	public function get_all_template_colors(): array {
		$colors = array();
		foreach ( $this->templates as $key => $class_name ) {
			$colors[ $key ] = $class_name::get_slider_colors( $this->schedule_id );
		}
		return $colors;
	}

	public function get_all_template_fonts(): array {
		$fonts = array();
		foreach ( $this->templates as $key => $class_name ) {
			$fonts[ $key ] = $class_name::get_slider_fonts( $this->schedule_id );
		}
		return $fonts;
	}
}
