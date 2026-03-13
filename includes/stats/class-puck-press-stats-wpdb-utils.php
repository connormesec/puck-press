<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Puck_Press_Stats_Wpdb_Utils {

	/**
	 * Default column visibility settings.
	 */
	public static function get_default_column_settings(): array {
		return array(
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
		);
	}

	/**
	 * Get skater stats joined with display roster data.
	 * When $roster_id > 0, filters to that group only.
	 * Returns one row per player × source (multiple rows per player when multiple sources exist).
	 */
	public function get_skater_stats( int $roster_id = 0 ): array {
		global $wpdb;

		$roster_table  = $wpdb->prefix . 'pp_roster_for_display';
		$stats_table   = $wpdb->prefix . 'pp_roster_stats';
		$rosters_table = $wpdb->prefix . 'pp_rosters';

		$where = $roster_id > 0
			? $wpdb->prepare( 'WHERE s.roster_id = %d', $roster_id )
			: '';

		$results = $wpdb->get_results(
			"SELECT
                d.name,
                d.pos,
                d.headshot_link,
                d.player_id,
                d.team_id,
                d.team_name,
                s.roster_id,
                s.source,
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
                s.shooting_percentage,
                r.name AS group_name
            FROM {$roster_table} d
            INNER JOIN {$stats_table} s
                ON d.player_id = s.player_id
                AND d.roster_id = s.roster_id
            LEFT JOIN {$rosters_table} r ON r.id = s.roster_id
            {$where}
            ORDER BY COALESCE(s.stat_rank, 9999) ASC, s.points DESC, s.goals DESC",
			ARRAY_A
		);

		return $results ?: array();
	}

	/**
	 * Get goalie stats joined with display roster data.
	 * When $roster_id > 0, filters to that group only.
	 * Returns one row per player × source.
	 */
	public function get_goalie_stats( int $roster_id = 0 ): array {
		global $wpdb;

		$roster_table  = $wpdb->prefix . 'pp_roster_for_display';
		$goalie_table  = $wpdb->prefix . 'pp_roster_goalie_stats';
		$rosters_table = $wpdb->prefix . 'pp_rosters';

		$where = $roster_id > 0
			? $wpdb->prepare( 'WHERE g.roster_id = %d', $roster_id )
			: '';

		$results = $wpdb->get_results(
			"SELECT
                d.name,
                d.pos,
                d.headshot_link,
                d.player_id,
                d.team_id,
                d.team_name,
                g.roster_id,
                g.source,
                g.stat_rank,
                g.games_played,
                g.wins,
                g.losses,
                g.overtime_losses,
                g.goals_against_average,
                g.save_percentage,
                g.shots_against,
                g.saves,
                r.name AS group_name
            FROM {$roster_table} d
            INNER JOIN {$goalie_table} g
                ON d.player_id = g.player_id
                AND d.roster_id = g.roster_id
            LEFT JOIN {$rosters_table} r ON r.id = g.roster_id
            {$where}
            ORDER BY g.games_played DESC, COALESCE(g.stat_rank, 9999) ASC, g.wins DESC",
			ARRAY_A
		);

		return $results ?: array();
	}

	/**
	 * Get archived skater stats for a given archive_key.
	 * Archive rows are denormalized and self-contained.
	 */
	public function get_archive_skater_stats( string $archive_key ): array {
		global $wpdb;

		$table   = $wpdb->prefix . 'pp_roster_stats_archive';
		$results = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT name, pos, headshot_link, player_id, team_id, team_name, roster_id, source, stat_rank,
                    games_played, goals, assists, points, penalty_minutes, power_play_goals,
                    short_handed_goals, game_winning_goals, points_per_game, shooting_percentage,
                    '' AS group_name
                FROM {$table}
                WHERE archive_key = %s
                ORDER BY COALESCE(stat_rank, 9999) ASC, points DESC, goals DESC",
				$archive_key
			),
			ARRAY_A
		);

		return $results ?: array();
	}

	/**
	 * Get archived goalie stats for a given archive_key.
	 */
	public function get_archive_goalie_stats( string $archive_key ): array {
		global $wpdb;

		$table   = $wpdb->prefix . 'pp_roster_goalie_stats_archive';
		$results = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT name, pos, headshot_link, player_id, team_id, team_name, roster_id, source, stat_rank,
                    games_played, wins, losses, overtime_losses, goals_against_average,
                    save_percentage, shots_against, saves,
                    '' AS group_name
                FROM {$table}
                WHERE archive_key = %s
                ORDER BY games_played DESC, COALESCE(stat_rank, 9999) ASC, wins DESC",
				$archive_key
			),
			ARRAY_A
		);

		return $results ?: array();
	}

	/**
	 * Returns list of all archived seasons ordered newest first.
	 */
	public function get_archive_list(): array {
		global $wpdb;

		$table   = $wpdb->prefix . 'pp_roster_archives';
		$results = $wpdb->get_results(
			"SELECT archive_key, season FROM {$table} ORDER BY created_at DESC",
			ARRAY_A
		);

		return $results ?: array();
	}

	/**
	 * Build the full data array expected by the stats template's render() method.
	 * $roster_id = 0 means all groups (no WHERE filter).
	 */
	public function get_stats_data( int $roster_id = 0 ): array {
		$defaults = self::get_default_column_settings();
		$saved    = get_option( 'pp_stats_column_settings', array() );
		$col      = array_merge( $defaults, is_array( $saved ) ? $saved : array() );

		$skaters = $this->get_skater_stats( $roster_id );
		$goalies = $this->get_goalie_stats( $roster_id );

		$team_names = array_values( array_filter( array_unique( array_merge(
			array_column( $skaters, 'team_name' ),
			array_column( $goalies, 'team_name' )
		) ) ) );

		return array(
			'skaters'              => $skaters,
			'goalies'              => $goalies,
			'column_settings'      => $col,
			'team_names'           => $team_names,
			'archives'             => $this->get_archive_list(),
			'current_season_label' => get_option( 'puck_press_current_season_label', '' ),
			'roster_id'            => $roster_id,
		);
	}
}
