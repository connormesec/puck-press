<?php

/**
 * Registers the /player/{slug} rewrite rule and pp_player query var.
 */
class Puck_Press_Rewrite_Manager
{
    public static function init(): void
    {
        add_action( 'init', [ self::class, 'add_rules' ] );
        add_filter( 'query_vars', [ self::class, 'register_query_vars' ] );
    }

    public static function add_rules(): void
    {
        add_rewrite_rule( '^player/([^/]+)/?$', 'index.php?pp_player=$matches[1]', 'top' );
    }

    public static function register_query_vars( array $vars ): array
    {
        $vars[] = 'pp_player';
        return $vars;
    }
}
