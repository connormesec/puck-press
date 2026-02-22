<?php

class Puck_Press_Roster_Admin_Stats_Table_Card extends Puck_Press_Admin_Card_Abstract
{
    public function render()
    {
        $skater_stats = $this->get_skater_stats();
        $goalie_stats = $this->get_goalie_stats();

        if ( empty( $skater_stats ) && empty( $goalie_stats ) ) {
            return '';
        }

        return "
            <div class='pp-card' id='pp-card-{$this->id}'>
                {$this->render_header()}
                <div class='pp-card-content' id='pp-card-content-{$this->id}'>
                    {$this->render_content()}
                </div>
            </div>
        ";
    }

    protected function render_content()
    {
        $skater_stats = $this->get_skater_stats();
        $goalie_stats = $this->get_goalie_stats();

        if ( empty( $skater_stats ) && empty( $goalie_stats ) ) {
            return '';
        }

        ob_start();

        if ( ! empty( $skater_stats ) ) :
        ?>
        <h3 style="margin: 0 0 12px; font-size: 15px; font-weight: 600;">Skater Stats</h3>
        <div style="overflow-x: auto; margin-bottom: 32px;">
            <table class="pp-table" id="pp-roster-skater-stats-table">
                <thead class="pp-thead">
                    <tr>
                        <th class="pp-th" title="Number">No.</th>
                        <th class="pp-th">Name</th>
                        <th class="pp-th">Source</th>
                        <th class="pp-th" title="Games Played">GP</th>
                        <th class="pp-th" title="Goals">G</th>
                        <th class="pp-th" title="Assists">A</th>
                        <th class="pp-th" title="Points">PTS</th>
                        <th class="pp-th" title="Points per Game">Pt/G</th>
                        <th class="pp-th" title="Power Play Goals">PPG</th>
                        <th class="pp-th" title="Short Handed Goals">SHG</th>
                        <th class="pp-th" title="Game Winning Goals">GWG</th>
                        <th class="pp-th" title="Shootout Winning Goals">SOGW</th>
                        <th class="pp-th" title="Penalty Minutes">PIM</th>
                        <th class="pp-th" title="Shooting Percentage">SH%</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ( $skater_stats as $row ) : ?>
                        <tr>
                            <td class="pp-td" style="text-align:center;"><?php echo esc_html( $row->player_number ?? '' ); ?></td>
                            <td class="pp-td"><?php echo esc_html( $row->player_name ?? $row->player_id ); ?></td>
                            <td class="pp-td"><?php echo esc_html( $row->source ); ?></td>
                            <td class="pp-td" style="text-align:center;"><?php echo esc_html( $row->games_played ?? '' ); ?></td>
                            <td class="pp-td" style="text-align:center;"><?php echo esc_html( $row->goals ?? '' ); ?></td>
                            <td class="pp-td" style="text-align:center;"><?php echo esc_html( $row->assists ?? '' ); ?></td>
                            <td class="pp-td" style="text-align:center;font-weight:600;"><?php echo esc_html( $row->points ?? '' ); ?></td>
                            <td class="pp-td" style="text-align:center;"><?php echo esc_html( $row->points_per_game ?? '' ); ?></td>
                            <td class="pp-td" style="text-align:center;"><?php echo esc_html( $row->power_play_goals ?? '' ); ?></td>
                            <td class="pp-td" style="text-align:center;"><?php echo esc_html( $row->short_handed_goals ?? '' ); ?></td>
                            <td class="pp-td" style="text-align:center;"><?php echo esc_html( $row->game_winning_goals ?? '' ); ?></td>
                            <td class="pp-td" style="text-align:center;"><?php echo esc_html( $row->shootout_winning_goals ?? '' ); ?></td>
                            <td class="pp-td" style="text-align:center;"><?php echo esc_html( $row->penalty_minutes ?? '' ); ?></td>
                            <td class="pp-td" style="text-align:center;"><?php echo esc_html( $row->shooting_percentage ?? '' ); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>

        <?php if ( ! empty( $goalie_stats ) ) : ?>
        <h3 style="margin: 0 0 12px; font-size: 15px; font-weight: 600;">Goalie Stats</h3>
        <div style="overflow-x: auto;">
            <table class="pp-table" id="pp-roster-goalie-stats-table">
                <thead class="pp-thead">
                    <tr>
                        <th class="pp-th" title="Number">No.</th>
                        <th class="pp-th">Name</th>
                        <th class="pp-th">Source</th>
                        <th class="pp-th" title="Games Played">GP</th>
                        <th class="pp-th" title="Wins">W</th>
                        <th class="pp-th" title="Losses">L</th>
                        <th class="pp-th" title="Overtime Losses">OTL</th>
                        <th class="pp-th" title="Shootout Losses">SOL</th>
                        <th class="pp-th" title="Shootout Wins">SOW</th>
                        <th class="pp-th" title="Shots Against">SA</th>
                        <th class="pp-th" title="Saves">SV</th>
                        <th class="pp-th" title="Save Percentage">SV%</th>
                        <th class="pp-th" title="Goals Against Average">GAA</th>
                        <th class="pp-th" title="Goals Against">GA</th>
                        <th class="pp-th" title="Goals">G</th>
                        <th class="pp-th" title="Assists">A</th>
                        <th class="pp-th" title="Penalty Minutes">PIM</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ( $goalie_stats as $row ) : ?>
                        <tr>
                            <td class="pp-td" style="text-align:center;"><?php echo esc_html( $row->player_number ?? '' ); ?></td>
                            <td class="pp-td"><?php echo esc_html( $row->player_name ?? $row->player_id ); ?></td>
                            <td class="pp-td"><?php echo esc_html( $row->source ); ?></td>
                            <td class="pp-td" style="text-align:center;"><?php echo esc_html( $row->games_played ?? '' ); ?></td>
                            <td class="pp-td" style="text-align:center;font-weight:600;"><?php echo esc_html( $row->wins ?? '' ); ?></td>
                            <td class="pp-td" style="text-align:center;"><?php echo esc_html( $row->losses ?? '' ); ?></td>
                            <td class="pp-td" style="text-align:center;"><?php echo esc_html( $row->overtime_losses ?? '' ); ?></td>
                            <td class="pp-td" style="text-align:center;"><?php echo esc_html( $row->shootout_losses ?? '' ); ?></td>
                            <td class="pp-td" style="text-align:center;"><?php echo esc_html( $row->shootout_wins ?? '' ); ?></td>
                            <td class="pp-td" style="text-align:center;"><?php echo esc_html( $row->shots_against ?? '' ); ?></td>
                            <td class="pp-td" style="text-align:center;"><?php echo esc_html( $row->saves ?? '' ); ?></td>
                            <td class="pp-td" style="text-align:center;"><?php echo esc_html( $row->save_percentage ?? '' ); ?></td>
                            <td class="pp-td" style="text-align:center;"><?php echo esc_html( $row->goals_against_average ?? '' ); ?></td>
                            <td class="pp-td" style="text-align:center;"><?php echo esc_html( $row->goals_against ?? '' ); ?></td>
                            <td class="pp-td" style="text-align:center;"><?php echo esc_html( $row->goals ?? '' ); ?></td>
                            <td class="pp-td" style="text-align:center;"><?php echo esc_html( $row->assists ?? '' ); ?></td>
                            <td class="pp-td" style="text-align:center;"><?php echo esc_html( $row->penalty_minutes ?? '' ); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif;

        return ob_get_clean();
    }

    protected function render_header_button_content()
    {
        return '';
    }

    private function get_skater_stats()
    {
        global $wpdb;
        $stats_table   = $wpdb->prefix . 'pp_roster_stats';
        $display_table = $wpdb->prefix . 'pp_roster_for_display';

        if ( $wpdb->get_var( $wpdb->prepare( "SHOW TABLES LIKE %s", $stats_table ) ) !== $stats_table ) {
            return [];
        }

        return $wpdb->get_results(
            "SELECT s.*, r.name AS player_name, r.number AS player_number
             FROM $stats_table s
             LEFT JOIN $display_table r ON s.player_id = r.player_id
             WHERE UPPER(COALESCE(r.pos, '')) != 'G'
             ORDER BY s.`rank` ASC",
            OBJECT
        ) ?: [];
    }

    private function get_goalie_stats()
    {
        global $wpdb;
        $stats_table   = $wpdb->prefix . 'pp_roster_goalie_stats';
        $display_table = $wpdb->prefix . 'pp_roster_for_display';

        if ( $wpdb->get_var( $wpdb->prepare( "SHOW TABLES LIKE %s", $stats_table ) ) !== $stats_table ) {
            return [];
        }

        return $wpdb->get_results(
            "SELECT s.*, r.name AS player_name, r.number AS player_number
             FROM $stats_table s
             LEFT JOIN $display_table r ON s.player_id = r.player_id
             ORDER BY s.wins DESC, s.games_played DESC",
            OBJECT
        ) ?: [];
    }
}
