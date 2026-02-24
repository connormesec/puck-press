<?php

require_once plugin_dir_path(__FILE__) . '../class-puck-press-render-utils-abstract.php';

class Puck_Press_Roster_Render_Utils extends Puck_Press_Render_Utils_Abstract
{
    public function __construct()
    {
        $this->load_dependencies();

        $this->template_manager = new Puck_Press_Roster_Template_Manager();
        $this->wpdb_utils       = new Puck_Press_Roster_Wpdb_Utils();

        $this->games     = $this->wpdb_utils->get_all_table_data('pp_roster_for_display', 'ARRAY_A');
        $this->templates = $this->template_manager->get_all_templates();

        $this->selected_template_key = $this->template_manager->get_current_template_key();
    }

    public function load_dependencies(): void
    {
        require_once plugin_dir_path(__FILE__) . '../../public/templates/class-puck-press-template-manager-abstract.php';
        require_once plugin_dir_path(__FILE__) . '../../public/templates/class-puck-press-roster-template-manager.php';
        require_once plugin_dir_path(__FILE__) . '../class-puck-press-wpdb-utils-base-abstract.php';
        require_once plugin_dir_path(__FILE__) . 'class-puck-press-roster-wpdb-utils.php';
        require_once plugin_dir_path(__FILE__) . 'class-puck-press-roster-player-detail.php';
    }

    /**
     * When ?player= is set, serve a fully server-rendered player detail page
     * (visible to Googlebot). JS will transparently replace it via AJAX on load.
     */
    public function get_current_template_html(): string
    {
        $player_id = sanitize_text_field( $_GET['player'] ?? '' );
        if ( ! empty( $player_id ) ) {
            $ssr = $this->get_player_ssr_html( $player_id );
            if ( $ssr !== null ) {
                return $ssr;
            }
        }
        return $this->build_schema() . $this->get_template_html( $this->selected_template_key );
    }

    private function get_player_ssr_html( string $player_slug ): ?string
    {
        global $wpdb;

        $all_players = $wpdb->get_results(
            "SELECT * FROM {$wpdb->prefix}pp_roster_for_display",
            ARRAY_A
        );
        $player = null;
        foreach ( $all_players as $row ) {
            if ( sanitize_title( $row['name'] ) === $player_slug ) {
                $player = $row;
                break;
            }
        }

        if ( ! $player ) return null;

        $is_goalie   = ( strtoupper( $player['pos'] ?? '' ) === 'G' );
        $stats_table = $is_goalie
            ? "{$wpdb->prefix}pp_roster_goalie_stats"
            : "{$wpdb->prefix}pp_roster_stats";

        $stats = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM {$stats_table} WHERE player_id = %s LIMIT 1",
                $player['player_id']
            ),
            ARRAY_A
        ) ?? [];

        $detail_html    = Puck_Press_Roster_Player_Detail::render( $player, $stats );
        $schema_html    = $this->build_player_schema( $player );
        $container_class = esc_attr( $this->selected_template_key . '_roster_container' );

        $container_html = '<div class="' . $container_class . '"'
            . ' data-ajaxurl="' . esc_attr( admin_url( 'admin-ajax.php' ) ) . '"'
            . ' data-nonce="' . esc_attr( wp_create_nonce( 'pp_player_detail_nonce' ) ) . '"'
            . '>'
            . $detail_html
            . '</div>';

        return $schema_html . $container_html;
    }

    private function build_player_schema( array $player ): string
    {
        $position_labels = [
            'F'  => 'Forward',    'C'  => 'Center',
            'LW' => 'Left Wing',  'RW' => 'Right Wing',
            'D'  => 'Defenseman', 'LD' => 'Left Defense',
            'RD' => 'Right Defense', 'G' => 'Goalie',
        ];

        $pos_code  = strtoupper( $player['pos'] ?? '' );
        $pos_label = $position_labels[ $pos_code ] ?? $pos_code;
        $number    = $player['number'] ?? '';

        $person = [
            '@context' => 'https://schema.org',
            '@type'    => 'Person',
            'name'     => $player['name'] ?? '',
            'memberOf' => [
                '@type' => 'SportsTeam',
                'name'  => get_bloginfo( 'name' ),
                'sport' => 'Ice Hockey',
            ],
        ];

        if ( $pos_label )                          $person['jobTitle']     = $pos_label;
        if ( $number )                             $person['description']  = '#' . $number . ( $pos_label ? ' · ' . $pos_label : '' );
        if ( ! empty( $player['headshot_link'] ) ) $person['image']        = $player['headshot_link'];
        if ( ! empty( $player['hometown'] ) )      $person['homeLocation'] = [ '@type' => 'Place', 'name' => $player['hometown'] ];

        return '<script type="application/ld+json">'
            . wp_json_encode( $person, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT )
            . '</script>';
    }

    protected function build_schema(): string
    {
        if (empty($this->games)) return '';

        $athletes = [];
        foreach ($this->games as $player) {
            $desc_parts = array_filter([
                !empty($player['pos'])      ? $player['pos']          : null,
                !empty($player['number'])   ? '#' . $player['number'] : null,
                !empty($player['hometown']) ? $player['hometown']      : null,
            ]);

            $athlete = ['@type' => 'Person', 'name' => $player['name']];
            if ($desc_parts) {
                $athlete['description'] = implode(', ', $desc_parts);
            }
            $athletes[] = $athlete;
        }

        $schema = [
            '@context' => 'https://schema.org',
            '@type'    => 'SportsTeam',
            'name'     => get_bloginfo('name'),
            'sport'    => 'Ice Hockey',
            'athlete'  => $athletes,
        ];

        return '<script type="application/ld+json">'
            . wp_json_encode($schema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT)
            . '</script>';
    }
}
