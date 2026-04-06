<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Puck_Press_Rest_Api {

    private $namespace = 'puck-press/v1';

    public function register_routes(): void {
        $endpoint_dir = plugin_dir_path( __FILE__ ) . 'endpoints/';
        $files        = glob( $endpoint_dir . 'class-pp-api-*.php' );

        foreach ( $files as $file ) {
            require_once $file;
            $filename   = basename( $file, '.php' );
            $parts      = explode( '-', str_replace( 'class-pp-api-', '', $filename ) );
            $class_name = 'PP_Api_' . implode( '_', array_map( 'ucfirst', $parts ) );

            if ( class_exists( $class_name ) ) {
                $endpoint = new $class_name( $this );
                $endpoint->register();
            }
        }
    }

    public function get_namespace(): string {
        return $this->namespace;
    }

    public function success_response( $data, int $status = 200, int $max_age = 60 ): WP_REST_Response {
        $response = new WP_REST_Response(
            array(
                'success' => true,
                'data'    => $data,
                'meta'    => array(
                    'generated_at' => gmdate( 'Y-m-d\TH:i:s\Z' ),
                ),
            ),
            $status
        );
        $response->header( 'Cache-Control', 'public, max-age=' . $max_age );
        return $response;
    }

    public function error_response( string $code, string $message, int $status = 404 ): WP_REST_Response {
        return new WP_REST_Response(
            array(
                'success' => false,
                'error'   => array(
                    'code'    => $code,
                    'message' => $message,
                ),
            ),
            $status
        );
    }

    public function parse_teams_param( $param ): array {
        if ( empty( $param ) ) {
            return array();
        }
        return array_values( array_filter( array_map( 'absint', explode( ',', (string) $param ) ) ) );
    }

    public function parse_pagination( WP_REST_Request $request ): array {
        $limit = $request->get_param( 'limit' );
        if ( $limit !== null ) {
            return array(
                'limit'  => min( absint( $limit ), 500 ),
                'offset' => 0,
            );
        }

        $page     = max( 1, absint( $request->get_param( 'page' ) ?: 1 ) );
        $per_page = min( max( 1, absint( $request->get_param( 'per_page' ) ?: 50 ) ), 100 );

        return array(
            'limit'  => $per_page,
            'offset' => ( $page - 1 ) * $per_page,
        );
    }

    public function paginate_array( array $items, WP_REST_Request $request ): array {
        $p = $this->parse_pagination( $request );
        return array_slice( $items, $p['offset'], $p['limit'] );
    }
}
