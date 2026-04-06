<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

require_once plugin_dir_path( dirname( __DIR__ ) ) . 'class-puck-press-wpdb-utils-base-abstract.php';
require_once plugin_dir_path( dirname( __DIR__ ) ) . 'stats/class-puck-press-stats-wpdb-utils.php';
require_once plugin_dir_path( dirname( __DIR__ ) ) . 'archive/class-puck-press-archive-manager.php';
require_once plugin_dir_path( dirname( __DIR__ ) ) . 'teams/class-puck-press-teams-wpdb-utils.php';

class PP_Api_Archives {

    private $api;

    public function __construct( Puck_Press_Rest_Api $api ) {
        $this->api = $api;
    }

    public function register(): void {
        $ns = $this->api->get_namespace();

        register_rest_route(
            $ns,
            '/archives',
            array(
                'methods'             => 'GET',
                'callback'            => array( $this, 'get_archives' ),
                'permission_callback' => '__return_true',
            )
        );

        register_rest_route(
            $ns,
            '/archives/(?P<season_key>[^/]+)/stats/skaters',
            array(
                'methods'             => 'GET',
                'callback'            => array( $this, 'get_archive_skaters' ),
                'permission_callback' => '__return_true',
            )
        );

        register_rest_route(
            $ns,
            '/archives/(?P<season_key>[^/]+)/stats/goalies',
            array(
                'methods'             => 'GET',
                'callback'            => array( $this, 'get_archive_goalies' ),
                'permission_callback' => '__return_true',
            )
        );

        register_rest_route(
            $ns,
            '/archives/(?P<season_key>[^/]+)/scores',
            array(
                'methods'             => 'GET',
                'callback'            => array( $this, 'get_archive_scores' ),
                'permission_callback' => '__return_true',
            )
        );
    }

    public function get_archives( WP_REST_Request $request ): WP_REST_Response {
        $teams = $this->api->parse_teams_param( $request->get_param( 'teams' ) );
        $utils = new Puck_Press_Stats_Wpdb_Utils();
        $list  = $utils->get_archive_list( $teams );

        return $this->api->success_response( $list, 200, 3600 );
    }

    public function get_archive_skaters( WP_REST_Request $request ): WP_REST_Response {
        $key   = sanitize_key( $request->get_param( 'season_key' ) );
        $teams = $this->api->parse_teams_param( $request->get_param( 'teams' ) );
        $utils = new Puck_Press_Stats_Wpdb_Utils();
        $rows  = $utils->get_archive_skater_stats_aggregated( $key, $teams );

        $sort = sanitize_key( $request->get_param( 'sort' ) ?: 'points' );
        usort(
            $rows,
            function ( $a, $b ) use ( $sort ) {
                return (float) ( $b[ $sort ] ?? 0 ) <=> (float) ( $a[ $sort ] ?? 0 );
            }
        );

        $limit = absint( $request->get_param( 'limit' ) );
        if ( $limit ) {
            $rows = array_slice( $rows, 0, $limit );
        }

        return $this->api->success_response( $rows, 200, 3600 );
    }

    public function get_archive_goalies( WP_REST_Request $request ): WP_REST_Response {
        $key   = sanitize_key( $request->get_param( 'season_key' ) );
        $teams = $this->api->parse_teams_param( $request->get_param( 'teams' ) );
        $utils = new Puck_Press_Stats_Wpdb_Utils();
        $rows  = $utils->get_archive_goalie_stats_aggregated( $key, $teams );

        $sort = sanitize_key( $request->get_param( 'sort' ) ?: 'wins' );
        usort(
            $rows,
            function ( $a, $b ) use ( $sort ) {
                return (float) ( $b[ $sort ] ?? 0 ) <=> (float) ( $a[ $sort ] ?? 0 );
            }
        );

        $limit = absint( $request->get_param( 'limit' ) );
        if ( $limit ) {
            $rows = array_slice( $rows, 0, $limit );
        }

        return $this->api->success_response( $rows, 200, 3600 );
    }

    public function get_archive_scores( WP_REST_Request $request ): WP_REST_Response {
        $key       = sanitize_key( $request->get_param( 'season_key' ) );
        $team_ids  = $this->api->parse_teams_param( $request->get_param( 'teams' ) );
        $team_names = array();

        if ( ! empty( $team_ids ) ) {
            $teams_utils = new Puck_Press_Teams_Wpdb_Utils();
            foreach ( $team_ids as $tid ) {
                $team = $teams_utils->get_team_by_id( $tid );
                if ( $team ) {
                    $team_names[] = $team['name'];
                }
            }
        }

        $archive = new Puck_Press_Archive_Manager();
        $games   = $archive->get_archive_games( $key, $team_names );

        $limit = absint( $request->get_param( 'limit' ) );
        if ( $limit ) {
            $games = array_slice( $games, 0, $limit );
        }

        return $this->api->success_response( $games, 200, 3600 );
    }
}
