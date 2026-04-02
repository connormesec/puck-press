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

        $year_filter_mode = empty( $year ) && ! empty( $parent ) && empty( $award_str );

        if ( $year_filter_mode ) {
            $available_years = ! empty( $parent )
                ? $this->wpdb_utils->get_distinct_years_for_parent( $parent )
                : $this->wpdb_utils->get_distinct_years();

            if ( empty( $available_years ) ) {
                return '<!-- [pp-awards] no matching awards found -->';
            }

            $url_year = isset( $_GET['pp_awards_year'] ) ? sanitize_text_field( $_GET['pp_awards_year'] ) : '';
            $year     = in_array( $url_year, $available_years, true ) ? $url_year : $available_years[0];
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

        $content_html = $this->render_content( $filters, $parent, $show_heads, $columns, $link );

        $html = '<div class="pp-awards-wrap"'
            . ' data-parent="' . esc_attr( $parent ) . '"'
            . ' data-award="' . esc_attr( $award_str ) . '"'
            . ' data-columns="' . esc_attr( $columns ) . '"'
            . ' data-show-headshots="' . esc_attr( $show_heads ? 'true' : 'false' ) . '"'
            . ' data-link-players="' . esc_attr( $link ? 'true' : 'false' ) . '"'
            . '>';

        if ( $year_filter_mode && count( $available_years ) > 1 ) {
            $html .= '<div class="pp-awards-year-filter">';
            $html .= '<label for="pp-awards-year-select">Year:</label>';
            $html .= '<select class="pp-awards-year-select">';
            foreach ( $available_years as $y ) {
                $selected = ( $y === $year ) ? ' selected' : '';
                $html    .= '<option value="' . esc_attr( $y ) . '"' . $selected . '>' . esc_html( $y ) . '</option>';
            }
            $html .= '</select>';
            $html .= '</div>';
        }

        $html .= '<div class="pp-awards-content">' . $content_html . '</div>';
        $html .= '</div>';

        return $html;
    }

    public function render_awards_html( array $atts ): string {
        $parent     = sanitize_text_field( $atts['parent'] ?? '' );
        $year       = sanitize_text_field( $atts['year'] ?? '' );
        $award_str  = sanitize_text_field( $atts['award'] ?? '' );
        $show_heads = ( 'false' !== strtolower( $atts['show_headshots'] ?? 'true' ) );
        $columns    = max( 1, (int) ( $atts['columns'] ?? 6 ) );
        $link       = ( 'false' !== strtolower( $atts['link_players'] ?? 'true' ) );

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

        return $this->render_content( $filters, $parent, $show_heads, $columns, $link );
    }

    private function render_content( array $filters, string $parent, bool $show_heads, int $columns, bool $link ): string {
        $awards = $this->wpdb_utils->get_awards_by_filters( $filters );
        if ( empty( $awards ) ) {
            return '<p style="color:#888;font-style:italic;padding:1rem;">No awards found for this selection.</p>';
        }

        $fallback = PuckPressTemplate::HEADSHOT_FALLBACK;

        if ( ! empty( $parent ) ) {
            return $this->render_grouped( $awards, $fallback, $show_heads, $columns, $link, $parent );
        }
        return $this->render_flat( $awards, $fallback, $show_heads, $columns, $link );
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
