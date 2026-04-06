<?php

class Puck_Press_Standings_Template_Manager extends Puck_Press_Template_Manager {

    private int $team_id;

    public function __construct( int $team_id = 0 ) {
        $this->team_id = $team_id;
        parent::__construct();
    }

    protected function get_template_dir(): string {
        return plugin_dir_path( __FILE__ ) . 'standings-templates';
    }

    protected function get_option_prefix(): string {
        return "pp_standings_{$this->team_id}_template_colors_";
    }

    protected function get_current_template_option(): string {
        return "pp_standings_{$this->team_id}_current_template";
    }

    public function get_all_template_colors(): array {
        $colors = array();
        foreach ( $this->templates as $key => $class_name ) {
            $option = $this->get_option_prefix() . $key;
            $saved  = get_option( $option, null );
            $colors[ $key ] = is_array( $saved ) ? $saved : $class_name::get_default_colors();
        }
        return $colors;
    }

    public function get_all_template_fonts(): array {
        $fonts        = array();
        $fonts_prefix = "pp_standings_{$this->team_id}_template_fonts_";
        foreach ( $this->templates as $key => $class_name ) {
            $option = $fonts_prefix . $key;
            $saved  = get_option( $option, null );
            $fonts[ $key ] = is_array( $saved ) ? $saved : $class_name::get_default_fonts();
        }
        return $fonts;
    }
}
