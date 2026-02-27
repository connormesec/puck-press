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
     * @param array $player Row from pp_roster_for_display (ARRAY_A).
     * @param array $stats  Row from pp_roster_stats or pp_roster_goalie_stats (ARRAY_A), or empty array if none.
     * @return string HTML string.
     */
    public static function render(array $player, array $stats): string
    {
        $fallback   = 'https://www.pathwaysvermont.org/wp-content/uploads/2017/03/avatar-placeholder-e1490629554738.png';
        $full_name  = $player['name'] ?? '';
        $name_parts = explode( ' ', $full_name, 2 );
        $first_name = esc_html( $name_parts[0] ?? '' );
        $last_name  = esc_html( $name_parts[1] ?? '' );
        $name_attr  = esc_attr( $full_name );

        $number    = ! empty( $player['number'] ) ? esc_html( $player['number'] ) : '';
        $pos_code  = strtoupper( $player['pos'] ?? '' );
        $pos_label = self::$position_labels[ $pos_code ] ?? esc_html( $pos_code );
        $headshot  = ! empty( $player['headshot_link'] )  ? esc_url( $player['headshot_link'] )  : $fallback;
        $hero_bg   = ! empty( $player['hero_image_url'] ) ? esc_url( $player['hero_image_url'] ) : $headshot;

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
        if ( ! empty( $stats ) ) {
            // Highlight cards
            if ( $is_goalie ) {
                $highlight_stats = [
                    [ $stats['wins']                  ?? '-', 'Wins' ],
                    [ $stats['save_percentage']       ?? '-', 'Save Pct.' ],
                    [ $stats['goals_against_average'] ?? '-', 'GAA' ],
                    [ $stats['games_played']          ?? '-', 'Games Played' ],
                ];
            } else {
                $highlight_stats = [
                    [ $stats['goals']        ?? '-', 'Goals' ],
                    [ $stats['assists']      ?? '-', 'Assists' ],
                    [ $stats['points']       ?? '-', 'Points' ],
                    [ $stats['games_played'] ?? '-', 'Games Played' ],
                ];
            }
            $highlights_html = '<div class="pp-stat-highlights">';
            foreach ( $highlight_stats as [ $val, $label ] ) {
                $highlights_html .= '<div class="pp-stat-highlight-card">'
                    . '<span class="pp-stat-highlight-value">' . esc_html( $val ) . '</span>'
                    . '<span class="pp-stat-highlight-label">' . esc_html( $label ) . '</span>'
                    . '</div>';
            }
            $highlights_html .= '</div>';

            if ( $is_goalie ) {
                $gp  = esc_html( $stats['games_played']          ?? '-' );
                $w   = esc_html( $stats['wins']                  ?? '-' );
                $l   = esc_html( $stats['losses']                ?? '-' );
                $otl = esc_html( $stats['overtime_losses']       ?? '-' );
                $sol = esc_html( $stats['shootout_losses']       ?? '-' );
                $sow = esc_html( $stats['shootout_wins']         ?? '-' );
                $sa  = esc_html( $stats['shots_against']         ?? '-' );
                $sv  = esc_html( $stats['saves']                 ?? '-' );
                $svp = esc_html( $stats['save_percentage']       ?? '-' );
                $gaa = esc_html( $stats['goals_against_average'] ?? '-' );
                $ga  = esc_html( $stats['goals_against']         ?? '-' );
                $g   = esc_html( $stats['goals']                 ?? '-' );
                $a   = esc_html( $stats['assists']               ?? '-' );
                $pim = esc_html( $stats['penalty_minutes']       ?? '-' );

                $stats_html = $highlights_html . '
                <div class="pp-stats-wrap">
                    <h3 class="pp-stats-heading">Season Statistics</h3>
                    <div class="pp-stats-table-wrap">
                        <table class="pp-player-stats-table">
                            <thead><tr>
                                <th>GP</th><th>W</th><th>L</th><th>OTL</th><th>SOL</th>
                                <th>SOW</th><th>SA</th><th>SV</th><th>SV%</th><th>GAA</th>
                                <th>GA</th><th>G</th><th>A</th><th>PIM</th>
                            </tr></thead>
                            <tbody><tr>
                                <td>' . $gp  . '</td><td>' . $w   . '</td><td>' . $l   . '</td>
                                <td>' . $otl . '</td><td>' . $sol . '</td><td>' . $sow . '</td>
                                <td>' . $sa  . '</td><td>' . $sv  . '</td><td>' . $svp . '</td>
                                <td>' . $gaa . '</td><td>' . $ga  . '</td><td>' . $g   . '</td>
                                <td>' . $a   . '</td><td>' . $pim . '</td>
                            </tr></tbody>
                        </table>
                    </div>
                </div>';
            } else {
                $gp  = esc_html( $stats['games_played']        ?? '-' );
                $g   = esc_html( $stats['goals']               ?? '-' );
                $a   = esc_html( $stats['assists']             ?? '-' );
                $pts = esc_html( $stats['points']              ?? '-' );
                $ppg = esc_html( $stats['points_per_game']     ?? '-' );
                $pp  = esc_html( $stats['power_play_goals']    ?? '-' );
                $shg = esc_html( $stats['short_handed_goals']  ?? '-' );
                $gw  = esc_html( $stats['game_winning_goals']  ?? '-' );
                $pim = esc_html( $stats['penalty_minutes']     ?? '-' );
                $pct = esc_html( $stats['shooting_percentage'] ?? '-' );

                $stats_html = $highlights_html . '
                <div class="pp-stats-wrap">
                    <h3 class="pp-stats-heading">Season Statistics</h3>
                    <div class="pp-stats-table-wrap">
                        <table class="pp-player-stats-table">
                            <thead><tr>
                                <th>GP</th><th>G</th><th>A</th><th>PTS</th><th>Pt/G</th>
                                <th>PPG</th><th>SHG</th><th>GWG</th><th>PIM</th><th>SH%</th>
                            </tr></thead>
                            <tbody><tr>
                                <td>' . $gp  . '</td><td>' . $g   . '</td><td>' . $a   . '</td>
                                <td>' . $pts . '</td><td>' . $ppg . '</td><td>' . $pp  . '</td>
                                <td>' . $shg . '</td><td>' . $gw  . '</td><td>' . $pim . '</td>
                                <td>' . $pct . '</td>
                            </tr></tbody>
                        </table>
                    </div>
                </div>';
            }
        } else {
            $stats_html = '<p class="pp-no-stats">No stats available for this player.</p>';
        }

        $icons = self::$tab_icons;

        return '
<div class="pp-player-detail">

    <!-- ── Action photo: 16:9 aspect ratio ──────────────────────────────────── -->
    <div class="pp-player-header-bg" style="background-image: url(\'' . $hero_bg . '\');"></div>

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
}
