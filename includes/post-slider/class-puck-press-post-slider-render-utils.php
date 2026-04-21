<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Puck_Press_Post_Slider_Render_Utils {

    private array $posts;
    private string $more_url;
    private string $more_text;
    private Puck_Press_Post_Slider_Template_Manager $template_manager;

    public function __construct( string $post_type, int $count, string $more_url, string $more_text, string $team = '' ) {
        $post_types = array_map( 'sanitize_key', array_map( 'trim', explode( ',', $post_type ) ) );

        $query_args = array(
            'post_type'      => count( $post_types ) === 1 ? $post_types[0] : $post_types,
            'posts_per_page' => $count,
            'post_status'    => 'publish',
            'orderby'        => 'date',
            'order'          => 'DESC',
        );

        if ( $team !== '' ) {
            $team_id = self::resolve_team_id( $team );
            if ( $team_id > 0 ) {
                $query_args['meta_query'] = array(
                    array(
                        'key'     => '_pp_team_id',
                        'value'   => $team_id,
                        'compare' => '=',
                        'type'    => 'NUMERIC',
                    ),
                );
            } else {
                $query_args['post__in'] = array( 0 );
            }
        }

        $this->posts     = get_posts( $query_args );
        $this->more_url  = $more_url;
        $this->more_text = $more_text;
        $this->load_dependencies();
        $this->template_manager = new Puck_Press_Post_Slider_Template_Manager();
    }

    private static function resolve_team_id( string $team ): int {
        if ( ctype_digit( $team ) && (int) $team > 0 ) {
            return (int) $team;
        }
        $cache_key = 'pp_team_id_slug_' . $team;
        $cached    = wp_cache_get( $cache_key, 'puck_press' );
        if ( $cached !== false ) {
            return (int) $cached;
        }
        global $wpdb;
        $id     = $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$wpdb->prefix}pp_teams WHERE slug = %s LIMIT 1", $team ) );
        $result = $id ? (int) $id : 0;
        wp_cache_set( $cache_key, $result, 'puck_press', 300 );
        return $result;
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
            'more_text' => $this->more_text === 'false' ? '' : $this->more_text,
        ) );
    }
}
