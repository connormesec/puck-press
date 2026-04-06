<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

require_once plugin_dir_path( dirname( __DIR__ ) ) . 'stats/class-puck-press-stats-wpdb-utils.php';
require_once plugin_dir_path( dirname( __DIR__ ) ) . 'stat-leaders/class-puck-press-stat-leaders-wpdb-utils.php';

class PP_Api_Stats {

    private $api;

    public function __construct( Puck_Press_Rest_Api $api ) {
        $this->api = $api;
    }

    public function register(): void {
        $ns = $this->api->get_namespace();

        register_rest_route(
            $ns,
            '/stats/skaters',
            array(
                'methods'             => 'GET',
                'callback'            => array( $this, 'get_skaters' ),
                'permission_callback' => '__return_true',
            )
        );

        register_rest_route(
            $ns,
            '/stats/goalies',
            array(
                'methods'             => 'GET',
                'callback'            => array( $this, 'get_goalies' ),
                'permission_callback' => '__return_true',
            )
        );

        register_rest_route(
            $ns,
            '/stats/leaders',
            array(
                'methods'             => 'GET',
                'callback'            => array( $this, 'get_leaders' ),
                'permission_callback' => '__return_true',
            )
        );
    }

    public function get_skaters( WP_REST_Request $request ): WP_REST_Response {
        $teams   = $this->api->parse_teams_param( $request->get_param( 'teams' ) );
        $utils   = new Puck_Press_Stats_Wpdb_Utils();
        $skaters = $utils->get_skater_stats( $teams );

        $sort = sanitize_key( $request->get_param( 'sort' ) ?: 'points' );
        $this->sort_rows( $skaters, $sort );

        $skaters = $this->api->paginate_array( $skaters, $request );

        return $this->api->success_response( $skaters );
    }

    public function get_goalies( WP_REST_Request $request ): WP_REST_Response {
        $teams   = $this->api->parse_teams_param( $request->get_param( 'teams' ) );
        $utils   = new Puck_Press_Stats_Wpdb_Utils();
        $goalies = $utils->get_goalie_stats( $teams );

        $sort = sanitize_key( $request->get_param( 'sort' ) ?: 'wins' );
        $this->sort_rows( $goalies, $sort );

        $goalies = $this->api->paginate_array( $goalies, $request );

        return $this->api->success_response( $goalies );
    }

    public function get_leaders( WP_REST_Request $request ): WP_REST_Response {
        $teams      = $this->api->parse_teams_param( $request->get_param( 'teams' ) );
        $categories = sanitize_text_field( $request->get_param( 'categories' ) ?: '' );
        $cat_filter = $categories ? array_map( 'trim', explode( ',', $categories ) ) : array();

        $utils          = new Puck_Press_Stat_Leaders_Wpdb_Utils();
        $skater_leaders = $utils->get_skater_leaders( $teams );
        $goalie_leaders = $utils->get_goalie_leaders( $teams );

        if ( ! empty( $cat_filter ) ) {
            $skater_leaders = array_values(
                array_filter(
                    $skater_leaders,
                    function ( $l ) use ( $cat_filter ) {
                        return in_array( $l['category'] ?? $l['stat_key'] ?? '', $cat_filter, true );
                    }
                )
            );
            $goalie_leaders = array_values(
                array_filter(
                    $goalie_leaders,
                    function ( $l ) use ( $cat_filter ) {
                        return in_array( $l['category'] ?? $l['stat_key'] ?? '', $cat_filter, true );
                    }
                )
            );
        }

        return $this->api->success_response(
            array(
                'skaters' => $skater_leaders,
                'goalies' => $goalie_leaders,
            )
        );
    }

    private function sort_rows( array &$rows, string $field ): void {
        usort(
            $rows,
            function ( $a, $b ) use ( $field ) {
                $av = (float) ( $a[ $field ] ?? 0 );
                $bv = (float) ( $b[ $field ] ?? 0 );
                return $bv <=> $av;
            }
        );
    }
}
