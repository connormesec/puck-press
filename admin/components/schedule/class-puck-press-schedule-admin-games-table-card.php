<?php
class Puck_Press_Schedule_Admin_Games_Table_Card extends Puck_Press_Admin_Card_Abstract
{
    public function render_content()
    {
        return $this->render_game_schedule_admin_preview();
    }

    public function render_header_button_content()
    {
        return '<button class="pp-button pp-button-primary" id="pp-add-game-button">+ Add Game</button>';
    }

    public function render_game_schedule_admin_preview()
    {
        $table_name = 'pp_game_schedule_for_display';
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
                <?php foreach ($games as $game) :
                    $is_manual = strpos($game['game_id'], 'manual_') === 0;
                    $source_type = $is_manual ? 'manual' : 'sourced';
                    $source_tag_class = $is_manual ? 'pp-tag-manual' : 'pp-tag-regular-season';
                ?>
                    <tr data-id="<?php echo esc_attr($game['game_id']) ?>" data-source-type="<?php echo esc_attr($source_type); ?>">
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
                            <span class="pp-tag <?php echo esc_attr($source_tag_class); ?>">
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

    public function ajax_add_manual_game_callback()
    {
        global $wpdb;

        $table_mods = $wpdb->prefix . 'pp_game_schedule_mods';

        // Sanitize required inputs
        $game_date         = sanitize_text_field($_POST['game_date'] ?? '');
        $target_team_name  = sanitize_text_field($_POST['target_team_name'] ?? '');
        $opponent_team_name = sanitize_text_field($_POST['opponent_team_name'] ?? '');

        if (empty($game_date) || empty($target_team_name) || empty($opponent_team_name)) {
            wp_send_json_error(['message' => 'Missing required fields: date, target team, and opponent team.']);
            wp_die();
        }

        // Sanitize optional inputs
        $game_time           = sanitize_text_field($_POST['game_time'] ?? '');
        $target_team_id      = sanitize_text_field($_POST['target_team_id'] ?? '0');
        $target_team_nickname = sanitize_text_field($_POST['target_team_nickname'] ?? '');
        $target_team_logo    = esc_url_raw($_POST['target_team_logo'] ?? '');
        $target_score        = sanitize_text_field($_POST['target_score'] ?? '');
        $opponent_team_id    = sanitize_text_field($_POST['opponent_team_id'] ?? '0');
        $opponent_team_nickname = sanitize_text_field($_POST['opponent_team_nickname'] ?? '');
        $opponent_team_logo  = esc_url_raw($_POST['opponent_team_logo'] ?? '');
        $opponent_score      = sanitize_text_field($_POST['opponent_score'] ?? '');
        $home_or_away        = sanitize_text_field($_POST['home_or_away'] ?? 'home');
        $raw_status          = sanitize_text_field($_POST['game_status'] ?? 'none');
        $venue               = sanitize_text_field($_POST['venue'] ?? '');

        // Derive computed fields
        $game_timestamp = Puck_Press_Schedule_Source_Importer::get_game_timestamp($game_date, $game_time);
        $game_date_day  = Puck_Press_Schedule_Source_Importer::format_game_date_day($game_date, $game_time);

        // Resolve status and time
        if ($raw_status === 'none') {
            $game_status_val = null;
            $game_time_val   = !empty($game_time) ? date('g:i A', strtotime($game_time)) : null;
        } else {
            $game_status_val = Puck_Press_Schedule_Source_Importer::format_game_status($raw_status, null);
            $game_time_val   = null;
        }

        $current_time = current_time('mysql');

        // Build initial game data with placeholder game_id
        $game_data = [
            'game_id'               => 'manual_placeholder',
            'target_team_id'        => $target_team_id,
            'target_team_name'      => $target_team_name,
            'target_team_nickname'  => $target_team_nickname ?: null,
            'target_team_logo'      => $target_team_logo ?: null,
            'target_score'          => $target_score !== '' ? $target_score : null,
            'opponent_team_id'      => $opponent_team_id,
            'opponent_team_name'    => $opponent_team_name,
            'opponent_team_nickname' => $opponent_team_nickname ?: null,
            'opponent_team_logo'    => $opponent_team_logo ?: null,
            'opponent_score'        => $opponent_score !== '' ? $opponent_score : null,
            'game_status'           => $game_status_val,
            'game_date_day'         => $game_date_day,
            'game_time'             => $game_time_val,
            'game_timestamp'        => $game_timestamp,
            'home_or_away'          => $home_or_away,
            'venue'                 => $venue ?: null,
            'source'                => 'Manual',
            'source_type'           => 'manual',
        ];

        // Insert the mod row with placeholder game_id
        $inserted = $wpdb->insert(
            $table_mods,
            [
                'external_id' => null,
                'edit_action' => 'insert',
                'edit_data'   => wp_json_encode($game_data),
                'created_at'  => $current_time,
                'updated_at'  => $current_time,
            ],
            ['%s', '%s', '%s', '%s', '%s']
        );

        if (!$inserted) {
            wp_send_json_error(['message' => 'Failed to insert manual game mod.']);
            wp_die();
        }

        // Now we have the real mod ID — update game_id to 'manual_{id}'
        $mod_id = $wpdb->insert_id;
        $game_data['game_id'] = 'manual_' . $mod_id;

        $wpdb->update(
            $table_mods,
            ['edit_data' => wp_json_encode($game_data)],
            ['id' => $mod_id]
        );

        // Rebuild the for_display table
        $utils = new Puck_Press_Schedule_Wpdb_Utils();
        $utils->reset_table('pp_game_schedule_for_display');
        $importer = new Puck_Press_Schedule_Source_Importer();
        $importer->apply_edits_and_save_to_display_table();

        // Return refreshed UI
        $games_table_card = new Puck_Press_Schedule_Admin_Games_Table_Card();
        $games_table_html = $games_table_card->render_game_schedule_admin_preview();

        $edits_card = new Puck_Press_Schedule_Admin_Edits_Table_Card();
        $edits_table_html = $edits_card->render_edits_table();

        wp_send_json_success([
            'message'          => 'Manual game added.',
            'game_id'          => $game_data['game_id'],
            'games_table_html' => $games_table_html,
            'edits_table_html' => $edits_table_html,
        ]);
        wp_die();
    }

    public function ajax_delete_manual_game_callback()
    {
        global $wpdb;

        $table_mods = $wpdb->prefix . 'pp_game_schedule_mods';

        $game_id = sanitize_text_field($_POST['game_id'] ?? '');

        if (empty($game_id) || strpos($game_id, 'manual_') !== 0) {
            wp_send_json_error(['message' => 'Invalid game_id for manual game deletion.']);
            wp_die();
        }

        $mod_id = intval(str_replace('manual_', '', $game_id));

        if ($mod_id <= 0) {
            wp_send_json_error(['message' => 'Could not parse mod ID from game_id.']);
            wp_die();
        }

        $deleted = $wpdb->delete($table_mods, ['id' => $mod_id], ['%d']);

        if ($deleted === false) {
            wp_send_json_error(['message' => 'Failed to delete manual game mod.']);
            wp_die();
        }

        // Rebuild the for_display table
        $utils = new Puck_Press_Schedule_Wpdb_Utils();
        $utils->reset_table('pp_game_schedule_for_display');
        $importer = new Puck_Press_Schedule_Source_Importer();
        $importer->apply_edits_and_save_to_display_table();

        // Return refreshed UI
        $games_table_card = new Puck_Press_Schedule_Admin_Games_Table_Card();
        $games_table_html = $games_table_card->render_game_schedule_admin_preview();

        $edits_card = new Puck_Press_Schedule_Admin_Edits_Table_Card();
        $edits_table_html = $edits_card->render_edits_table();

        wp_send_json_success([
            'message'          => 'Manual game deleted.',
            'games_table_html' => $games_table_html,
            'edits_table_html' => $edits_table_html,
        ]);
        wp_die();
    }
}
