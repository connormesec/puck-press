<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Puck_Press_Stats_Admin_Preview_Card extends Puck_Press_Admin_Preview_Card_Abstract {

	protected function make_template_manager() {
		return new Puck_Press_Stats_Template_Manager();
	}

	protected function make_wpdb_utils() {
		return new Puck_Press_Stats_Wpdb_Utils();
	}

	// Not used — we override init() to build a composite data array.
	protected function get_data_table_name(): string {
		return '';
	}

	protected function get_outer_wrapper_id(): string {
		return 'pp-stats-preview-wrapper';
	}

	protected function get_inner_preview_id(): string {
		return 'pp-stats-preview';
	}

	/**
	 * Override init() to build the combined skaters/goalies/column_settings data array
	 * instead of fetching a flat table.
	 */
	public function init() {
		$this->data      = $this->wpdb_utils->get_stats_data();
		$this->templates             = $this->template_manager->get_all_templates();
		$this->selected_template_key = $this->template_manager->get_current_template_key();
		$this->template_manager->enqueue_all_template_assets();
	}

	public function render_header_button_content() {
		ob_start();
		?>
		<button class="pp-button pp-button-primary" id="pp-stats-colorPaletteBtn">
			<i>🎨</i>
			Customize Colors
		</button>
		<?php
		return ob_get_clean();
	}
}
