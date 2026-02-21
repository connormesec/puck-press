<?php

class Puck_Press_Roster_Admin_Stats_Table_Card extends Puck_Press_Admin_Card_Abstract
{
    public function render()
    {
        $stats = $this->get_stats();

        if ( empty( $stats ) ) {
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
        $stats = $this->get_stats();

        if ( empty( $stats ) ) {
            return '';
        }

        ob_start();
        ?>
        <div style="overflow-x: auto;">
            <table class="pp-table" id="pp-roster-stats-table">
                <thead class="pp-thead">
                    <tr>
                        <th class="pp-th" title="Rank">#</th>
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
                    <?php foreach ( $stats as $row ) : ?>
                        <tr>
                            <td class="pp-td" style="text-align:center;"><?php echo esc_html( $row->rank ?? '' ); ?></td>
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
        <?php
        return ob_get_clean();
    }

    protected function render_header_button_content()
    {
        return '';
    }

    private function get_stats()
    {
        global $wpdb;
        $stats_table   = $wpdb->prefix . 'pp_roster_stats';
        $display_table = $wpdb->prefix . 'pp_roster_for_display';

        if ( $wpdb->get_var( $wpdb->prepare( "SHOW TABLES LIKE %s", $stats_table ) ) !== $stats_table ) {
            return [];
        }

        return $wpdb->get_results(
            "SELECT s.*, r.name AS player_name
             FROM $stats_table s
             LEFT JOIN $display_table r ON s.player_id = r.player_id
             ORDER BY s.rank ASC",
            OBJECT
        ) ?: [];
    }
}
