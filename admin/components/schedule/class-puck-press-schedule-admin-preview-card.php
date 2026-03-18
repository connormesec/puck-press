<?php
class Puck_Press_Schedule_Admin_Preview_Card extends Puck_Press_Admin_Preview_Card_Abstract {

	private int $schedule_id;

	public function __construct( array $args = array(), int $schedule_id = 1 ) {
		$this->schedule_id = $schedule_id;
		parent::__construct( $args );
	}

	public static function create_and_init( int $schedule_id = 1 ) {
		$instance = new static( array(), $schedule_id );
		$instance->init();
		return $instance;
	}

	protected function make_template_manager() {
		return new Puck_Press_Schedule_Template_Manager( $this->schedule_id ); }
	protected function get_data_table_name(): string {
		return 'pp_schedule_games_display'; }
	protected function get_outer_wrapper_id(): string {
		return 'pp-schedule-preview-wrapper'; }
	protected function get_inner_preview_id(): string {
		return 'pp-game-schedule-preview'; }

	public function init() {
		$schedules_utils             = new Puck_Press_Schedules_Wpdb_Utils();
		$this->data                  = $schedules_utils->get_schedule_games_display( $this->schedule_id );
		$this->templates             = $this->template_manager->get_all_templates();
		$this->selected_template_key = $this->template_manager->get_current_template_key();
		$this->template_manager->enqueue_all_template_assets();
	}

	public function get_all_templates_html() {
		$output = '';
		foreach ( $this->templates as $template ) {
			$output .= $template->render_with_options( $this->data, array( 'schedule_id' => $this->schedule_id ) );
		}
		return $output;
	}

	public function get_template_html( $template_name ) {
		foreach ( $this->templates as $template ) {
			if ( $template->get_key() === $template_name ) {
				return $template->render_with_options( $this->data, array( 'schedule_id' => $this->schedule_id ) );
			}
		}
		return '<p>Template not found: ' . esc_html( $template_name ) . '</p>';
	}

	public function render_header_button_content() {
		ob_start();
		?>
		<button class="pp-button pp-button-primary" id="pp-schedule-colorPaletteBtn">
			<i>🎨</i>
			Customize Colors
		</button>
		<?php
		return ob_get_clean();
	}
}
