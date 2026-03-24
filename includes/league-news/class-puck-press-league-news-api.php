<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Puck_Press_League_News_Api {

    const TRANSIENT_TTL = 6 * HOUR_IN_SECONDS;
    const TRANSIENT_KEY = 'pp_league_news_posts_';

    const SOURCES = array(
        'acha'  => 'https://www.achahockey.org/wp-json/wp/v2/posts',
        'usphl' => 'https://usphl.com/wp-json/wp/v2/posts',
    );

    const CATEGORIES = array(
        'acha'  => array(
            1  => 'All News',
            13 => 'M1 News',
            14 => 'M2 News',
            15 => 'M3 News',
            16 => 'W1 News',
            17 => 'W2 News',
        ),
        'usphl' => array(
            13 => 'USPHL Premier',
            12 => 'USPHL Elite',
            9  => 'NCDC',
            11 => 'THF',
        ),
    );

    /**
     * Fetch and cache league news posts.
     *
     * @param string $source      'acha' or 'usphl'.
     * @param int    $category_id Category ID for the given source.
     * @param int    $count       Number of posts to return.
     * @return array Normalized array of post data.
     */
    public static function get_posts( string $source, int $category_id, int $count ): array {
        if ( ! isset( self::SOURCES[ $source ] ) ) {
            return array();
        }

        $transient_key = self::TRANSIENT_KEY . $source . '_' . $category_id;
        $cached        = get_transient( $transient_key );

        if ( false !== $cached && is_array( $cached ) ) {
            return array_slice( $cached, 0, $count );
        }

        $url      = add_query_arg(
            array(
                'categories' => $category_id,
                'per_page'   => 20,
                '_embed'     => 1,
                'orderby'    => 'date',
                'order'      => 'desc',
            ),
            self::SOURCES[ $source ]
        );
        $response = wp_remote_get( $url, array( 'timeout' => 10 ) );

        if ( is_wp_error( $response ) || 200 !== wp_remote_retrieve_response_code( $response ) ) {
            return array();
        }

        $body = json_decode( wp_remote_retrieve_body( $response ), true );

        if ( ! is_array( $body ) ) {
            return array();
        }

        $posts = array();
        foreach ( $body as $item ) {
            $image_url = '';
            if ( ! empty( $item['_embedded']['wp:featuredmedia'][0]['source_url'] ) ) {
                $image_url = $item['_embedded']['wp:featuredmedia'][0]['source_url'];
            }

            $posts[] = array(
                'title'     => wp_strip_all_tags( $item['title']['rendered'] ?? '' ),
                'link'      => $item['link'] ?? '#',
                'date'      => $item['date'] ?? '',
                'image_url' => $image_url,
            );
        }

        set_transient( $transient_key, $posts, self::TRANSIENT_TTL );

        return array_slice( $posts, 0, $count );
    }

    /**
     * Delete the cached posts transient for a given source and category.
     *
     * @param string $source      'acha' or 'usphl'.
     * @param int    $category_id Category ID.
     */
    public static function bust_cache( string $source, int $category_id ): void {
        delete_transient( self::TRANSIENT_KEY . $source . '_' . $category_id );
    }

    /**
     * Return the category map for a given source.
     *
     * @param string $source 'acha' or 'usphl'.
     * @return array Map of category_id => label.
     */
    public static function get_categories( string $source ): array {
        return self::CATEGORIES[ $source ] ?? array();
    }
}
