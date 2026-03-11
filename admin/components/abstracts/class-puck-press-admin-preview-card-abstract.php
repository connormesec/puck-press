<?php
abstract class Puck_Press_Admin_Preview_Card_Abstract extends Puck_Press_Admin_Card_Abstract {

	protected $template_manager;
	protected $wpdb_utils;
	protected $data;
	protected $templates;
	protected $selected_template_key;

	// --- Factory methods (subclass provides the concrete class to instantiate) ---

	abstract protected function make_template_manager();
	abstract protected function make_wpdb_utils();

	// --- Template IDs (subclass provides the CSS element IDs) ---

	abstract protected function get_data_table_name(): string;
	abstract protected function get_outer_wrapper_id(): string;
	abstract protected function get_inner_preview_id(): string;

	// --- Shared implementations ---

	public function __construct( array $args = array() ) {
		parent::__construct( $args );
		$this->template_manager = $this->make_template_manager();
		$this->wpdb_utils       = $this->make_wpdb_utils();
	}

	public static function create_and_init() {
		$instance = new static();
		$instance->init();
		return $instance;
	}

	public function init() {
		$this->data                  = $this->wpdb_utils->get_all_table_data( $this->get_data_table_name(), 'ARRAY_A' );
		$this->templates             = $this->template_manager->get_all_templates();
		$this->selected_template_key = $this->template_manager->get_current_template_key();
		$this->template_manager->enqueue_all_template_assets();
	}

	public function render_content() {
		ob_start();
		$outer_id = $this->get_outer_wrapper_id();
		$inner_id = $this->get_inner_preview_id();
		?>
		<div id="<?php echo esc_attr( $outer_id ); ?>" class="loading">
			<div class="spinner-container">
				<span class="spinner is-active big-spinner"></span>
			</div>
			<div id="<?php echo esc_attr( $inner_id ); ?>">
				<?php echo $this->get_all_templates_html(); ?>
			</div>
		</div>
		<?php
		return ob_get_clean();
	}

	public function render_all_templates() {
		echo $this->get_all_templates_html();
	}

	public function render_template( $template_name ) {
		echo $this->get_template_html( $template_name );
	}

	public function get_all_templates_html() {
		$output = '';
		foreach ( $this->templates as $template ) {
			$output .= $template->render( $this->data );
		}
		return $output;
	}

	public function get_template_html( $template_name ) {
		foreach ( $this->templates as $template ) {
			if ( $template->get_key() === $template_name ) {
				return $template->render( $this->data );
			}
		}
		return '<p>Template not found: ' . esc_html( $template_name ) . '</p>';
	}

	public function render_current_template() {
		return $this->render_template( $this->selected_template_key );
	}

	public function get_current_template_html() {
		return $this->get_template_html( $this->selected_template_key );
	}
}
