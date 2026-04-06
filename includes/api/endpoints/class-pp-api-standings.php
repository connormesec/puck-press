<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

require_once plugin_dir_path( dirname( __DIR__ ) ) . 'standings/class-puck-press-standings-wpdb-utils.php';

class PP_Api_Standings {

    private Puck_Press_Rest_Api $api;

    public function __construct( Puck_Press_Rest_Api $api ) {
        $this->api = $api;
    }

    public function register(): void {
        $ns = $this->api->get_namespace();

        register_rest_route(
            $ns,
            '/teams/(?P<id>\d+)/division-standings',
            array(
                'methods'             => 'GET',
                'callback'            => array( $this, 'get_division_standings' ),
                'permission_callback' => '__return_true',
            )
        );
    }

    public function get_division_standings( WP_REST_Request $request ): WP_REST_Response {
        $team_id = absint( $request->get_param( 'id' ) );

        $utils  = new Puck_Press_Standings_Wpdb_Utils();
        $cached = $utils->get_standings_for_team( $team_id );

        if ( ! $cached ) {
            return $this->api->error_response( 'not_found', 'No standings cached for this team.' );
        }

        return $this->api->success_response( array(
            'team_id'        => $team_id,
            'league_type'    => $cached['league_type'] ?? '',
            'division_name'  => $cached['division_name'] ?? '',
            'computed_at'    => $cached['computed_at'] ?? '',
            'standings'      => $cached['standings_data'] ?? array(),
        ) );
    }
}
