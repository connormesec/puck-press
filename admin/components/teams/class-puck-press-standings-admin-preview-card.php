<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Puck_Press_Standings_Admin_Preview_Card extends Puck_Press_Admin_Preview_Card_Abstract {

    private int $team_id;

    public function __construct( array $options = array() ) {
        $this->team_id = (int) ( $options['team_id'] ?? 0 );
        parent::__construct( $options );
    }

    protected function make_template_manager() {
        return new Puck_Press_Standings_Template_Manager( $this->team_id );
    }

    protected function get_data_table_name(): string {
        return 'pp_team_standings_cache';
    }

    protected function get_outer_wrapper_id(): string {
        return 'pp-standings-preview-wrapper';
    }

    protected function get_inner_preview_id(): string {
        return 'pp-standings-preview';
    }

    private function get_render_options(): array {
        return array( 'team_id' => $this->team_id );
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

        require_once plugin_dir_path( __DIR__ ) . '/../../includes/standings/class-puck-press-standings-wpdb-utils.php';
        $utils  = new Puck_Press_Standings_Wpdb_Utils();
        $cached = $utils->get_standings_for_team( $this->team_id );

        $overall_rows  = ( $cached && ! empty( $cached['standings_data'] ) ) ? $cached['standings_data'] : array();
        $division_rows = ( $cached && ! empty( $cached['division_standings_data'] ) ) ? $cached['division_standings_data'] : array();

        $this->data = array(
            'rows'           => ! empty( $division_rows ) ? $division_rows : $overall_rows,
            'overall_rows'   => ! empty( $division_rows ) ? $overall_rows : array(),
            'division_name'  => $cached['division_name'] ?? '',
            'show_home_away' => 'true',
            'show_goals'     => 'true',
            'show_pct'       => 'true',
            'show_streak'    => 'true',
            'show_title'     => 'true',
            'show_tabs'      => 'true',
            'highlight'      => 'true',
            'title'          => '',
        );
    }

    public function render_header_button_content() {
        ob_start();
        ?>
        <button class="pp-button pp-button-primary" id="pp-standings-colorPaletteBtn">
            <i>🎨</i>
            Customize Colors
        </button>
        <?php
        return ob_get_clean();
    }
}
