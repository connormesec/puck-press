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

	/**
	 * Render only the skater + goalie sections for archive swapping via AJAX.
	 * Returns HTML for both .pp-stats-section elements (no container).
	 */
	public function build_archive_sections( array $skaters_agg, array $skaters_raw, array $goalies_agg, array $goalies_raw, array $col ): string {
		return $this->buildSkatersSection( $skaters_agg, $skaters_raw, $col )
			. $this->buildGoaliesSection( $goalies_agg, $goalies_raw, $col );
	}

	private function buildStats( array $data ): string {
		$skaters      = $data['skaters'] ?? array();
		$skaters_raw  = $data['skaters_raw'] ?? array();
		$goalies      = $data['goalies'] ?? array();
		$goalies_raw  = $data['goalies_raw'] ?? array();
		$col          = $data['column_settings'] ?? array();
		$team_names   = $data['team_names'] ?? array();
		$archives     = $data['archives'] ?? array();
		$season_label = $data['current_season_label'] ?? '';
		$sources      = $data['sources'] ?? array();
		$instance     = 'pp-stats-' . substr( md5( uniqid( '', true ) ), 0, 8 );

		$teams         = $data['teams'] ?? array();
		$show_team_val = ! empty( $col['show_team'] ) ? '1' : '0';
		$html  = '<div class="standard_stats_container" id="' . esc_attr( $instance ) . '"'
			. ' data-ajaxurl="' . esc_attr( admin_url( 'admin-ajax.php' ) ) . '"'
			. ' data-nonce="' . esc_attr( wp_create_nonce( 'pp_player_detail_nonce' ) ) . '"'
			. ' data-show-team="' . esc_attr( $show_team_val ) . '"'
			. ' data-original-sources="' . esc_attr( wp_json_encode( $sources ) ) . '"'
			. ' data-teams="' . esc_attr( wp_json_encode( $teams ) ) . '"'
			. '>';
		$show_team = ! empty( $col['show_team'] );
		$html .= $this->buildFilterToolbar( $team_names, $archives, $season_label, $sources, $show_team );
		$html .= '<div class="pp-stats-sections">';
		$html .= $this->buildSkatersSection( $skaters, $skaters_raw, $col );
		$html .= $this->buildGoaliesSection( $goalies, $goalies_raw, $col );
		$html .= '</div>';
		$html .= '</div>';

		return $html;
	}

	private function buildFilterToolbar( array $team_names, array $archives, string $season_label, array $sources, bool $show_team = true ): string {
		$html  = '<div class="pp-stats-filter-toolbar">';

		$html .= '<div class="pp-stats-toolbar-left">';
		if ( $show_team && count( $team_names ) > 1 ) {
			$html .= '<select class="pp-stats-team-select" name="pp_stats_team" autocomplete="off">';
			$html .= '<option value="all">All Teams</option>';
			foreach ( $team_names as $team ) {
				$html .= '<option value="' . esc_attr( $team ) . '">' . esc_html( $team ) . '</option>';
			}
			$html .= '</select>';
		}
		$source_style = count( $sources ) <= 1 ? ' style="display:none;"' : '';
		$html .= '<select class="pp-stats-source-select" name="pp_stats_source" autocomplete="off"' . $source_style . '>';
		$html .= '<option value="__all__">All</option>';
		foreach ( $sources as $src ) {
			$html .= '<option value="' . esc_attr( $src ) . '">' . esc_html( $src ) . '</option>';
		}
		$html .= '</select>';
		$html .= '</div>';

		$html .= '<div class="pp-stats-toolbar-right">';
		$html .= '<select class="pp-stats-season-select" name="pp_stats_season" autocomplete="off">';
		$html .= '<option value="current">' . esc_html( $season_label ?: 'Current Season' ) . '</option>';
		if ( ! empty( $archives ) ) {
			$html .= '<optgroup label="' . esc_attr__( 'Past Seasons', 'puck-press' ) . '">';
			foreach ( $archives as $archive ) {
				$html .= '<option value="' . esc_attr( $archive['archive_key'] ) . '">'
					. esc_html( $archive['season'] ) . '</option>';
			}
			$html .= '</optgroup>';
		}
		$html .= '</select>';
		$html .= '</div>';

		$html .= '</div>';
		return $html;
	}

	private function buildSkatersSection( array $skaters, array $skaters_raw, array $col ): string {
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
		$html .= '<th class="pp-stats-col-player pp-stats-th-sortable" data-col="name" data-type="str">Player</th>';
		if ( ! empty( $col['show_team'] ) ) {
			$html .= '<th class="pp-stats-col-team pp-stats-th-sortable" data-col="team" data-type="str">Team</th>';
		}
		$html .= '<th class="pp-stats-col-pos pp-stats-th-sortable" data-tip="Position" data-col="pos" data-type="str">Pos</th>';
		$html .= '<th class="pp-stats-th-sortable" data-tip="Games Played" data-col="gp" data-type="num">GP</th>';
		$html .= '<th class="pp-stats-th-sortable" data-tip="Goals" data-col="g" data-type="num">G</th>';
		$html .= '<th class="pp-stats-th-sortable" data-tip="Assists" data-col="a" data-type="num">A</th>';
		$html .= '<th class="pp-stats-th-sortable" data-tip="Points" data-col="pts" data-type="num">Pts</th>';

		if ( ! empty( $col['show_pts_per_game'] ) ) {
			$html .= '<th class="pp-stats-col-opt pp-stats-th-sortable" data-tip="Points Per Game" data-col="ptsgp" data-type="num">Pts/GP</th>';
		}
		if ( ! empty( $col['show_pim'] ) ) {
			$html .= '<th class="pp-stats-col-opt pp-stats-th-sortable" data-tip="Penalty Minutes" data-col="pim" data-type="num">PIM</th>';
		}
		if ( ! empty( $col['show_ppg'] ) ) {
			$html .= '<th class="pp-stats-col-opt pp-stats-th-sortable" data-tip="Power Play Goals" data-col="ppg" data-type="num">PPG</th>';
		}
		if ( ! empty( $col['show_shg'] ) ) {
			$html .= '<th class="pp-stats-col-opt pp-stats-th-sortable" data-tip="Short-Handed Goals" data-col="shg" data-type="num">SHG</th>';
		}
		if ( ! empty( $col['show_gwg'] ) ) {
			$html .= '<th class="pp-stats-col-opt pp-stats-th-sortable" data-tip="Game-Winning Goals" data-col="gwg" data-type="num">GWG</th>';
		}
		if ( ! empty( $col['show_sh_pct'] ) ) {
			$html .= '<th class="pp-stats-col-opt pp-stats-th-sortable" data-tip="Shooting Percentage" data-col="shpct" data-type="num">SH%</th>';
		}

		$html .= '</tr></thead>';
		$html .= '<tbody>';

		$fallback = 'data:image/svg+xml;base64,' . base64_encode(
			'<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 32 32">'
			. '<circle cx="16" cy="11" r="7" fill="#d1d5db"/>'
			. '<path d="M2 32c0-7.7 6.3-14 14-14s14 6.3 14 14" fill="#d1d5db"/>'
			. '</svg>'
		);

		$raw_by_player = array();
		foreach ( $skaters_raw as $row ) {
			$pid = (string) ( $row['player_id'] ?? '' );
			$rid = (string) ( $row['roster_id'] ?? '' );
			if ( '' !== $pid ) {
				$raw_by_player[ $pid . '_' . $rid ][] = $row;
			}
		}

		foreach ( $skaters as $i => $s ) {
			$rank    = ! empty( $s['stat_rank'] ) ? (int) $s['stat_rank'] : ( $i + 1 );
			$name    = esc_html( $s['name'] ?? '' );
			$pos     = esc_html( $s['pos'] ?? '' );
			$slug    = sanitize_title( $name );
			$src     = ! empty( $s['headshot_link'] ) ? esc_url( $s['headshot_link'] ) : $fallback;
			$img     = '<img src="' . $src . '" loading="lazy" decoding="async"'
				. ' width="32" height="32"'
				. ' onerror="this.onerror=null;this.src=\'' . $fallback . '\';"'
				. ' alt="' . esc_attr( $name . ' headshot' ) . '"'
				. ' class="pp-stats-headshot" />';

			$html .= $this->buildSkaterRow( $s, $rank, $img, $name, $slug, '__all__', $col, false );

			$pkey = ( (string) ( $s['player_id'] ?? '' ) ) . '_' . ( (string) ( $s['roster_id'] ?? '' ) );
			if ( ! empty( $raw_by_player[ $pkey ] ) ) {
				foreach ( $raw_by_player[ $pkey ] as $rs ) {
					$rs_rank = ! empty( $rs['stat_rank'] ) ? (int) $rs['stat_rank'] : ( $i + 1 );
					$rs_name = esc_html( $rs['name'] ?? '' );
					$rs_slug = sanitize_title( $rs_name );
					$rs_src  = ! empty( $rs['headshot_link'] ) ? esc_url( $rs['headshot_link'] ) : $fallback;
					$rs_img  = '<img src="' . $rs_src . '" loading="lazy" decoding="async"'
						. ' width="32" height="32"'
						. ' onerror="this.onerror=null;this.src=\'' . $fallback . '\';"'
						. ' alt="' . esc_attr( $rs_name . ' headshot' ) . '"'
						. ' class="pp-stats-headshot" />';
					$html .= $this->buildSkaterRow( $rs, $rs_rank, $rs_img, $rs_name, $rs_slug, $rs['source'] ?? '', $col, true );
				}
			}
		}

		$html .= '</tbody></table></div></section>';
		return $html;
	}

	private function buildSkaterRow( array $s, int $rank, string $img, string $name, string $slug, string $source, array $col, bool $hidden ): string {
		$style = $hidden ? ' style="display:none;"' : '';
		$html  = '<tr'
			. ' data-source="' . esc_attr( $source ) . '"'
			. ' data-roster-id="' . esc_attr( $s['roster_id'] ?? '' ) . '"'
			. ' data-team-name="' . esc_attr( $s['team_name'] ?? '' ) . '"'
			. ' data-team-group="' . esc_attr( $s['group_name'] ?? '' ) . '"'
			. ' data-team-id="' . esc_attr( $s['team_id'] ?? '' ) . '"'
			. $style
			. '>';
		$html .= '<td class="pp-stats-rank-cell">' . $rank . '</td>';
		$html .= '<td><div class="pp-stats-player-cell">' . $img . '<a class="pp-stats-player-link" href="' . esc_url( home_url( '/player/' . $slug ) ) . '">' . $name . '</a></div></td>';
		if ( ! empty( $col['show_team'] ) ) {
			$html .= '<td class="pp-stats-team">' . esc_html( $s['team_name'] ?? '' ) . '</td>';
		}
		$html .= '<td class="pp-stats-pos">' . esc_html( $s['pos'] ?? '' ) . '</td>';
		$html .= '<td>' . esc_html( $s['games_played'] ?? 0 ) . '</td>';
		$html .= '<td>' . esc_html( $s['goals'] ?? 0 ) . '</td>';
		$html .= '<td>' . esc_html( $s['assists'] ?? 0 ) . '</td>';
		$html .= '<td class="pp-stats-pts">' . esc_html( $s['points'] ?? 0 ) . '</td>';

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
		return $html;
	}

	private function buildGoaliesSection( array $goalies, array $goalies_raw, array $col ): string {
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
		$html .= '<th class="pp-stats-col-player pp-stats-th-sortable" data-col="name" data-type="str">Player</th>';
		if ( ! empty( $col['show_team'] ) ) {
			$html .= '<th class="pp-stats-col-team pp-stats-th-sortable" data-col="team" data-type="str">Team</th>';
		}
		$html .= '<th class="pp-stats-th-sortable" data-tip="Games Played" data-col="gp" data-type="num">GP</th>';
		$html .= '<th class="pp-stats-th-sortable" data-tip="Wins" data-col="w" data-type="num">W</th>';
		$html .= '<th class="pp-stats-th-sortable" data-tip="Losses" data-col="l" data-type="num">L</th>';

		if ( ! empty( $col['show_goalie_otl'] ) ) {
			$html .= '<th class="pp-stats-col-opt pp-stats-th-sortable" data-tip="Overtime Losses" data-col="otl" data-type="num">OTL</th>';
		}
		if ( ! empty( $col['show_goalie_gaa'] ) ) {
			$html .= '<th class="pp-stats-col-opt pp-stats-th-sortable" data-tip="Goals Against Average" data-col="gaa" data-type="num">GAA</th>';
		}
		if ( ! empty( $col['show_goalie_svpct'] ) ) {
			$html .= '<th class="pp-stats-col-opt pp-stats-th-sortable" data-tip="Save Percentage" data-col="svpct" data-type="num">SV%</th>';
		}
		if ( ! empty( $col['show_goalie_sa'] ) ) {
			$html .= '<th class="pp-stats-col-opt pp-stats-th-sortable" data-tip="Shots Against" data-col="sa" data-type="num">SA</th>';
		}
		if ( ! empty( $col['show_goalie_saves'] ) ) {
			$html .= '<th class="pp-stats-col-opt pp-stats-th-sortable" data-tip="Total Saves" data-col="saves" data-type="num">Saves</th>';
		}

		$html .= '</tr></thead>';
		$html .= '<tbody>';

		$fallback = 'data:image/svg+xml;base64,' . base64_encode(
			'<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 32 32">'
			. '<circle cx="16" cy="11" r="7" fill="#d1d5db"/>'
			. '<path d="M2 32c0-7.7 6.3-14 14-14s14 6.3 14 14" fill="#d1d5db"/>'
			. '</svg>'
		);

		$raw_by_player = array();
		foreach ( $goalies_raw as $row ) {
			$pid = (string) ( $row['player_id'] ?? '' );
			$rid = (string) ( $row['roster_id'] ?? '' );
			if ( '' !== $pid ) {
				$raw_by_player[ $pid . '_' . $rid ][] = $row;
			}
		}

		foreach ( $goalies as $i => $g ) {
			$rank   = ! empty( $g['stat_rank'] ) ? (int) $g['stat_rank'] : ( $i + 1 );
			$name   = esc_html( $g['name'] ?? '' );
			$slug   = sanitize_title( $name );
			$src    = ! empty( $g['headshot_link'] ) ? esc_url( $g['headshot_link'] ) : $fallback;
			$img    = '<img src="' . $src . '" loading="lazy" decoding="async"'
				. ' width="32" height="32"'
				. ' onerror="this.onerror=null;this.src=\'' . $fallback . '\';"'
				. ' alt="' . esc_attr( $name . ' headshot' ) . '"'
				. ' class="pp-stats-headshot" />';

			$html .= $this->buildGoalieRow( $g, $rank, $img, $name, $slug, '__all__', $col, false );

			$pkey = ( (string) ( $g['player_id'] ?? '' ) ) . '_' . ( (string) ( $g['roster_id'] ?? '' ) );
			if ( ! empty( $raw_by_player[ $pkey ] ) ) {
				foreach ( $raw_by_player[ $pkey ] as $rg ) {
					$rg_rank = ! empty( $rg['stat_rank'] ) ? (int) $rg['stat_rank'] : ( $i + 1 );
					$rg_name = esc_html( $rg['name'] ?? '' );
					$rg_slug = sanitize_title( $rg_name );
					$rg_src  = ! empty( $rg['headshot_link'] ) ? esc_url( $rg['headshot_link'] ) : $fallback;
					$rg_img  = '<img src="' . $rg_src . '" loading="lazy" decoding="async"'
						. ' width="32" height="32"'
						. ' onerror="this.onerror=null;this.src=\'' . $fallback . '\';"'
						. ' alt="' . esc_attr( $rg_name . ' headshot' ) . '"'
						. ' class="pp-stats-headshot" />';
					$html .= $this->buildGoalieRow( $rg, $rg_rank, $rg_img, $rg_name, $rg_slug, $rg['source'] ?? '', $col, true );
				}
			}
		}

		$html .= '</tbody></table></div></section>';
		return $html;
	}

	private function buildGoalieRow( array $g, int $rank, string $img, string $name, string $slug, string $source, array $col, bool $hidden ): string {
		$style = $hidden ? ' style="display:none;"' : '';
		$html  = '<tr'
			. ' data-source="' . esc_attr( $source ) . '"'
			. ' data-roster-id="' . esc_attr( $g['roster_id'] ?? '' ) . '"'
			. ' data-team-name="' . esc_attr( $g['team_name'] ?? '' ) . '"'
			. ' data-team-group="' . esc_attr( $g['group_name'] ?? '' ) . '"'
			. ' data-team-id="' . esc_attr( $g['team_id'] ?? '' ) . '"'
			. $style
			. '>';
		$html .= '<td class="pp-stats-rank-cell">' . $rank . '</td>';
		$html .= '<td><div class="pp-stats-player-cell">' . $img . '<a class="pp-stats-player-link" href="' . esc_url( home_url( '/player/' . $slug ) ) . '">' . $name . '</a></div></td>';
		if ( ! empty( $col['show_team'] ) ) {
			$html .= '<td class="pp-stats-team">' . esc_html( $g['team_name'] ?? '' ) . '</td>';
		}
		$html .= '<td>' . esc_html( $g['games_played'] ?? 0 ) . '</td>';
		$html .= '<td>' . esc_html( $g['wins'] ?? 0 ) . '</td>';
		$html .= '<td>' . esc_html( $g['losses'] ?? 0 ) . '</td>';

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
		return $html;
	}
}
