<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

require_once plugin_dir_path( dirname( __DIR__ ) ) . 'awards/class-puck-press-awards-wpdb-utils.php';

class PP_Api_Awards {

    private $api;

    public function __construct( Puck_Press_Rest_Api $api ) {
        $this->api = $api;
    }

    public function register(): void {
        $ns = $this->api->get_namespace();

        register_rest_route(
            $ns,
            '/awards',
            array(
                'methods'             => 'GET',
                'callback'            => array( $this, 'get_awards' ),
                'permission_callback' => '__return_true',
            )
        );

        register_rest_route(
            $ns,
            '/awards/(?P<slug>[^/]+)/players',
            array(
                'methods'             => 'GET',
                'callback'            => array( $this, 'get_award_players' ),
                'permission_callback' => '__return_true',
            )
        );
    }

    public function get_awards( WP_REST_Request $request ): WP_REST_Response {
        $utils  = new Puck_Press_Awards_Wpdb_Utils();
        $year   = sanitize_text_field( $request->get_param( 'year' ) ?: '' );
        $parent = sanitize_text_field( $request->get_param( 'parent' ) ?: '' );

        $awards = $utils->get_all_awards( $year ?: null );

        if ( $parent ) {
            $parent_lower = strtolower( $parent );
            $awards       = array_values(
                array_filter(
                    $awards,
                    function ( $a ) use ( $parent_lower ) {
                        return strtolower( $a['parent_name'] ?? '' ) === $parent_lower;
                    }
                )
            );
        }

        $data = array();
        foreach ( $awards as $a ) {
            $data[] = array(
                'id'          => (int) $a['id'],
                'name'        => $a['name'],
                'slug'        => $a['slug'],
                'year'        => $a['year'],
                'parent_name' => $a['parent_name'],
                'icon_type'   => $a['icon_type'],
                'icon_value'  => $a['icon_value'],
                'sort_order'  => (int) $a['sort_order'],
            );
        }

        return $this->api->success_response( $data );
    }

    public function get_award_players( WP_REST_Request $request ): WP_REST_Response {
        $slug  = sanitize_title( $request->get_param( 'slug' ) );
        $utils = new Puck_Press_Awards_Wpdb_Utils();
        $award = $utils->get_award_by_slug( $slug );

        if ( ! $award ) {
            return $this->api->error_response( 'not_found', 'Award not found.' );
        }

        $players = $utils->get_players_for_award( (int) $award['id'] );

        $player_data = array();
        foreach ( $players as $p ) {
            $player_data[] = array(
                'player_name'   => $p['player_name'],
                'player_slug'   => $p['player_slug'],
                'team_name'     => $p['team_name'],
                'position'      => $p['position'],
                'headshot_url'  => $p['headshot_url'],
                'team_logo_url' => $p['team_logo_url'],
                'is_external'   => (int) $p['is_external'],
            );
        }

        return $this->api->success_response(
            array(
                'award'   => array(
                    'id'          => (int) $award['id'],
                    'name'        => $award['name'],
                    'year'        => $award['year'],
                    'parent_name' => $award['parent_name'],
                    'icon_type'   => $award['icon_type'],
                    'icon_value'  => $award['icon_value'],
                ),
                'players' => $player_data,
            )
        );
    }
}
