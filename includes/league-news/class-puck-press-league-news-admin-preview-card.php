<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Puck_Press_League_News_Admin_Preview_Card {

    private static function load_dependencies(): void {
        require_once plugin_dir_path( __FILE__ ) . '../../public/templates/class-puck-press-template-manager-abstract.php';
        require_once plugin_dir_path( __FILE__ ) . '../../public/templates/class-puck-press-template-abstract.php';
        require_once plugin_dir_path( __FILE__ ) . '../../public/templates/class-puck-press-league-news-template-manager.php';
        require_once plugin_dir_path( __FILE__ ) . 'class-puck-press-league-news-api.php';
    }

    private static function get_preview_posts(): array {
        $source      = get_option( 'pp_league_news_source', 'acha' );
        $category_id = (int) get_option( 'pp_league_news_' . $source . '_category', 1 );
        $count       = max( 1, (int) get_option( 'pp_league_news_count', 8 ) );
        return Puck_Press_League_News_Api::get_posts( $source, $category_id, $count );
    }

    public static function get_all_templates_html(): string {
        self::load_dependencies();
        $posts   = self::get_preview_posts();
        $manager = new Puck_Press_League_News_Template_Manager();
        $manager->enqueue_all_template_assets();
        $html    = '';

        foreach ( $manager->get_all_templates() as $key => $template ) {
            $html .= $template->render_with_options( $posts, array() );
        }

        return $html;
    }

    public static function get_current_template_html(): string {
        self::load_dependencies();
        $posts    = self::get_preview_posts();
        $manager  = new Puck_Press_League_News_Template_Manager();
        $template = $manager->get_current_template();

        if ( ! $template ) {
            return '';
        }

        return $template->render_with_options( $posts, array() );
    }
}
