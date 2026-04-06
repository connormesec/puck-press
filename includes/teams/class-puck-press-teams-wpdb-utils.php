<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

require_once plugin_dir_path( __FILE__ ) . '../class-puck-press-wpdb-utils-base-abstract.php';

class Puck_Press_Teams_Wpdb_Utils extends Puck_Press_Wpdb_Utils_Base {

    protected $table_schemas = array(
        'pp_teams'              => "
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            slug VARCHAR(100) NOT NULL,
            name VARCHAR(200) NOT NULL,
            description TEXT DEFAULT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY slug (slug)
        ",
        'pp_team_sources'       => "
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            team_id BIGINT(20) UNSIGNED NOT NULL,
            name VARCHAR(100) NOT NULL,
            type TEXT NOT NULL,
            season VARCHAR(50) DEFAULT NULL,
            source_url_or_path TEXT DEFAULT NULL,
            last_updated DATETIME DEFAULT NULL,
            status ENUM('active', 'inactive') DEFAULT 'active',
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            csv_data LONGTEXT NULL,
            other_data LONGTEXT NULL,
            PRIMARY KEY (id),
            KEY team_id (team_id)
        ",
        'pp_team_games_raw'     => "
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
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
            game_date_day VARCHAR(50) NOT NULL,
            game_time VARCHAR(50) DEFAULT NULL,
            game_timestamp DATETIME NULL DEFAULT NULL,
            home_or_away ENUM('home', 'away') NOT NULL DEFAULT 'home',
            venue VARCHAR(150) DEFAULT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY team_game (team_id, game_id),
            KEY team_id (team_id)
        ",
        'pp_team_game_mods'     => "
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            team_id BIGINT(20) UNSIGNED NOT NULL,
            external_id VARCHAR(50) DEFAULT NULL,
            edit_action VARCHAR(50),
            edit_data LONGTEXT NOT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY team_id (team_id)
        ",
        'pp_team_games_display'        => "
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
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
            UNIQUE KEY team_game (team_id, game_id),
            KEY team_id (team_id)
        ",
        'pp_team_roster_sources'       => "
            id INT AUTO_INCREMENT PRIMARY KEY,
            team_id INT NOT NULL,
            name VARCHAR(255) NOT NULL,
            type VARCHAR(64) NOT NULL,
            source_url_or_path TEXT,
            status VARCHAR(32) NOT NULL DEFAULT 'active',
            csv_data LONGTEXT,
            other_data TEXT,
            season_year VARCHAR(16) DEFAULT NULL,
            last_updated DATETIME,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        ",
        'pp_team_players_raw'          => "
            id INT AUTO_INCREMENT PRIMARY KEY,
            team_id INT NOT NULL,
            api_team_id VARCHAR(100),
            api_team_name VARCHAR(255),
            source VARCHAR(64),
            player_id VARCHAR(128),
            headshot_link TEXT,
            number VARCHAR(16),
            name VARCHAR(255),
            pos VARCHAR(32),
            ht VARCHAR(16),
            wt VARCHAR(16),
            shoots VARCHAR(8),
            hometown VARCHAR(255),
            last_team VARCHAR(255),
            year_in_school VARCHAR(64),
            major VARCHAR(128)
        ",
        'pp_team_player_mods'          => "
            id INT AUTO_INCREMENT PRIMARY KEY,
            team_id INT NOT NULL,
            external_id VARCHAR(128),
            edit_action VARCHAR(32) NOT NULL,
            edit_data TEXT,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ",
        'pp_team_players_display'      => "
            id INT AUTO_INCREMENT PRIMARY KEY,
            team_id INT NOT NULL,
            api_team_id VARCHAR(100),
            api_team_name VARCHAR(255),
            source VARCHAR(64),
            player_id VARCHAR(128),
            headshot_link TEXT,
            number VARCHAR(16),
            name VARCHAR(255),
            pos VARCHAR(32),
            ht VARCHAR(16),
            wt VARCHAR(16),
            shoots VARCHAR(8),
            hometown VARCHAR(255),
            last_team VARCHAR(255),
            year_in_school VARCHAR(64),
            major VARCHAR(128),
            hero_image_url TEXT
        ",
        'pp_team_player_stats'         => "
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            team_id INT NOT NULL,
            player_id VARCHAR(50) NOT NULL,
            source VARCHAR(100) NOT NULL,
            games_played SMALLINT DEFAULT NULL,
            goals SMALLINT DEFAULT NULL,
            assists SMALLINT DEFAULT NULL,
            points SMALLINT DEFAULT NULL,
            points_per_game DECIMAL(5,2) DEFAULT NULL,
            power_play_goals SMALLINT DEFAULT NULL,
            short_handed_goals SMALLINT DEFAULT NULL,
            game_winning_goals SMALLINT DEFAULT NULL,
            shootout_winning_goals SMALLINT DEFAULT NULL,
            penalty_minutes SMALLINT DEFAULT NULL,
            shooting_percentage DECIMAL(5,2) DEFAULT NULL,
            stat_rank SMALLINT DEFAULT NULL,
            PRIMARY KEY (id),
            KEY team_id (team_id)
        ",
        'pp_team_player_goalie_stats'  => "
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            team_id INT NOT NULL,
            player_id VARCHAR(50) NOT NULL,
            source VARCHAR(100) NOT NULL,
            games_played SMALLINT DEFAULT NULL,
            wins SMALLINT DEFAULT NULL,
            losses SMALLINT DEFAULT NULL,
            overtime_losses SMALLINT DEFAULT NULL,
            shootout_losses SMALLINT DEFAULT NULL,
            shootout_wins SMALLINT DEFAULT NULL,
            shots_against SMALLINT DEFAULT NULL,
            saves SMALLINT DEFAULT NULL,
            save_percentage DECIMAL(6,3) DEFAULT NULL,
            goals_against_average DECIMAL(5,2) DEFAULT NULL,
            goals_against SMALLINT DEFAULT NULL,
            goals SMALLINT DEFAULT NULL,
            assists SMALLINT DEFAULT NULL,
            penalty_minutes SMALLINT DEFAULT NULL,
            stat_rank SMALLINT DEFAULT NULL,
            PRIMARY KEY (id),
            KEY team_id (team_id)
        ",
        'pp_team_standings_cache' => "
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            team_id BIGINT(20) UNSIGNED NOT NULL,
            source_id BIGINT(20) UNSIGNED NOT NULL,
            league_type VARCHAR(20) NOT NULL,
            division_name VARCHAR(200) DEFAULT NULL,
            standings_data LONGTEXT NOT NULL,
            computed_at DATETIME NOT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY team_source (team_id, source_id),
            KEY team_id (team_id)
        ",
    );

    public function maybe_create_or_update_tables(): void {
        foreach ( array_keys( $this->table_schemas ) as $table ) {
            $this->maybe_create_or_update_table( $table );
        }
    }

    public function get_all_teams(): array {
        global $wpdb;
        $table = $wpdb->prefix . 'pp_teams';
        return $wpdb->get_results( "SELECT * FROM $table ORDER BY id ASC", ARRAY_A ) ?? array();
    }

    public function get_team_by_id( int $team_id ): ?array {
        global $wpdb;
        $table = $wpdb->prefix . 'pp_teams';
        return $wpdb->get_row(
            $wpdb->prepare( "SELECT * FROM $table WHERE id = %d", $team_id ),
            ARRAY_A
        );
    }

    public function get_team_by_slug( string $slug ): ?array {
        global $wpdb;
        $table = $wpdb->prefix . 'pp_teams';
        return $wpdb->get_row(
            $wpdb->prepare( "SELECT * FROM $table WHERE slug = %s", $slug ),
            ARRAY_A
        );
    }

    public function create_team( string $slug, string $name, string $description = '' ): int {
        global $wpdb;
        $table  = $wpdb->prefix . 'pp_teams';
        $result = $wpdb->insert(
            $table,
            array(
                'slug'        => sanitize_title( $slug ),
                'name'        => sanitize_text_field( $name ),
                'description' => sanitize_textarea_field( $description ),
                'created_at'  => current_time( 'mysql' ),
            )
        );
        return $result ? (int) $wpdb->insert_id : 0;
    }

    public function update_team( int $team_id, array $data ): bool {
        global $wpdb;
        $table      = $wpdb->prefix . 'pp_teams';
        $allowed    = array( 'slug', 'name', 'description' );
        $clean_data = array_intersect_key( $data, array_flip( $allowed ) );
        if ( isset( $clean_data['slug'] ) ) {
            $clean_data['slug'] = sanitize_title( $clean_data['slug'] );
        }
        if ( isset( $clean_data['name'] ) ) {
            $clean_data['name'] = sanitize_text_field( $clean_data['name'] );
        }
        if ( empty( $clean_data ) ) {
            return false;
        }
        return (bool) $wpdb->update( $table, $clean_data, array( 'id' => $team_id ) );
    }

    public function get_domain_tables(): array {
        return array(
            'pp_team_sources',
            'pp_team_games_raw',
            'pp_team_game_mods',
            'pp_team_games_display',
            'pp_team_roster_sources',
            'pp_team_players_raw',
            'pp_team_player_mods',
            'pp_team_players_display',
            'pp_team_player_stats',
            'pp_team_player_goalie_stats',
        );
    }

    public function delete_team( int $team_id ): bool {
        global $wpdb;
        foreach ( $this->get_domain_tables() as $table ) {
            $wpdb->delete( $wpdb->prefix . $table, array( 'team_id' => $team_id ), array( '%d' ) );
        }
        $wpdb->delete( $wpdb->prefix . 'pp_schedule_teams', array( 'team_id' => $team_id ), array( '%d' ) );
        $wpdb->delete( $wpdb->prefix . 'pp_roster_teams', array( 'team_id' => $team_id ), array( '%d' ) );
        return (bool) $wpdb->delete( $wpdb->prefix . 'pp_teams', array( 'id' => $team_id ), array( '%d' ) );
    }

    public function get_team_sources( int $team_id ): array {
        global $wpdb;
        $table = $wpdb->prefix . 'pp_team_sources';
        return $wpdb->get_results(
            $wpdb->prepare( "SELECT * FROM $table WHERE team_id = %d ORDER BY id ASC", $team_id ),
            ARRAY_A
        ) ?? array();
    }

    public function get_active_team_sources( int $team_id ): array {
        global $wpdb;
        $table = $wpdb->prefix . 'pp_team_sources';
        return $wpdb->get_results(
            $wpdb->prepare( "SELECT * FROM $table WHERE team_id = %d AND status = 'active' ORDER BY id ASC", $team_id ),
            ARRAY_A
        ) ?? array();
    }

    public function add_team_source( int $team_id, array $data ): int {
        global $wpdb;
        $table   = $wpdb->prefix . 'pp_team_sources';
        $allowed = array( 'name', 'type', 'season', 'source_url_or_path', 'status', 'csv_data', 'other_data' );
        $row     = array_intersect_key( $data, array_flip( $allowed ) );
        $row['team_id']    = $team_id;
        $row['created_at'] = current_time( 'mysql' );
        $row['status']     = $row['status'] ?? 'active';
        $result = $wpdb->insert( $table, $row );
        return $result ? (int) $wpdb->insert_id : 0;
    }

    public function update_team_source( int $source_id, array $data ): int|false {
        global $wpdb;
        $table   = $wpdb->prefix . 'pp_team_sources';
        $allowed = array( 'name', 'type', 'season', 'source_url_or_path', 'status', 'csv_data', 'other_data' );
        $row     = array_intersect_key( $data, array_flip( $allowed ) );
        if ( empty( $row ) ) {
            return false;
        }
        return $wpdb->update( $table, $row, array( 'id' => $source_id ) );
    }

    public function delete_team_source( int $source_id ): bool {
        global $wpdb;
        return (bool) $wpdb->delete( $wpdb->prefix . 'pp_team_sources', array( 'id' => $source_id ), array( '%d' ) );
    }

    public function get_team_games_display( int $team_id ): array {
        global $wpdb;
        $table = $wpdb->prefix . 'pp_team_games_display';
        return $wpdb->get_results(
            $wpdb->prepare( "SELECT * FROM $table WHERE team_id = %d ORDER BY game_timestamp ASC, id ASC", $team_id ),
            ARRAY_A
        ) ?? array();
    }

    public function get_team_game_mods( int $team_id ): array {
        global $wpdb;
        $table = $wpdb->prefix . 'pp_team_game_mods';
        return $wpdb->get_results(
            $wpdb->prepare( "SELECT * FROM $table WHERE team_id = %d ORDER BY id ASC", $team_id ),
            ARRAY_A
        ) ?? array();
    }

    public function upsert_team_game_mod( int $team_id, ?string $external_id, string $action, array $data ): bool {
        global $wpdb;
        $table = $wpdb->prefix . 'pp_team_game_mods';
        $now   = current_time( 'mysql' );
        $result = $wpdb->insert(
            $table,
            array(
                'team_id'     => $team_id,
                'external_id' => $external_id,
                'edit_action' => $action,
                'edit_data'   => wp_json_encode( $data ),
                'created_at'  => $now,
                'updated_at'  => $now,
            )
        );
        return (bool) $result;
    }

    public function delete_team_game_mod( int $mod_id ): bool {
        global $wpdb;
        return (bool) $wpdb->delete( $wpdb->prefix . 'pp_team_game_mods', array( 'id' => $mod_id ), array( '%d' ) );
    }

    public function delete_raw_for_team( int $team_id ): void {
        global $wpdb;
        $wpdb->delete( $wpdb->prefix . 'pp_team_games_raw', array( 'team_id' => $team_id ), array( '%d' ) );
    }

    public function delete_display_for_team( int $team_id ): void {
        global $wpdb;
        $wpdb->delete( $wpdb->prefix . 'pp_team_games_display', array( 'team_id' => $team_id ), array( '%d' ) );
    }

    public function insert_multiple_team_game_raw_rows( int $team_id, array $game_rows ): array {
        return $this->insert_multiple_rows(
            'pp_team_games_raw',
            $game_rows,
            'game_rows',
            fn( $row, $field ) => ! isset( $row[ $field ] ) || $row[ $field ] === ''
        );
    }

    public function delete_row_by_game_id( string $table_name, string $game_id ): bool {
        global $wpdb;
        return (bool) $wpdb->delete( $wpdb->prefix . $table_name, array( 'game_id' => $game_id ) );
    }
}
