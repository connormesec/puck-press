<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Puck_Press_Schedule_Materializer {

    private Puck_Press_Schedules_Wpdb_Utils $schedules_utils;

    public function __construct() {
        require_once plugin_dir_path( __FILE__ ) . 'class-puck-press-schedules-wpdb-utils.php';
        $this->schedules_utils = new Puck_Press_Schedules_Wpdb_Utils();
    }

    public function materialize_schedule( int $schedule_id ): void {
        global $wpdb;

        $team_ids = $this->schedules_utils->get_schedule_team_ids( $schedule_id );
        $this->schedules_utils->clear_schedule_games_display( $schedule_id );

        if ( empty( $team_ids ) ) {
            return;
        }

        $placeholders = implode( ', ', array_fill( 0, count( $team_ids ), '%d' ) );

        $wpdb->query(
            $wpdb->prepare(
                "INSERT INTO {$wpdb->prefix}pp_schedule_games_display
                    (schedule_id, team_id, source, source_type, game_id,
                     target_team_id, target_team_name, target_team_nickname, target_team_logo,
                     opponent_team_id, opponent_team_name, opponent_team_nickname, opponent_team_logo,
                     target_score, opponent_score, game_status,
                     promo_header, promo_text, promo_img_url, promo_ticket_link, post_link,
                     game_date_day, game_time, game_timestamp, home_or_away, venue)
                SELECT %d, t.team_id, t.source, t.source_type, t.game_id,
                       t.target_team_id, t.target_team_name, t.target_team_nickname, t.target_team_logo,
                       t.opponent_team_id, t.opponent_team_name, t.opponent_team_nickname, t.opponent_team_logo,
                       t.target_score, t.opponent_score, t.game_status,
                       t.promo_header, t.promo_text, t.promo_img_url, t.promo_ticket_link, t.post_link,
                       t.game_date_day, t.game_time, t.game_timestamp, t.home_or_away, t.venue
                FROM {$wpdb->prefix}pp_team_games_display t
                INNER JOIN (
                    SELECT game_id,
                           COALESCE(
                               MIN(CASE WHEN home_or_away = 'home' THEN id END),
                               MIN(id)
                           ) AS preferred_id
                    FROM {$wpdb->prefix}pp_team_games_display
                    WHERE team_id IN ($placeholders)
                    GROUP BY game_id
                ) pref ON t.id = pref.preferred_id
                WHERE t.team_id IN ($placeholders)",
                array_merge(
                    array( $schedule_id ),
                    $team_ids,
                    $team_ids
                )
            )
        );
    }

    public function materialize_all_schedules(): void {
        $schedules = $this->schedules_utils->get_all_schedules();
        foreach ( $schedules as $schedule ) {
            $this->materialize_schedule( (int) $schedule['id'] );
        }
    }

    public function get_schedule_ids_for_team( int $team_id ): array {
        return $this->schedules_utils->get_schedule_ids_for_team( $team_id );
    }
}
