<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

require_once plugin_dir_path( dirname( __DIR__ ) ) . 'class-puck-press-wpdb-utils-base-abstract.php';
require_once plugin_dir_path( dirname( __DIR__ ) ) . 'roster/class-puck-press-roster-registry-wpdb-utils.php';

class PP_Api_Roster {

    private $api;

    public function __construct( Puck_Press_Rest_Api $api ) {
        $this->api = $api;
    }

    public function register(): void {
        $ns = $this->api->get_namespace();

        register_rest_route(
            $ns,
            '/rosters/(?P<id>\d+)/players',
            array(
                'methods'             => 'GET',
                'callback'            => array( $this, 'get_roster_players' ),
                'permission_callback' => '__return_true',
            )
        );

        register_rest_route(
            $ns,
            '/players/(?P<player_id>[^/]+)',
            array(
                'methods'             => 'GET',
                'callback'            => array( $this, 'get_player' ),
                'permission_callback' => '__return_true',
            )
        );
    }

    public function get_roster_players( WP_REST_Request $request ): WP_REST_Response {
        $id    = absint( $request->get_param( 'id' ) );
        $utils = new Puck_Press_Roster_Registry_Wpdb_Utils();

        if ( ! $utils->get_roster_by_id( $id ) ) {
            return $this->api->error_response( 'not_found', 'Roster not found.' );
        }

        $players = $utils->get_roster_players_display( $id );
        $pos     = sanitize_text_field( $request->get_param( 'pos' ) ?: '' );

        if ( $pos ) {
            $pos_upper = strtoupper( $pos );
            $players   = array_values(
                array_filter(
                    $players,
                    function ( $p ) use ( $pos_upper ) {
                        return strtoupper( $p['pos'] ?? '' ) === $pos_upper;
                    }
                )
            );
        }

        return $this->api->success_response( $players );
    }

    public function get_player( WP_REST_Request $request ): WP_REST_Response {
        global $wpdb;

        $player_id = sanitize_text_field( $request->get_param( 'player_id' ) );
        $team_id   = absint( $request->get_param( 'team_id' ) );
        $d         = $wpdb->prefix . 'pp_team_players_display';
        $t         = $wpdb->prefix . 'pp_teams';

        if ( $team_id ) {
            $player = $wpdb->get_row(
                $wpdb->prepare(
                    "SELECT d.*, t.name AS team_name FROM $d d JOIN $t t ON t.id = d.team_id WHERE d.player_id = %s AND d.team_id = %d LIMIT 1",
                    $player_id,
                    $team_id
                ),
                ARRAY_A
            );
        } else {
            $player = $wpdb->get_row(
                $wpdb->prepare(
                    "SELECT d.*, t.name AS team_name FROM $d d JOIN $t t ON t.id = d.team_id WHERE d.player_id = %s ORDER BY d.team_id ASC LIMIT 1",
                    $player_id
                ),
                ARRAY_A
            );
        }

        if ( ! $player ) {
            return $this->api->error_response( 'not_found', 'Player not found.' );
        }

        $is_goalie   = strtoupper( $player['pos'] ?? '' ) === 'G';
        $p_team_id   = (int) $player['team_id'];
        $stats_table = $is_goalie
            ? $wpdb->prefix . 'pp_team_player_goalie_stats'
            : $wpdb->prefix . 'pp_team_player_stats';

        $stats_row = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM $stats_table WHERE player_id = %s AND team_id = %d LIMIT 1",
                $player_id,
                $p_team_id
            ),
            ARRAY_A
        );

        $player['stats'] = $stats_row ?: null;

        return $this->api->success_response( $player );
    }
}
