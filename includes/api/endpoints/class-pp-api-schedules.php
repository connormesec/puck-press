<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

require_once plugin_dir_path( dirname( __DIR__ ) ) . 'schedule/class-puck-press-schedules-wpdb-utils.php';

class PP_Api_Schedules {

    private $api;

    public function __construct( Puck_Press_Rest_Api $api ) {
        $this->api = $api;
    }

    public function register(): void {
        $ns = $this->api->get_namespace();

        register_rest_route(
            $ns,
            '/schedules',
            array(
                'methods'             => 'GET',
                'callback'            => array( $this, 'get_schedules' ),
                'permission_callback' => '__return_true',
            )
        );

        register_rest_route(
            $ns,
            '/schedules/(?P<id>\d+)/games',
            array(
                'methods'             => 'GET',
                'callback'            => array( $this, 'get_games' ),
                'permission_callback' => '__return_true',
            )
        );
    }

    public function get_schedules( WP_REST_Request $request ): WP_REST_Response {
        $utils     = new Puck_Press_Schedules_Wpdb_Utils();
        $schedules = $utils->get_all_schedules();

        $data = array();
        foreach ( $schedules as $s ) {
            $data[] = array(
                'id'      => (int) $s['id'],
                'slug'    => $s['slug'],
                'name'    => $s['name'],
                'is_main' => (bool) $s['is_main'],
            );
        }

        return $this->api->success_response( $data );
    }

    public function get_games( WP_REST_Request $request ): WP_REST_Response {
        $id    = absint( $request->get_param( 'id' ) );
        $utils = new Puck_Press_Schedules_Wpdb_Utils();

        if ( ! $utils->get_schedule_by_id( $id ) ) {
            return $this->api->error_response( 'not_found', 'Schedule not found.' );
        }

        $games  = $utils->get_schedule_games_display( $id );
        $status = sanitize_key( $request->get_param( 'status' ) ?: 'all' );
        $team   = absint( $request->get_param( 'team' ) );

        if ( $team ) {
            $games = array_values(
                array_filter(
                    $games,
                    function ( $g ) use ( $team ) {
                        return (int) $g['team_id'] === $team;
                    }
                )
            );
        }

        if ( 'completed' === $status ) {
            $games = array_values(
                array_filter(
                    $games,
                    function ( $g ) {
                        return $g['target_score'] !== null && $g['opponent_score'] !== null
                            && ! empty( $g['game_status'] ) && $g['game_status'] !== 'null';
                    }
                )
            );
        } elseif ( 'upcoming' === $status ) {
            $games = array_values(
                array_filter(
                    $games,
                    function ( $g ) {
                        return $g['target_score'] === null || $g['game_status'] === null || $g['game_status'] === '';
                    }
                )
            );
        }

        usort(
            $games,
            function ( $a, $b ) {
                return strcmp( $a['game_timestamp'] ?? '', $b['game_timestamp'] ?? '' );
            }
        );

        $games = $this->api->paginate_array( $games, $request );

        return $this->api->success_response( $games );
    }
}
