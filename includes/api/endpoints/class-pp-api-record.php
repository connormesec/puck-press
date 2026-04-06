<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

require_once plugin_dir_path( dirname( __DIR__ ) ) . 'schedule/class-puck-press-schedules-wpdb-utils.php';
require_once plugin_dir_path( dirname( __DIR__ ) ) . 'record/class-puck-press-record-wpdb-utils.php';

class PP_Api_Record {

    private $api;

    public function __construct( Puck_Press_Rest_Api $api ) {
        $this->api = $api;
    }

    public function register(): void {
        $ns = $this->api->get_namespace();

        register_rest_route(
            $ns,
            '/schedules/(?P<id>\d+)/record',
            array(
                'methods'             => 'GET',
                'callback'            => array( $this, 'get_record' ),
                'permission_callback' => '__return_true',
            )
        );

        register_rest_route(
            $ns,
            '/schedules/(?P<id>\d+)/standings',
            array(
                'methods'             => 'GET',
                'callback'            => array( $this, 'get_standings' ),
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

    public function get_record( WP_REST_Request $request ): WP_REST_Response {
        $id    = absint( $request->get_param( 'id' ) );
        $error = $this->validate_schedule( $id );
        if ( $error ) {
            return $error;
        }

        $utils  = new Puck_Press_Record_Wpdb_Utils();
        $record = $utils->get_record_stats( $id );

        $gf   = (int) ( $record['gf'] ?? 0 );
        $ga   = (int) ( $record['ga'] ?? 0 );
        $record['diff'] = $gf - $ga;

        return $this->api->success_response( $record );
    }

    public function get_standings( WP_REST_Request $request ): WP_REST_Response {
        $id    = absint( $request->get_param( 'id' ) );
        $error = $this->validate_schedule( $id );
        if ( $error ) {
            return $error;
        }

        $mode  = sanitize_key( $request->get_param( 'mode' ) ?: 'conference' );
        $utils = new Puck_Press_Record_Wpdb_Utils();

        if ( 'overall' === $mode ) {
            $rows = $utils->get_multi_source_stats_with_overall( $id );
        } else {
            $rows = $utils->get_multi_source_stats( $id );
        }

        foreach ( $rows as &$row ) {
            $w   = (int) ( $row['wins'] ?? 0 );
            $otl = (int) ( $row['otl'] ?? 0 );
            $t   = (int) ( $row['ties'] ?? 0 );
            $row['points'] = ( $w * 2 ) + $otl + $t;
        }
        unset( $row );

        usort(
            $rows,
            function ( $a, $b ) {
                return ( $b['points'] ?? 0 ) <=> ( $a['points'] ?? 0 );
            }
        );

        return $this->api->success_response( $rows );
    }
}
