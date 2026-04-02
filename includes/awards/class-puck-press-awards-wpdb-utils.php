<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

require_once plugin_dir_path( __DIR__ ) . 'class-puck-press-wpdb-utils-base-abstract.php';

class Puck_Press_Awards_Wpdb_Utils extends Puck_Press_Wpdb_Utils_Base {

    protected $table_schemas = array(
        'pp_awards'        => "
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            name VARCHAR(255) NOT NULL,
            slug VARCHAR(255) NOT NULL,
            shortcode_label VARCHAR(8) NOT NULL,
            year VARCHAR(16) NOT NULL,
            parent_name VARCHAR(255) DEFAULT NULL,
            icon_type VARCHAR(32) NOT NULL DEFAULT 'emoji',
            icon_value TEXT NOT NULL,
            sort_order INT DEFAULT 0,
            show_in_shortcode TINYINT(1) NOT NULL DEFAULT 1,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY slug (slug),
            KEY year (year),
            KEY parent_name (parent_name(100))
        ",
        'pp_award_players' => "
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            award_id BIGINT(20) UNSIGNED NOT NULL,
            player_id VARCHAR(128) DEFAULT NULL,
            team_id INT DEFAULT NULL,
            player_name VARCHAR(255) NOT NULL,
            player_slug VARCHAR(255) DEFAULT NULL,
            team_name VARCHAR(255) DEFAULT NULL,
            position VARCHAR(32) DEFAULT NULL,
            headshot_url TEXT DEFAULT NULL,
            team_logo_url TEXT DEFAULT NULL,
            is_external TINYINT(1) NOT NULL DEFAULT 0,
            sort_order INT DEFAULT 0,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY award_id (award_id),
            KEY player_team (player_id, team_id)
        ",
    );

    public function maybe_create_or_update_tables(): void {
        global $wpdb;
        foreach ( array_keys( $this->table_schemas ) as $table ) {
            $this->maybe_create_or_update_table( $table );
        }
        $wpdb->query( "UPDATE {$wpdb->prefix}pp_awards SET show_in_shortcode = 1 WHERE show_in_shortcode IS NULL" );
    }

    // ── Award CRUD ────────────────────────────────────────────────────────────

    public function create_award( array $data ) {
        global $wpdb;
        $table = $wpdb->prefix . 'pp_awards';

        $name            = sanitize_text_field( $data['name'] ?? '' );
        $year            = sanitize_text_field( $data['year'] ?? '' );
        $shortcode_label   = sanitize_text_field( $data['shortcode_label'] ?? '' );
        $parent_name       = isset( $data['parent_name'] ) ? trim( sanitize_text_field( $data['parent_name'] ) ) : null;
        $icon_type         = sanitize_key( $data['icon_type'] ?? 'emoji' );
        $icon_value        = ( $icon_type === 'image' ) ? esc_url_raw( $data['icon_value'] ?? '' ) : sanitize_text_field( $data['icon_value'] ?? '🏅' );
        $sort_order        = (int) ( $data['sort_order'] ?? 0 );
        $show_in_shortcode = isset( $data['show_in_shortcode'] ) ? (int) $data['show_in_shortcode'] : 1;

        if ( empty( $name ) || empty( $year ) ) {
            return new WP_Error( 'missing_fields', 'Name and year are required.' );
        }

        if ( empty( $shortcode_label ) ) {
            $shortcode_label = mb_strtoupper( mb_substr( $name, 0, 4 ) );
        }

        if ( empty( $icon_value ) ) {
            $icon_value = '🏅';
        }

        $slug   = sanitize_title( $year . '-' . $name );
        $exists = $wpdb->get_var( $wpdb->prepare( "SELECT id FROM $table WHERE slug = %s", $slug ) );
        if ( $exists ) {
            return new WP_Error( 'duplicate_slug', 'An award with this year + name combination already exists.' );
        }

        $result = $wpdb->insert(
            $table,
            array(
                'name'            => $name,
                'slug'            => $slug,
                'shortcode_label' => $shortcode_label,
                'year'            => $year,
                'parent_name'     => $parent_name ?: null,
                'icon_type'       => $icon_type,
                'icon_value'      => $icon_value,
                'sort_order'        => $sort_order,
                'show_in_shortcode' => $show_in_shortcode,
                'created_at'        => current_time( 'mysql' ),
            )
        );

        if ( false === $result ) {
            return new WP_Error( 'db_error', 'Failed to create award.', $wpdb->last_error );
        }

        return (int) $wpdb->insert_id;
    }

    public function update_award( int $id, array $data ) {
        global $wpdb;
        $table = $wpdb->prefix . 'pp_awards';

        $award = $this->get_award_by_id( $id );
        if ( ! $award ) {
            return new WP_Error( 'not_found', 'Award not found.' );
        }

        $update = array();

        if ( isset( $data['name'] ) ) {
            $update['name'] = sanitize_text_field( $data['name'] );
        }
        if ( isset( $data['year'] ) ) {
            $update['year'] = sanitize_text_field( $data['year'] );
        }
        if ( array_key_exists( 'parent_name', $data ) ) {
            $pn                   = $data['parent_name'] ? trim( sanitize_text_field( $data['parent_name'] ) ) : null;
            $update['parent_name'] = $pn ?: null;
        }
        if ( isset( $data['icon_type'] ) ) {
            $update['icon_type'] = sanitize_key( $data['icon_type'] );
        }
        if ( isset( $data['icon_value'] ) ) {
            $it                   = $update['icon_type'] ?? $award['icon_type'];
            $update['icon_value'] = ( $it === 'image' ) ? esc_url_raw( $data['icon_value'] ) : sanitize_text_field( $data['icon_value'] );
        }
        if ( isset( $data['sort_order'] ) ) {
            $update['sort_order'] = (int) $data['sort_order'];
        }
        if ( isset( $data['show_in_shortcode'] ) ) {
            $update['show_in_shortcode'] = (int) $data['show_in_shortcode'];
        }

        if ( empty( $update ) ) {
            return true;
        }

        $result = $wpdb->update( $table, $update, array( 'id' => $id ), null, array( '%d' ) );
        return false !== $result ? true : new WP_Error( 'db_error', 'Failed to update award.', $wpdb->last_error );
    }

    public function delete_award( int $id ): bool {
        global $wpdb;

        $award = $this->get_award_by_id( $id );
        if ( ! $award ) {
            return false;
        }

        $wpdb->delete( $wpdb->prefix . 'pp_award_players', array( 'award_id' => $id ), array( '%d' ) );
        return (bool) $wpdb->delete( $wpdb->prefix . 'pp_awards', array( 'id' => $id ), array( '%d' ) );
    }

    public function get_award_by_id( int $id ): ?array {
        global $wpdb;
        $table = $wpdb->prefix . 'pp_awards';
        return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $table WHERE id = %d", $id ), ARRAY_A );
    }

    public function get_award_by_slug( string $slug ): ?array {
        global $wpdb;
        $table = $wpdb->prefix . 'pp_awards';
        return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $table WHERE slug = %s", $slug ), ARRAY_A );
    }

    public function get_all_awards( ?string $year = null ): array {
        global $wpdb;
        $table = $wpdb->prefix . 'pp_awards';

        if ( $year ) {
            return $wpdb->get_results(
                $wpdb->prepare( "SELECT * FROM $table WHERE year = %s ORDER BY sort_order ASC, created_at ASC", $year ),
                ARRAY_A
            ) ?? array();
        }

        return $wpdb->get_results( "SELECT * FROM $table ORDER BY year DESC, sort_order ASC, created_at ASC", ARRAY_A ) ?? array();
    }

    public function get_distinct_parent_names(): array {
        global $wpdb;
        $table = $wpdb->prefix . 'pp_awards';
        return $wpdb->get_col(
            "SELECT DISTINCT parent_name FROM $table WHERE parent_name IS NOT NULL AND parent_name != '' ORDER BY parent_name ASC"
        ) ?? array();
    }

    public function get_distinct_years(): array {
        global $wpdb;
        $table = $wpdb->prefix . 'pp_awards';
        return $wpdb->get_col( "SELECT DISTINCT year FROM $table ORDER BY year DESC" ) ?? array();
    }

    public function get_distinct_years_for_parent( string $parent ): array {
        global $wpdb;
        $table = $wpdb->prefix . 'pp_awards';
        return $wpdb->get_col(
            $wpdb->prepare(
                "SELECT DISTINCT year FROM $table WHERE LOWER(parent_name) = LOWER(%s) AND (show_in_shortcode = 1 OR show_in_shortcode IS NULL) ORDER BY year DESC",
                $parent
            )
        ) ?? array();
    }

    // ── Player CRUD ──────────────────────────────────────────────────────────

    public function add_player( array $data ) {
        global $wpdb;
        $table = $wpdb->prefix . 'pp_award_players';

        $award_id    = (int) ( $data['award_id'] ?? 0 );
        $is_external = (int) ( $data['is_external'] ?? 0 );
        $player_name = sanitize_text_field( $data['player_name'] ?? '' );
        $team_name   = sanitize_text_field( $data['team_name'] ?? '' );
        $position    = sanitize_text_field( $data['position'] ?? '' );
        $sort_order  = (int) ( $data['sort_order'] ?? 0 );

        if ( ! $award_id || empty( $player_name ) ) {
            return new WP_Error( 'missing_fields', 'Award ID and player name are required.' );
        }

        $player_id     = $is_external ? null : sanitize_text_field( $data['player_id'] ?? '' );
        $team_id       = $is_external ? null : ( (int) ( $data['team_id'] ?? 0 ) ?: null );
        $player_slug   = $is_external ? null : sanitize_title( $player_name );
        $headshot_url  = ! empty( $data['headshot_url'] ) ? esc_url_raw( $data['headshot_url'] ) : null;
        $team_logo_url = ! empty( $data['team_logo_url'] ) ? esc_url_raw( $data['team_logo_url'] ) : null;

        if ( ! $is_external && $player_id && $team_id ) {
            $dup = $wpdb->get_var(
                $wpdb->prepare(
                    "SELECT id FROM $table WHERE award_id = %d AND player_id = %s AND team_id = %d",
                    $award_id,
                    $player_id,
                    $team_id
                )
            );
            if ( $dup ) {
                return new WP_Error( 'duplicate', 'This player is already on this award.' );
            }
        } else {
            $dup = $wpdb->get_var(
                $wpdb->prepare(
                    "SELECT id FROM $table WHERE award_id = %d AND player_name = %s AND team_name = %s AND is_external = 1",
                    $award_id,
                    $player_name,
                    $team_name
                )
            );
            if ( $dup ) {
                return new WP_Error( 'duplicate', 'This player is already on this award.' );
            }
        }

        $result = $wpdb->insert(
            $table,
            array(
                'award_id'      => $award_id,
                'player_id'     => $player_id ?: null,
                'team_id'       => $team_id,
                'player_name'   => $player_name,
                'player_slug'   => $player_slug,
                'team_name'     => $team_name ?: null,
                'position'      => $position ?: null,
                'headshot_url'  => $headshot_url,
                'team_logo_url' => $team_logo_url,
                'is_external'   => $is_external,
                'sort_order'    => $sort_order,
                'created_at'    => current_time( 'mysql' ),
            )
        );

        if ( false === $result ) {
            return new WP_Error( 'db_error', 'Failed to add player to award.', $wpdb->last_error );
        }

        return (int) $wpdb->insert_id;
    }

    public function remove_player( int $id ): bool {
        global $wpdb;
        return (bool) $wpdb->delete( $wpdb->prefix . 'pp_award_players', array( 'id' => $id ), array( '%d' ) );
    }

    public function get_players_for_award( int $award_id ): array {
        global $wpdb;
        $table = $wpdb->prefix . 'pp_award_players';
        return $wpdb->get_results(
            $wpdb->prepare( "SELECT * FROM $table WHERE award_id = %d ORDER BY sort_order ASC, id ASC", $award_id ),
            ARRAY_A
        ) ?? array();
    }

    public function reorder_players( array $id_order_pairs ): void {
        global $wpdb;
        $table = $wpdb->prefix . 'pp_award_players';

        foreach ( $id_order_pairs as $id => $order ) {
            $wpdb->update( $table, array( 'sort_order' => (int) $order ), array( 'id' => (int) $id ), array( '%d' ), array( '%d' ) );
        }
    }

    public function get_awards_for_player( string $player_id, int $team_id ): array {
        global $wpdb;
        $ap = $wpdb->prefix . 'pp_award_players';
        $a  = $wpdb->prefix . 'pp_awards';

        return $wpdb->get_results(
            $wpdb->prepare(
                "SELECT ap.*, a.name AS award_name, a.shortcode_label, a.year, a.icon_type, a.icon_value
                FROM $ap ap
                JOIN $a a ON a.id = ap.award_id
                WHERE ap.player_id = %s AND ap.team_id = %d AND ap.is_external = 0
                ORDER BY a.year DESC, a.sort_order ASC",
                $player_id,
                $team_id
            ),
            ARRAY_A
        ) ?? array();
    }

    public function get_awards_by_filters( array $filters ): array {
        global $wpdb;
        $a  = $wpdb->prefix . 'pp_awards';
        $ap = $wpdb->prefix . 'pp_award_players';

        $where  = array();
        $values = array();

        if ( ! empty( $filters['slugs'] ) ) {
            $placeholders = implode( ', ', array_fill( 0, count( $filters['slugs'] ), '%s' ) );
            $where[]      = "a.slug IN ($placeholders)";
            $values       = array_merge( $values, $filters['slugs'] );
        }
        if ( ! empty( $filters['year'] ) ) {
            $where[]  = 'a.year = %s';
            $values[] = $filters['year'];
        }
        if ( ! empty( $filters['parent'] ) ) {
            $where[]  = 'LOWER(a.parent_name) = LOWER(%s)';
            $values[] = $filters['parent'];
        }

        if ( ! isset( $filters['include_hidden'] ) || ! $filters['include_hidden'] ) {
            $where[] = '(a.show_in_shortcode = 1 OR a.show_in_shortcode IS NULL)';
        }

        if ( empty( $where ) ) {
            return array();
        }

        $where_sql = 'WHERE ' . implode( ' AND ', $where );

        $sql = "SELECT a.*, ap.id AS ap_id, ap.player_id, ap.team_id, ap.player_name, ap.player_slug,
                       ap.team_name, ap.position, ap.headshot_url, ap.team_logo_url, ap.is_external, ap.sort_order AS player_sort_order
                FROM $a a
                LEFT JOIN $ap ap ON ap.award_id = a.id
                $where_sql
                ORDER BY a.sort_order ASC, a.id ASC, ap.sort_order ASC, ap.id ASC";

        if ( ! empty( $values ) ) {
            $sql = $wpdb->prepare( $sql, $values );
        }

        $rows = $wpdb->get_results( $sql, ARRAY_A ) ?? array();

        $awards = array();
        foreach ( $rows as $row ) {
            $aid = (int) $row['id'];
            if ( ! isset( $awards[ $aid ] ) ) {
                $awards[ $aid ] = array(
                    'id'              => $aid,
                    'name'            => $row['name'],
                    'slug'            => $row['slug'],
                    'shortcode_label' => $row['shortcode_label'],
                    'year'            => $row['year'],
                    'parent_name'     => $row['parent_name'],
                    'icon_type'       => $row['icon_type'],
                    'icon_value'      => $row['icon_value'],
                    'sort_order'      => (int) $row['sort_order'],
                    'players'         => array(),
                );
            }
            if ( ! empty( $row['ap_id'] ) ) {
                $awards[ $aid ]['players'][] = array(
                    'id'            => (int) $row['ap_id'],
                    'player_id'     => $row['player_id'],
                    'team_id'       => $row['team_id'] ? (int) $row['team_id'] : null,
                    'player_name'   => $row['player_name'],
                    'player_slug'   => $row['player_slug'],
                    'team_name'     => $row['team_name'],
                    'position'      => $row['position'],
                    'headshot_url'  => $row['headshot_url'],
                    'team_logo_url' => $row['team_logo_url'],
                    'is_external'   => (int) $row['is_external'],
                    'sort_order'    => (int) $row['player_sort_order'],
                );
            }
        }

        return array_values( $awards );
    }

    // ── Logo resolution ──────────────────────────────────────────────────────

    public function resolve_team_logo( int $team_id ): ?string {
        global $wpdb;
        $table = $wpdb->prefix . 'pp_team_games_display';
        return $wpdb->get_var(
            $wpdb->prepare(
                "SELECT target_team_logo FROM $table WHERE team_id = %d AND target_team_logo IS NOT NULL AND target_team_logo != '' LIMIT 1",
                $team_id
            )
        );
    }

    // ── Player search for autocomplete ───────────────────────────────────────

    public function search_players( string $query, int $limit = 20 ): array {
        global $wpdb;
        $d = $wpdb->prefix . 'pp_team_players_display';
        $t = $wpdb->prefix . 'pp_teams';

        return $wpdb->get_results(
            $wpdb->prepare(
                "SELECT d.player_id, d.team_id, d.name, d.pos, d.headshot_link, t.name AS team_name
                FROM $d d
                JOIN $t t ON t.id = d.team_id
                WHERE d.name LIKE %s
                ORDER BY d.name ASC
                LIMIT %d",
                '%' . $wpdb->esc_like( $query ) . '%',
                $limit
            ),
            ARRAY_A
        ) ?? array();
    }

    public function get_next_player_sort_order( int $award_id ): int {
        global $wpdb;
        $table = $wpdb->prefix . 'pp_award_players';
        $max   = $wpdb->get_var(
            $wpdb->prepare( "SELECT MAX(sort_order) FROM $table WHERE award_id = %d", $award_id )
        );
        return $max !== null ? ( (int) $max + 1 ) : 0;
    }

    public function get_players_for_team( int $team_id ): array {
        global $wpdb;
        $d = $wpdb->prefix . 'pp_team_players_display';
        $t = $wpdb->prefix . 'pp_teams';

        return $wpdb->get_results(
            $wpdb->prepare(
                "SELECT d.player_id, d.team_id, d.name, d.pos, d.headshot_link, t.name AS team_name
                FROM $d d
                JOIN $t t ON t.id = d.team_id
                WHERE d.team_id = %d
                ORDER BY d.name ASC",
                $team_id
            ),
            ARRAY_A
        ) ?? array();
    }

    public function bulk_add_team_players( int $award_id, int $team_id ): array {
        $players   = $this->get_players_for_team( $team_id );
        $logo_url  = $this->resolve_team_logo( $team_id );
        $sort      = $this->get_next_player_sort_order( $award_id );
        $added     = 0;
        $skipped   = 0;

        foreach ( $players as $p ) {
            $result = $this->add_player(
                array(
                    'award_id'      => $award_id,
                    'player_id'     => $p['player_id'],
                    'team_id'       => $p['team_id'],
                    'player_name'   => $p['name'],
                    'team_name'     => $p['team_name'],
                    'position'      => $p['pos'],
                    'headshot_url'  => $p['headshot_link'] ?? '',
                    'team_logo_url' => $logo_url ?? '',
                    'is_external'   => 0,
                    'sort_order'    => $sort,
                )
            );

            if ( is_wp_error( $result ) ) {
                ++$skipped;
            } else {
                ++$added;
                ++$sort;
            }
        }

        return array(
            'added'   => $added,
            'skipped' => $skipped,
            'total'   => count( $players ),
        );
    }
}
