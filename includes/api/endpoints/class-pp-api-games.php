<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

require_once plugin_dir_path( dirname( __DIR__ ) ) . 'schedule/class-puck-press-schedules-wpdb-utils.php';

class PP_Api_Games {

    private $api;

    public function __construct( Puck_Press_Rest_Api $api ) {
        $this->api = $api;
    }

    public function register(): void {
        $ns = $this->api->get_namespace();

        register_rest_route(
            $ns,
            '/schedules/(?P<id>\d+)/scores/recent',
            array(
                'methods'             => 'GET',
                'callback'            => array( $this, 'get_recent_scores' ),
                'permission_callback' => '__return_true',
            )
        );

        register_rest_route(
            $ns,
            '/schedules/(?P<id>\d+)/games/upcoming',
            array(
                'methods'             => 'GET',
                'callback'            => array( $this, 'get_upcoming' ),
                'permission_callback' => '__return_true',
            )
        );

        register_rest_route(
            $ns,
            '/schedules/(?P<id>\d+)/games/next',
            array(
                'methods'             => 'GET',
                'callback'            => array( $this, 'get_next' ),
                'permission_callback' => '__return_true',
            )
        );
    }

    private function validate_schedule( int $id ): ?WP_REST_Response {
        $utils = new Puck_Press_Schedules_Wpdb_Utils();
        if ( ! $utils->get_schedule_by_id( $id ) ) {
            return $this->api->error_response( 'not_found', 'Schedule not found.' );
        }
        return null;
    }

    private function get_completed_games( int $schedule_id ): array {
        global $wpdb;
        $table = $wpdb->prefix . 'pp_schedule_games_display';
        return $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM $table
                WHERE schedule_id = %d
                  AND target_score IS NOT NULL
                  AND opponent_score IS NOT NULL
                  AND game_status IS NOT NULL
                  AND game_status NOT IN ('', 'null')
                ORDER BY game_timestamp DESC, id DESC",
                $schedule_id
            ),
            ARRAY_A
        ) ?? array();
    }

    private function get_upcoming_games( int $schedule_id ): array {
        global $wpdb;
        $table = $wpdb->prefix . 'pp_schedule_games_display';
        return $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM $table
                WHERE schedule_id = %d
                  AND (target_score IS NULL OR game_status IS NULL OR game_status = '')
                ORDER BY
                  CASE WHEN game_timestamp IS NULL THEN 1 ELSE 0 END ASC,
                  game_timestamp ASC,
                  id ASC",
                $schedule_id
            ),
            ARRAY_A
        ) ?? array();
    }

    public function get_recent_scores( WP_REST_Request $request ): WP_REST_Response {
        $id    = absint( $request->get_param( 'id' ) );
        $error = $this->validate_schedule( $id );
        if ( $error ) {
            return $error;
        }

        $limit = min( absint( $request->get_param( 'limit' ) ?: 5 ), 20 );
        $games = array_slice( $this->get_completed_games( $id ), 0, $limit );

        return $this->api->success_response( $games );
    }

    public function get_upcoming( WP_REST_Request $request ): WP_REST_Response {
        $id    = absint( $request->get_param( 'id' ) );
        $error = $this->validate_schedule( $id );
        if ( $error ) {
            return $error;
        }

        $limit = min( absint( $request->get_param( 'limit' ) ?: 5 ), 20 );
        $games = array_slice( $this->get_upcoming_games( $id ), 0, $limit );

        return $this->api->success_response( $games );
    }

    public function get_next( WP_REST_Request $request ): WP_REST_Response {
        $id    = absint( $request->get_param( 'id' ) );
        $error = $this->validate_schedule( $id );
        if ( $error ) {
            return $error;
        }

        $games = $this->get_upcoming_games( $id );
        $next  = ! empty( $games ) ? $games[0] : null;

        return $this->api->success_response( $next );
    }
}
