<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

require_once plugin_dir_path( dirname( __DIR__ ) ) . 'class-puck-press-wpdb-utils-base-abstract.php';
require_once plugin_dir_path( dirname( __DIR__ ) ) . 'teams/class-puck-press-teams-wpdb-utils.php';

class PP_Api_Teams {

    private $api;

    public function __construct( Puck_Press_Rest_Api $api ) {
        $this->api = $api;
    }

    public function register(): void {
        register_rest_route(
            $this->api->get_namespace(),
            '/teams',
            array(
                'methods'             => 'GET',
                'callback'            => array( $this, 'get_teams' ),
                'permission_callback' => '__return_true',
            )
        );
    }

    public function get_teams( WP_REST_Request $request ): WP_REST_Response {
        $utils = new Puck_Press_Teams_Wpdb_Utils();
        $teams = $utils->get_all_teams();

        $data = array();
        foreach ( $teams as $t ) {
            $data[] = array(
                'id'          => (int) $t['id'],
                'slug'        => $t['slug'],
                'name'        => $t['name'],
                'description' => $t['description'],
            );
        }

        return $this->api->success_response( $data );
    }
}
