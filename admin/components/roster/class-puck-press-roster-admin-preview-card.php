<?php
class Puck_Press_Roster_Admin_Preview_Card extends Puck_Press_Admin_Preview_Card_Abstract {

	private int $roster_id;

	public function __construct( array $args = array(), int $roster_id = 1 ) {
		$this->roster_id = $roster_id;
		parent::__construct( $args );
	}

	protected function make_template_manager() {
		return new Puck_Press_Roster_Template_Manager( $this->roster_id );
	}

	protected function make_wpdb_utils() {
		return new Puck_Press_Roster_Wpdb_Utils();
	}

	protected function get_data_table_name(): string {
		return 'pp_team_players_display';
	}

	protected function get_outer_wrapper_id(): string {
		return 'pp-roster-preview-wrapper';
	}

	protected function get_inner_preview_id(): string {
		return 'pp-roster-preview';
	}

	public function init() {
		global $wpdb;
		$registry = new Puck_Press_Roster_Registry_Wpdb_Utils();

		// Step 1: resolve team IDs for this roster.
		$team_ids = $registry->get_roster_team_ids( $this->roster_id );
		error_log( '[PP Roster Preview] init() step 1 — roster_id=' . $this->roster_id . ' team_ids=[' . implode( ', ', $team_ids ) . '] count=' . count( $team_ids ) );

		// Step 2: query players from display table.
		if ( ! empty( $team_ids ) ) {
			$placeholders = implode( ', ', array_fill( 0, count( $team_ids ), '%d' ) );
			$this->data   = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT * FROM {$wpdb->prefix}pp_team_players_display WHERE team_id IN ($placeholders)",
					$team_ids
				),
				ARRAY_A
			) ?? array();
		} else {
			$this->data = array();
		}
		error_log( '[PP Roster Preview] init() step 2 — players fetched from pp_team_players_display: ' . count( $this->data ) );

		// Step 3: load templates.
		$this->templates             = $this->template_manager->get_all_templates();
		$this->selected_template_key = $this->template_manager->get_current_template_key();
		$this->template_manager->enqueue_all_template_assets();
		$template_keys = array_map( fn( $t ) => $t->get_key(), $this->templates );
		error_log( '[PP Roster Preview] init() step 3 — templates=[' . implode( ', ', $template_keys ) . '] selected=' . $this->selected_template_key );
	}

	public function get_all_templates_html() {
		$output = '';
		foreach ( $this->templates as $template ) {
			$html    = $template->render_with_options( $this->data, array( 'roster_id' => $this->roster_id ) );
			error_log( '[PP Roster Preview] get_all_templates_html() — template=' . $template->get_key() . ' html_length=' . strlen( $html ) );
			$output .= $html;
		}
		error_log( '[PP Roster Preview] get_all_templates_html() — total html_length=' . strlen( $output ) );
		return $output;
	}

	public function get_template_html( $template_name ) {
		foreach ( $this->templates as $template ) {
			if ( $template->get_key() === $template_name ) {
				return $template->render_with_options( $this->data, array( 'roster_id' => $this->roster_id ) );
			}
		}
		return '<p>Template not found: ' . esc_html( $template_name ) . '</p>';
	}

	public function render_header_button_content() {
		ob_start();
		?>
		<button class="pp-button pp-button-primary" id="pp-roster-colorPaletteBtn">
			<i>🎨</i>
			Customize Colors
		</button>
		<?php
		return ob_get_clean();
	}
}
