<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Puck_Press_Post_Slider_Render_Utils {

    private array $posts;
    private string $more_url;
    private string $more_text;
    private Puck_Press_Post_Slider_Template_Manager $template_manager;

    public function __construct( string $post_type, int $count, string $more_url, string $more_text ) {
        $post_types      = array_map( 'sanitize_key', array_map( 'trim', explode( ',', $post_type ) ) );
        $this->posts     = get_posts( array(
            'post_type'      => count( $post_types ) === 1 ? $post_types[0] : $post_types,
            'posts_per_page' => $count,
            'post_status'    => 'publish',
            'orderby'        => 'date',
            'order'          => 'DESC',
        ) );
        $this->more_url  = $more_url;
        $this->more_text = $more_text;
        $this->load_dependencies();
        $this->template_manager = new Puck_Press_Post_Slider_Template_Manager();
    }

    private function load_dependencies(): void {
        require_once plugin_dir_path( __FILE__ ) . '../../public/templates/class-puck-press-template-manager-abstract.php';
        require_once plugin_dir_path( __FILE__ ) . '../../public/templates/class-puck-press-template-abstract.php';
        require_once plugin_dir_path( __FILE__ ) . '../../public/templates/class-puck-press-post-slider-template-manager.php';
    }

    public function get_html(): string {
        $template = $this->template_manager->get_current_template();
        if ( ! $template ) {
            return '';
        }
        return $template->render_with_options( $this->posts, array(
            'more_url'  => $this->more_url,
            'more_text' => $this->more_text,
        ) );
    }
}
