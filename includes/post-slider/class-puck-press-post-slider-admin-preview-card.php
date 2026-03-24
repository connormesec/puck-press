<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Puck_Press_Post_Slider_Admin_Preview_Card {

    private static function load_dependencies(): void {
        require_once plugin_dir_path( __FILE__ ) . '../../public/templates/class-puck-press-template-manager-abstract.php';
        require_once plugin_dir_path( __FILE__ ) . '../../public/templates/class-puck-press-template-abstract.php';
        require_once plugin_dir_path( __FILE__ ) . '../../public/templates/class-puck-press-post-slider-template-manager.php';
    }

    private static function get_preview_posts(): array {
        $posts = get_posts( array(
            'post_type'      => array( 'post', 'pp_insta_post', 'pp_game_summary' ),
            'posts_per_page' => 6,
            'post_status'    => 'publish',
            'orderby'        => 'date',
            'order'          => 'DESC',
        ) );

        if ( empty( $posts ) ) {
            $posts = get_posts( array(
                'post_type'      => 'any',
                'posts_per_page' => 6,
                'post_status'    => 'publish',
                'orderby'        => 'date',
                'order'          => 'DESC',
            ) );
        }

        return $posts;
    }

    public static function get_all_templates_html(): string {
        self::load_dependencies();
        $posts   = self::get_preview_posts();
        $manager = new Puck_Press_Post_Slider_Template_Manager();
        $manager->enqueue_all_template_assets();
        $html    = '';

        foreach ( $manager->get_all_templates() as $key => $template ) {
            $html .= $template->render_with_options( $posts, array(
                'more_url'  => '#',
                'more_text' => 'More Posts',
            ) );
        }

        return $html;
    }

    public static function get_current_template_html(): string {
        self::load_dependencies();
        $posts    = self::get_preview_posts();
        $manager  = new Puck_Press_Post_Slider_Template_Manager();
        $template = $manager->get_current_template();

        if ( ! $template ) {
            return '';
        }

        return $template->render_with_options( $posts, array(
            'more_url'  => '#',
            'more_text' => 'More Posts',
        ) );
    }
}
