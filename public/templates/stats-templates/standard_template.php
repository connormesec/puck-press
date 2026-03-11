<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class StandardTemplate extends PuckPressTemplate {

	public static function get_key(): string {
		return 'standard';
	}

	public static function get_label(): string {
		return 'Standard Stats Table';
	}

	protected static function get_directory(): string {
		return 'stats-templates';
	}

	public static function forceResetColors(): bool {
		return false;
	}

	public static function get_default_colors(): array {
		return array(
			'page_bg'             => '#FFFFFF',
			'section_header_bg'   => '#F0F4F5',
			'section_header_text' => '#1A1A2E',
			'table_header_bg'     => '#1565C0',
			'table_header_text'   => '#FFFFFF',
			'row_odd_bg'          => '#F8F9FA',
			'row_even_bg'         => '#FFFFFF',
			'text'                => '#202124',
			'text_muted'          => '#5F6368',
			'border'              => '#E8EAED',
			'accent'              => '#1565C0',
		);
	}

	public static function get_color_labels(): array {
		return array(
			'page_bg'             => 'Page Background',
			'section_header_bg'   => 'Section Header Background',
			'section_header_text' => 'Section Header Text',
			'table_header_bg'     => 'Table Header Background',
			'table_header_text'   => 'Table Header Text',
			'row_odd_bg'          => 'Odd Row Background',
			'row_even_bg'         => 'Even Row Background',
			'text'                => 'Body Text',
			'text_muted'          => 'Secondary / Rank Text',
			'border'              => 'Border Color',
			'accent'              => 'Accent / Player Name Color',
		);
	}

	public static function get_default_fonts(): array {
		return array( 'stats_font' => '' );
	}

	public static function get_font_labels(): array {
		return array( 'stats_font' => 'Stats Font' );
	}

	public static function get_js_dependencies(): array {
		return array( 'jquery', 'pp-player-detail' );
	}

	public function render_with_options( array $data, array $options ): string {
		$inline_css = self::get_inline_css();
		$css_block  = $inline_css ? '<style>' . $inline_css . '</style>' : '';
		return $css_block . $this->buildStats( $data );
	}

	private function buildStats( array $data ): string {
		$skaters = $data['skaters'] ?? array();
		$goalies = $data['goalies'] ?? array();
		$col     = $data['column_settings'] ?? array();

		$html  = '<div class="standard_stats_container"'
			. ' data-ajaxurl="' . esc_attr( admin_url( 'admin-ajax.php' ) ) . '"'
			. ' data-nonce="' . esc_attr( wp_create_nonce( 'pp_player_detail_nonce' ) ) . '"'
			. '>';
		$html .= '<h2 class="pp-stats-heading">Statistics</h2>';
		$html .= $this->buildSkatersSection( $skaters, $col );
		$html .= $this->buildGoaliesSection( $goalies, $col );
		$html .= '</div>';

		return $html;
	}

	private function buildSkatersSection( array $skaters, array $col ): string {
		$html  = '<section class="pp-stats-section">';
		$html .= '<h2 class="pp-stats-section-title">Skaters</h2>';

		if ( empty( $skaters ) ) {
			$html .= '<p class="pp-stats-empty">No skater stats available. Refresh your roster sources to populate data.</p>';
			$html .= '</section>';
			return $html;
		}

		$html .= '<div class="pp-stats-table-wrapper">';
		$html .= '<table class="pp-stats-table">';
		$html .= '<thead><tr>';
		$html .= '<th class="pp-stats-col-rank"></th>';
		$html .= '<th class="pp-stats-col-player">Player</th>';
		$html .= '<th class="pp-stats-col-pos" data-tip="Position">Pos</th>';
		$html .= '<th data-tip="Games Played">GP</th>';
		$html .= '<th data-tip="Goals">G</th>';
		$html .= '<th data-tip="Assists">A</th>';
		$html .= '<th data-tip="Points">Pts</th>';

		if ( ! empty( $col['show_pts_per_game'] ) ) {
			$html .= '<th class="pp-stats-col-opt" data-tip="Points Per Game">Pts/GP</th>';
		}
		if ( ! empty( $col['show_pim'] ) ) {
			$html .= '<th class="pp-stats-col-opt" data-tip="Penalty Minutes">PIM</th>';
		}
		if ( ! empty( $col['show_ppg'] ) ) {
			$html .= '<th class="pp-stats-col-opt" data-tip="Power Play Goals">PPG</th>';
		}
		if ( ! empty( $col['show_shg'] ) ) {
			$html .= '<th class="pp-stats-col-opt" data-tip="Short-Handed Goals">SHG</th>';
		}
		if ( ! empty( $col['show_gwg'] ) ) {
			$html .= '<th class="pp-stats-col-opt" data-tip="Game-Winning Goals">GWG</th>';
		}
		if ( ! empty( $col['show_sh_pct'] ) ) {
			$html .= '<th class="pp-stats-col-opt" data-tip="Shooting Percentage">SH%</th>';
		}

		$html .= '</tr></thead>';
		$html .= '<tbody>';

		$fallback = 'data:image/svg+xml;base64,' . base64_encode(
			'<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 32 32">'
			. '<circle cx="16" cy="11" r="7" fill="#d1d5db"/>'
			. '<path d="M2 32c0-7.7 6.3-14 14-14s14 6.3 14 14" fill="#d1d5db"/>'
			. '</svg>'
		);

		foreach ( $skaters as $i => $s ) {
			$rank    = ! empty( $s['stat_rank'] ) ? (int) $s['stat_rank'] : ( $i + 1 );
			$name    = esc_html( $s['name'] ?? '' );
			$pos     = esc_html( $s['pos'] ?? '' );
			$gp      = esc_html( $s['games_played'] ?? 0 );
			$goals   = esc_html( $s['goals'] ?? 0 );
			$assists = esc_html( $s['assists'] ?? 0 );
			$points  = esc_html( $s['points'] ?? 0 );

			$src = ! empty( $s['headshot_link'] ) ? esc_url( $s['headshot_link'] ) : $fallback;
			$img = '<img src="' . $src . '" loading="lazy" decoding="async"'
				. ' width="32" height="32"'
				. ' onerror="this.onerror=null;this.src=\'' . $fallback . '\';"'
				. ' alt="' . esc_attr( $name . ' headshot' ) . '"'
				. ' class="pp-stats-headshot" />';

			$slug  = sanitize_title( $name );
			$html .= '<tr>';
			$html .= '<td class="pp-stats-rank-cell">' . $rank . '</td>';
			$html .= '<td class="pp-stats-player-cell">' . $img . '<a class="pp-stats-player-link" href="' . esc_url( home_url( '/player/' . $slug ) ) . '">' . $name . '</a></td>';
			$html .= '<td class="pp-stats-pos">' . $pos . '</td>';
			$html .= '<td>' . $gp . '</td>';
			$html .= '<td>' . $goals . '</td>';
			$html .= '<td>' . $assists . '</td>';
			$html .= '<td class="pp-stats-pts">' . $points . '</td>';

			if ( ! empty( $col['show_pts_per_game'] ) ) {
				$ppg_rate = isset( $s['points_per_game'] ) ? number_format( (float) $s['points_per_game'], 2 ) : '0.00';
				$html    .= '<td class="pp-stats-col-opt">' . esc_html( $ppg_rate ) . '</td>';
			}
			if ( ! empty( $col['show_pim'] ) ) {
				$html .= '<td class="pp-stats-col-opt">' . esc_html( $s['penalty_minutes'] ?? 0 ) . '</td>';
			}
			if ( ! empty( $col['show_ppg'] ) ) {
				$html .= '<td class="pp-stats-col-opt">' . esc_html( $s['power_play_goals'] ?? 0 ) . '</td>';
			}
			if ( ! empty( $col['show_shg'] ) ) {
				$html .= '<td class="pp-stats-col-opt">' . esc_html( $s['short_handed_goals'] ?? 0 ) . '</td>';
			}
			if ( ! empty( $col['show_gwg'] ) ) {
				$html .= '<td class="pp-stats-col-opt">' . esc_html( $s['game_winning_goals'] ?? 0 ) . '</td>';
			}
			if ( ! empty( $col['show_sh_pct'] ) ) {
				$sh_pct = isset( $s['shooting_percentage'] ) ? number_format( (float) $s['shooting_percentage'], 1 ) : '0.0';
				$html  .= '<td class="pp-stats-col-opt">' . esc_html( $sh_pct ) . '</td>';
			}

			$html .= '</tr>';
		}

		$html .= '</tbody></table></div></section>';
		return $html;
	}

	private function buildGoaliesSection( array $goalies, array $col ): string {
		$html  = '<section class="pp-stats-section">';
		$html .= '<h2 class="pp-stats-section-title">Goalies</h2>';

		if ( empty( $goalies ) ) {
			$html .= '<p class="pp-stats-empty">No goalie stats available. Refresh your roster sources to populate data.</p>';
			$html .= '</section>';
			return $html;
		}

		$html .= '<div class="pp-stats-table-wrapper">';
		$html .= '<table class="pp-stats-table">';
		$html .= '<thead><tr>';
		$html .= '<th class="pp-stats-col-rank"></th>';
		$html .= '<th class="pp-stats-col-player">Player</th>';
		$html .= '<th data-tip="Games Played">GP</th>';
		$html .= '<th data-tip="Wins">W</th>';
		$html .= '<th data-tip="Losses">L</th>';

		if ( ! empty( $col['show_goalie_otl'] ) ) {
			$html .= '<th class="pp-stats-col-opt" data-tip="Overtime Losses">OTL</th>';
		}
		if ( ! empty( $col['show_goalie_gaa'] ) ) {
			$html .= '<th class="pp-stats-col-opt" data-tip="Goals Against Average">GAA</th>';
		}
		if ( ! empty( $col['show_goalie_svpct'] ) ) {
			$html .= '<th class="pp-stats-col-opt" data-tip="Save Percentage">SV%</th>';
		}
		if ( ! empty( $col['show_goalie_sa'] ) ) {
			$html .= '<th class="pp-stats-col-opt" data-tip="Shots Against">SA</th>';
		}
		if ( ! empty( $col['show_goalie_saves'] ) ) {
			$html .= '<th class="pp-stats-col-opt" data-tip="Total Saves">Saves</th>';
		}

		$html .= '</tr></thead>';
		$html .= '<tbody>';

		$fallback = 'data:image/svg+xml;base64,' . base64_encode(
			'<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 32 32">'
			. '<circle cx="16" cy="11" r="7" fill="#d1d5db"/>'
			. '<path d="M2 32c0-7.7 6.3-14 14-14s14 6.3 14 14" fill="#d1d5db"/>'
			. '</svg>'
		);

		foreach ( $goalies as $i => $g ) {
			$rank   = ! empty( $g['stat_rank'] ) ? (int) $g['stat_rank'] : ( $i + 1 );
			$name   = esc_html( $g['name'] ?? '' );
			$gp     = esc_html( $g['games_played'] ?? 0 );
			$wins   = esc_html( $g['wins'] ?? 0 );
			$losses = esc_html( $g['losses'] ?? 0 );

			$src = ! empty( $g['headshot_link'] ) ? esc_url( $g['headshot_link'] ) : $fallback;
			$img = '<img src="' . $src . '" loading="lazy" decoding="async"'
				. ' width="32" height="32"'
				. ' onerror="this.onerror=null;this.src=\'' . $fallback . '\';"'
				. ' alt="' . esc_attr( $name . ' headshot' ) . '"'
				. ' class="pp-stats-headshot" />';

			$slug  = sanitize_title( $name );
			$html .= '<tr>';
			$html .= '<td class="pp-stats-rank-cell">' . $rank . '</td>';
			$html .= '<td class="pp-stats-player-cell">' . $img . '<a class="pp-stats-player-link" href="' . esc_url( home_url( '/player/' . $slug ) ) . '">' . $name . '</a></td>';
			$html .= '<td>' . $gp . '</td>';
			$html .= '<td>' . $wins . '</td>';
			$html .= '<td>' . $losses . '</td>';

			if ( ! empty( $col['show_goalie_otl'] ) ) {
				$html .= '<td class="pp-stats-col-opt">' . esc_html( $g['overtime_losses'] ?? 0 ) . '</td>';
			}
			if ( ! empty( $col['show_goalie_gaa'] ) ) {
				$gaa   = isset( $g['goals_against_average'] ) ? number_format( (float) $g['goals_against_average'], 2 ) : '0.00';
				$html .= '<td class="pp-stats-col-opt">' . esc_html( $gaa ) . '</td>';
			}
			if ( ! empty( $col['show_goalie_svpct'] ) ) {
				$svp   = isset( $g['save_percentage'] ) ? number_format( (float) $g['save_percentage'], 3 ) : '.000';
				$html .= '<td class="pp-stats-col-opt">' . esc_html( $svp ) . '</td>';
			}
			if ( ! empty( $col['show_goalie_sa'] ) ) {
				$html .= '<td class="pp-stats-col-opt">' . esc_html( $g['shots_against'] ?? 0 ) . '</td>';
			}
			if ( ! empty( $col['show_goalie_saves'] ) ) {
				$html .= '<td class="pp-stats-col-opt">' . esc_html( $g['saves'] ?? 0 ) . '</td>';
			}

			$html .= '</tr>';
		}

		$html .= '</tbody></table></div></section>';
		return $html;
	}
}
