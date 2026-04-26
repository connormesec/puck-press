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
            api_label VARCHAR(200) DEFAULT NULL,
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
            other_data TEXT DEFAULT NULL,
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
        'pp_team_players_archive'          => "
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            archive_id BIGINT(20) UNSIGNED NOT NULL,
            season_key VARCHAR(50) NOT NULL,
            team_name VARCHAR(200) NOT NULL DEFAULT '',
            player_id VARCHAR(50) NOT NULL,
            name VARCHAR(100) DEFAULT NULL,
            number VARCHAR(10) DEFAULT NULL,
            pos VARCHAR(10) DEFAULT NULL,
            shoots VARCHAR(10) DEFAULT NULL,
            ht VARCHAR(20) DEFAULT NULL,
            wt VARCHAR(20) DEFAULT NULL,
            hometown VARCHAR(200) DEFAULT NULL,
            headshot_link TEXT DEFAULT NULL,
            last_team VARCHAR(200) DEFAULT NULL,
            year_in_school VARCHAR(50) DEFAULT NULL,
            major VARCHAR(100) DEFAULT NULL,
            hero_image_url TEXT DEFAULT NULL,
            api_team_id VARCHAR(100) DEFAULT NULL,
            api_team_name VARCHAR(255) DEFAULT NULL,
            source VARCHAR(100) DEFAULT NULL,
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
                    (archive_id, season_key, team_id, name, type, season, source_url_or_path, other_data)
                SELECT %d, %s, team_id, name, type, season, source_url_or_path, other_data
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

        // Copy roster bios.
        $wpdb->query(
            $wpdb->prepare(
                "INSERT INTO {$wpdb->prefix}pp_team_players_archive
                    (archive_id, season_key, team_name, player_id, name, number, pos, shoots,
                     ht, wt, hometown, headshot_link, last_team, year_in_school, major,
                     hero_image_url, api_team_id, api_team_name, source)
                SELECT %d, %s, t.name, d.player_id, d.name, d.number, d.pos, d.shoots,
                       d.ht, d.wt, d.hometown, d.headshot_link, d.last_team, d.year_in_school,
                       d.major, d.hero_image_url, d.api_team_id, d.api_team_name, d.source
                FROM {$wpdb->prefix}pp_team_players_display d
                INNER JOIN {$wpdb->prefix}pp_teams t ON t.id = d.team_id
                WHERE d.team_id = %d",
                $archive_id,
                $season_key,
                $team_id
            )
        );
        $roster_archived = (int) $wpdb->rows_affected;

        return array(
            'success'       => true,
            'message'       => sprintf(
                'Archived %d games, %d skaters, %d goalies, %d roster for team "%s".',
                $games_archived,
                $skaters_archived,
                $goalies_archived,
                $roster_archived,
                $team_name
            ),
            'game_count'    => $games_archived,
            'skater_count'  => $skaters_archived,
            'goalie_count'  => $goalies_archived,
            'roster_count'  => $roster_archived,
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
        $total_roster  = 0;
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
            $total_roster  += (int) ( $result['roster_count'] ?? 0 );
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
            'success'       => true,
            'message'       => sprintf(
                'Archived %d games, %d skaters, %d goalies, %d roster for season "%s".',
                $total_games,
                $total_skaters,
                $total_goalies,
                $total_roster,
                esc_html( $season_key )
            ),
            'game_count'    => $total_games,
            'skater_count'  => $total_skaters,
            'goalie_count'  => $total_goalies,
            'roster_count'  => $total_roster,
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
                "SELECT s.season_key,
                        COALESCE(a.label, s.season_key) AS season_label,
                        s.player_id, s.source,
                        MAX(s.name) AS name, MAX(s.pos) AS pos, MAX(s.headshot_link) AS headshot_link,
                        MAX(s.team_name) AS team_name, MAX(s.api_team_id) AS api_team_id, MAX(s.api_team_name) AS api_team_name,
                        SUM(s.games_played) AS games_played, SUM(s.goals) AS goals,
                        SUM(s.assists) AS assists, SUM(s.points) AS points,
                        CAST(ROUND(SUM(s.points) / NULLIF(SUM(s.games_played), 0), 2) AS DECIMAL(5,2)) AS points_per_game,
                        SUM(s.power_play_goals) AS power_play_goals,
                        SUM(s.short_handed_goals) AS short_handed_goals,
                        SUM(s.game_winning_goals) AS game_winning_goals,
                        SUM(s.shootout_winning_goals) AS shootout_winning_goals,
                        SUM(s.penalty_minutes) AS penalty_minutes,
                        CAST(AVG(s.shooting_percentage) AS DECIMAL(5,2)) AS shooting_percentage,
                        MIN(s.stat_rank) AS stat_rank
                 FROM {$wpdb->prefix}pp_team_player_stats_archive s
                 LEFT JOIN {$wpdb->prefix}pp_archive_seasons a ON a.season_key = s.season_key
                 WHERE s.player_id = %s
                 GROUP BY s.season_key, a.label, s.source
                 ORDER BY s.season_key DESC",
                $player_id
            ),
            ARRAY_A
        ) ?? array();
    }

    public function get_player_goalie_archives( string $player_id ): array {
        global $wpdb;
        return $wpdb->get_results(
            $wpdb->prepare(
                "SELECT s.season_key,
                        COALESCE(a.label, s.season_key) AS season_label,
                        s.player_id, s.source,
                        MAX(s.name) AS name, MAX(s.pos) AS pos, MAX(s.headshot_link) AS headshot_link,
                        MAX(s.team_name) AS team_name, MAX(s.api_team_id) AS api_team_id, MAX(s.api_team_name) AS api_team_name,
                        SUM(s.games_played) AS games_played, SUM(s.wins) AS wins, SUM(s.losses) AS losses,
                        SUM(s.overtime_losses) AS overtime_losses, SUM(s.shootout_losses) AS shootout_losses,
                        SUM(s.shootout_wins) AS shootout_wins,
                        SUM(s.shots_against) AS shots_against, SUM(s.saves) AS saves,
                        CAST(CASE WHEN SUM(s.shots_against) > 0 THEN ROUND(SUM(s.saves) / SUM(s.shots_against), 3) ELSE 0 END AS DECIMAL(6,3)) AS save_percentage,
                        CAST(CASE WHEN SUM(s.games_played) > 0 THEN ROUND(SUM(s.goals_against) / SUM(s.games_played), 2) ELSE 0 END AS DECIMAL(5,2)) AS goals_against_average,
                        SUM(s.goals_against) AS goals_against,
                        SUM(s.goals) AS goals, SUM(s.assists) AS assists,
                        SUM(s.penalty_minutes) AS penalty_minutes,
                        MIN(s.stat_rank) AS stat_rank
                 FROM {$wpdb->prefix}pp_team_player_goalie_stats_archive s
                 LEFT JOIN {$wpdb->prefix}pp_archive_seasons a ON a.season_key = s.season_key
                 WHERE s.player_id = %s
                 GROUP BY s.season_key, a.label, s.source
                 ORDER BY s.season_key DESC",
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
                    a.api_label,
                    a.archived_at,
                    COUNT(DISTINCT g.id) AS game_count,
                    COUNT(DISTINCT s.id) AS skater_count,
                    COUNT(DISTINCT go.id) AS goalie_count,
                    COUNT(DISTINCT r.id) AS roster_count
             FROM {$wpdb->prefix}pp_archive_seasons a
             LEFT JOIN {$wpdb->prefix}pp_team_games_archive g ON g.archive_id = a.id
             LEFT JOIN {$wpdb->prefix}pp_team_player_stats_archive s ON s.archive_id = a.id
             LEFT JOIN {$wpdb->prefix}pp_team_player_goalie_stats_archive go ON go.archive_id = a.id
             LEFT JOIN {$wpdb->prefix}pp_team_players_archive r ON r.archive_id = a.id
             GROUP BY a.id, a.season_key, a.label, a.api_label, a.archived_at
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
        $wpdb->delete( $wpdb->prefix . 'pp_team_players_archive', array( 'archive_id' => $archive_id ), array( '%d' ) );
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

    public function delete_archive_for_team( string $season_key, string $team_name ): void {
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

        $where = array( 'archive_id' => $archive_id, 'team_name' => $team_name );
        $fmt   = array( '%d', '%s' );

        $wpdb->delete( $wpdb->prefix . 'pp_team_player_goalie_stats_archive', $where, $fmt );
        $wpdb->delete( $wpdb->prefix . 'pp_team_player_stats_archive', $where, $fmt );
        $wpdb->delete( $wpdb->prefix . 'pp_team_players_archive', $where, $fmt );
        $wpdb->delete( $wpdb->prefix . 'pp_team_games_archive', $where, $fmt );

        // Sources use team_id, not team_name — resolve from pp_teams.
        $local_team_id = (int) $wpdb->get_var(
            $wpdb->prepare( "SELECT id FROM {$wpdb->prefix}pp_teams WHERE name = %s LIMIT 1", $team_name )
        );
        if ( $local_team_id ) {
            $wpdb->delete(
                $wpdb->prefix . 'pp_team_sources_archive',
                array( 'archive_id' => $archive_id, 'team_id' => $local_team_id ),
                array( '%d', '%d' )
            );
        }

        // If no child data remains for any team, delete the season row too.
        $remaining = (int) $wpdb->get_var(
            $wpdb->prepare(
                "SELECT (
                    SELECT COUNT(*) FROM {$wpdb->prefix}pp_team_games_archive WHERE archive_id = %d
                ) + (
                    SELECT COUNT(*) FROM {$wpdb->prefix}pp_team_player_stats_archive WHERE archive_id = %d
                ) + (
                    SELECT COUNT(*) FROM {$wpdb->prefix}pp_team_players_archive WHERE archive_id = %d
                )",
                $archive_id,
                $archive_id,
                $archive_id
            )
        );

        if ( $remaining === 0 ) {
            $wpdb->delete( $wpdb->prefix . 'pp_archive_seasons', array( 'id' => $archive_id ), array( '%d' ) );
        }
    }

    public function get_archive_teams( string $season_key ): array {
        global $wpdb;

        $archive_id = (int) $wpdb->get_var(
            $wpdb->prepare(
                "SELECT id FROM {$wpdb->prefix}pp_archive_seasons WHERE season_key = %s",
                $season_key
            )
        );

        if ( ! $archive_id ) {
            return array();
        }

        return $wpdb->get_col(
            $wpdb->prepare(
                "SELECT DISTINCT team_name FROM {$wpdb->prefix}pp_team_games_archive WHERE archive_id = %d
                 UNION
                 SELECT DISTINCT team_name FROM {$wpdb->prefix}pp_team_players_archive WHERE archive_id = %d
                 UNION
                 SELECT DISTINCT team_name FROM {$wpdb->prefix}pp_team_player_stats_archive WHERE archive_id = %d",
                $archive_id,
                $archive_id,
                $archive_id
            )
        ) ?? array();
    }

    public function rename_archive( string $season_key, string $new_label ): bool {
        global $wpdb;

        return (bool) $wpdb->update(
            $wpdb->prefix . 'pp_archive_seasons',
            array( 'label' => $new_label ),
            array( 'season_key' => $season_key ),
            array( '%s' ),
            array( '%s' )
        );
    }

    private static function strip_acha_prefix( string $name ): string {
        return preg_replace( '/^(?:#\d+\s+)?(?:(?:MD[1-3]|WD[1-3]|M[1-3]|W[1-3])\s+)?(?:#\d+\s+)?/', '', $name );
    }

    private function get_or_create_archive_id( string $season_key, string $label, string $api_label = '' ): int {
        global $wpdb;

        $label     = stripslashes( $label );
        $api_label = stripslashes( $api_label );

        $archive_id = (int) $wpdb->get_var(
            $wpdb->prepare(
                "SELECT id FROM {$wpdb->prefix}pp_archive_seasons WHERE season_key = %s",
                $season_key
            )
        );

        if ( $archive_id ) {
            return $archive_id;
        }

        $data = array(
            'season_key'  => $season_key,
            'label'       => $label !== '' ? $label : $season_key,
            'archived_at' => current_time( 'mysql' ),
        );
        $formats = array( '%s', '%s', '%s' );

        if ( $api_label !== '' ) {
            $data['api_label'] = $api_label;
            $formats[]         = '%s';
        }

        $wpdb->insert( $wpdb->prefix . 'pp_archive_seasons', $data, $formats );
        return (int) $wpdb->insert_id;
    }

    private function team_has_archive_data( int $archive_id, string $team_name ): bool {
        global $wpdb;

        $count = (int) $wpdb->get_var(
            $wpdb->prepare(
                "SELECT (
                    SELECT COUNT(*) FROM {$wpdb->prefix}pp_team_games_archive WHERE archive_id = %d AND team_name = %s
                ) + (
                    SELECT COUNT(*) FROM {$wpdb->prefix}pp_team_players_archive WHERE archive_id = %d AND team_name = %s
                ) + (
                    SELECT COUNT(*) FROM {$wpdb->prefix}pp_team_player_stats_archive WHERE archive_id = %d AND team_name = %s
                ) + (
                    SELECT COUNT(*) FROM {$wpdb->prefix}pp_team_player_goalie_stats_archive WHERE archive_id = %d AND team_name = %s
                )",
                $archive_id, $team_name,
                $archive_id, $team_name,
                $archive_id, $team_name,
                $archive_id, $team_name
            )
        );

        return $count > 0;
    }

    public function import_from_acha_api( array $params ): array {
        global $wpdb;
        $this->maybe_create_or_update_tables();

        $local_team_id = (int) $params['team_id'];
        $api_team_id   = (string) $params['api_team_id'];
        $season_id     = (string) $params['season_id'];
        $division_id   = (string) ( $params['division_id'] ?? '-1' );
        $season_key    = (string) $params['season_key'];
        $label         = (string) ( $params['label'] ?? '' );
        $api_label     = (string) ( $params['api_label'] ?? '' );
        $import_schedule     = ! empty( $params['schedule'] );
        $import_roster_stats = ! empty( $params['roster_and_stats'] );
        $append              = ! empty( $params['append'] );

        $team_name = (string) $wpdb->get_var(
            $wpdb->prepare( "SELECT name FROM {$wpdb->prefix}pp_teams WHERE id = %d", $local_team_id )
        );
        if ( $team_name === '' ) {
            return array( 'success' => false, 'message' => "Team $local_team_id not found." );
        }

        $archive_id = $this->get_or_create_archive_id( $season_key, $label, $api_label );
        if ( ! $archive_id ) {
            return array( 'success' => false, 'message' => 'Failed to create archive season record.' );
        }

        if ( $this->team_has_archive_data( $archive_id, $team_name ) && ! $append ) {
            return array(
                'success' => false,
                'message' => sprintf( 'Team "%s" already has data archived for season "%s". Delete it first or use Append to add data.', $team_name, $season_key ),
            );
        }

        $result = array(
            'success'       => true,
            'game_count'    => 0,
            'roster_count'  => 0,
            'skater_count'  => 0,
            'goalie_count'  => 0,
            'errors'        => array(),
        );

        // Derive season_year from bootstrap for ACHA date resolution.
        require_once plugin_dir_path( __FILE__ ) . '../schedule/class-puck-press-acha-season-discoverer.php';
        $meta = Puck_Press_Acha_Season_Discoverer::get_team_season_meta( $api_team_id, $season_id );
        $season_year = '';
        $source_label = 'ACHA';
        if ( ! is_wp_error( $meta ) ) {
            $season_year  = $meta['season_year'] ?? '';
            $season_name  = $meta['season_name'] ?? '';
            $bootstrap    = Puck_Press_Acha_Season_Discoverer::get_bootstrap();
            $playoff_ids  = array_column( $bootstrap['playoffSeasons'] ?? array(), 'id' );
            $is_playoff   = in_array( (int) $season_id, array_map( 'intval', $playoff_ids ), true );
            $source_label = $is_playoff ? 'ACHA Playoffs' : 'ACHA Regular Season';
        }

        if ( $import_schedule ) {
            require_once plugin_dir_path( __FILE__ ) . '../schedule/class-puck-press-schedule-process-acha-url.php';
            $processor = new Puck_Press_Schedule_Process_Acha_Url( $api_team_id, $season_id, $division_id, $season_year );
            $games     = $processor->raw_schedule_data;

            $existing_game_ids = array();
            if ( $append ) {
                $existing_game_ids = array_flip(
                    $wpdb->get_col( $wpdb->prepare(
                        "SELECT game_id FROM {$wpdb->prefix}pp_team_games_archive WHERE archive_id = %d AND team_name = %s",
                        $archive_id, $team_name
                    ) ) ?: array()
                );
            }

            if ( is_array( $games ) && ! isset( $games['error'] ) ) {
                $inserted_games = 0;
                foreach ( $games as $game ) {
                    if ( isset( $existing_game_ids[ $game['game_id'] ?? '' ] ) ) {
                        continue;
                    }
                    $inserted_games++;
                    $wpdb->insert(
                        $wpdb->prefix . 'pp_team_games_archive',
                        array(
                            'archive_id'             => $archive_id,
                            'season_key'             => $season_key,
                            'team_name'              => $team_name,
                            'source'                 => 'ACHA API Import',
                            'source_type'            => 'achaApiImport',
                            'game_id'                => $game['game_id'] ?? '',
                            'target_team_id'         => $game['target_team_id'] ?? '',
                            'target_team_name'       => $game['target_team_name'] ?? '',
                            'target_team_nickname'   => $game['target_team_nickname'] ?? null,
                            'target_team_logo'       => $game['target_team_logo'] ?? null,
                            'opponent_team_id'       => $game['opponent_team_id'] ?? '',
                            'opponent_team_name'     => $game['opponent_team_name'] ?? '',
                            'opponent_team_nickname'  => $game['opponent_team_nickname'] ?? null,
                            'opponent_team_logo'     => $game['opponent_team_logo'] ?? null,
                            'target_score'           => $game['target_score'] ?? null,
                            'opponent_score'         => $game['opponent_score'] ?? null,
                            'game_status'            => $game['game_status'] ?? null,
                            'game_date_day'          => $game['game_date_day'] ?? '',
                            'game_time'              => $game['game_time'] ?? null,
                            'game_timestamp'         => $game['game_timestamp'] ?? null,
                            'home_or_away'           => $game['home_or_away'] ?? 'home',
                            'venue'                  => $game['venue'] ?? null,
                        )
                    );
                }
                $result['game_count'] = $inserted_games;
            } else {
                $result['errors'][] = 'Schedule: ' . ( $games['error'] ?? 'No data returned' );
            }
        }

        $player_lookup = array();

        $existing_player_ids = array();
        if ( $append ) {
            $existing_player_ids = array_flip(
                $wpdb->get_col( $wpdb->prepare(
                    "SELECT player_id FROM {$wpdb->prefix}pp_team_players_archive WHERE archive_id = %d AND team_name = %s",
                    $archive_id, $team_name
                ) ) ?: array()
            );
        }

        if ( $import_roster_stats ) {
            // Fetch roster bios.
            require_once plugin_dir_path( __FILE__ ) . '../roster/class-puck-press-roster-process-acha-url.php';
            $roster_proc = new Puck_Press_Roster_Process_Acha_Url( $api_team_id, $season_id );
            $roster_data = $roster_proc->raw_roster_data;
            $api_team_name_raw = $roster_proc->team_name;
            $api_team_name_clean = self::strip_acha_prefix( $api_team_name_raw );

            if ( is_array( $roster_data ) && ! isset( $roster_data['error'] ) ) {
                $inserted_roster = 0;
                foreach ( $roster_data as $player ) {
                    $pid = (string) ( $player['player_id'] ?? '' );
                    if ( $pid === '' ) {
                        continue;
                    }
                    $player_lookup[ $pid ] = array(
                        'name'          => $player['name'] ?? null,
                        'pos'           => $player['pos'] ?? null,
                        'headshot_link' => $player['headshot_link'] ?? null,
                    );

                    if ( isset( $existing_player_ids[ $pid ] ) ) {
                        continue;
                    }
                    $inserted_roster++;
                    $wpdb->insert(
                        $wpdb->prefix . 'pp_team_players_archive',
                        array(
                            'archive_id'    => $archive_id,
                            'season_key'    => $season_key,
                            'team_name'     => $team_name,
                            'player_id'     => $pid,
                            'name'          => $player['name'] ?? null,
                            'number'        => $player['number'] ?? null,
                            'pos'           => $player['pos'] ?? null,
                            'shoots'        => $player['shoots'] ?? null,
                            'ht'            => $player['ht'] ?? null,
                            'wt'            => $player['wt'] ?? null,
                            'hometown'      => $player['hometown'] ?? null,
                            'headshot_link' => $player['headshot_link'] ?? null,
                            'last_team'     => $player['last_team'] ?? null,
                            'year_in_school' => $player['year_in_school'] ?? null,
                            'major'         => $player['major'] ?? null,
                            'api_team_id'   => $api_team_id,
                            'api_team_name' => $api_team_name_clean,
                            'source'        => $source_label,
                        )
                    );
                }
                $result['roster_count'] = $inserted_roster;
            } else {
                $result['errors'][] = 'Roster: ' . ( $roster_data['error'] ?? 'No data returned' );
            }

            // Fetch skater stats.
            require_once plugin_dir_path( __FILE__ ) . '../roster/class-puck-press-roster-process-acha-stats.php';
            $skater_proc = new Puck_Press_Roster_Process_Acha_Stats( $api_team_id, $season_id, false );
            $skater_data = $skater_proc->raw_stats_data;

            if ( is_array( $skater_data ) && ! isset( $skater_data['error'] ) ) {
                foreach ( $skater_data as $stat ) {
                    $pid  = (string) ( $stat['player_id'] ?? '' );
                    $bio  = $player_lookup[ $pid ] ?? array();

                    // Fallback: stats API provides name/position for players not on the roster
                    if ( empty( $bio['name'] ) && ! empty( $stat['name'] ) ) {
                        $headshot = 'https://assets.leaguestat.com/acha/240x240/' . $pid . '.jpg';
                        $bio = array(
                            'name'          => $stat['name'],
                            'pos'           => $stat['position'] ?? null,
                            'headshot_link' => $headshot,
                        );
                        $player_lookup[ $pid ] = $bio;

                        // Create stub roster archive entry for this player
                        if ( ! isset( $existing_player_ids[ $pid ] ) ) {
                            $wpdb->insert(
                                $wpdb->prefix . 'pp_team_players_archive',
                                array(
                                    'archive_id'    => $archive_id,
                                    'season_key'    => $season_key,
                                    'team_name'     => $team_name,
                                    'player_id'     => $pid,
                                    'name'          => $stat['name'],
                                    'pos'           => $stat['position'] ?? null,
                                    'headshot_link' => $headshot,
                                    'api_team_id'   => $api_team_id,
                                    'api_team_name' => $api_team_name_clean,
                                    'source'        => $source_label,
                                )
                            );
                        }
                    }

                    $wpdb->insert(
                        $wpdb->prefix . 'pp_team_player_stats_archive',
                        array(
                            'archive_id'             => $archive_id,
                            'season_key'             => $season_key,
                            'player_id'              => $pid,
                            'name'                   => $bio['name'] ?? null,
                            'pos'                    => $bio['pos'] ?? null,
                            'headshot_link'          => $bio['headshot_link'] ?? null,
                            'team_name'              => $team_name,
                            'api_team_id'            => $api_team_id,
                            'api_team_name'          => $api_team_name_clean,
                            'source'                 => $source_label,
                            'games_played'           => $stat['games_played'] ?? null,
                            'goals'                  => $stat['goals'] ?? null,
                            'assists'                => $stat['assists'] ?? null,
                            'points'                 => $stat['points'] ?? null,
                            'points_per_game'        => $stat['points_per_game'] ?? null,
                            'power_play_goals'       => $stat['power_play_goals'] ?? null,
                            'short_handed_goals'     => $stat['short_handed_goals'] ?? null,
                            'game_winning_goals'     => $stat['game_winning_goals'] ?? null,
                            'shootout_winning_goals' => $stat['shootout_winning_goals'] ?? null,
                            'penalty_minutes'        => $stat['penalty_minutes'] ?? null,
                            'shooting_percentage'    => $stat['shooting_percentage'] ?? null,
                            'stat_rank'              => $stat['stat_rank'] ?? null,
                        )
                    );
                }
                $result['skater_count'] = count( $skater_data );
            } else {
                $result['errors'][] = 'Skater stats: ' . ( $skater_data['error'] ?? 'No data returned' );
            }

            // Fetch goalie stats.
            $goalie_proc = new Puck_Press_Roster_Process_Acha_Stats( $api_team_id, $season_id, true );
            $goalie_data = $goalie_proc->raw_goalie_stats_data;

            if ( is_array( $goalie_data ) && ! isset( $goalie_data['error'] ) ) {
                foreach ( $goalie_data as $stat ) {
                    $pid  = (string) ( $stat['player_id'] ?? '' );
                    $bio  = $player_lookup[ $pid ] ?? array();

                    if ( empty( $bio['name'] ) && ! empty( $stat['name'] ) ) {
                        $headshot = 'https://assets.leaguestat.com/acha/240x240/' . $pid . '.jpg';
                        $bio = array(
                            'name'          => $stat['name'],
                            'pos'           => $stat['position'] ?? 'G',
                            'headshot_link' => $headshot,
                        );
                        $player_lookup[ $pid ] = $bio;

                        if ( ! isset( $existing_player_ids[ $pid ] ) ) {
                            $wpdb->insert(
                                $wpdb->prefix . 'pp_team_players_archive',
                                array(
                                    'archive_id'    => $archive_id,
                                    'season_key'    => $season_key,
                                    'team_name'     => $team_name,
                                    'player_id'     => $pid,
                                    'name'          => $stat['name'],
                                    'pos'           => $stat['position'] ?? 'G',
                                    'headshot_link' => $headshot,
                                    'api_team_id'   => $api_team_id,
                                    'api_team_name' => $api_team_name_clean,
                                    'source'        => $source_label,
                                )
                            );
                        }
                    }

                    $wpdb->insert(
                        $wpdb->prefix . 'pp_team_player_goalie_stats_archive',
                        array(
                            'archive_id'            => $archive_id,
                            'season_key'            => $season_key,
                            'player_id'             => $pid,
                            'name'                  => $bio['name'] ?? null,
                            'pos'                   => $bio['pos'] ?? null,
                            'headshot_link'         => $bio['headshot_link'] ?? null,
                            'team_name'             => $team_name,
                            'api_team_id'           => $api_team_id,
                            'api_team_name'         => $api_team_name_clean,
                            'source'                => $source_label,
                            'games_played'          => $stat['games_played'] ?? null,
                            'wins'                  => $stat['wins'] ?? null,
                            'losses'                => $stat['losses'] ?? null,
                            'overtime_losses'       => $stat['overtime_losses'] ?? null,
                            'shootout_losses'       => $stat['shootout_losses'] ?? null,
                            'shootout_wins'         => $stat['shootout_wins'] ?? null,
                            'shots_against'         => $stat['shots_against'] ?? null,
                            'saves'                 => $stat['saves'] ?? null,
                            'save_percentage'       => $stat['save_percentage'] ?? null,
                            'goals_against_average' => $stat['goals_against_average'] ?? null,
                            'goals_against'         => $stat['goals_against'] ?? null,
                            'goals'                 => $stat['goals'] ?? null,
                            'assists'               => $stat['assists'] ?? null,
                            'penalty_minutes'       => $stat['penalty_minutes'] ?? null,
                            'stat_rank'             => $stat['stat_rank'] ?? null,
                        )
                    );
                }
                $result['goalie_count'] = count( $goalie_data );
            } else {
                $result['errors'][] = 'Goalie stats: ' . ( $goalie_data['error'] ?? 'No data returned' );
            }

            // Backfill roster bios for stat rows missing names (very old seasons).
            if ( empty( $player_lookup ) && ( $result['skater_count'] > 0 || $result['goalie_count'] > 0 ) ) {
                $this->backfill_roster_bios_from_other_seasons( $archive_id, $season_key );
                $result['errors'][] = 'Roster data unavailable — player names may be missing from stats display.';
            }
        }

        // Synthesize source archive row.
        $wpdb->insert(
            $wpdb->prefix . 'pp_team_sources_archive',
            array(
                'archive_id'        => $archive_id,
                'season_key'        => $season_key,
                'team_id'           => $local_team_id,
                'name'              => 'ACHA API Import',
                'type'              => 'achaApiImport',
                'season'            => $season_id,
                'source_url_or_path' => $api_team_id,
                'other_data'        => wp_json_encode( array(
                    'season_id'   => $season_id,
                    'division_id' => $division_id,
                    'import_date' => current_time( 'mysql' ),
                ) ),
            )
        );

        $result['message'] = sprintf(
            'Imported %d games, %d roster, %d skaters, %d goalies for "%s".',
            $result['game_count'],
            $result['roster_count'],
            $result['skater_count'],
            $result['goalie_count'],
            $team_name
        );

        return $result;
    }

    public function import_from_usphl_api( array $params ): array {
        global $wpdb;
        $this->maybe_create_or_update_tables();

        $local_team_id = (int) $params['team_id'];
        $api_team_id   = (string) $params['api_team_id'];
        $season_id     = (string) $params['season_id'];
        $season_key    = (string) $params['season_key'];
        $label         = (string) ( $params['label'] ?? '' );
        $api_label     = (string) ( $params['api_label'] ?? '' );
        $import_schedule     = ! empty( $params['schedule'] );
        $import_roster_stats = ! empty( $params['roster_and_stats'] );
        $append              = ! empty( $params['append'] );

        $team_name = (string) $wpdb->get_var(
            $wpdb->prepare( "SELECT name FROM {$wpdb->prefix}pp_teams WHERE id = %d", $local_team_id )
        );
        if ( $team_name === '' ) {
            return array( 'success' => false, 'message' => "Team $local_team_id not found." );
        }

        $archive_id = $this->get_or_create_archive_id( $season_key, $label, $api_label );
        if ( ! $archive_id ) {
            return array( 'success' => false, 'message' => 'Failed to create archive season record.' );
        }

        if ( $this->team_has_archive_data( $archive_id, $team_name ) && ! $append ) {
            return array(
                'success' => false,
                'message' => sprintf( 'Team "%s" already has data archived for season "%s". Delete it first or use Append to add data.', $team_name, $season_key ),
            );
        }

        $result = array(
            'success'       => true,
            'game_count'    => 0,
            'roster_count'  => 0,
            'skater_count'  => 0,
            'goalie_count'  => 0,
            'errors'        => array(),
        );

        $source_label = 'USPHL';

        if ( $import_schedule ) {
            require_once plugin_dir_path( __FILE__ ) . '../schedule/class-puck-press-schedule-process-usphl-url.php';
            require_once plugin_dir_path( __FILE__ ) . '../class-puck-press-tts-api.php';
            $processor = new Puck_Press_Schedule_Process_Usphl_Url( $api_team_id, $season_id );
            $games     = $processor->raw_schedule_data;

            $existing_game_ids = array();
            if ( $append ) {
                $existing_game_ids = array_flip(
                    $wpdb->get_col( $wpdb->prepare(
                        "SELECT game_id FROM {$wpdb->prefix}pp_team_games_archive WHERE archive_id = %d AND team_name = %s",
                        $archive_id, $team_name
                    ) ) ?: array()
                );
            }

            if ( is_array( $games ) && ! isset( $games['error'] ) ) {
                $inserted_games = 0;
                foreach ( $games as $game ) {
                    if ( isset( $existing_game_ids[ $game['game_id'] ?? '' ] ) ) {
                        continue;
                    }
                    $inserted_games++;
                    $wpdb->insert(
                        $wpdb->prefix . 'pp_team_games_archive',
                        array(
                            'archive_id'             => $archive_id,
                            'season_key'             => $season_key,
                            'team_name'              => $team_name,
                            'source'                 => 'USPHL API Import',
                            'source_type'            => 'usphlApiImport',
                            'game_id'                => $game['game_id'] ?? '',
                            'target_team_id'         => $game['target_team_id'] ?? '',
                            'target_team_name'       => $game['target_team_name'] ?? '',
                            'target_team_nickname'   => $game['target_team_nickname'] ?? null,
                            'target_team_logo'       => $game['target_team_logo'] ?? null,
                            'opponent_team_id'       => $game['opponent_team_id'] ?? '',
                            'opponent_team_name'     => $game['opponent_team_name'] ?? '',
                            'opponent_team_nickname'  => $game['opponent_team_nickname'] ?? null,
                            'opponent_team_logo'     => $game['opponent_team_logo'] ?? null,
                            'target_score'           => $game['target_score'] ?? null,
                            'opponent_score'         => $game['opponent_score'] ?? null,
                            'game_status'            => $game['game_status'] ?? null,
                            'game_date_day'          => $game['game_date_day'] ?? '',
                            'game_time'              => $game['game_time'] ?? null,
                            'game_timestamp'         => $game['game_timestamp'] ?? null,
                            'home_or_away'           => $game['home_or_away'] ?? 'home',
                            'venue'                  => $game['venue'] ?? null,
                        )
                    );
                }
                $result['game_count'] = $inserted_games;
            } else {
                $result['errors'][] = 'Schedule: ' . ( $games['error'] ?? 'No data returned' );
            }
        }

        $player_lookup = array();

        $existing_player_ids = array();
        if ( $append ) {
            $existing_player_ids = array_flip(
                $wpdb->get_col( $wpdb->prepare(
                    "SELECT player_id FROM {$wpdb->prefix}pp_team_players_archive WHERE archive_id = %d AND team_name = %s",
                    $archive_id, $team_name
                ) ) ?: array()
            );
        }

        if ( $import_roster_stats ) {
            require_once plugin_dir_path( __FILE__ ) . '../roster/class-puck-press-roster-process-usphl-url.php';
            require_once plugin_dir_path( __FILE__ ) . '../class-puck-press-tts-api.php';
            $roster_proc = new Puck_Press_Roster_Process_Usphl_Url( $api_team_id, $season_id );

            $roster_data = $roster_proc->raw_roster_data;
            if ( is_array( $roster_data ) && ! empty( $roster_data ) ) {
                $inserted_roster = 0;
                foreach ( $roster_data as $player ) {
                    $pid = (string) ( $player['player_id'] ?? '' );
                    if ( $pid === '' ) {
                        continue;
                    }
                    $player_lookup[ $pid ] = array(
                        'name'          => $player['name'] ?? null,
                        'pos'           => $player['pos'] ?? null,
                        'headshot_link' => $player['headshot_link'] ?? null,
                    );

                    if ( isset( $existing_player_ids[ $pid ] ) ) {
                        continue;
                    }
                    $inserted_roster++;
                    $wpdb->insert(
                        $wpdb->prefix . 'pp_team_players_archive',
                        array(
                            'archive_id'    => $archive_id,
                            'season_key'    => $season_key,
                            'team_name'     => $team_name,
                            'player_id'     => $pid,
                            'name'          => $player['name'] ?? null,
                            'number'        => $player['number'] ?? null,
                            'pos'           => $player['pos'] ?? null,
                            'shoots'        => $player['shoots'] ?? null,
                            'ht'            => $player['ht'] ?? null,
                            'wt'            => $player['wt'] ?? null,
                            'hometown'      => $player['hometown'] ?? null,
                            'headshot_link' => $player['headshot_link'] ?? null,
                            'last_team'     => $player['last_team'] ?? null,
                            'year_in_school' => $player['year_in_school'] ?? null,
                            'major'         => $player['major'] ?? null,
                            'api_team_id'   => $api_team_id,
                            'api_team_name' => null,
                            'source'        => $source_label,
                        )
                    );
                }
                $result['roster_count'] = $inserted_roster;
            } else {
                $result['errors'][] = 'Roster: No data returned';
            }

            $skater_data = $roster_proc->raw_stats_data;
            if ( is_array( $skater_data ) && ! empty( $skater_data ) ) {
                foreach ( $skater_data as $stat ) {
                    $pid  = (string) ( $stat['player_id'] ?? '' );
                    $bio  = $player_lookup[ $pid ] ?? array();
                    $wpdb->insert(
                        $wpdb->prefix . 'pp_team_player_stats_archive',
                        array(
                            'archive_id'             => $archive_id,
                            'season_key'             => $season_key,
                            'player_id'              => $pid,
                            'name'                   => $bio['name'] ?? null,
                            'pos'                    => $bio['pos'] ?? null,
                            'headshot_link'          => $bio['headshot_link'] ?? null,
                            'team_name'              => $team_name,
                            'api_team_id'            => $api_team_id,
                            'api_team_name'          => null,
                            'source'                 => $source_label,
                            'games_played'           => $stat['games_played'] ?? null,
                            'goals'                  => $stat['goals'] ?? null,
                            'assists'                => $stat['assists'] ?? null,
                            'points'                 => $stat['points'] ?? null,
                            'points_per_game'        => $stat['points_per_game'] ?? null,
                            'power_play_goals'       => $stat['power_play_goals'] ?? null,
                            'short_handed_goals'     => $stat['short_handed_goals'] ?? null,
                            'game_winning_goals'     => $stat['game_winning_goals'] ?? null,
                            'shootout_winning_goals' => $stat['shootout_winning_goals'] ?? null,
                            'penalty_minutes'        => $stat['penalty_minutes'] ?? null,
                            'shooting_percentage'    => $stat['shooting_percentage'] ?? null,
                            'stat_rank'              => null,
                        )
                    );
                }
                $result['skater_count'] = count( $skater_data );
            }

            $goalie_data = $roster_proc->raw_goalie_stats_data;
            if ( is_array( $goalie_data ) && ! empty( $goalie_data ) ) {
                foreach ( $goalie_data as $stat ) {
                    $pid  = (string) ( $stat['player_id'] ?? '' );
                    $bio  = $player_lookup[ $pid ] ?? array();
                    $wpdb->insert(
                        $wpdb->prefix . 'pp_team_player_goalie_stats_archive',
                        array(
                            'archive_id'            => $archive_id,
                            'season_key'            => $season_key,
                            'player_id'             => $pid,
                            'name'                  => $bio['name'] ?? null,
                            'pos'                   => $bio['pos'] ?? null,
                            'headshot_link'         => $bio['headshot_link'] ?? null,
                            'team_name'             => $team_name,
                            'api_team_id'           => $api_team_id,
                            'api_team_name'         => null,
                            'source'                => $source_label,
                            'games_played'          => $stat['games_played'] ?? null,
                            'wins'                  => $stat['wins'] ?? null,
                            'losses'                => $stat['losses'] ?? null,
                            'overtime_losses'       => $stat['overtime_losses'] ?? null,
                            'shootout_losses'       => $stat['shootout_losses'] ?? null,
                            'shootout_wins'         => $stat['shootout_wins'] ?? null,
                            'shots_against'         => $stat['shots_against'] ?? null,
                            'saves'                 => $stat['saves'] ?? null,
                            'save_percentage'       => $stat['save_percentage'] ?? null,
                            'goals_against_average' => $stat['goals_against_average'] ?? null,
                            'goals_against'         => $stat['goals_against'] ?? null,
                            'goals'                 => $stat['goals'] ?? null,
                            'assists'               => $stat['assists'] ?? null,
                            'penalty_minutes'       => $stat['penalty_minutes'] ?? null,
                            'stat_rank'             => null,
                        )
                    );
                }
                $result['goalie_count'] = count( $goalie_data );
            }

            if ( empty( $player_lookup ) && ( $result['skater_count'] > 0 || $result['goalie_count'] > 0 ) ) {
                $this->backfill_roster_bios_from_other_seasons( $archive_id, $season_key );
                $result['errors'][] = 'Roster data unavailable — player names may be missing from stats display.';
            }
        }

        // Synthesize source archive row.
        $wpdb->insert(
            $wpdb->prefix . 'pp_team_sources_archive',
            array(
                'archive_id'        => $archive_id,
                'season_key'        => $season_key,
                'team_id'           => $local_team_id,
                'name'              => 'USPHL API Import',
                'type'              => 'usphlApiImport',
                'season'            => $season_id,
                'source_url_or_path' => $api_team_id,
                'other_data'        => wp_json_encode( array(
                    'season_id'   => $season_id,
                    'import_date' => current_time( 'mysql' ),
                ) ),
            )
        );

        $result['message'] = sprintf(
            'Imported %d games, %d roster, %d skaters, %d goalies for "%s".',
            $result['game_count'],
            $result['roster_count'],
            $result['skater_count'],
            $result['goalie_count'],
            $team_name
        );

        return $result;
    }

    private function backfill_roster_bios_from_other_seasons( int $archive_id, string $season_key ): void {
        global $wpdb;

        $tables = array(
            $wpdb->prefix . 'pp_team_player_stats_archive',
            $wpdb->prefix . 'pp_team_player_goalie_stats_archive',
        );

        foreach ( $tables as $table ) {
            $wpdb->query(
                $wpdb->prepare(
                    "UPDATE {$table} s
                     INNER JOIN {$wpdb->prefix}pp_team_players_archive r
                         ON r.player_id = s.player_id AND r.archive_id != %d
                     SET s.name = COALESCE(s.name, r.name),
                         s.pos = COALESCE(s.pos, r.pos),
                         s.headshot_link = COALESCE(s.headshot_link, r.headshot_link)
                     WHERE s.archive_id = %d AND s.name IS NULL",
                    $archive_id,
                    $archive_id
                )
            );
        }
    }

    public function refresh_all_archives(): array {
        global $wpdb;

        // Read all API import source metadata before wiping anything.
        $sources = $wpdb->get_results(
            "SELECT s.*, a.season_key, COALESCE(a.label, a.season_key) AS label, a.api_label
             FROM {$wpdb->prefix}pp_team_sources_archive s
             INNER JOIN {$wpdb->prefix}pp_archive_seasons a ON a.id = s.archive_id
             WHERE s.type IN ('achaApiImport', 'usphlApiImport')
             ORDER BY a.season_key ASC, s.id ASC",
            ARRAY_A
        ) ?: array();

        if ( empty( $sources ) ) {
            return array( 'refreshed' => 0, 'errors' => array(), 'message' => 'No API-imported archives found.' );
        }

        $log = array();
        $log[] = sprintf( 'Found %d API import source(s) to replay.', count( $sources ) );

        // Wipe ALL archive data tables clean — removes orphaned rows, cruft, and partial imports.
        $wpdb->query( "TRUNCATE TABLE {$wpdb->prefix}pp_team_games_archive" );
        $wpdb->query( "TRUNCATE TABLE {$wpdb->prefix}pp_team_player_stats_archive" );
        $wpdb->query( "TRUNCATE TABLE {$wpdb->prefix}pp_team_player_goalie_stats_archive" );
        $wpdb->query( "TRUNCATE TABLE {$wpdb->prefix}pp_team_players_archive" );
        $wpdb->query( "TRUNCATE TABLE {$wpdb->prefix}pp_team_sources_archive" );
        $wpdb->query( "TRUNCATE TABLE {$wpdb->prefix}pp_archive_seasons" );
        $log[] = 'Truncated all 6 archive tables.';

        // Group by season_key, then re-import each source.
        $by_season = array();
        foreach ( $sources as $src ) {
            $by_season[ $src['season_key'] ][] = $src;
        }
        $log[] = sprintf( 'Grouped into %d season(s).', count( $by_season ) );

        $refreshed = 0;
        $errors    = array();
        $season_num = 0;

        foreach ( $by_season as $season_key => $season_sources ) {
            $season_num++;
            $label     = $season_sources[0]['label'] ?? $season_key;
            $api_label = $season_sources[0]['api_label'] ?? '';
            $log[] = sprintf( '[%d/%d] Season "%s" — %d source(s)', $season_num, count( $by_season ), $season_key, count( $season_sources ) );

            foreach ( $season_sources as $src ) {
                $other    = json_decode( $src['other_data'] ?? '{}', true ) ?: array();
                $src_type = $src['type'];
                $src_desc = $src_type . ' team_id=' . $src['team_id'] . ' api_team=' . $src['source_url_or_path'] . ' season_id=' . $src['season'];
                $log[] = '  Importing: ' . $src_desc;

                if ( $src_type === 'achaApiImport' ) {
                    $result = $this->import_from_acha_api( array(
                        'team_id'          => (int) $src['team_id'],
                        'api_team_id'      => $src['source_url_or_path'],
                        'season_id'        => $src['season'],
                        'division_id'      => $other['division_id'] ?? '-1',
                        'season_key'       => $season_key,
                        'label'            => $label,
                        'api_label'        => $api_label,
                        'schedule'         => true,
                        'roster_and_stats' => true,
                        'append'           => true,
                    ) );
                } elseif ( $src_type === 'usphlApiImport' ) {
                    $result = $this->import_from_usphl_api( array(
                        'team_id'          => (int) $src['team_id'],
                        'api_team_id'      => $src['source_url_or_path'],
                        'season_id'        => $src['season'],
                        'season_key'       => $season_key,
                        'label'            => $label,
                        'api_label'        => $api_label,
                        'schedule'         => true,
                        'roster_and_stats' => true,
                        'append'           => true,
                    ) );
                } else {
                    $log[] = '  Skipped unknown type: ' . $src_type;
                    continue;
                }

                if ( ! empty( $result['success'] ) ) {
                    $refreshed++;
                    $log[] = sprintf( '  OK — %d games, %d roster, %d skaters, %d goalies',
                        $result['game_count'] ?? 0, $result['roster_count'] ?? 0,
                        $result['skater_count'] ?? 0, $result['goalie_count'] ?? 0
                    );
                    if ( ! empty( $result['errors'] ) ) {
                        foreach ( $result['errors'] as $warn ) {
                            $log[] = '  Warning: ' . $warn;
                        }
                    }
                } else {
                    $err_msg = $result['message'] ?? 'Unknown error';
                    $errors[] = $season_key . ': ' . $err_msg;
                    $log[] = '  FAILED — ' . $err_msg;
                }
            }
        }

        $log[] = sprintf( 'Done. Refreshed %d imports across %d seasons.', $refreshed, count( $by_season ) );

        return array(
            'refreshed' => $refreshed,
            'errors'    => $errors,
            'log'       => $log,
            'message'   => sprintf( 'Refreshed %d imports across %d seasons.', $refreshed, count( $by_season ) ),
        );
    }
}
