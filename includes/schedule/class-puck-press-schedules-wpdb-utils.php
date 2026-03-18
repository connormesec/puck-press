<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

require_once plugin_dir_path( __FILE__ ) . '../class-puck-press-wpdb-utils-base-abstract.php';

class Puck_Press_Schedules_Wpdb_Utils extends Puck_Press_Wpdb_Utils_Base {

    protected $table_schemas = array(
        'pp_schedules'              => "
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            slug VARCHAR(100) NOT NULL,
            name VARCHAR(200) NOT NULL,
            description TEXT DEFAULT NULL,
            is_main TINYINT(1) NOT NULL DEFAULT 0,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY slug (slug)
        ",
        'pp_schedule_teams'         => "
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            schedule_id BIGINT(20) UNSIGNED NOT NULL,
            team_id BIGINT(20) UNSIGNED NOT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY schedule_team (schedule_id, team_id),
            KEY schedule_id (schedule_id),
            KEY team_id (team_id)
        ",
        'pp_schedule_games_display' => "
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            schedule_id BIGINT(20) UNSIGNED NOT NULL,
            team_id BIGINT(20) UNSIGNED NOT NULL,
            source VARCHAR(100) NOT NULL,
            source_type VARCHAR(100) NOT NULL,
            game_id VARCHAR(50) NOT NULL,
            target_team_id VARCHAR(50) NOT NULL,
            target_team_name VARCHAR(100) NOT NULL,
            target_team_nickname VARCHAR(50) DEFAULT NULL,
            target_team_logo TEXT DEFAULT NULL,
            opponent_team_id VARCHAR(50) NOT NULL,
            opponent_team_name VARCHAR(100) NOT NULL,
            opponent_team_nickname VARCHAR(50) DEFAULT NULL,
            opponent_team_logo TEXT DEFAULT NULL,
            target_score TINYINT DEFAULT NULL,
            opponent_score TINYINT DEFAULT NULL,
            game_status VARCHAR(50) DEFAULT NULL,
            promo_header VARCHAR(100) DEFAULT NULL,
            promo_text TEXT DEFAULT NULL,
            promo_img_url TEXT DEFAULT NULL,
            promo_ticket_link TEXT DEFAULT NULL,
            post_link TEXT DEFAULT NULL,
            game_date_day VARCHAR(50) NOT NULL,
            game_time VARCHAR(50) DEFAULT NULL,
            game_timestamp DATETIME NULL DEFAULT NULL,
            home_or_away ENUM('home', 'away') NOT NULL DEFAULT 'home',
            venue VARCHAR(150) DEFAULT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY schedule_id (schedule_id),
            KEY team_id (team_id)
        ",
    );

    public function maybe_create_or_update_tables(): void {
        foreach ( array_keys( $this->table_schemas ) as $table ) {
            $this->maybe_create_or_update_table( $table );
        }
    }

    public function get_all_schedules(): array {
        global $wpdb;
        $table = $wpdb->prefix . 'pp_schedules';
        return $wpdb->get_results( "SELECT * FROM $table ORDER BY is_main DESC, id ASC", ARRAY_A ) ?? array();
    }

    public function get_schedule_by_id( int $schedule_id ): ?array {
        global $wpdb;
        $table = $wpdb->prefix . 'pp_schedules';
        return $wpdb->get_row(
            $wpdb->prepare( "SELECT * FROM $table WHERE id = %d", $schedule_id ),
            ARRAY_A
        );
    }

    public function get_schedule_by_slug( string $slug ): ?array {
        global $wpdb;
        $table = $wpdb->prefix . 'pp_schedules';
        return $wpdb->get_row(
            $wpdb->prepare( "SELECT * FROM $table WHERE slug = %s", $slug ),
            ARRAY_A
        );
    }

    public function get_main_schedule(): ?array {
        global $wpdb;
        $table = $wpdb->prefix . 'pp_schedules';
        return $wpdb->get_row( "SELECT * FROM $table WHERE is_main = 1 LIMIT 1", ARRAY_A );
    }

    public function get_main_schedule_id(): int {
        $main = $this->get_main_schedule();
        return $main ? (int) $main['id'] : 0;
    }

    public function create_schedule( string $slug, string $name, bool $is_main = false ): int {
        global $wpdb;
        $table  = $wpdb->prefix . 'pp_schedules';
        $result = $wpdb->insert(
            $table,
            array(
                'slug'       => sanitize_title( $slug ),
                'name'       => sanitize_text_field( $name ),
                'is_main'    => $is_main ? 1 : 0,
                'created_at' => current_time( 'mysql' ),
            )
        );
        return $result ? (int) $wpdb->insert_id : 0;
    }

    public function update_schedule( int $schedule_id, array $data ): bool {
        global $wpdb;
        $table   = $wpdb->prefix . 'pp_schedules';
        $allowed = array( 'slug', 'name', 'description' );
        $row     = array_intersect_key( $data, array_flip( $allowed ) );
        if ( isset( $row['slug'] ) ) {
            $row['slug'] = sanitize_title( $row['slug'] );
        }
        if ( isset( $row['name'] ) ) {
            $row['name'] = sanitize_text_field( $row['name'] );
        }
        if ( empty( $row ) ) {
            return false;
        }
        return (bool) $wpdb->update( $table, $row, array( 'id' => $schedule_id ) );
    }

    public function delete_schedule( int $schedule_id ): bool {
        global $wpdb;
        $schedule = $this->get_schedule_by_id( $schedule_id );
        if ( ! $schedule || (int) $schedule['is_main'] === 1 ) {
            return false;
        }
        $wpdb->delete( $wpdb->prefix . 'pp_schedule_teams', array( 'schedule_id' => $schedule_id ), array( '%d' ) );
        $wpdb->delete( $wpdb->prefix . 'pp_schedule_games_display', array( 'schedule_id' => $schedule_id ), array( '%d' ) );
        return (bool) $wpdb->delete( $wpdb->prefix . 'pp_schedules', array( 'id' => $schedule_id ), array( '%d' ) );
    }

    public function get_schedule_team_ids( int $schedule_id ): array {
        global $wpdb;
        $schedule = $this->get_schedule_by_id( $schedule_id );
        if ( ! $schedule ) {
            return array();
        }
        if ( (int) $schedule['is_main'] === 1 ) {
            $results = $wpdb->get_col( "SELECT id FROM {$wpdb->prefix}pp_teams ORDER BY id ASC" );
            return array_map( 'intval', $results ?? array() );
        }
        $table   = $wpdb->prefix . 'pp_schedule_teams';
        $results = $wpdb->get_col(
            $wpdb->prepare( "SELECT team_id FROM $table WHERE schedule_id = %d ORDER BY id ASC", $schedule_id )
        );
        return array_map( 'intval', $results ?? array() );
    }

    public function get_schedule_teams( int $schedule_id ): array {
        $team_ids = $this->get_schedule_team_ids( $schedule_id );
        if ( empty( $team_ids ) ) {
            return array();
        }
        global $wpdb;
        $placeholders = implode( ', ', array_fill( 0, count( $team_ids ), '%d' ) );
        return $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM {$wpdb->prefix}pp_teams WHERE id IN ($placeholders) ORDER BY id ASC",
                ...$team_ids
            ),
            ARRAY_A
        ) ?? array();
    }

    public function add_team_to_schedule( int $schedule_id, int $team_id ): bool {
        $schedule = $this->get_schedule_by_id( $schedule_id );
        if ( ! $schedule || (int) $schedule['is_main'] === 1 ) {
            return false;
        }
        global $wpdb;
        $table  = $wpdb->prefix . 'pp_schedule_teams';
        $result = $wpdb->insert(
            $table,
            array(
                'schedule_id' => $schedule_id,
                'team_id'     => $team_id,
                'created_at'  => current_time( 'mysql' ),
            )
        );
        return (bool) $result;
    }

    public function remove_team_from_schedule( int $schedule_id, int $team_id ): bool {
        $schedule = $this->get_schedule_by_id( $schedule_id );
        if ( ! $schedule || (int) $schedule['is_main'] === 1 ) {
            return false;
        }
        global $wpdb;
        return (bool) $wpdb->delete(
            $wpdb->prefix . 'pp_schedule_teams',
            array(
                'schedule_id' => $schedule_id,
                'team_id'     => $team_id,
            ),
            array( '%d', '%d' )
        );
    }

    public function get_schedule_ids_for_team( int $team_id ): array {
        global $wpdb;
        $non_main = $wpdb->get_col(
            $wpdb->prepare(
                "SELECT schedule_id FROM {$wpdb->prefix}pp_schedule_teams WHERE team_id = %d",
                $team_id
            )
        );
        $main_id = $this->get_main_schedule_id();
        $ids     = array_map( 'intval', $non_main ?? array() );
        if ( $main_id > 0 && ! in_array( $main_id, $ids, true ) ) {
            $ids[] = $main_id;
        }
        return array_unique( $ids );
    }

    public function get_schedule_games_display( int $schedule_id ): array {
        global $wpdb;
        $table = $wpdb->prefix . 'pp_schedule_games_display';
        return $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM $table WHERE schedule_id = %d ORDER BY game_timestamp ASC, id ASC",
                $schedule_id
            ),
            ARRAY_A
        ) ?? array();
    }

    public function clear_schedule_games_display( int $schedule_id ): void {
        global $wpdb;
        $wpdb->delete( $wpdb->prefix . 'pp_schedule_games_display', array( 'schedule_id' => $schedule_id ), array( '%d' ) );
    }

    public function seed_main_schedule( string $slug = 'default', string $name = 'Main Schedule' ): int {
        global $wpdb;
        $table    = $wpdb->prefix . 'pp_schedules';
        $existing = $wpdb->get_var( "SELECT id FROM $table WHERE is_main = 1 LIMIT 1" );
        if ( $existing ) {
            return (int) $existing;
        }
        return $this->create_schedule( $slug, $name, true );
    }
}
