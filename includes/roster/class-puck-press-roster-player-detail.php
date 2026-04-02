<?php

/**
 * Renders the full player detail view for the photogrid template.
 */
class Puck_Press_Roster_Player_Detail {

	private static $position_labels = array(
		'F'  => 'Forward',
		'C'  => 'Center',
		'LW' => 'Left Wing',
		'RW' => 'Right Wing',
		'D'  => 'Defenseman',
		'LD' => 'Left Defense',
		'RD' => 'Right Defense',
		'G'  => 'Goalie',
	);

	// ── SVG icons for tabs ────────────────────────────────────────────────────
	private static $tab_icons = array(
		'bio'        => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor"><path d="M20 2H4c-1.1 0-2 .9-2 2v18l4-4h14c1.1 0 2-.9 2-2V4c0-1.1-.9-2-2-2z"/></svg>',
		'stats'      => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor"><path d="M5 9h2v11H5zm4-5h2v16H9zm4 8h2v8h-2zm4-4h2v12h-2z"/></svg>',
		'related'    => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><circle cx="18" cy="5" r="3" fill="currentColor" stroke="none"/><circle cx="6" cy="12" r="3" fill="currentColor" stroke="none"/><circle cx="18" cy="19" r="3" fill="currentColor" stroke="none"/><line x1="8.59" y1="13.51" x2="15.42" y2="17.49"/><line x1="15.41" y1="6.51" x2="8.59" y2="10.49"/></svg>',
		'awards'     => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor"><path d="M19 5h-2V3H7v2H5c-1.1 0-2 .9-2 2v1c0 2.55 1.92 4.63 4.39 4.94.63 1.5 1.98 2.63 3.61 2.96V19H8v2h8v-2h-3v-3.1c1.63-.33 2.98-1.46 3.61-2.96C19.08 12.63 21 10.55 21 8V7c0-1.1-.9-2-2-2zM5 8V7h2v3.82C5.84 10.4 5 9.3 5 8zm14 0c0 1.3-.84 2.4-2 2.82V7h2v1z"/></svg>',
	);

	/**
	 * Find a player row by URL slug (sanitize_title of their name).
	 *
	 * Caches the result statically so multiple callers within the same request
	 * (e.g. filter_player_page_title and player-page.php) only hit the DB once.
	 *
	 * @param string $slug The URL slug to match against sanitize_title(name).
	 * @return array|null Player row (ARRAY_A) or null if not found.
	 */
	public static function find_by_slug( string $slug ): ?array {
		static $cache = array();

		if ( array_key_exists( $slug, $cache ) ) {
			return $cache[ $slug ];
		}

		global $wpdb;
		$rows = $wpdb->get_results(
			"SELECT * FROM {$wpdb->prefix}pp_team_players_display",
			ARRAY_A
		);

		foreach ( $rows as $row ) {
			if ( sanitize_title( $row['name'] ) === $slug ) {
				$cache[ $slug ] = $row;
				return $row;
			}
		}

		$cache[ $slug ] = null;
		return null;
	}

	/**
	 * Renders the player detail HTML.
	 *
	 * @param array $player     Row from pp_roster_for_display (ARRAY_A).
	 * @param array $stats_rows Array of stat rows — each row is ARRAY_A from
	 *                          pp_roster_stats / pp_roster_goalie_stats (with
	 *                          a 'season' key added) or from the archive tables.
	 *                          Empty array when the player has no stats.
	 * @return string HTML string.
	 */
	public static function render( array $player, array $stats_rows, array $player_awards = array() ): string {
		$fallback   = PuckPressTemplate::HEADSHOT_FALLBACK;
		$full_name  = $player['name'] ?? '';
		$player_slug = sanitize_title( $full_name );
		$name_parts = explode( ' ', $full_name, 2 );
		$first_name = esc_html( $name_parts[0] ?? '' );
		$last_name  = esc_html( $name_parts[1] ?? '' );
		$name_attr  = esc_attr( $full_name );

		$number    = ! empty( $player['number'] ) ? esc_html( $player['number'] ) : '';
		$pos_code  = strtoupper( $player['pos'] ?? '' );
		$pos_label = self::$position_labels[ $pos_code ] ?? esc_html( $pos_code );
		$headshot  = ! empty( $player['headshot_link'] ) ? esc_url( $player['headshot_link'] ) : $fallback;
		$hero_bg   = ! empty( $player['hero_image_url'] ) ? esc_url( $player['hero_image_url'] ) : '';

		$ht        = esc_html( $player['ht'] ?? '' );
		$wt        = ! empty( $player['wt'] ) ? esc_html( $player['wt'] ) . ' lbs' : '';
		$ht_wt     = implode( ' / ', array_filter( array( $ht, $wt ) ) );
		$hometown  = esc_html( $player['hometown'] ?? '' );
		$last_team = esc_html( $player['last_team'] ?? '' );
		$year      = esc_html( $player['year_in_school'] ?? '' );
		$major     = esc_html( $player['major'] ?? '' );
		$shoots    = esc_html( $player['shoots'] ?? '' );
		$is_goalie = ( $pos_code === 'G' );

		// ── Header fields (quick-scan) ────────────────────────────────────────
		$header_fields = array();
		if ( $pos_label ) {
			$header_fields[] = array( 'Position', $pos_label );
		}
		if ( $year ) {
			$header_fields[] = array( 'Class', $year );
		}
		if ( $ht_wt ) {
			$header_fields[] = array( 'Ht./Wt.', $ht_wt );
		}
		if ( $shoots ) {
			$header_fields[] = array( $is_goalie ? 'Catches' : 'Shoots', $shoots );
		}

		$header_fields_html = '';
		foreach ( $header_fields as [ $label, $value ] ) {
			$header_fields_html .= '<li class="pp-field-item">'
				. '<span class="pp-field-label">' . esc_html( $label ) . '</span>'
				. '<span class="pp-field-value">' . esc_html( $value ) . '</span>'
				. '</li>';
		}

		// ── Bio tab ───────────────────────────────────────────────────────────
		$bio_html = '';

		// Biographical detail fields (unique to bio tab)
		$bio_fields = array();
		if ( $hometown ) {
			$bio_fields[] = array( 'Hometown', $hometown );
		}
		if ( $last_team ) {
			$bio_fields[] = array( 'Last Team', $last_team );
		}
		if ( $major ) {
			$bio_fields[] = array( 'Major', $major );
		}

		if ( ! empty( $bio_fields ) ) {
			$bio_html .= '<ul class="pp-bio-fields">';
			foreach ( $bio_fields as [ $label, $value ] ) {
				$bio_html .= '<li class="pp-bio-field-item">'
					. '<span class="pp-bio-field-label">' . esc_html( $label ) . '</span>'
					. '<span class="pp-bio-field-value">' . esc_html( $value ) . '</span>'
					. '</li>';
			}
			$bio_html .= '</ul>';
		}

		if ( empty( $bio_html ) ) {
			$bio_html = '<p class="pp-no-stats">No bio information available.</p>';
		}

		// ── Stats tab content ──────────────────────────────────────────────────
		if ( ! empty( $stats_rows ) ) {
			$stats_html = $is_goalie
				? self::build_goalie_stats_html( $stats_rows )
				: self::build_skater_stats_html( $stats_rows );
		} else {
			$stats_html = '<p class="pp-no-stats">No stats available for this player.</p>';
		}

		// ── Related tab: game summaries that mention this player ─────────────
		$related_query = new WP_Query(
			array(
				'post_type'      => 'pp_game_summary',
				'post_status'    => 'publish',
				'posts_per_page' => 10,
				'orderby'        => 'date',
				'order'          => 'DESC',
				'meta_query'     => array(
					array(
						'key'     => '_mentioned_player_slugs',
						'value'   => '"' . $player_slug . '"',
						'compare' => 'LIKE',
					),
				),
			)
		);

		if ( $related_query->have_posts() ) {
			$related_list = '';
			while ( $related_query->have_posts() ) {
				$related_query->the_post();
				$related_list .= '<li class="pp-related-post">'
					. '<span class="pp-related-date">' . esc_html( get_the_date() ) . '</span>'
					. '<a href="' . esc_url( get_permalink() ) . '">' . esc_html( get_the_title() ) . '</a>'
					. '</li>';
			}
			wp_reset_postdata();
			$related_html = '<div class="pp-related-wrap">'
				. '<h3 class="pp-related-heading">Game Recaps</h3>'
				. '<ul class="pp-related-posts">' . $related_list . '</ul>'
				. '</div>';
		} else {
			$related_html = '<div class="pp-related-wrap">'
				. '<h3 class="pp-related-heading">Game Recaps</h3>'
				. '<p class="pp-related-empty">No game recaps found for this player yet.</p>'
				. '</div>';
		}

		$icons = self::$tab_icons;

		return '
<div class="pp-player-detail">

    <!-- ── Action photo: 16:9 aspect ratio ──────────────────────────────────── -->
    <div class="pp-player-header-bg"' . ( $hero_bg ? ' style="background-image: url(\'' . $hero_bg . '\');"' : '' ) . '></div>

    <!-- ── Header details: headshot + name bar + fields ─────────────────────── -->
    <!--
         Headshot floats left with margin-top:-8rem, pulling it 8rem up into the photo.
         Name bar has margin-top:-6rem, filling the space to the right of the float.
         Fields use clear:left, sitting below both in normal flow.
    -->
    <div class="pp-player-header-details">
        <div class="pp-player-headshot-wrap">
            <img
                src="' . $headshot . '"
                onerror="this.onerror=null;this.src=\'' . $fallback . '\';"
                alt="' . $name_attr . ' headshot"
                class="pp-player-headshot"
                loading="lazy"
                decoding="async"
            />
        </div>
        <div class="pp-player-heading-bar">
            ' . ( $number ? '<span class="pp-player-number">' . $number . '</span>' : '' ) . '
            <div class="pp-player-name">
                <span class="pp-player-first-name">' . $first_name . '</span>
                <span class="pp-player-last-name">' . $last_name . '</span>
            </div>
        </div>
        ' . ( $header_fields_html ? '<ul class="pp-player-fields">' . $header_fields_html . '</ul>' : '' ) . '
        <div class="pp-clear"></div>
    </div>

    <!-- ── Tabs + content ────────────────────────────────────────────────── -->
    <div class="pp-player-body">
        <a href="#" class="pp-player-back-btn">&#8592; Back to Roster</a>
        <div class="pp-player-tabs-bar">
            <button class="pp-player-tab pp-tab-active" data-tab="bio">' . $icons['bio'] . ' Bio</button>
            <button class="pp-player-tab" data-tab="stats">' . $icons['stats'] . ' Stats</button>
            <button class="pp-player-tab" data-tab="related">' . $icons['related'] . ' Related</button>
            <button class="pp-player-tab" data-tab="awards">' . $icons['awards'] . ' Awards</button>
        </div>
        <div class="pp-player-tab-panels">
            <div id="pp-panel-bio" class="pp-player-tab-panel pp-panel-active">
                ' . $bio_html . '
            </div>
            <div id="pp-panel-stats" class="pp-player-tab-panel">
                ' . $stats_html . '
            </div>
            <div id="pp-panel-related" class="pp-player-tab-panel">
                ' . $related_html . '
            </div>
            <div id="pp-panel-awards" class="pp-player-tab-panel">
                ' . self::build_awards_html( $player_awards ) . '
            </div>
        </div>
    </div>

</div>';
	}

	// ── Career aggregation ────────────────────────────────────────────────────

	private static function compute_career_skater( array $rows ): array {
		$gp = $g = $a = $pts = $ppg = $shg = $gwg = $pim = 0;
		foreach ( $rows as $r ) {
			$gp  += (int) ( $r['games_played'] ?? 0 );
			$g   += (int) ( $r['goals'] ?? 0 );
			$a   += (int) ( $r['assists'] ?? 0 );
			$pts += (int) ( $r['points'] ?? 0 );
			$ppg += (int) ( $r['power_play_goals'] ?? 0 );
			$shg += (int) ( $r['short_handed_goals'] ?? 0 );
			$gwg += (int) ( $r['game_winning_goals'] ?? 0 );
			$pim += (int) ( $r['penalty_minutes'] ?? 0 );
		}
		$ptg = $gp > 0 ? round( $pts / $gp, 2 ) : 0;
		return compact( 'gp', 'g', 'a', 'pts', 'ptg', 'ppg', 'shg', 'gwg', 'pim' );
	}

	private static function compute_career_goalie( array $rows ): array {
		$gp = $w = $l = $otl = $sol = $sow = $sa = $sv = $ga = $g = $a = $pim = 0;
		foreach ( $rows as $r ) {
			$gp  += (int) ( $r['games_played'] ?? 0 );
			$w   += (int) ( $r['wins'] ?? 0 );
			$l   += (int) ( $r['losses'] ?? 0 );
			$otl += (int) ( $r['overtime_losses'] ?? 0 );
			$sol += (int) ( $r['shootout_losses'] ?? 0 );
			$sow += (int) ( $r['shootout_wins'] ?? 0 );
			$sa  += (int) ( $r['shots_against'] ?? 0 );
			$sv  += (int) ( $r['saves'] ?? 0 );
			$ga  += (int) ( $r['goals_against'] ?? 0 );
			$g   += (int) ( $r['goals'] ?? 0 );
			$a   += (int) ( $r['assists'] ?? 0 );
			$pim += (int) ( $r['penalty_minutes'] ?? 0 );
		}
		$svp = $sa > 0 ? round( $sv / $sa, 3 ) : 0;
		$gaa = $gp > 0 ? round( ( $ga / $gp ) * 60, 2 ) : 0;
		return compact( 'gp', 'w', 'l', 'otl', 'sol', 'sow', 'sa', 'sv', 'svp', 'gaa', 'ga', 'g', 'a', 'pim' );
	}

	// ── Stats tab builders ────────────────────────────────────────────────────

	private static function build_skater_stats_html( array $rows ): string {
		$career = self::compute_career_skater( $rows );

		// Highlight cards — career totals
		$highlights_html = '<div class="pp-stat-highlights">';
		foreach ( array(
			array( $career['g'], 'Career Goals' ),
			array( $career['a'], 'Career Assists' ),
			array( $career['pts'], 'Career Points' ),
			array( $career['gp'], 'Career Games Played' ),
		) as [ $val, $label ] ) {
			$highlights_html .= '<div class="pp-stat-highlight-card">'
				. '<span class="pp-stat-highlight-value">' . esc_html( $val ) . '</span>'
				. '<span class="pp-stat-highlight-label">' . esc_html( $label ) . '</span>'
				. '</div>';
		}
		$highlights_html .= '</div>';

		// Per-season rows
		$body_rows = '';
		foreach ( $rows as $r ) {
			$body_rows .= '<tr>'
				. '<td>' . esc_html( $r['season'] ?? '-' ) . '</td>'
				. '<td>' . esc_html( $r['games_played'] ?? '-' ) . '</td>'
				. '<td>' . esc_html( $r['goals'] ?? '-' ) . '</td>'
				. '<td>' . esc_html( $r['assists'] ?? '-' ) . '</td>'
				. '<td>' . esc_html( $r['points'] ?? '-' ) . '</td>'
				. '<td>' . esc_html( $r['points_per_game'] ?? '-' ) . '</td>'
				. '<td>' . esc_html( $r['power_play_goals'] ?? '-' ) . '</td>'
				. '<td>' . esc_html( $r['short_handed_goals'] ?? '-' ) . '</td>'
				. '<td>' . esc_html( $r['game_winning_goals'] ?? '-' ) . '</td>'
				. '<td>' . esc_html( $r['penalty_minutes'] ?? '-' ) . '</td>'
				. '<td>' . esc_html( $r['shooting_percentage'] ?? '-' ) . '</td>'
				. '</tr>';
		}

		// Career totals row (only shown when there are multiple seasons)
		$career_row = '';
		if ( count( $rows ) > 1 ) {
			$career_row = '<tr class="pp-career-row">'
				. '<td><strong>Career</strong></td>'
				. '<td>' . esc_html( $career['gp'] ) . '</td>'
				. '<td>' . esc_html( $career['g'] ) . '</td>'
				. '<td>' . esc_html( $career['a'] ) . '</td>'
				. '<td>' . esc_html( $career['pts'] ) . '</td>'
				. '<td>' . esc_html( $career['ptg'] ) . '</td>'
				. '<td>' . esc_html( $career['ppg'] ) . '</td>'
				. '<td>' . esc_html( $career['shg'] ) . '</td>'
				. '<td>' . esc_html( $career['gwg'] ) . '</td>'
				. '<td>' . esc_html( $career['pim'] ) . '</td>'
				. '<td>&mdash;</td>'
				. '</tr>';
		}

		return $highlights_html . '
        <div class="pp-stats-wrap">
            <h3 class="pp-stats-heading">Statistics</h3>
            <div class="pp-stats-table-wrap">
                <table class="pp-player-stats-table">
                    <thead><tr>
                        <th>Season</th><th>GP</th><th>G</th><th>A</th><th>PTS</th><th>Pt/G</th>
                        <th>PPG</th><th>SHG</th><th>GWG</th><th>PIM</th><th>SH%</th>
                    </tr></thead>
                    <tbody>' . $body_rows . $career_row . '</tbody>
                </table>
            </div>
        </div>';
	}

	private static function build_goalie_stats_html( array $rows ): string {
		$career = self::compute_career_goalie( $rows );

		// Highlight cards — career totals
		$highlights_html = '<div class="pp-stat-highlights">';
		foreach ( array(
			array( $career['w'], 'Career Wins' ),
			array( $career['svp'], 'Career Save Pct.' ),
			array( $career['gaa'], 'Career GAA' ),
			array( $career['gp'], 'Career Games Played' ),
		) as [ $val, $label ] ) {
			$highlights_html .= '<div class="pp-stat-highlight-card">'
				. '<span class="pp-stat-highlight-value">' . esc_html( $val ) . '</span>'
				. '<span class="pp-stat-highlight-label">' . esc_html( $label ) . '</span>'
				. '</div>';
		}
		$highlights_html .= '</div>';

		// Per-season rows
		$body_rows = '';
		foreach ( $rows as $r ) {
			$body_rows .= '<tr>'
				. '<td>' . esc_html( $r['season'] ?? '-' ) . '</td>'
				. '<td>' . esc_html( $r['games_played'] ?? '-' ) . '</td>'
				. '<td>' . esc_html( $r['wins'] ?? '-' ) . '</td>'
				. '<td>' . esc_html( $r['losses'] ?? '-' ) . '</td>'
				. '<td>' . esc_html( $r['overtime_losses'] ?? '-' ) . '</td>'
				. '<td>' . esc_html( $r['shootout_losses'] ?? '-' ) . '</td>'
				. '<td>' . esc_html( $r['shootout_wins'] ?? '-' ) . '</td>'
				. '<td>' . esc_html( $r['shots_against'] ?? '-' ) . '</td>'
				. '<td>' . esc_html( $r['saves'] ?? '-' ) . '</td>'
				. '<td>' . esc_html( $r['save_percentage'] ?? '-' ) . '</td>'
				. '<td>' . esc_html( $r['goals_against_average'] ?? '-' ) . '</td>'
				. '<td>' . esc_html( $r['goals_against'] ?? '-' ) . '</td>'
				. '<td>' . esc_html( $r['goals'] ?? '-' ) . '</td>'
				. '<td>' . esc_html( $r['assists'] ?? '-' ) . '</td>'
				. '<td>' . esc_html( $r['penalty_minutes'] ?? '-' ) . '</td>'
				. '</tr>';
		}

		// Career totals row (only shown when there are multiple seasons)
		$career_row = '';
		if ( count( $rows ) > 1 ) {
			$career_row = '<tr class="pp-career-row">'
				. '<td><strong>Career</strong></td>'
				. '<td>' . esc_html( $career['gp'] ) . '</td>'
				. '<td>' . esc_html( $career['w'] ) . '</td>'
				. '<td>' . esc_html( $career['l'] ) . '</td>'
				. '<td>' . esc_html( $career['otl'] ) . '</td>'
				. '<td>' . esc_html( $career['sol'] ) . '</td>'
				. '<td>' . esc_html( $career['sow'] ) . '</td>'
				. '<td>' . esc_html( $career['sa'] ) . '</td>'
				. '<td>' . esc_html( $career['sv'] ) . '</td>'
				. '<td>' . esc_html( $career['svp'] ) . '</td>'
				. '<td>' . esc_html( $career['gaa'] ) . '</td>'
				. '<td>' . esc_html( $career['ga'] ) . '</td>'
				. '<td>' . esc_html( $career['g'] ) . '</td>'
				. '<td>' . esc_html( $career['a'] ) . '</td>'
				. '<td>' . esc_html( $career['pim'] ) . '</td>'
				. '</tr>';
		}

		return $highlights_html . '
        <div class="pp-stats-wrap">
            <h3 class="pp-stats-heading">Statistics</h3>
            <div class="pp-stats-table-wrap">
                <table class="pp-player-stats-table">
                    <thead><tr>
                        <th>Season</th><th>GP</th><th>W</th><th>L</th><th>OTL</th><th>SOL</th>
                        <th>SOW</th><th>SA</th><th>SV</th><th>SV%</th><th>GAA</th>
                        <th>GA</th><th>G</th><th>A</th><th>PIM</th>
                    </tr></thead>
                    <tbody>' . $body_rows . $career_row . '</tbody>
                </table>
            </div>
        </div>';
	}

	private static function build_awards_html( array $player_awards ): string {
		if ( empty( $player_awards ) ) {
			return '<p class="pp-no-awards">No awards yet.</p>';
		}

		$badges = '';
		foreach ( $player_awards as $award ) {
			$tooltip = esc_attr( $award['year'] . ' ' . $award['award_name'] );
			$label   = esc_html( $award['award_name'] );
			$year    = esc_html( $award['year'] );

			if ( $award['icon_type'] === 'image' && ! empty( $award['icon_value'] ) ) {
				$icon = '<img src="' . esc_url( $award['icon_value'] ) . '" alt="' . $tooltip . '">';
			} else {
				$icon = esc_html( $award['icon_value'] ?: '🏅' );
			}

			$badges .= '<div class="pp-award-badge" title="' . $tooltip . '">'
				. '<span class="pp-award-badge-icon">' . $icon . '</span>'
				. '<span class="pp-award-badge-label">' . $label . '</span>'
				. '<span class="pp-award-badge-year">' . $year . '</span>'
				. '</div>';
		}

		return '<div class="pp-award-badges">' . $badges . '</div>';
	}
}
