<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Puck_Press_Record_Admin_Preview_Card extends Puck_Press_Admin_Preview_Card_Abstract {

	private int $schedule_id;
	private string $schedule_name = '';

	public function __construct( array $options = array() ) {
		$this->schedule_id = (int) ( $options['schedule_id'] ?? 1 );
		parent::__construct( $options );
	}

	protected function make_template_manager() {
		return new Puck_Press_Record_Template_Manager( $this->schedule_id );
	}

	protected function make_wpdb_utils() {
		return new Puck_Press_Record_Wpdb_Utils();
	}

	protected function get_data_table_name(): string {
		return 'pp_game_schedule_for_display';
	}

	protected function get_outer_wrapper_id(): string {
		return 'pp-record-preview-wrapper';
	}

	protected function get_inner_preview_id(): string {
		return 'pp-record-preview';
	}

	private function get_render_options(): array {
		return array(
			'schedule_id'   => $this->schedule_id,
			'schedule_name' => $this->schedule_name,
		);
	}

	public function get_all_templates_html(): string {
		$output = '';
		foreach ( $this->templates as $template ) {
			$output .= $template->render_with_options( $this->data, $this->get_render_options() );
		}
		return $output;
	}

	public function get_template_html( $template_name ): string {
		foreach ( $this->templates as $template ) {
			if ( $template->get_key() === $template_name ) {
				return $template->render_with_options( $this->data, $this->get_render_options() );
			}
		}
		return '<p>Template not found: ' . esc_html( $template_name ) . '</p>';
	}

	public function init() {
		$this->templates             = $this->template_manager->get_all_templates();
		$this->selected_template_key = $this->template_manager->get_current_template_key();
		$this->template_manager->enqueue_all_template_assets();

		global $wpdb;
		$this->schedule_name = (string) ( $wpdb->get_var(
			$wpdb->prepare( "SELECT name FROM {$wpdb->prefix}pp_schedules WHERE id = %d LIMIT 1", $this->schedule_id )
		) ?? '' );

		if ( $this->selected_template_key === 'slim_conference' ) {
			$this->data = array(
				'rows'           => $this->wpdb_utils->get_multi_source_stats_with_overall( $this->schedule_id ),
				'show_home_away' => 'true',
				'show_goals'     => 'true',
				'show_diff'      => 'true',
			);
		} elseif ( $this->selected_template_key === 'conference' ) {
			$this->data = array(
				'rows'           => $this->wpdb_utils->get_multi_source_stats( $this->schedule_id ),
				'show_home_away' => 'true',
				'show_goals'     => 'true',
				'show_diff'      => 'true',
			);
		} else {
			$this->data = $this->wpdb_utils->get_record_stats( $this->schedule_id );
		}
	}

	public function render_header_button_content() {
		ob_start();
		?>
		<button class="pp-button pp-button-primary" id="pp-record-colorPaletteBtn">
			<i>🎨</i>
			Customize Colors
		</button>
		<?php
		return ob_get_clean();
	}
}
