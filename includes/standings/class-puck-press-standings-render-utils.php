<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Puck_Press_Standings_Render_Utils {

    private Puck_Press_Standings_Template_Manager $template_manager;
    private Puck_Press_Standings_Wpdb_Utils $wpdb_utils;
    private int $team_id;

    public function __construct( int $team_id ) {
        $this->load_dependencies();
        $this->template_manager = new Puck_Press_Standings_Template_Manager( $team_id );
        $this->wpdb_utils       = new Puck_Press_Standings_Wpdb_Utils();
        $this->team_id          = $team_id;
    }

    private function load_dependencies(): void {
        require_once plugin_dir_path( __FILE__ ) . '../../public/templates/class-puck-press-template-manager-abstract.php';
        require_once plugin_dir_path( __FILE__ ) . '../../public/templates/class-puck-press-standings-template-manager.php';
        require_once plugin_dir_path( __FILE__ ) . 'class-puck-press-standings-wpdb-utils.php';
    }

    public function get_current_template_html( array $atts = array() ): string {
        $template = $this->template_manager->get_current_template();
        if ( ! $template ) {
            return '';
        }

        $cached = $this->wpdb_utils->get_standings_for_team( $this->team_id );
        if ( ! $cached || empty( $cached['standings_data'] ) ) {
            return '';
        }

        $division_rows = $cached['division_standings_data'] ?? array();
        $overall_rows  = $cached['standings_data'];

        if ( ! empty( $division_rows ) && ! empty( $overall_rows ) ) {
            $overall_by_id = array_column( $overall_rows, null, 'team_id' );
            foreach ( $division_rows as &$div_row ) {
                $tid = $div_row['team_id'] ?? '';
                if ( isset( $overall_by_id[ $tid ] ) ) {
                    $div_row['streak']  = $overall_by_id[ $tid ]['streak'] ?? '';
                    $div_row['last_10'] = $overall_by_id[ $tid ]['last_10'] ?? '';
                }
            }
            unset( $div_row );
        }

        $data = array(
            'rows'           => ! empty( $division_rows ) ? $division_rows : $overall_rows,
            'overall_rows'   => ! empty( $division_rows ) ? $overall_rows : array(),
            'division_name'  => $cached['division_name'] ?? '',
            'compact'        => $atts['compact'] ?? 'false',
            'show_home_away' => $atts['show_home_away'] ?? 'true',
            'show_goals'     => $atts['show_goals'] ?? 'true',
            'show_pct'       => $atts['show_pct'] ?? 'true',
            'show_streak'    => $atts['show_streak'] ?? 'true',
            'show_title'     => $atts['show_title'] ?? 'true',
            'show_tabs'      => $atts['show_tabs'] ?? 'true',
            'highlight'      => $atts['highlight'] ?? 'true',
            'title'          => $atts['title'] ?? '',
        );

        $options = array( 'team_id' => $this->team_id );

        return $template->render_with_options( $data, $options );
    }
}
