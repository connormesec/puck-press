<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Puck_Press_Api_Admin_Display {

    public function render(): string {
        $base_url = rest_url( 'puck-press/v1' );
        ob_start();
        include __DIR__ . '/api-docs-template.php';
        return ob_get_clean();
    }
}
