<?php

/**
 * Renders the full player detail view for the photogrid template.
 */
class Puck_Press_Roster_Player_Detail
{
    private static $position_labels = [
        'F'  => 'Forward',
        'C'  => 'Center',
        'LW' => 'Left Wing',
        'RW' => 'Right Wing',
        'D'  => 'Defenseman',
        'LD' => 'Left Defense',
        'RD' => 'Right Defense',
        'G'  => 'Goalie',
    ];

    // ── SVG icons for tabs ────────────────────────────────────────────────────
    private static $tab_icons = [
        'bio'        => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor"><path d="M20 2H4c-1.1 0-2 .9-2 2v18l4-4h14c1.1 0 2-.9 2-2V4c0-1.1-.9-2-2-2z"/></svg>',
        'stats'      => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor"><path d="M5 9h2v11H5zm4-5h2v16H9zm4 8h2v8h-2zm4-4h2v12h-2z"/></svg>',
        'related'    => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><circle cx="18" cy="5" r="3" fill="currentColor" stroke="none"/><circle cx="6" cy="12" r="3" fill="currentColor" stroke="none"/><circle cx="18" cy="19" r="3" fill="currentColor" stroke="none"/><line x1="8.59" y1="13.51" x2="15.42" y2="17.49"/><line x1="15.41" y1="6.51" x2="8.59" y2="10.49"/></svg>',
        'historical' => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor"><path d="M13 3c-4.97 0-9 4.03-9 9H1l3.89 3.89.07.14L9 12H6c0-3.87 3.13-7 7-7s7 3.13 7 7-3.13 7-7 7c-1.93 0-3.68-.79-4.94-2.06l-1.42 1.42C8.27 19.99 10.51 21 13 21c4.97 0 9-4.03 9-9s-4.03-9-9-9zm-1 5v5l4.28 2.54.72-1.21-3.5-2.08V8H12z"/></svg>',
    ];

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
    public static function render( array $player, array $stats_rows ): string
    {
        $fallback   = PuckPressTemplate::HEADSHOT_FALLBACK;
        $full_name  = $player['name'] ?? '';
        $name_parts = explode( ' ', $full_name, 2 );
        $first_name = esc_html( $name_parts[0] ?? '' );
        $last_name  = esc_html( $name_parts[1] ?? '' );
        $name_attr  = esc_attr( $full_name );

        $number    = ! empty( $player['number'] ) ? esc_html( $player['number'] ) : '';
        $pos_code  = strtoupper( $player['pos'] ?? '' );
        $pos_label = self::$position_labels[ $pos_code ] ?? esc_html( $pos_code );
        $headshot  = ! empty( $player['headshot_link'] )  ? esc_url( $player['headshot_link'] )  : $fallback;
        $hero_bg   = ! empty( $player['hero_image_url'] ) ? esc_url( $player['hero_image_url'] ) : '';

        $ht        = esc_html( $player['ht'] ?? '' );
        $wt        = ! empty( $player['wt'] ) ? esc_html( $player['wt'] ) . ' lbs' : '';
        $ht_wt     = implode( ' / ', array_filter( [ $ht, $wt ] ) );
        $hometown  = esc_html( $player['hometown']       ?? '' );
        $last_team = esc_html( $player['last_team']      ?? '' );
        $year      = esc_html( $player['year_in_school'] ?? '' );
        $major     = esc_html( $player['major']          ?? '' );
        $shoots    = esc_html( $player['shoots']         ?? '' );
        $is_goalie = ( $pos_code === 'G' );

        // ── Header fields (quick-scan) ────────────────────────────────────────
        $header_fields = [];
        if ( $pos_label ) $header_fields[] = [ 'Position',                        $pos_label ];
        if ( $year )       $header_fields[] = [ 'Class',                           $year ];
        if ( $ht_wt )      $header_fields[] = [ 'Ht./Wt.',                         $ht_wt ];
        if ( $shoots )     $header_fields[] = [ $is_goalie ? 'Catches' : 'Shoots', $shoots ];

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
        $bio_fields = [];
        if ( $hometown )  $bio_fields[] = [ 'Hometown',  $hometown ];
        if ( $last_team ) $bio_fields[] = [ 'Last Team', $last_team ];
        if ( $major )     $bio_fields[] = [ 'Major',     $major ];

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
            <button class="pp-player-tab" data-tab="historical">' . $icons['historical'] . ' Historical</button>
        </div>
        <div class="pp-player-tab-panels">
            <div id="pp-panel-bio" class="pp-player-tab-panel pp-panel-active">
                ' . $bio_html . '
            </div>
            <div id="pp-panel-stats" class="pp-player-tab-panel">
                ' . $stats_html . '
            </div>
            <div id="pp-panel-related" class="pp-player-tab-panel">
                <p class="pp-coming-soon">Coming soon.</p>
            </div>
            <div id="pp-panel-historical" class="pp-player-tab-panel">
                <p class="pp-coming-soon">Coming soon.</p>
            </div>
        </div>
    </div>

</div>';
    }

    // ── Career aggregation ────────────────────────────────────────────────────

    private static function compute_career_skater( array $rows ): array
    {
        $gp = $g = $a = $pts = $ppg = $shg = $gwg = $pim = 0;
        foreach ( $rows as $r ) {
            $gp  += (int) ( $r['games_played']        ?? 0 );
            $g   += (int) ( $r['goals']               ?? 0 );
            $a   += (int) ( $r['assists']              ?? 0 );
            $pts += (int) ( $r['points']               ?? 0 );
            $ppg += (int) ( $r['power_play_goals']     ?? 0 );
            $shg += (int) ( $r['short_handed_goals']   ?? 0 );
            $gwg += (int) ( $r['game_winning_goals']   ?? 0 );
            $pim += (int) ( $r['penalty_minutes']      ?? 0 );
        }
        $ptg = $gp > 0 ? round( $pts / $gp, 2 ) : 0;
        return compact( 'gp', 'g', 'a', 'pts', 'ptg', 'ppg', 'shg', 'gwg', 'pim' );
    }

    private static function compute_career_goalie( array $rows ): array
    {
        $gp = $w = $l = $otl = $sol = $sow = $sa = $sv = $ga = $g = $a = $pim = 0;
        foreach ( $rows as $r ) {
            $gp  += (int) ( $r['games_played']      ?? 0 );
            $w   += (int) ( $r['wins']               ?? 0 );
            $l   += (int) ( $r['losses']             ?? 0 );
            $otl += (int) ( $r['overtime_losses']    ?? 0 );
            $sol += (int) ( $r['shootout_losses']    ?? 0 );
            $sow += (int) ( $r['shootout_wins']      ?? 0 );
            $sa  += (int) ( $r['shots_against']      ?? 0 );
            $sv  += (int) ( $r['saves']              ?? 0 );
            $ga  += (int) ( $r['goals_against']      ?? 0 );
            $g   += (int) ( $r['goals']              ?? 0 );
            $a   += (int) ( $r['assists']            ?? 0 );
            $pim += (int) ( $r['penalty_minutes']    ?? 0 );
        }
        $svp = $sa > 0 ? round( $sv / $sa, 3 ) : 0;
        $gaa = $gp > 0 ? round( ( $ga / $gp ) * 60, 2 ) : 0;
        return compact( 'gp', 'w', 'l', 'otl', 'sol', 'sow', 'sa', 'sv', 'svp', 'gaa', 'ga', 'g', 'a', 'pim' );
    }

    // ── Stats tab builders ────────────────────────────────────────────────────

    private static function build_skater_stats_html( array $rows ): string
    {
        $career = self::compute_career_skater( $rows );

        // Highlight cards — career totals
        $highlights_html = '<div class="pp-stat-highlights">';
        foreach ( [
            [ $career['g'],   'Career Goals'      ],
            [ $career['a'],   'Career Assists'     ],
            [ $career['pts'], 'Career Points'      ],
            [ $career['gp'],  'Career Games Played'],
        ] as [ $val, $label ] ) {
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
                . '<td>' . esc_html( $r['season']              ?? '-' ) . '</td>'
                . '<td>' . esc_html( $r['games_played']        ?? '-' ) . '</td>'
                . '<td>' . esc_html( $r['goals']               ?? '-' ) . '</td>'
                . '<td>' . esc_html( $r['assists']             ?? '-' ) . '</td>'
                . '<td>' . esc_html( $r['points']              ?? '-' ) . '</td>'
                . '<td>' . esc_html( $r['points_per_game']     ?? '-' ) . '</td>'
                . '<td>' . esc_html( $r['power_play_goals']    ?? '-' ) . '</td>'
                . '<td>' . esc_html( $r['short_handed_goals']  ?? '-' ) . '</td>'
                . '<td>' . esc_html( $r['game_winning_goals']  ?? '-' ) . '</td>'
                . '<td>' . esc_html( $r['penalty_minutes']     ?? '-' ) . '</td>'
                . '<td>' . esc_html( $r['shooting_percentage'] ?? '-' ) . '</td>'
                . '</tr>';
        }

        // Career totals row (only shown when there are multiple seasons)
        $career_row = '';
        if ( count( $rows ) > 1 ) {
            $career_row = '<tr class="pp-career-row">'
                . '<td><strong>Career</strong></td>'
                . '<td>' . esc_html( $career['gp']  ) . '</td>'
                . '<td>' . esc_html( $career['g']   ) . '</td>'
                . '<td>' . esc_html( $career['a']   ) . '</td>'
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

    private static function build_goalie_stats_html( array $rows ): string
    {
        $career = self::compute_career_goalie( $rows );

        // Highlight cards — career totals
        $highlights_html = '<div class="pp-stat-highlights">';
        foreach ( [
            [ $career['w'],   'Career Wins'        ],
            [ $career['svp'], 'Career Save Pct.'   ],
            [ $career['gaa'], 'Career GAA'         ],
            [ $career['gp'],  'Career Games Played'],
        ] as [ $val, $label ] ) {
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
                . '<td>' . esc_html( $r['season']              ?? '-' ) . '</td>'
                . '<td>' . esc_html( $r['games_played']        ?? '-' ) . '</td>'
                . '<td>' . esc_html( $r['wins']                ?? '-' ) . '</td>'
                . '<td>' . esc_html( $r['losses']              ?? '-' ) . '</td>'
                . '<td>' . esc_html( $r['overtime_losses']     ?? '-' ) . '</td>'
                . '<td>' . esc_html( $r['shootout_losses']     ?? '-' ) . '</td>'
                . '<td>' . esc_html( $r['shootout_wins']       ?? '-' ) . '</td>'
                . '<td>' . esc_html( $r['shots_against']       ?? '-' ) . '</td>'
                . '<td>' . esc_html( $r['saves']               ?? '-' ) . '</td>'
                . '<td>' . esc_html( $r['save_percentage']     ?? '-' ) . '</td>'
                . '<td>' . esc_html( $r['goals_against_average'] ?? '-' ) . '</td>'
                . '<td>' . esc_html( $r['goals_against']       ?? '-' ) . '</td>'
                . '<td>' . esc_html( $r['goals']               ?? '-' ) . '</td>'
                . '<td>' . esc_html( $r['assists']             ?? '-' ) . '</td>'
                . '<td>' . esc_html( $r['penalty_minutes']     ?? '-' ) . '</td>'
                . '</tr>';
        }

        // Career totals row (only shown when there are multiple seasons)
        $career_row = '';
        if ( count( $rows ) > 1 ) {
            $career_row = '<tr class="pp-career-row">'
                . '<td><strong>Career</strong></td>'
                . '<td>' . esc_html( $career['gp']  ) . '</td>'
                . '<td>' . esc_html( $career['w']   ) . '</td>'
                . '<td>' . esc_html( $career['l']   ) . '</td>'
                . '<td>' . esc_html( $career['otl'] ) . '</td>'
                . '<td>' . esc_html( $career['sol'] ) . '</td>'
                . '<td>' . esc_html( $career['sow'] ) . '</td>'
                . '<td>' . esc_html( $career['sa']  ) . '</td>'
                . '<td>' . esc_html( $career['sv']  ) . '</td>'
                . '<td>' . esc_html( $career['svp'] ) . '</td>'
                . '<td>' . esc_html( $career['gaa'] ) . '</td>'
                . '<td>' . esc_html( $career['ga']  ) . '</td>'
                . '<td>' . esc_html( $career['g']   ) . '</td>'
                . '<td>' . esc_html( $career['a']   ) . '</td>'
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
}
