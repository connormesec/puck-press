<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Puck_Press_Data_Shortcodes {

    private static $last_game_cache  = array();
    private static $next_game_cache  = array();
    private static $top_scorer_cache = array();
    private static $record_cache     = array();
    private static $completed_cache  = array();
    private static $upcoming_cache   = array();
    private static $goalie_cache     = array();

    public function get_last_game( int $schedule_id ): ?array {
        if ( array_key_exists( $schedule_id, self::$last_game_cache ) ) {
            return self::$last_game_cache[ $schedule_id ];
        }
        global $wpdb;
        $row = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM {$wpdb->prefix}pp_schedule_games_display
                  WHERE schedule_id = %d
                    AND target_score IS NOT NULL
                    AND opponent_score IS NOT NULL
                    AND game_status IS NOT NULL
                    AND game_status NOT IN ('', 'null')
                  ORDER BY game_timestamp DESC, id DESC
                  LIMIT 1",
                $schedule_id
            ),
            ARRAY_A
        );
        self::$last_game_cache[ $schedule_id ] = $row;
        return $row;
    }

    public function get_next_game( int $schedule_id ): ?array {
        if ( array_key_exists( $schedule_id, self::$next_game_cache ) ) {
            return self::$next_game_cache[ $schedule_id ];
        }
        global $wpdb;
        $row = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM {$wpdb->prefix}pp_schedule_games_display
                  WHERE schedule_id = %d
                    AND ( target_score IS NULL
                          OR game_status IS NULL
                          OR game_status = '' )
                  ORDER BY
                    CASE WHEN game_timestamp IS NULL THEN 1 ELSE 0 END ASC,
                    game_timestamp ASC,
                    id ASC
                  LIMIT 1",
                $schedule_id
            ),
            ARRAY_A
        );
        self::$next_game_cache[ $schedule_id ] = $row;
        return $row;
    }

    public function get_top_scorer( array $team_ids = array() ): ?array {
        $cache_key = implode( ',', $team_ids ) ?: '__all__';
        if ( array_key_exists( $cache_key, self::$top_scorer_cache ) ) {
            return self::$top_scorer_cache[ $cache_key ];
        }
        require_once plugin_dir_path( __DIR__ ) . 'stats/class-puck-press-stats-wpdb-utils.php';
        $utils   = new Puck_Press_Stats_Wpdb_Utils();
        $skaters = $utils->get_skater_stats( $team_ids );
        $leader  = $skaters[0] ?? null;
        self::$top_scorer_cache[ $cache_key ] = $leader;
        return $leader;
    }

    public function get_top_goalie( array $team_ids = array(), string $sort = 'wins' ): ?array {
        $cache_key = $sort . ':' . ( implode( ',', $team_ids ) ?: '__all__' );
        if ( array_key_exists( $cache_key, self::$goalie_cache ) ) {
            return self::$goalie_cache[ $cache_key ];
        }
        require_once plugin_dir_path( __DIR__ ) . 'stats/class-puck-press-stats-wpdb-utils.php';
        $utils   = new Puck_Press_Stats_Wpdb_Utils();
        $goalies = $utils->get_goalie_stats( $team_ids );

        if ( empty( $goalies ) ) {
            self::$goalie_cache[ $cache_key ] = null;
            return null;
        }

        $asc_fields = array( 'goals_against_average' );
        $ascending  = in_array( $sort, $asc_fields, true );

        usort(
            $goalies,
            function ( $a, $b ) use ( $sort, $ascending ) {
                $av = (float) ( $a[ $sort ] ?? 0 );
                $bv = (float) ( $b[ $sort ] ?? 0 );
                return $ascending ? ( $av <=> $bv ) : ( $bv <=> $av );
            }
        );

        $leader = $goalies[0];
        self::$goalie_cache[ $cache_key ] = $leader;
        return $leader;
    }

    public function get_record( int $schedule_id ): array {
        if ( array_key_exists( $schedule_id, self::$record_cache ) ) {
            return self::$record_cache[ $schedule_id ];
        }
        require_once plugin_dir_path( __DIR__ ) . 'record/class-puck-press-record-wpdb-utils.php';
        $utils  = new Puck_Press_Record_Wpdb_Utils();
        $record = $utils->get_record_stats( $schedule_id );
        self::$record_cache[ $schedule_id ] = $record;
        return $record;
    }

    public function get_completed_games( int $schedule_id ): array {
        if ( array_key_exists( $schedule_id, self::$completed_cache ) ) {
            return self::$completed_cache[ $schedule_id ];
        }
        global $wpdb;
        $table = $wpdb->prefix . 'pp_schedule_games_display';
        $rows  = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM $table
                WHERE schedule_id = %d
                  AND target_score IS NOT NULL
                  AND opponent_score IS NOT NULL
                  AND game_status IS NOT NULL
                  AND game_status NOT IN ('', 'null')
                ORDER BY game_timestamp DESC, id DESC",
                $schedule_id
            ),
            ARRAY_A
        ) ?? array();
        self::$completed_cache[ $schedule_id ] = $rows;
        return $rows;
    }

    public function get_upcoming_games( int $schedule_id ): array {
        if ( array_key_exists( $schedule_id, self::$upcoming_cache ) ) {
            return self::$upcoming_cache[ $schedule_id ];
        }
        global $wpdb;
        $table = $wpdb->prefix . 'pp_schedule_games_display';
        $rows  = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM $table
                WHERE schedule_id = %d
                  AND (target_score IS NULL OR game_status IS NULL OR game_status = '')
                ORDER BY
                  CASE WHEN game_timestamp IS NULL THEN 1 ELSE 0 END ASC,
                  game_timestamp ASC, id ASC",
                $schedule_id
            ),
            ARRAY_A
        ) ?? array();
        self::$upcoming_cache[ $schedule_id ] = $rows;
        return $rows;
    }

    public function get_streak( int $schedule_id ): string {
        $games = $this->get_completed_games( $schedule_id );
        if ( empty( $games ) ) {
            return '';
        }

        $streak_type  = '';
        $streak_count = 0;

        foreach ( $games as $game ) {
            $result = $this->derive_result( $game );
            if ( $streak_type === '' ) {
                $streak_type  = $result;
                $streak_count = 1;
            } elseif ( $result === $streak_type ) {
                ++$streak_count;
            } else {
                break;
            }
        }

        return $streak_type . $streak_count;
    }

    public function get_player_by_lookup( string $lookup, array $team_ids = array() ): ?array {
        global $wpdb;
        $d = $wpdb->prefix . 'pp_team_players_display';
        $t = $wpdb->prefix . 'pp_teams';

        $team_where = '';
        $params     = array();

        if ( ! empty( $team_ids ) ) {
            $placeholders = implode( ', ', array_fill( 0, count( $team_ids ), '%d' ) );
            $team_where   = "AND d.team_id IN ($placeholders)";
            $params       = $team_ids;
        }

        if ( is_numeric( $lookup ) ) {
            $sql    = "SELECT d.*, t.name AS team_name FROM $d d JOIN $t t ON t.id = d.team_id WHERE d.number = %s $team_where ORDER BY d.team_id ASC LIMIT 1";
            $params = array_merge( array( $lookup ), $params );
        } else {
            $sql    = "SELECT d.*, t.name AS team_name FROM $d d JOIN $t t ON t.id = d.team_id WHERE d.name LIKE %s $team_where ORDER BY d.team_id ASC LIMIT 1";
            $params = array_merge( array( '%' . $wpdb->esc_like( $lookup ) . '%' ), $params );
        }

        return $wpdb->get_row( $wpdb->prepare( $sql, $params ), ARRAY_A );
    }

    public function extract_record_field( array $record, string $field ): string {
        $w   = (int) ( $record['wins'] ?? 0 );
        $l   = (int) ( $record['losses'] ?? 0 );
        $otl = (int) ( $record['otl'] ?? 0 );
        $t   = (int) ( $record['ties'] ?? 0 );

        switch ( $field ) {
            case 'record':
                $str = "{$w}-{$l}-{$otl}";
                if ( $t > 0 ) {
                    $str .= "-{$t}T";
                }
                return $str;
            case 'home_record':
                return (int) ( $record['home_wins'] ?? 0 ) . '-' . (int) ( $record['home_losses'] ?? 0 ) . '-' . (int) ( $record['home_otl'] ?? 0 );
            case 'away_record':
                return (int) ( $record['away_wins'] ?? 0 ) . '-' . (int) ( $record['away_losses'] ?? 0 ) . '-' . (int) ( $record['away_otl'] ?? 0 );
            case 'diff':
                $d = (int) ( $record['gf'] ?? 0 ) - (int) ( $record['ga'] ?? 0 );
                return ( $d >= 0 ? '+' : '' ) . $d;
            case 'gp':
                return (string) ( $w + $l + $otl + $t );
            case 'win_pct':
                $gp = $w + $l + $otl + $t;
                if ( $gp === 0 ) {
                    return '.000';
                }
                $pct = ( $w * 2 + $otl + $t ) / ( $gp * 2 );
                return '.' . str_pad( (string) round( $pct * 1000 ), 3, '0', STR_PAD_LEFT );
            case 'points':
                return (string) ( $w * 2 + $otl + $t );
            default:
                return $this->clean( $record[ $field ] ?? '' );
        }
    }

    public function resolve_schedule_id( string $slug_or_id ): int {
        if ( is_numeric( $slug_or_id ) && (int) $slug_or_id > 0 ) {
            return (int) $slug_or_id;
        }
        require_once plugin_dir_path( __DIR__ ) . 'class-puck-press-group-resolver.php';
        return Puck_Press_Group_Resolver::resolve( $slug_or_id, 'pp_schedules' );
    }

    private function clean( $val ): string {
        if ( $val === null || $val === 'null' || $val === 'NULL' ) {
            return '';
        }
        return (string) $val;
    }

    public function extract_game_field( array $game, string $field, string $date_format = 'M j, Y' ): string {
        switch ( $field ) {
            case 'date':
                $ts = $this->clean( $game['game_timestamp'] ?? '' );
                if ( $ts !== '' ) {
                    return date( $date_format, strtotime( $ts ) );
                }
                return $this->clean( $game['game_date_day'] ?? '' );
            case 'time':
                return $this->clean( $game['game_time'] ?? '' );
            case 'opponent':
                return $this->clean( $game['opponent_team_name'] ?? '' );
            case 'opponent_logo':
                return $this->clean( $game['opponent_team_logo'] ?? '' );
            case 'target_team':
                return $this->clean( $game['target_team_name'] ?? '' );
            case 'target_logo':
                return $this->clean( $game['target_team_logo'] ?? '' );
            case 'status':
                return $this->clean( $game['game_status'] ?? '' );
            case 'score':
                $ts = $this->clean( $game['target_score'] ?? '' );
                $os = $this->clean( $game['opponent_score'] ?? '' );
                return ( $ts !== '' && $os !== '' ) ? "{$ts} – {$os}" : '';
            case 'venue':
                return $this->clean( $game['venue'] ?? '' );
            case 'location':
                return ( $game['home_or_away'] ?? '' ) === 'home' ? 'Home' : 'Away';
            case 'result':
                return $this->derive_result( $game );
            case 'ticket_link':
                return $this->clean( $game['promo_ticket_link'] ?? '' );
            case 'promo_header':
                return $this->clean( $game['promo_header'] ?? '' );
            default:
                return $this->clean( $game[ $field ] ?? '' );
        }
    }

    public function derive_result( array $game ): string {
        $ts     = (int) ( $game['target_score'] ?? 0 );
        $os     = (int) ( $game['opponent_score'] ?? 0 );
        $status = strtoupper( trim( $game['game_status'] ?? '' ) );
        $tokens = preg_split( '/[\s\/\-_]+/', $status );
        $is_ot  = in_array( 'OT', $tokens, true ) || in_array( 'SO', $tokens, true );
        if ( $ts > $os ) {
            return 'W';
        }
        if ( $ts < $os ) {
            return $is_ot ? 'OTL' : 'L';
        }
        if ( $is_ot && preg_match( '/^W\b/i', $status ) ) {
            return 'W';
        }
        return $is_ot ? 'OTL' : 'T';
    }
}
