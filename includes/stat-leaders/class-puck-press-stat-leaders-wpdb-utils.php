<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Puck_Press_Stat_Leaders_Wpdb_Utils {

	public function get_skater_leaders( array $teams = array() ): array {
		require_once plugin_dir_path( __FILE__ ) . '../stats/class-puck-press-stats-wpdb-utils.php';
		$utils    = new Puck_Press_Stats_Wpdb_Utils();
		$skaters  = $utils->get_skater_stats( $teams );
		$settings = get_option( 'pp_stat_leaders_skater_settings', self::get_default_skater_settings() );

		$rows = array();
		if ( ! empty( $settings['show_goals'] ) ) {
			$row = $this->find_leader( $skaters, 'goals', 'Goals' );
			if ( $row ) {
				$rows[] = $row;
			}
		}
		if ( ! empty( $settings['show_assists'] ) ) {
			$row = $this->find_leader( $skaters, 'assists', 'Assists' );
			if ( $row ) {
				$rows[] = $row;
			}
		}
		if ( ! empty( $settings['show_points'] ) ) {
			$row = $this->find_leader( $skaters, 'points', 'Points' );
			if ( $row ) {
				$rows[] = $row;
			}
		}
		if ( ! empty( $settings['show_pim'] ) ) {
			$row = $this->find_leader( $skaters, 'penalty_minutes', 'PIM' );
			if ( $row ) {
				$rows[] = $row;
			}
		}

		return $rows;
	}

	public function get_goalie_leaders( array $teams = array() ): array {
		require_once plugin_dir_path( __FILE__ ) . '../stats/class-puck-press-stats-wpdb-utils.php';
		$utils    = new Puck_Press_Stats_Wpdb_Utils();
		$goalies  = $utils->get_goalie_stats( $teams );
		$settings = get_option( 'pp_stat_leaders_goalie_settings', self::get_default_goalie_settings() );

		$active_goalies = array_values( array_filter( $goalies, fn( $g ) => (int) ( $g['games_played'] ?? 0 ) > 0 && (float) ( $g['goals_against_average'] ?? 0 ) > 0.0 ) );

		$rows = array();
		if ( ! empty( $settings['show_gaa'] ) ) {
			$row = $this->find_leader( $active_goalies, 'goals_against_average', 'GAA', 'asc', 2 );
			if ( $row ) {
				$rows[] = $row;
			}
		}
		if ( ! empty( $settings['show_saves'] ) ) {
			$row = $this->find_leader( $goalies, 'saves', 'Saves' );
			if ( $row ) {
				$rows[] = $row;
			}
		}
		if ( ! empty( $settings['show_sv_pct'] ) ) {
			$row = $this->find_leader( $active_goalies, 'save_percentage', 'SV%', 'desc', 3 );
			if ( $row ) {
				$rows[] = $row;
			}
		}
		if ( ! empty( $settings['show_wins'] ) ) {
			$row = $this->find_leader( $goalies, 'wins', 'Wins' );
			if ( $row ) {
				$rows[] = $row;
			}
		}

		return $rows;
	}

	private function find_leader( array $players, string $stat_key, string $label, string $dir = 'desc', int $decimals = 0 ): ?array {
		if ( empty( $players ) ) {
			return null;
		}

		usort(
			$players,
			function ( $a, $b ) use ( $stat_key, $dir ) {
				$av = (float) ( $a[ $stat_key ] ?? 0 );
				$bv = (float) ( $b[ $stat_key ] ?? 0 );
				return $dir === 'asc' ? $av <=> $bv : $bv <=> $av;
			}
		);

		$leader = $players[0];
		$raw    = $leader[ $stat_key ] ?? 0;
		$value  = $decimals > 0 ? number_format( (float) $raw, $decimals ) : (string) (int) $raw;

		return array(
			'label'  => $label,
			'player' => $leader['name']      ?? '',
			'team'   => $leader['team_name'] ?? '',
			'value'  => $value,
		);
	}

	public static function get_default_skater_settings(): array {
		return array(
			'show_goals'   => 1,
			'show_assists' => 1,
			'show_points'  => 1,
			'show_pim'     => 0,
		);
	}

	public static function get_default_goalie_settings(): array {
		return array(
			'show_gaa'    => 1,
			'show_saves'  => 1,
			'show_sv_pct' => 0,
			'show_wins'   => 0,
		);
	}

	public static function get_all_team_names(): array {
		require_once plugin_dir_path( __FILE__ ) . '../stats/class-puck-press-stats-wpdb-utils.php';
		$utils   = new Puck_Press_Stats_Wpdb_Utils();
		$skaters = $utils->get_skater_stats();
		$goalies = $utils->get_goalie_stats();

		$names = array_values(
			array_filter(
				array_unique(
					array_merge(
						array_column( $skaters, 'team_name' ),
						array_column( $goalies, 'team_name' )
					)
				)
			)
		);
		sort( $names );
		return $names;
	}
}
