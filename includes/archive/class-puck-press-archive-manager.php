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
            archive_id BIGINT(20) UNSIGNED NOT NULL,
            season_key VARCHAR(50) NOT NULL,
            team_name VARCHAR(200) NOT NULL DEFAULT '',
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
            KEY archive_id (archive_id),
            KEY season_key (season_key)
        ",
        'pp_team_sources_archive'          => "
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            archive_id BIGINT(20) UNSIGNED NOT NULL,
            season_key VARCHAR(50) NOT NULL,
            team_id BIGINT(20) UNSIGNED NOT NULL,
            name VARCHAR(100) NOT NULL,
            type TEXT NOT NULL,
            season VARCHAR(50) DEFAULT NULL,
            source_url_or_path TEXT DEFAULT NULL,
            PRIMARY KEY (id),
            KEY archive_id (archive_id),
            KEY season_team (season_key, team_id)
        ",
        'pp_team_player_stats_archive'     => "
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            archive_id BIGINT(20) UNSIGNED NOT NULL,
            season_key VARCHAR(50) NOT NULL,
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
            KEY archive_id (archive_id),
            KEY season_key (season_key),
            KEY player_id (player_id)
        ",
        'pp_team_player_goalie_stats_archive' => "
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            archive_id BIGINT(20) UNSIGNED NOT NULL,
            season_key VARCHAR(50) NOT NULL,
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
            KEY archive_id (archive_id),
            KEY season_key (season_key),
            KEY player_id (player_id)
        ",
    );

    public function maybe_create_or_update_tables(): void {
        foreach ( array_keys( $this->table_schemas ) as $table ) {
            $this->maybe_create_or_update_table( $table );
        }
    }

    /**
     * Archive one team's games and stats under the given season_key.
     * If a season row for season_key already exists, its archive_id is reused
     * so multiple teams accumulate under a single archive.
     * Live tables are NOT cleared here — call clear_all_teams_season_data() for that.
     */
    private function archive_team_season( int $team_id, string $season_key, string $label = '' ): array {
        global $wpdb;

        // Look up the admin-given team name — stored on archive rows as the stable identifier.
        $team_name = (string) $wpdb->get_var(
            $wpdb->prepare( "SELECT name FROM {$wpdb->prefix}pp_teams WHERE id = %d", $team_id )
        );

        if ( $team_name === '' ) {
            return array(
                'success' => false,
                'message' => "Team $team_id not found.",
            );
        }

        // Get or create the season row.
        $archive_id = (int) $wpdb->get_var(
            $wpdb->prepare(
                "SELECT id FROM {$wpdb->prefix}pp_archive_seasons WHERE season_key = %s",
                $season_key
            )
        );

        if ( $archive_id === 0 ) {
            $wpdb->insert(
                $wpdb->prefix . 'pp_archive_seasons',
                array(
                    'season_key'  => $season_key,
                    'label'       => $label !== '' ? $label : $season_key,
                    'archived_at' => current_time( 'mysql' ),
                ),
                array( '%s', '%s', '%s' )
            );

            if ( $wpdb->last_error ) {
                error_log( "[PP Archive] pp_archive_seasons insert error: {$wpdb->last_error}" );
                return array(
                    'success' => false,
                    'message' => 'Failed to create archive season record.',
                );
            }

            $archive_id = (int) $wpdb->insert_id;
        }

        // Guard: if this team_name already has games under this archive, skip to avoid duplicates.
        $already_has_games = (int) $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM {$wpdb->prefix}pp_team_games_archive WHERE archive_id = %d AND team_name = %s",
                $archive_id,
                $team_name
            )
        );

        if ( $already_has_games > 0 ) {
            return array(
                'success' => false,
                'message' => sprintf( 'Team "%s" is already archived for season "%s".', $team_name, $season_key ),
            );
        }

        // Copy games.
        $wpdb->query(
            $wpdb->prepare(
                "INSERT INTO {$wpdb->prefix}pp_team_games_archive
                    (archive_id, season_key, team_name, source, source_type, game_id,
                     target_team_id, target_team_name, target_team_nickname, target_team_logo,
                     opponent_team_id, opponent_team_name, opponent_team_nickname, opponent_team_logo,
                     target_score, opponent_score, game_status,
                     promo_header, promo_text, promo_img_url, promo_ticket_link, post_link,
                     game_date_day, game_time, game_timestamp, home_or_away, venue)
                SELECT %d, %s, %s, source, source_type, game_id,
                       target_team_id, target_team_name, target_team_nickname, target_team_logo,
                       opponent_team_id, opponent_team_name, opponent_team_nickname, opponent_team_logo,
                       target_score, opponent_score, game_status,
                       promo_header, promo_text, promo_img_url, promo_ticket_link, post_link,
                       game_date_day, game_time, game_timestamp, home_or_away, venue
                FROM {$wpdb->prefix}pp_team_games_display
                WHERE team_id = %d",
                $archive_id,
                $season_key,
                $team_name,
                $team_id
            )
        );
        $games_archived = (int) $wpdb->rows_affected;
        error_log( "[PP Archive] pp_team_games_archive rows_affected=$games_archived team=$team_name" . ( $wpdb->last_error ? " error={$wpdb->last_error}" : '' ) );

        // Copy sources.
        $wpdb->query(
            $wpdb->prepare(
                "INSERT INTO {$wpdb->prefix}pp_team_sources_archive
                    (archive_id, season_key, team_id, name, type, season, source_url_or_path)
                SELECT %d, %s, team_id, name, type, season, source_url_or_path
                FROM {$wpdb->prefix}pp_team_sources
                WHERE team_id = %d AND status = 'active'",
                $archive_id,
                $season_key,
                $team_id
            )
        );

        // Copy skater stats.
        $wpdb->query(
            $wpdb->prepare(
                "INSERT INTO {$wpdb->prefix}pp_team_player_stats_archive
                    (archive_id, season_key, player_id, name, pos, headshot_link, team_name,
                     api_team_id, api_team_name,
                     source, games_played, goals, assists, points, points_per_game,
                     power_play_goals, short_handed_goals, game_winning_goals,
                     shootout_winning_goals, penalty_minutes, shooting_percentage, stat_rank)
                SELECT %d, %s, s.player_id, d.name, d.pos, d.headshot_link, t.name AS team_name,
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
                $archive_id,
                $season_key,
                $team_id
            )
        );
        $skaters_archived = (int) $wpdb->rows_affected;
        error_log( "[PP Archive] pp_team_player_stats_archive rows_affected=$skaters_archived" . ( $wpdb->last_error ? " error={$wpdb->last_error}" : '' ) );

        // Copy goalie stats.
        $wpdb->query(
            $wpdb->prepare(
                "INSERT INTO {$wpdb->prefix}pp_team_player_goalie_stats_archive
                    (archive_id, season_key, player_id, name, pos, headshot_link, team_name,
                     api_team_id, api_team_name,
                     source, games_played, wins, losses, overtime_losses, shootout_losses,
                     shootout_wins, shots_against, saves, save_percentage,
                     goals_against_average, goals_against, goals, assists, penalty_minutes, stat_rank)
                SELECT %d, %s, g.player_id, d.name, d.pos, d.headshot_link, t.name AS team_name,
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
                $archive_id,
                $season_key,
                $team_id
            )
        );
        $goalies_archived = (int) $wpdb->rows_affected;
        error_log( "[PP Archive] pp_team_player_goalie_stats_archive rows_affected=$goalies_archived" . ( $wpdb->last_error ? " error={$wpdb->last_error}" : '' ) );

        return array(
            'success'       => true,
            'message'       => sprintf(
                'Archived %d games, %d skaters, %d goalies for team "%s".',
                $games_archived,
                $skaters_archived,
                $goalies_archived,
                $team_name
            ),
            'game_count'    => $games_archived,
            'skater_count'  => $skaters_archived,
            'goalie_count'  => $goalies_archived,
        );
    }

    /**
     * Archive all teams for the given season under one shared archive_id.
     */
    public function archive_all_teams_season( string $season_key, string $label = '' ): array {
        global $wpdb;

        $this->maybe_create_or_update_tables();

        $teams = $wpdb->get_results(
            "SELECT id, name FROM {$wpdb->prefix}pp_teams ORDER BY id ASC",
            ARRAY_A
        ) ?? array();

        $total_games   = 0;
        $total_skaters = 0;
        $total_goalies = 0;
        $errors        = array();

        foreach ( $teams as $team ) {
            $result = $this->archive_team_season( (int) $team['id'], $season_key, $label );
            if ( ! $result['success'] ) {
                error_log( "[PP Archive] archive_all_teams_season: team {$team['id']} ({$team['name']}) failed: {$result['message']}" );
                $errors[] = $result['message'];
                continue;
            }
            $total_games   += (int) $result['game_count'];
            $total_skaters += (int) $result['skater_count'];
            $total_goalies += (int) $result['goalie_count'];
        }

        // If no teams exist, still create the season row.
        if ( empty( $teams ) ) {
            $existing = (int) $wpdb->get_var(
                $wpdb->prepare(
                    "SELECT id FROM {$wpdb->prefix}pp_archive_seasons WHERE season_key = %s",
                    $season_key
                )
            );
            if ( ! $existing ) {
                $wpdb->insert(
                    $wpdb->prefix . 'pp_archive_seasons',
                    array(
                        'season_key'  => $season_key,
                        'label'       => $label !== '' ? $label : $season_key,
                        'archived_at' => current_time( 'mysql' ),
                    ),
                    array( '%s', '%s', '%s' )
                );
            }
        }

        return array(
            'success'      => true,
            'message'      => sprintf(
                'Archived %d games, %d skaters, %d goalies for season "%s".',
                $total_games,
                $total_skaters,
                $total_goalies,
                esc_html( $season_key )
            ),
            'game_count'   => $total_games,
            'skater_count' => $total_skaters,
            'goalie_count' => $total_goalies,
        );
    }

    /**
     * Clear all live game and roster data for every team.
     * Called as the wipe step after archiving.
     */
    public function clear_all_teams_season_data(): void {
        global $wpdb;
        $wpdb->query( "DELETE FROM {$wpdb->prefix}pp_team_player_stats" );
        $wpdb->query( "DELETE FROM {$wpdb->prefix}pp_team_player_goalie_stats" );
        $wpdb->query( "DELETE FROM {$wpdb->prefix}pp_team_players_display" );
        $wpdb->query( "DELETE FROM {$wpdb->prefix}pp_team_roster_sources" );
        $wpdb->query( "DELETE FROM {$wpdb->prefix}pp_team_game_mods" );
        $wpdb->query( "DELETE FROM {$wpdb->prefix}pp_team_games_raw" );
        $wpdb->query( "DELETE FROM {$wpdb->prefix}pp_team_games_display" );
        $wpdb->query( "DELETE FROM {$wpdb->prefix}pp_team_sources" );
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

    /**
     * Return all archived seasons with counts. No team filter — archives are site-wide.
     */
    public function get_all_archives(): array {
        global $wpdb;

        return $wpdb->get_results(
            "SELECT a.id AS archive_id,
                    a.season_key,
                    COALESCE(a.label, a.season_key) AS label,
                    a.archived_at,
                    COUNT(DISTINCT g.id) AS game_count,
                    COUNT(DISTINCT s.id) AS skater_count,
                    COUNT(DISTINCT go.id) AS goalie_count
             FROM {$wpdb->prefix}pp_archive_seasons a
             LEFT JOIN {$wpdb->prefix}pp_team_games_archive g ON g.archive_id = a.id
             LEFT JOIN {$wpdb->prefix}pp_team_player_stats_archive s ON s.archive_id = a.id
             LEFT JOIN {$wpdb->prefix}pp_team_player_goalie_stats_archive go ON go.archive_id = a.id
             GROUP BY a.id, a.season_key, a.label, a.archived_at
             ORDER BY a.archived_at DESC",
            ARRAY_A
        ) ?? array();
    }

    /**
     * Delete an entire archived season and all its child data by season_key.
     */
    public function delete_archive( string $season_key ): void {
        global $wpdb;

        $archive_id = (int) $wpdb->get_var(
            $wpdb->prepare(
                "SELECT id FROM {$wpdb->prefix}pp_archive_seasons WHERE season_key = %s",
                $season_key
            )
        );

        if ( ! $archive_id ) {
            return;
        }

        $wpdb->delete( $wpdb->prefix . 'pp_team_player_goalie_stats_archive', array( 'archive_id' => $archive_id ), array( '%d' ) );
        $wpdb->delete( $wpdb->prefix . 'pp_team_player_stats_archive', array( 'archive_id' => $archive_id ), array( '%d' ) );
        $wpdb->delete( $wpdb->prefix . 'pp_team_sources_archive', array( 'archive_id' => $archive_id ), array( '%d' ) );
        $wpdb->delete( $wpdb->prefix . 'pp_team_games_archive', array( 'archive_id' => $archive_id ), array( '%d' ) );
        $wpdb->delete( $wpdb->prefix . 'pp_archive_seasons', array( 'id' => $archive_id ), array( '%d' ) );
    }

    /**
     * Get games for a season, optionally filtered by team names (admin-given names).
     * Empty $team_names returns all teams' games, deduplicated (main/conference view).
     */
    public function get_archive_games( string $season_key, array $team_names = array() ): array {
        global $wpdb;

        if ( empty( $team_names ) ) {
            // All teams, deduplicated.
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
                         WHERE season_key = %s
                         GROUP BY game_id
                     ) pref ON t.id = pref.preferred_id
                     WHERE t.season_key = %s
                     ORDER BY t.game_timestamp ASC, t.id ASC",
                    $season_key,
                    $season_key
                ),
                ARRAY_A
            ) ?? array();
        }

        $placeholders = implode( ', ', array_fill( 0, count( $team_names ), '%s' ) );

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
                     WHERE season_key = %s AND team_name IN ($placeholders)
                     GROUP BY game_id
                 ) pref ON t.id = pref.preferred_id
                 WHERE t.season_key = %s AND t.team_name IN ($placeholders)
                 ORDER BY t.game_timestamp ASC, t.id ASC",
                array_merge(
                    array( $season_key ),
                    $team_names,
                    array( $season_key ),
                    $team_names
                )
            ),
            ARRAY_A
        ) ?? array();
    }
}
