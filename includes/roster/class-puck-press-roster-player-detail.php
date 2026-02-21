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

    /**
     * Renders the player detail HTML.
     *
     * @param array $player Row from pp_roster_for_display (ARRAY_A).
     * @param array $stats  Row from pp_roster_stats (ARRAY_A), or empty array if none.
     * @return string HTML string.
     */
    public static function render(array $player, array $stats): string
    {
        $fallback  = 'https://www.pathwaysvermont.org/wp-content/uploads/2017/03/avatar-placeholder-e1490629554738.png';
        $name      = esc_html($player['name'] ?? '');
        $number    = !empty($player['number']) ? esc_html($player['number']) : '';
        $pos_code  = strtoupper($player['pos'] ?? '');
        $pos_label = self::$position_labels[$pos_code] ?? esc_html($pos_code);
        $headshot  = !empty($player['headshot_link']) ? esc_url($player['headshot_link']) : $fallback;

        $ht     = esc_html($player['ht'] ?? '');
        $wt     = !empty($player['wt']) ? esc_html($player['wt']) . ' lbs' : '';
        $ht_wt  = implode(' / ', array_filter([$ht, $wt]));

        $hometown  = esc_html($player['hometown'] ?? '');
        $last_team = esc_html($player['last_team'] ?? '');
        $year      = esc_html($player['year_in_school'] ?? '');
        $major     = esc_html($player['major'] ?? '');
        $shoots    = esc_html($player['shoots'] ?? '');

        // Bio rows — only include non-empty fields
        $bio_rows = [];
        if ($pos_label)  $bio_rows[] = ['Position',  $pos_label];
        if ($year)        $bio_rows[] = ['Class',      $year];
        if ($ht_wt)       $bio_rows[] = ['Ht / Wt',   $ht_wt];
        if ($shoots)      $bio_rows[] = ['Shoots',     $shoots];
        if ($hometown)    $bio_rows[] = ['Hometown',   $hometown];
        if ($last_team)   $bio_rows[] = ['Last Team',  $last_team];
        if ($major)       $bio_rows[] = ['Major',      $major];

        $bio_html = '';
        foreach ($bio_rows as [$label, $value]) {
            $bio_html .= '<div class="pp-bio-row">';
            $bio_html .= '<span class="pp-bio-label">' . esc_html($label) . '</span>';
            $bio_html .= '<span class="pp-bio-value">' . $value . '</span>';
            $bio_html .= '</div>';
        }
        if (empty($bio_html)) {
            $bio_html = '<p class="pp-no-stats">No bio information available.</p>';
        }

        // Stats tab content
        if (!empty($stats)) {
            $gp  = esc_html($stats['games_played']         ?? '-');
            $g   = esc_html($stats['goals']                ?? '-');
            $a   = esc_html($stats['assists']              ?? '-');
            $pts = esc_html($stats['points']               ?? '-');
            $ppg = esc_html($stats['points_per_game']      ?? '-');
            $pp  = esc_html($stats['power_play_goals']     ?? '-');
            $shg = esc_html($stats['short_handed_goals']   ?? '-');
            $gw  = esc_html($stats['game_winning_goals']   ?? '-');
            $pim = esc_html($stats['penalty_minutes']      ?? '-');
            $pct = esc_html($stats['shooting_percentage']  ?? '-');

            $stats_html = '
            <div class="pp-stats-wrap">
                <h3 class="pp-stats-heading">Season Statistics</h3>
                <div class="pp-stats-table-wrap">
                    <table class="pp-player-stats-table">
                        <thead>
                            <tr>
                                <th>GP</th>
                                <th>G</th>
                                <th>A</th>
                                <th>PTS</th>
                                <th>Pt/G</th>
                                <th>PPG</th>
                                <th>SHG</th>
                                <th>GWG</th>
                                <th>PIM</th>
                                <th>SH%</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td>' . $gp  . '</td>
                                <td>' . $g   . '</td>
                                <td>' . $a   . '</td>
                                <td>' . $pts . '</td>
                                <td>' . $ppg . '</td>
                                <td>' . $pp  . '</td>
                                <td>' . $shg . '</td>
                                <td>' . $gw  . '</td>
                                <td>' . $pim . '</td>
                                <td>' . $pct . '</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>';
        } else {
            $stats_html = '<p class="pp-no-stats">No stats available for this player.</p>';
        }

        $number_display = $number ? '#' . $number : '';

        return '
<div class="pp-player-detail">
    <div class="pp-player-hero" style="background-image: url(\'' . $headshot . '\');">
        <div class="pp-player-hero-overlay"></div>
        <div class="pp-player-headshot-wrap">
            <img
                src="' . $headshot . '"
                onerror="this.onerror=null;this.src=\'' . $fallback . '\';"
                alt="' . $name . '"
                class="pp-player-headshot"
            />
        </div>
        <div class="pp-player-identity">
            ' . ($number_display ? '<span class="pp-player-number">' . $number_display . '</span>' : '') . '
            <span class="pp-player-name">' . $name . '</span>
            ' . ($pos_label ? '<span class="pp-player-pos-badge">' . esc_html($pos_label) . '</span>' : '') . '
        </div>
    </div>
    <div class="pp-player-body">
        <a href="#" class="pp-player-back-btn">&#8592; Back to Roster</a>
        <div class="pp-player-tabs-bar">
            <button class="pp-player-tab pp-tab-active" data-tab="bio">Bio</button>
            <button class="pp-player-tab" data-tab="stats">Stats</button>
            <button class="pp-player-tab" data-tab="related">Related</button>
            <button class="pp-player-tab" data-tab="historical">Historical</button>
        </div>
        <div class="pp-player-tab-panels">
            <div id="pp-panel-bio" class="pp-player-tab-panel pp-panel-active">
                <div class="pp-bio-grid">' . $bio_html . '</div>
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
