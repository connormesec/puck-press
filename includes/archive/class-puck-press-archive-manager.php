<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

require_once plugin_dir_path( __FILE__ ) . '../class-puck-press-wpdb-utils-base-abstract.php';

class Puck_Press_Archive_Manager extends Puck_Press_Wpdb_Utils_Base {

    protected $table_schemas = array(
        'pp_archive_seasons'               => "
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            season_key VARCHAR(50) NOT NULL,
            label VARCHAR(100) DEFAULT NULL,
            archived_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY season_key (season_key)
        ",
        'pp_team_games_archive'            => "
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            season_key VARCHAR(50) NOT NULL,
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
            KEY season_team (season_key, team_id)
        ",
        'pp_team_sources_archive'          => "
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            season_key VARCHAR(50) NOT NULL,
            team_id BIGINT(20) UNSIGNED NOT NULL,
            name VARCHAR(100) NOT NULL,
            type TEXT NOT NULL,
            season VARCHAR(50) DEFAULT NULL,
            source_url_or_path TEXT DEFAULT NULL,
            PRIMARY KEY (id),
            KEY season_team (season_key, team_id)
        ",
        'pp_team_player_stats_archive'     => "
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            season_key VARCHAR(50) NOT NULL,
            team_id BIGINT(20) UNSIGNED NOT NULL,
            player_id VARCHAR(50) NOT NULL,
            name VARCHAR(100) DEFAULT NULL,
            pos VARCHAR(10) DEFAULT NULL,
            headshot_link TEXT DEFAULT NULL,
            team_name VARCHAR(200) DEFAULT NULL,
            api_team_id VARCHAR(100) DEFAULT NULL,
            api_team_name VARCHAR(255) DEFAULT NULL,
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
            KEY season_team (season_key, team_id),
            KEY player_id (player_id)
        ",
        'pp_team_player_goalie_stats_archive' => "
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            season_key VARCHAR(50) NOT NULL,
            team_id BIGINT(20) UNSIGNED NOT NULL,
            player_id VARCHAR(50) NOT NULL,
            name VARCHAR(100) DEFAULT NULL,
            pos VARCHAR(10) DEFAULT NULL,
            headshot_link TEXT DEFAULT NULL,
            team_name VARCHAR(200) DEFAULT NULL,
            api_team_id VARCHAR(100) DEFAULT NULL,
            api_team_name VARCHAR(255) DEFAULT NULL,
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
            KEY season_team (season_key, team_id),
            KEY player_id (player_id)
        ",
    );

    public function maybe_create_or_update_tables(): void {
        foreach ( array_keys( $this->table_schemas ) as $table ) {
            $this->maybe_create_or_update_table( $table );
        }
    }

    public function archive_team_season( int $team_id, string $season_key, string $label = '' ): array {
        global $wpdb;

        $this->maybe_create_or_update_tables();

        $existing_games = (int) $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM {$wpdb->prefix}pp_team_games_archive WHERE season_key = %s AND team_id = %d",
                $season_key,
                $team_id
            )
        );
        $existing_stats = (int) $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM {$wpdb->prefix}pp_team_player_stats_archive WHERE season_key = %s AND team_id = %d",
                $season_key,
                $team_id
            )
        );
        $existing_goalie_stats = (int) $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM {$wpdb->prefix}pp_team_player_goalie_stats_archive WHERE season_key = %s AND team_id = %d",
                $season_key,
                $team_id
            )
        );

        if ( $existing_games > 0 || $existing_stats > 0 || $existing_goalie_stats > 0 ) {
            return array(
                'success' => false,
                'message' => sprintf(
                    'Season "%s" has already been archived for this team. Choose a different season key or delete the existing archive.',
                    esc_html( $season_key )
                ),
            );
        }

        error_log( "[PP Archive] archive_team_season: team_id=$team_id season_key=$season_key label=$label" );

        $wpdb->query(
            $wpdb->prepare(
                "INSERT IGNORE INTO {$wpdb->prefix}pp_archive_seasons (season_key, label, archived_at) VALUES (%s, %s, %s)",
                $season_key,
                $label !== '' ? $label : $season_key,
                current_time( 'mysql' )
            )
        );
        if ( $wpdb->last_error ) {
            error_log( "[PP Archive] pp_archive_seasons insert error: {$wpdb->last_error}" );
        }

        $wpdb->query(
            $wpdb->prepare(
                "INSERT INTO {$wpdb->prefix}pp_team_games_archive
                    (season_key, team_id, source, source_type, game_id,
                     target_team_id, target_team_name, target_team_nickname, target_team_logo,
                     opponent_team_id, opponent_team_name, opponent_team_nickname, opponent_team_logo,
                     target_score, opponent_score, game_status,
                     promo_header, promo_text, promo_img_url, promo_ticket_link, post_link,
                     game_date_day, game_time, game_timestamp, home_or_away, venue)
                SELECT %s, team_id, source, source_type, game_id,
                       target_team_id, target_team_name, target_team_nickname, target_team_logo,
                       opponent_team_id, opponent_team_name, opponent_team_nickname, opponent_team_logo,
                       target_score, opponent_score, game_status,
                       promo_header, promo_text, promo_img_url, promo_ticket_link, post_link,
                       game_date_day, game_time, game_timestamp, home_or_away, venue
                FROM {$wpdb->prefix}pp_team_games_display
                WHERE team_id = %d",
                $season_key,
                $team_id
            )
        );
        error_log( "[PP Archive] pp_team_games_archive rows_affected={$wpdb->rows_affected}" . ( $wpdb->last_error ? " error={$wpdb->last_error}" : '' ) );

        $wpdb->query(
            $wpdb->prepare(
                "INSERT INTO {$wpdb->prefix}pp_team_sources_archive
                    (season_key, team_id, name, type, season, source_url_or_path)
                SELECT %s, team_id, name, type, season, source_url_or_path
                FROM {$wpdb->prefix}pp_team_sources
                WHERE team_id = %d AND status = 'active'",
                $season_key,
                $team_id
            )
        );
        error_log( "[PP Archive] pp_team_sources_archive rows_affected={$wpdb->rows_affected}" . ( $wpdb->last_error ? " error={$wpdb->last_error}" : '' ) );

        $wpdb->query(
            $wpdb->prepare(
                "INSERT INTO {$wpdb->prefix}pp_team_player_stats_archive
                    (season_key, team_id, player_id, name, pos, headshot_link, team_name,
                     api_team_id, api_team_name,
                     source, games_played, goals, assists, points, points_per_game,
                     power_play_goals, short_handed_goals, game_winning_goals,
                     shootout_winning_goals, penalty_minutes, shooting_percentage, stat_rank)
                SELECT %s, s.team_id, s.player_id, d.name, d.pos, d.headshot_link, t.name AS team_name,
                       d.api_team_id, d.api_team_name,
                       s.source, s.games_played, s.goals, s.assists, s.points, s.points_per_game,
                       s.power_play_goals, s.short_handed_goals, s.game_winning_goals,
                       s.shootout_winning_goals, s.penalty_minutes, s.shooting_percentage, s.stat_rank
                FROM {$wpdb->prefix}pp_team_player_stats s
                INNER JOIN {$wpdb->prefix}pp_team_players_display d
                    ON d.player_id = s.player_id AND d.team_id = s.team_id
                INNER JOIN {$wpdb->prefix}pp_teams t
                    ON t.id = s.team_id
                WHERE s.team_id = %d",
                $season_key,
                $team_id
            )
        );
        error_log( "[PP Archive] pp_team_player_stats_archive rows_affected={$wpdb->rows_affected}" . ( $wpdb->last_error ? " error={$wpdb->last_error}" : '' ) );

        $wpdb->query(
            $wpdb->prepare(
                "INSERT INTO {$wpdb->prefix}pp_team_player_goalie_stats_archive
                    (season_key, team_id, player_id, name, pos, headshot_link, team_name,
                     api_team_id, api_team_name,
                     source, games_played, wins, losses, overtime_losses, shootout_losses,
                     shootout_wins, shots_against, saves, save_percentage,
                     goals_against_average, goals_against, goals, assists, penalty_minutes, stat_rank)
                SELECT %s, g.team_id, g.player_id, d.name, d.pos, d.headshot_link, t.name AS team_name,
                       d.api_team_id, d.api_team_name,
                       g.source, g.games_played, g.wins, g.losses, g.overtime_losses, g.shootout_losses,
                       g.shootout_wins, g.shots_against, g.saves, g.save_percentage,
                       g.goals_against_average, g.goals_against, g.goals, g.assists, g.penalty_minutes, g.stat_rank
                FROM {$wpdb->prefix}pp_team_player_goalie_stats g
                INNER JOIN {$wpdb->prefix}pp_team_players_display d
                    ON d.player_id = g.player_id AND d.team_id = g.team_id
                INNER JOIN {$wpdb->prefix}pp_teams t
                    ON t.id = g.team_id
                WHERE g.team_id = %d",
                $season_key,
                $team_id
            )
        );
        error_log( "[PP Archive] pp_team_player_goalie_stats_archive rows_affected={$wpdb->rows_affected}" . ( $wpdb->last_error ? " error={$wpdb->last_error}" : '' ) );

        $game_count = (int) $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM {$wpdb->prefix}pp_team_games_archive WHERE season_key = %s AND team_id = %d",
                $season_key,
                $team_id
            )
        );

        $skater_count = (int) $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM {$wpdb->prefix}pp_team_player_stats_archive WHERE season_key = %s AND team_id = %d",
                $season_key,
                $team_id
            )
        );

        $goalie_count = (int) $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM {$wpdb->prefix}pp_team_player_goalie_stats_archive WHERE season_key = %s AND team_id = %d",
                $season_key,
                $team_id
            )
        );

        return array(
            'success'       => true,
            'message'       => sprintf(
                'Archived %d games, %d skaters, %d goalies for season "%s".',
                $game_count,
                $skater_count,
                $goalie_count,
                esc_html( $season_key )
            ),
            'game_count'    => $game_count,
            'skater_count'  => $skater_count,
            'goalie_count'  => $goalie_count,
        );
    }

    public function clear_team_season_stats( int $team_id ): void {
        global $wpdb;
        $wpdb->delete( $wpdb->prefix . 'pp_team_player_stats', array( 'team_id' => $team_id ), array( '%d' ) );
        $wpdb->delete( $wpdb->prefix . 'pp_team_player_goalie_stats', array( 'team_id' => $team_id ), array( '%d' ) );
        $wpdb->delete( $wpdb->prefix . 'pp_team_players_display', array( 'team_id' => $team_id ), array( '%d' ) );
        $wpdb->delete( $wpdb->prefix . 'pp_team_roster_sources', array( 'team_id' => $team_id ), array( '%d' ) );
        $wpdb->delete( $wpdb->prefix . 'pp_team_game_mods', array( 'team_id' => $team_id ), array( '%d' ) );
        $wpdb->delete( $wpdb->prefix . 'pp_team_games_raw', array( 'team_id' => $team_id ), array( '%d' ) );
        $wpdb->delete( $wpdb->prefix . 'pp_team_games_display', array( 'team_id' => $team_id ), array( '%d' ) );
        $wpdb->delete( $wpdb->prefix . 'pp_team_sources', array( 'team_id' => $team_id ), array( '%d' ) );
    }

    public function get_player_skater_archives( string $player_id ): array {
        global $wpdb;
        return $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM {$wpdb->prefix}pp_team_player_stats_archive
                 WHERE player_id = %s
                 ORDER BY season_key DESC",
                $player_id
            ),
            ARRAY_A
        ) ?? array();
    }

    public function get_player_goalie_archives( string $player_id ): array {
        global $wpdb;
        return $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM {$wpdb->prefix}pp_team_player_goalie_stats_archive
                 WHERE player_id = %s
                 ORDER BY season_key DESC",
                $player_id
            ),
            ARRAY_A
        ) ?? array();
    }

    public function get_team_archives( int $team_id ): array {
        global $wpdb;

        return $wpdb->get_results(
            $wpdb->prepare(
                "SELECT a.season_key,
                        COALESCE(a.label, a.season_key) AS label,
                        a.archived_at,
                        COUNT(DISTINCT g.id) AS game_count,
                        COUNT(DISTINCT s.player_id) AS skater_count,
                        COUNT(DISTINCT go.player_id) AS goalie_count
                 FROM {$wpdb->prefix}pp_archive_seasons a
                 INNER JOIN {$wpdb->prefix}pp_team_games_archive g
                         ON g.season_key = a.season_key AND g.team_id = %d
                 LEFT JOIN {$wpdb->prefix}pp_team_player_stats_archive s
                        ON s.season_key = a.season_key AND s.team_id = %d
                 LEFT JOIN {$wpdb->prefix}pp_team_player_goalie_stats_archive go
                        ON go.season_key = a.season_key AND go.team_id = %d
                 GROUP BY a.id, a.season_key, a.label, a.archived_at
                 ORDER BY a.archived_at DESC",
                $team_id,
                $team_id,
                $team_id
            ),
            ARRAY_A
        ) ?? array();
    }

    public function delete_archive( string $season_key ): void {
        global $wpdb;
        $wpdb->delete( $wpdb->prefix . 'pp_team_player_goalie_stats_archive', array( 'season_key' => $season_key ), array( '%s' ) );
        $wpdb->delete( $wpdb->prefix . 'pp_team_player_stats_archive', array( 'season_key' => $season_key ), array( '%s' ) );
        $wpdb->delete( $wpdb->prefix . 'pp_team_sources_archive', array( 'season_key' => $season_key ), array( '%s' ) );
        $wpdb->delete( $wpdb->prefix . 'pp_team_games_archive', array( 'season_key' => $season_key ), array( '%s' ) );
        $wpdb->delete( $wpdb->prefix . 'pp_archive_seasons', array( 'season_key' => $season_key ), array( '%s' ) );
    }

    public function get_archive_games( string $season_key, array $team_ids ): array {
        global $wpdb;

        if ( empty( $team_ids ) ) {
            return array();
        }

        $placeholders = implode( ', ', array_fill( 0, count( $team_ids ), '%d' ) );

        return $wpdb->get_results(
            $wpdb->prepare(
                "SELECT t.*
                 FROM {$wpdb->prefix}pp_team_games_archive t
                 INNER JOIN (
                     SELECT game_id,
                            COALESCE(
                                MIN(CASE WHEN home_or_away = 'home' THEN id END),
                                MIN(id)
                            ) AS preferred_id
                     FROM {$wpdb->prefix}pp_team_games_archive
                     WHERE season_key = %s AND team_id IN ($placeholders)
                     GROUP BY game_id
                 ) pref ON t.id = pref.preferred_id
                 WHERE t.season_key = %s AND t.team_id IN ($placeholders)
                 ORDER BY t.game_timestamp ASC, t.id ASC",
                array_merge(
                    array( $season_key ),
                    $team_ids,
                    array( $season_key ),
                    $team_ids
                )
            ),
            ARRAY_A
        ) ?? array();
    }
}
