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
			'show_team'         => 1,
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

	public function get_skater_stats( array $teams = array() ): array {
		global $wpdb;

		$stats_table  = $wpdb->prefix . 'pp_team_player_stats';
		$roster_table = $wpdb->prefix . 'pp_team_players_display';
		$teams_table  = $wpdb->prefix . 'pp_teams';

		$where_parts = array();
		if ( ! empty( $teams ) ) {
			$placeholders  = implode( ', ', array_fill( 0, count( $teams ), '%d' ) );
			$where_parts[] = $wpdb->prepare( "s.team_id IN ($placeholders)", ...$teams );
		}
		$where = $where_parts ? 'WHERE ' . implode( ' AND ', $where_parts ) : '';

		$results = $wpdb->get_results(
			"SELECT
                MAX(d.name) AS name,
                MAX(d.pos) AS pos,
                MAX(d.headshot_link) AS headshot_link,
                MAX(d.number) AS number,
                s.player_id,
                s.team_id,
                COALESCE(MAX(d.api_team_name), MAX(t.name)) AS team_name,
                MIN(s.source) AS source,
                MIN(s.stat_rank) AS stat_rank,
                SUM(s.games_played) AS games_played,
                SUM(s.goals) AS goals,
                SUM(s.assists) AS assists,
                SUM(s.points) AS points,
                SUM(s.penalty_minutes) AS penalty_minutes,
                SUM(s.power_play_goals) AS power_play_goals,
                SUM(s.short_handed_goals) AS short_handed_goals,
                SUM(s.game_winning_goals) AS game_winning_goals,
                ROUND(SUM(s.points) / NULLIF(SUM(s.games_played), 0), 2) AS points_per_game,
                AVG(s.shooting_percentage) AS shooting_percentage,
                '' AS group_name
            FROM {$stats_table} s
            INNER JOIN {$roster_table} d ON d.player_id = s.player_id AND d.team_id = s.team_id
            INNER JOIN {$teams_table} t ON t.id = s.team_id
            {$where}
            GROUP BY s.player_id, s.team_id
            ORDER BY COALESCE(MIN(s.stat_rank), 9999) ASC, SUM(s.points) DESC, SUM(s.goals) DESC",
			ARRAY_A
		);

		return $results ?: array();
	}

	public function get_skater_stats_per_source( array $teams = array() ): array {
		global $wpdb;

		$stats_table  = $wpdb->prefix . 'pp_team_player_stats';
		$roster_table = $wpdb->prefix . 'pp_team_players_display';
		$teams_table  = $wpdb->prefix . 'pp_teams';

		$where_parts = array();
		if ( ! empty( $teams ) ) {
			$placeholders  = implode( ', ', array_fill( 0, count( $teams ), '%d' ) );
			$where_parts[] = $wpdb->prepare( "s.team_id IN ($placeholders)", ...$teams );
		}
		$where = $where_parts ? 'WHERE ' . implode( ' AND ', $where_parts ) : '';

		$results = $wpdb->get_results(
			"SELECT
                MAX(d.name) AS name,
                MAX(d.pos) AS pos,
                MAX(d.headshot_link) AS headshot_link,
                s.player_id,
                s.team_id,
                COALESCE(MAX(d.api_team_name), MAX(t.name)) AS team_name,
                s.source,
                MIN(s.stat_rank) AS stat_rank,
                SUM(s.games_played) AS games_played,
                SUM(s.goals) AS goals,
                SUM(s.assists) AS assists,
                SUM(s.points) AS points,
                SUM(s.penalty_minutes) AS penalty_minutes,
                SUM(s.power_play_goals) AS power_play_goals,
                SUM(s.short_handed_goals) AS short_handed_goals,
                SUM(s.game_winning_goals) AS game_winning_goals,
                ROUND(SUM(s.points) / NULLIF(SUM(s.games_played), 0), 2) AS points_per_game,
                AVG(s.shooting_percentage) AS shooting_percentage,
                '' AS group_name
            FROM {$stats_table} s
            INNER JOIN {$roster_table} d ON d.player_id = s.player_id AND d.team_id = s.team_id
            INNER JOIN {$teams_table} t ON t.id = s.team_id
            {$where}
            GROUP BY s.player_id, s.team_id, s.source
            ORDER BY COALESCE(MIN(s.stat_rank), 9999) ASC, SUM(s.points) DESC, SUM(s.goals) DESC",
			ARRAY_A
		);

		return $results ?: array();
	}

	public function get_goalie_stats( array $teams = array() ): array {
		global $wpdb;

		$goalie_table = $wpdb->prefix . 'pp_team_player_goalie_stats';
		$roster_table = $wpdb->prefix . 'pp_team_players_display';
		$teams_table  = $wpdb->prefix . 'pp_teams';

		$where_parts = array();
		if ( ! empty( $teams ) ) {
			$placeholders  = implode( ', ', array_fill( 0, count( $teams ), '%d' ) );
			$where_parts[] = $wpdb->prepare( "g.team_id IN ($placeholders)", ...$teams );
		}
		$where = $where_parts ? 'WHERE ' . implode( ' AND ', $where_parts ) : '';

		$results = $wpdb->get_results(
			"SELECT
                MAX(d.name) AS name,
                MAX(d.pos) AS pos,
                MAX(d.headshot_link) AS headshot_link,
                MAX(d.number) AS number,
                g.player_id,
                g.team_id,
                COALESCE(MAX(d.api_team_name), MAX(t.name)) AS team_name,
                MIN(g.source) AS source,
                MIN(g.stat_rank) AS stat_rank,
                SUM(g.games_played) AS games_played,
                SUM(g.wins) AS wins,
                SUM(g.losses) AS losses,
                SUM(g.overtime_losses) AS overtime_losses,
                COALESCE(
                    ROUND(SUM(g.saves) / NULLIF(SUM(g.shots_against), 0), 3),
                    ROUND(SUM(g.save_percentage * g.games_played) / NULLIF(SUM(g.games_played), 0), 3)
                ) AS save_percentage,
                ROUND(SUM(g.goals_against_average * g.games_played) / NULLIF(SUM(g.games_played), 0), 2) AS goals_against_average,
                SUM(g.shots_against) AS shots_against,
                SUM(g.saves) AS saves,
                '' AS group_name
            FROM {$goalie_table} g
            INNER JOIN {$roster_table} d ON d.player_id = g.player_id AND d.team_id = g.team_id
            INNER JOIN {$teams_table} t ON t.id = g.team_id
            {$where}
            GROUP BY g.player_id, g.team_id
            ORDER BY SUM(g.games_played) DESC, COALESCE(MIN(g.stat_rank), 9999) ASC, SUM(g.wins) DESC",
			ARRAY_A
		);

		return $results ?: array();
	}

	public function get_goalie_stats_per_source( array $teams = array() ): array {
		global $wpdb;

		$goalie_table = $wpdb->prefix . 'pp_team_player_goalie_stats';
		$roster_table = $wpdb->prefix . 'pp_team_players_display';
		$teams_table  = $wpdb->prefix . 'pp_teams';

		$where_parts = array();
		if ( ! empty( $teams ) ) {
			$placeholders  = implode( ', ', array_fill( 0, count( $teams ), '%d' ) );
			$where_parts[] = $wpdb->prepare( "g.team_id IN ($placeholders)", ...$teams );
		}
		$where = $where_parts ? 'WHERE ' . implode( ' AND ', $where_parts ) : '';

		$results = $wpdb->get_results(
			"SELECT
                MAX(d.name) AS name,
                MAX(d.pos) AS pos,
                MAX(d.headshot_link) AS headshot_link,
                g.player_id,
                g.team_id,
                COALESCE(MAX(d.api_team_name), MAX(t.name)) AS team_name,
                g.source,
                MIN(g.stat_rank) AS stat_rank,
                SUM(g.games_played) AS games_played,
                SUM(g.wins) AS wins,
                SUM(g.losses) AS losses,
                SUM(g.overtime_losses) AS overtime_losses,
                COALESCE(
                    ROUND(SUM(g.saves) / NULLIF(SUM(g.shots_against), 0), 3),
                    ROUND(SUM(g.save_percentage * g.games_played) / NULLIF(SUM(g.games_played), 0), 3)
                ) AS save_percentage,
                ROUND(SUM(g.goals_against_average * g.games_played) / NULLIF(SUM(g.games_played), 0), 2) AS goals_against_average,
                SUM(g.shots_against) AS shots_against,
                SUM(g.saves) AS saves,
                '' AS group_name
            FROM {$goalie_table} g
            INNER JOIN {$roster_table} d ON d.player_id = g.player_id AND d.team_id = g.team_id
            INNER JOIN {$teams_table} t ON t.id = g.team_id
            {$where}
            GROUP BY g.player_id, g.team_id, g.source
            ORDER BY SUM(g.games_played) DESC, COALESCE(MIN(g.stat_rank), 9999) ASC, SUM(g.wins) DESC",
			ARRAY_A
		);

		return $results ?: array();
	}

	public function get_distinct_sources( array $teams = array() ): array {
		global $wpdb;

		$stats_table = $wpdb->prefix . 'pp_team_player_stats';
		$teams_table = $wpdb->prefix . 'pp_teams';

		$where_parts = array();
		if ( ! empty( $teams ) ) {
			$placeholders  = implode( ', ', array_fill( 0, count( $teams ), '%d' ) );
			$where_parts[] = $wpdb->prepare( "s.team_id IN ($placeholders)", ...$teams );
		}
		$where = $where_parts ? 'WHERE ' . implode( ' AND ', $where_parts ) : '';

		$join = '';

		$results = $wpdb->get_results(
			"SELECT DISTINCT s.source FROM {$stats_table} s {$join} {$where} ORDER BY s.source ASC",
			ARRAY_A
		);

		if ( ! $results ) {
			return array();
		}

		return array_values( array_filter( array_column( $results, 'source' ) ) );
	}

	public function get_archive_skater_stats( string $archive_key, array $teams = array() ): array {
		global $wpdb;

		$table = $wpdb->prefix . 'pp_team_player_stats_archive';

		$where_parts = array( $wpdb->prepare( 's.season_key = %s', $archive_key ) );
		if ( ! empty( $teams ) ) {
			$placeholders  = implode( ', ', array_fill( 0, count( $teams ), '%d' ) );
			$where_parts[] = $wpdb->prepare( "s.team_id IN ($placeholders)", ...$teams );
		}
		$where = 'WHERE ' . implode( ' AND ', $where_parts );

		$results = $wpdb->get_results(
			"SELECT MAX(s.name) AS name, MAX(s.pos) AS pos, MAX(s.headshot_link) AS headshot_link,
                s.player_id, COALESCE(MAX(s.api_team_name), MAX(s.team_name)) AS team_name,
                s.source, MIN(s.stat_rank) AS stat_rank,
                SUM(s.games_played) AS games_played,
                SUM(s.goals) AS goals,
                SUM(s.assists) AS assists,
                SUM(s.points) AS points,
                SUM(s.penalty_minutes) AS penalty_minutes,
                SUM(s.power_play_goals) AS power_play_goals,
                SUM(s.short_handed_goals) AS short_handed_goals,
                SUM(s.game_winning_goals) AS game_winning_goals,
                ROUND(SUM(s.points) / NULLIF(SUM(s.games_played), 0), 2) AS points_per_game,
                AVG(s.shooting_percentage) AS shooting_percentage,
                '' AS group_name
            FROM {$table} s
            {$where}
            GROUP BY s.player_id, s.source
            ORDER BY COALESCE(MIN(s.stat_rank), 9999) ASC, SUM(s.points) DESC, SUM(s.goals) DESC",
			ARRAY_A
		);

		return $results ?: array();
	}

	public function get_archive_skater_stats_aggregated( string $archive_key, array $teams = array() ): array {
		global $wpdb;

		$table = $wpdb->prefix . 'pp_team_player_stats_archive';

		$where_parts = array( $wpdb->prepare( 's.season_key = %s', $archive_key ) );
		if ( ! empty( $teams ) ) {
			$placeholders  = implode( ', ', array_fill( 0, count( $teams ), '%d' ) );
			$where_parts[] = $wpdb->prepare( "s.team_id IN ($placeholders)", ...$teams );
		}
		$where = 'WHERE ' . implode( ' AND ', $where_parts );

		$results = $wpdb->get_results(
			"SELECT MAX(s.name) AS name, MAX(s.pos) AS pos, MAX(s.headshot_link) AS headshot_link,
                s.player_id, COALESCE(MAX(s.api_team_name), MAX(s.team_name)) AS team_name,
                MIN(s.source) AS source, MIN(s.stat_rank) AS stat_rank,
                SUM(s.games_played) AS games_played,
                SUM(s.goals) AS goals,
                SUM(s.assists) AS assists,
                SUM(s.points) AS points,
                SUM(s.penalty_minutes) AS penalty_minutes,
                SUM(s.power_play_goals) AS power_play_goals,
                SUM(s.short_handed_goals) AS short_handed_goals,
                SUM(s.game_winning_goals) AS game_winning_goals,
                ROUND(SUM(s.points) / NULLIF(SUM(s.games_played), 0), 2) AS points_per_game,
                AVG(s.shooting_percentage) AS shooting_percentage,
                '' AS group_name
            FROM {$table} s
            {$where}
            GROUP BY s.player_id
            ORDER BY COALESCE(MIN(s.stat_rank), 9999) ASC, SUM(s.points) DESC, SUM(s.goals) DESC",
			ARRAY_A
		);

		return $results ?: array();
	}

	public function get_archive_goalie_stats( string $archive_key, array $teams = array() ): array {
		global $wpdb;

		$table = $wpdb->prefix . 'pp_team_player_goalie_stats_archive';

		$where_parts = array( $wpdb->prepare( 'g.season_key = %s', $archive_key ) );
		if ( ! empty( $teams ) ) {
			$placeholders  = implode( ', ', array_fill( 0, count( $teams ), '%d' ) );
			$where_parts[] = $wpdb->prepare( "g.team_id IN ($placeholders)", ...$teams );
		}
		$where = 'WHERE ' . implode( ' AND ', $where_parts );

		$results = $wpdb->get_results(
			"SELECT MAX(g.name) AS name, MAX(g.pos) AS pos, MAX(g.headshot_link) AS headshot_link,
                g.player_id, COALESCE(MAX(g.api_team_name), MAX(g.team_name)) AS team_name,
                g.source, MIN(g.stat_rank) AS stat_rank,
                SUM(g.games_played) AS games_played,
                SUM(g.wins) AS wins,
                SUM(g.losses) AS losses,
                SUM(g.overtime_losses) AS overtime_losses,
                SUM(g.shots_against) AS shots_against,
                SUM(g.saves) AS saves,
                COALESCE(
                    ROUND(SUM(g.saves) / NULLIF(SUM(g.shots_against), 0), 3),
                    ROUND(SUM(g.save_percentage * g.games_played) / NULLIF(SUM(g.games_played), 0), 3)
                ) AS save_percentage,
                ROUND(SUM(g.goals_against_average * g.games_played) / NULLIF(SUM(g.games_played), 0), 2) AS goals_against_average,
                '' AS group_name
            FROM {$table} g
            {$where}
            GROUP BY g.player_id, g.source
            ORDER BY SUM(g.games_played) DESC, COALESCE(MIN(g.stat_rank), 9999) ASC, SUM(g.wins) DESC",
			ARRAY_A
		);

		return $results ?: array();
	}

	public function get_archive_goalie_stats_aggregated( string $archive_key, array $teams = array() ): array {
		global $wpdb;

		$table = $wpdb->prefix . 'pp_team_player_goalie_stats_archive';

		$where_parts = array( $wpdb->prepare( 'g.season_key = %s', $archive_key ) );
		if ( ! empty( $teams ) ) {
			$placeholders  = implode( ', ', array_fill( 0, count( $teams ), '%d' ) );
			$where_parts[] = $wpdb->prepare( "g.team_id IN ($placeholders)", ...$teams );
		}
		$where = 'WHERE ' . implode( ' AND ', $where_parts );

		$results = $wpdb->get_results(
			"SELECT MAX(g.name) AS name, MAX(g.pos) AS pos, MAX(g.headshot_link) AS headshot_link,
                g.player_id, COALESCE(MAX(g.api_team_name), MAX(g.team_name)) AS team_name,
                MIN(g.source) AS source, MIN(g.stat_rank) AS stat_rank,
                SUM(g.games_played) AS games_played,
                SUM(g.wins) AS wins,
                SUM(g.losses) AS losses,
                SUM(g.overtime_losses) AS overtime_losses,
                SUM(g.shots_against) AS shots_against,
                SUM(g.saves) AS saves,
                COALESCE(
                    ROUND(SUM(g.saves) / NULLIF(SUM(g.shots_against), 0), 3),
                    ROUND(SUM(g.save_percentage * g.games_played) / NULLIF(SUM(g.games_played), 0), 3)
                ) AS save_percentage,
                ROUND(SUM(g.goals_against_average * g.games_played) / NULLIF(SUM(g.games_played), 0), 2) AS goals_against_average,
                '' AS group_name
            FROM {$table} g
            {$where}
            GROUP BY g.player_id
            ORDER BY SUM(g.games_played) DESC, COALESCE(MIN(g.stat_rank), 9999) ASC, SUM(g.wins) DESC",
			ARRAY_A
		);

		return $results ?: array();
	}

	public function get_archive_distinct_sources( string $archive_key, array $teams = array() ): array {
		global $wpdb;

		$table = $wpdb->prefix . 'pp_team_player_stats_archive';

		$where_parts = array( $wpdb->prepare( 's.season_key = %s', $archive_key ) );
		if ( ! empty( $teams ) ) {
			$placeholders  = implode( ', ', array_fill( 0, count( $teams ), '%d' ) );
			$where_parts[] = $wpdb->prepare( "s.team_id IN ($placeholders)", ...$teams );
		}
		$where = 'WHERE ' . implode( ' AND ', $where_parts );

		$results = $wpdb->get_results(
			"SELECT DISTINCT s.source FROM {$table} s {$where} ORDER BY s.source ASC",
			ARRAY_A
		);

		if ( ! $results ) {
			return array();
		}

		return array_values( array_filter( array_column( $results, 'source' ) ) );
	}

	public function get_archive_list( array $teams = array() ): array {
		global $wpdb;

		$seasons_table = $wpdb->prefix . 'pp_archive_seasons';
		$sources_table = $wpdb->prefix . 'pp_team_sources_archive';

		if ( ! empty( $teams ) ) {
			$placeholders = implode( ', ', array_fill( 0, count( $teams ), '%d' ) );
			$results      = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT DISTINCT a.season_key AS archive_key, COALESCE(a.label, a.season_key) AS season
                     FROM {$seasons_table} a
                     INNER JOIN {$sources_table} src ON src.archive_id = a.id
                     WHERE src.team_id IN ($placeholders)
                     ORDER BY a.archived_at DESC",
					...$teams
				),
				ARRAY_A
			);
		} else {
			$results = $wpdb->get_results(
				"SELECT DISTINCT a.season_key AS archive_key, COALESCE(a.label, a.season_key) AS season
                 FROM {$seasons_table} a
                 ORDER BY a.archived_at DESC",
				ARRAY_A
			);
		}

		return $results ?: array();
	}

	public function get_stats_data( array $teams = array() ): array {
		$defaults = self::get_default_column_settings();
		$saved    = get_option( 'pp_stats_column_settings', array() );
		$col      = array_merge( $defaults, is_array( $saved ) ? $saved : array() );

		$skaters     = $this->get_skater_stats( $teams );
		$goalies     = $this->get_goalie_stats( $teams );
		$skaters_raw = $this->get_skater_stats_per_source( $teams );
		$goalies_raw = $this->get_goalie_stats_per_source( $teams );
		$sources     = $this->get_distinct_sources( $teams );

		$team_names = array_values( array_filter( array_unique( array_merge(
			array_column( $skaters, 'team_name' ),
			array_column( $goalies, 'team_name' )
		) ) ) );

		return array(
			'skaters'              => $skaters,
			'goalies'              => $goalies,
			'skaters_raw'          => $skaters_raw,
			'goalies_raw'          => $goalies_raw,
			'sources'              => $sources,
			'column_settings'      => $col,
			'team_names'           => $team_names,
			'archives'             => $this->get_archive_list( $teams ),
			'current_season_label' => get_option( 'puck_press_current_season_label', '' ),
			'teams'                => $teams,
		);
	}
}
