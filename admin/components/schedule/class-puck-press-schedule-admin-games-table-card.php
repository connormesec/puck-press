<?php
class Puck_Press_Schedule_Admin_Games_Table_Card extends Puck_Press_Admin_Card_Abstract
{
    public function render_content()
    {
        return $this->render_game_schedule_admin_preview();
    }

    public function render_header_button_content()
    {
        return '';
    }

    public function render_game_schedule_admin_preview()
    {
        $table_name = 'pp_game_schedule_raw';
        $schedule_db_utils = new Puck_Press_Schedule_Wpdb_Utils;
        $games = $schedule_db_utils->get_all_table_data($table_name, 'ARRAY_A');
        if ($games == null) {
            return '<table class="pp-table" id="pp-games-table"><caption>' . esc_html__('No games scheduled yet.', 'puck-press') . '</caption></table>';
        }

        ob_start();
    ?>
        <table class="pp-table" id="pp-games-table">
            <thead class="pp-thead">
                <tr>
                    <th class="pp-th"><?php esc_html_e('Date', 'puck-press'); ?></th>
                    <th class="pp-th"><?php esc_html_e('ID', 'puck-press'); ?></th>
                    <th class="pp-th"><?php esc_html_e('Opponent', 'puck-press'); ?></th>
                    <th class="pp-th"><?php esc_html_e('Location', 'puck-press'); ?></th>
                    <th class="pp-th"><?php esc_html_e('Status', 'puck-press'); ?></th>
                    <th class="pp-th"><?php esc_html_e('Source', 'puck-press'); ?></th>
                    <th class="pp-th"><?php esc_html_e('Actions', 'puck-press'); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($games as $game) : ?>
                    <tr data-id="<?php echo esc_html($game['source']) ?>">
                        <td class="pp-td"><?php echo esc_html(date('M d', strtotime($game['game_timestamp']))); ?></td>
                        <td class="pp-td"><?php echo esc_html(ucfirst($game['game_id'])); ?></td>
                        <td class="pp-td"><?php echo esc_html($game['opponent_team_name']); ?></td>
                        <td class="pp-td"><?php echo esc_html($game['venue']); ?></td>
                        <td class="pp-td">
                            <?php if (!is_null($game['game_status'])) : ?>
                                <span class="pp-tag pp-tag-<?php echo sanitize_html_class(strtolower($game['game_status'])); ?>">
                                    <?php echo esc_html(ucfirst((string) ($game['game_status'] ?? ''))); ?>
                                </span>
                            <?php endif; ?>
                        </td>
                        <td class="pp-td">
                            <span class="pp-tag pp-tag-regular-season">
                                <?php echo esc_html(isset($game['source']) ? $game['source'] : 'No Source'); ?>
                            </span>
                        </td>
                        <td class="pp-td">
                            <div class="pp-flex-small-gap">
                                <button class="pp-button-icon" id="pp-edit-game-button" data-game-id="<?php echo esc_attr($game['game_id']); ?>">✏️</button>
                                <button class="pp-button-icon" id="pp-delete-game-button" data-game-id="<?php echo esc_attr($game['game_id']); ?>">🗑️</button>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
<?php
        return ob_get_clean();
    }

    function console_log($output, $with_script_tags = true)
    {
        $js_code = 'console.log(' . json_encode($output, JSON_HEX_TAG) .
            ');';
        if ($with_script_tags) {
            $js_code = '<script>' . $js_code . '</script>';
        }
        echo $js_code;
    }
}
