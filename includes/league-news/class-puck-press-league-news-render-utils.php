<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Puck_Press_League_News_Render_Utils {

    private array $posts;
    private Puck_Press_League_News_Template_Manager $template_manager;

    public function __construct() {
        $source      = get_option( 'pp_league_news_source', 'acha' );
        $category_id = (int) get_option( 'pp_league_news_' . $source . '_category', 1 );
        $count       = max( 1, (int) get_option( 'pp_league_news_count', 8 ) );
        $this->posts = Puck_Press_League_News_Api::get_posts( $source, $category_id, $count );
        $this->load_dependencies();
        $this->template_manager = new Puck_Press_League_News_Template_Manager();
    }

    private function load_dependencies(): void {
        require_once plugin_dir_path( __FILE__ ) . '../../public/templates/class-puck-press-template-manager-abstract.php';
        require_once plugin_dir_path( __FILE__ ) . '../../public/templates/class-puck-press-template-abstract.php';
        require_once plugin_dir_path( __FILE__ ) . '../../public/templates/class-puck-press-league-news-template-manager.php';
    }

    public function get_html(): string {
        $template = $this->template_manager->get_current_template();
        if ( ! $template ) {
            return '';
        }
        return $template->render_with_options( $this->posts, array() );
    }
}
