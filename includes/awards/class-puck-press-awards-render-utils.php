<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

require_once plugin_dir_path( __FILE__ ) . 'class-puck-press-awards-wpdb-utils.php';
require_once plugin_dir_path( dirname( __DIR__ ) ) . 'public/templates/class-puck-press-template-abstract.php';

class Puck_Press_Awards_Render_Utils {

    private $wpdb_utils;

    public function __construct() {
        $this->wpdb_utils = new Puck_Press_Awards_Wpdb_Utils();
        $this->wpdb_utils->maybe_create_or_update_tables();
    }

    public function render_shortcode( $atts ): string {
        $atts = shortcode_atts(
            array(
                'award'          => '',
                'year'           => '',
                'parent'         => '',
                'show_headshots' => 'true',
                'columns'        => 6,
                'link_players'   => 'true',
            ),
            $atts,
            'pp-awards'
        );

        $award_str  = trim( $atts['award'] );
        $year       = trim( $atts['year'] );
        $parent     = trim( $atts['parent'] );
        $show_heads = ( 'false' !== strtolower( $atts['show_headshots'] ) );
        $columns    = max( 1, (int) $atts['columns'] );
        $link       = ( 'false' !== strtolower( $atts['link_players'] ) );

        if ( empty( $award_str ) && empty( $year ) && empty( $parent ) ) {
            return '<!-- [pp-awards] requires at least one of: award, year, parent -->';
        }

        $filters = array();
        if ( ! empty( $award_str ) ) {
            $filters['slugs'] = array_map( 'trim', explode( ',', $award_str ) );
        }
        if ( ! empty( $year ) ) {
            $filters['year'] = $year;
        }
        if ( ! empty( $parent ) ) {
            $filters['parent'] = $parent;
        }

        $awards = $this->wpdb_utils->get_awards_by_filters( $filters );
        if ( empty( $awards ) ) {
            return '<!-- [pp-awards] no matching awards found -->';
        }

        $fallback = PuckPressTemplate::HEADSHOT_FALLBACK;
        $html     = '<div class="pp-awards-wrap">';

        if ( ! empty( $parent ) ) {
            $html .= $this->render_grouped( $awards, $fallback, $show_heads, $columns, $link, $parent );
        } else {
            $html .= $this->render_flat( $awards, $fallback, $show_heads, $columns, $link );
        }

        $html .= '</div>';
        return $html;
    }

    private function render_grouped( array $awards, string $fallback, bool $show_heads, int $columns, bool $link, string $parent_label ): string {
        $first_award = $awards[0] ?? array();
        $icon_html   = $this->render_award_icon( $first_award );

        $html  = '<div class="pp-awards-group">';
        $html .= '<h2 class="pp-awards-group-title">' . $icon_html . ' ' . esc_html( $parent_label ) . '</h2>';

        foreach ( $awards as $award ) {
            $html .= $this->render_section( $award, $fallback, $show_heads, $columns, $link );
        }

        $html .= '</div>';
        return $html;
    }

    private function render_flat( array $awards, string $fallback, bool $show_heads, int $columns, bool $link ): string {
        $html = '';
        foreach ( $awards as $award ) {
            $html .= $this->render_section( $award, $fallback, $show_heads, $columns, $link );
        }
        return $html;
    }

    private function render_section( array $award, string $fallback, bool $show_heads, int $columns, bool $link ): string {
        $html  = '<div class="pp-awards-section">';
        $html .= '<h3 class="pp-awards-section-title">' . esc_html( $award['year'] . ' ' . $award['name'] ) . '</h3>';
        $html .= '<div class="pp-awards-grid" style="grid-template-columns:repeat(' . $columns . ',1fr);">';

        foreach ( $award['players'] as $p ) {
            $html .= $this->render_player_card( $p, $fallback, $show_heads, $link );
        }

        $html .= '</div></div>';
        return $html;
    }

    private function render_player_card( array $p, string $fallback, bool $show_heads, bool $link ): string {
        $name       = esc_html( $p['player_name'] );
        $is_db      = ! (int) $p['is_external'];
        $slug       = $p['player_slug'] ?? '';
        $can_link   = $link && $is_db && ! empty( $slug );
        $headshot   = ! empty( $p['headshot_url'] ) ? esc_url( $p['headshot_url'] ) : $fallback;
        $logo_url   = ! empty( $p['team_logo_url'] ) ? esc_url( $p['team_logo_url'] ) : '';
        $team_name  = esc_html( $p['team_name'] ?? '' );
        $pos        = esc_html( $p['position'] ?? '' );

        $html = '<div class="pp-award-player-card">';

        if ( $show_heads ) {
            $html .= '<div class="pp-award-player-headshot">';
            $html .= '<img src="' . $headshot . '" alt="' . esc_attr( $p['player_name'] ) . '" onerror="this.onerror=null;this.src=\'' . $fallback . '\';">';
            $html .= '</div>';
        }

        if ( $logo_url ) {
            $html .= '<img class="pp-award-player-logo" src="' . $logo_url . '" alt="' . esc_attr( $p['team_name'] ?? '' ) . '">';
        } elseif ( $team_name ) {
            $html .= '<span class="pp-award-player-team-text">' . $team_name . '</span>';
        }

        $html .= '<div class="pp-award-player-name">';
        if ( $can_link ) {
            $html .= '<a href="' . esc_url( home_url( '/player/' . $slug . '/' ) ) . '">' . $name . '</a>';
        } else {
            $html .= $name;
        }
        $html .= '</div>';
        if ( $pos ) {
            $html .= '<div class="pp-award-player-pos">' . $pos . '</div>';
        }

        $html .= '</div>';
        return $html;
    }

    private function render_award_icon( array $award ): string {
        if ( ! empty( $award['icon_type'] ) && $award['icon_type'] === 'image' && ! empty( $award['icon_value'] ) ) {
            return '<img src="' . esc_url( $award['icon_value'] ) . '" alt="" style="width:1.5em;height:1.5em;object-fit:contain;vertical-align:middle;">';
        }
        return esc_html( $award['icon_value'] ?? '🏅' );
    }
}
