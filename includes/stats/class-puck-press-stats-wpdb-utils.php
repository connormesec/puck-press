<?php

if (!defined('ABSPATH')) {
    exit;
}

class Puck_Press_Stats_Wpdb_Utils
{
    /**
     * Default column visibility settings.
     */
    public static function get_default_column_settings(): array
    {
        return [
            'show_pim'          => 1,
            'show_ppg'          => 1,
            'show_shg'          => 1,
            'show_gwg'          => 1,
            'show_pts_per_game' => 0,
            'show_sh_pct'       => 0,
            'show_goalie_otl'   => 1,
            'show_goalie_gaa'   => 1,
            'show_goalie_svpct' => 1,
            'show_goalie_sa'    => 1,
            'show_goalie_saves' => 0,
        ];
    }

    /**
     * Get skater stats joined with display roster data.
     * Returns rows ordered by rank ASC (nulls last), then points DESC, goals DESC.
     */
    public function get_skater_stats(): array
    {
        global $wpdb;

        $roster_table = $wpdb->prefix . 'pp_roster_for_display';
        $stats_table  = $wpdb->prefix . 'pp_roster_stats';

        $results = $wpdb->get_results(
            "SELECT
                d.name,
                d.pos,
                d.headshot_link,
                d.player_id,
                s.stat_rank,
                s.games_played,
                s.goals,
                s.assists,
                s.points,
                s.penalty_minutes,
                s.power_play_goals,
                s.short_handed_goals,
                s.game_winning_goals,
                s.points_per_game,
                s.shooting_percentage
            FROM {$roster_table} d
            INNER JOIN {$stats_table} s ON d.player_id = s.player_id
            ORDER BY COALESCE(s.stat_rank, 9999) ASC, s.points DESC, s.goals DESC",
            ARRAY_A
        );

        return $results ?: [];
    }

    /**
     * Get goalie stats joined with display roster data.
     * Returns rows ordered by games_played DESC, then rank ASC (nulls last), then wins DESC.
     */
    public function get_goalie_stats(): array
    {
        global $wpdb;

        $roster_table = $wpdb->prefix . 'pp_roster_for_display';
        $goalie_table = $wpdb->prefix . 'pp_roster_goalie_stats';

        $results = $wpdb->get_results(
            "SELECT
                d.name,
                d.pos,
                d.headshot_link,
                d.player_id,
                g.stat_rank,
                g.games_played,
                g.wins,
                g.losses,
                g.overtime_losses,
                g.goals_against_average,
                g.save_percentage,
                g.shots_against,
                g.saves
            FROM {$roster_table} d
            INNER JOIN {$goalie_table} g ON d.player_id = g.player_id
            ORDER BY g.games_played DESC, COALESCE(g.stat_rank, 9999) ASC, g.wins DESC",
            ARRAY_A
        );

        return $results ?: [];
    }

    /**
     * Build the full data array expected by the stats template's render() method.
     */
    public function get_stats_data(): array
    {
        $defaults = self::get_default_column_settings();
        $saved    = get_option('pp_stats_column_settings', []);
        $col      = array_merge($defaults, is_array($saved) ? $saved : []);

        return [
            'skaters'         => $this->get_skater_stats(),
            'goalies'         => $this->get_goalie_stats(),
            'column_settings' => $col,
        ];
    }
}
