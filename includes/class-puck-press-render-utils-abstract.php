<?php

abstract class Puck_Press_Render_Utils_Abstract {

	protected $template_manager;
	protected $wpdb_utils;
	protected $games;
	protected $templates;
	protected $selected_template_key;

	/**
	 * Subclasses override this to return a JSON-LD <script> block.
	 * Default: empty string (no schema output).
	 */
	protected function build_schema(): string {
		return '';
	}

	// Return HTML string for all templates
	public function get_all_templates_html(): string {
		$output = '';
		foreach ( $this->templates as $template ) {
			$output .= $template->render( $this->games );
		}
		return $output;
	}

	// Return HTML string for a specific template
	public function get_template_html( string $template_name, array $options = array() ): string {
		foreach ( $this->templates as $template ) {
			if ( $template->get_key() === $template_name ) {
				return $template->render_with_options( $this->games, $options );
			}
		}
		return '<p>Template not found: ' . esc_html( $template_name ) . '</p>';
	}

	// Return JSON-LD + HTML for the active template
	public function get_current_template_html( array $options = array() ): string {
		return $this->build_schema() . $this->get_template_html( $this->selected_template_key, $options );
	}

	// Echo all templates
	public function render_all_templates(): void {
		$this->template_manager->enqueue_all_template_assets();
		echo $this->get_all_templates_html();
	}

	// Echo one template
	public function render_template( string $template_name ): void {
		$this->template_manager->enqueue_template_assets( $template_name );
		echo $this->get_template_html( $template_name );
	}

	public function render_current_template(): void {
		$this->render_template( $this->selected_template_key );
	}
}
