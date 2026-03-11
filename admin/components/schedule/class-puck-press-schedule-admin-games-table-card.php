<?php
class Puck_Press_Schedule_Admin_Games_Table_Card extends Puck_Press_Admin_Card_Abstract
{
    private int $schedule_id;

    public function __construct(array $args = [], int $schedule_id = 1)
    {
        parent::__construct($args);
        $this->schedule_id = $schedule_id;
    }

    public function render_content()
    {
        return $this->render_game_schedule_admin_preview();
    }

    public function render_header_button_content()
    {
        return '
            <button class="pp-button pp-button-secondary" id="pp-bulk-edit-schedule-btn">Bulk Edit Games</button>
            <button class="pp-button pp-button-primary" id="pp-add-game-button">+ Add Game</button>
        ';
    }

    public function render_game_schedule_admin_preview()
    {
        global $wpdb;
        $display_table = $wpdb->prefix . 'pp_game_schedule_for_display';
        $mods_table    = $wpdb->prefix . 'pp_game_schedule_mods';
        $raw_table     = $wpdb->prefix . 'pp_game_schedule_raw';

        // Active games: from for_display, LEFT JOIN update mods for override highlighting
        $active_games = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT f.*, m.edit_data AS override_data, m.id AS mod_id
                 FROM $display_table f
                 LEFT JOIN $mods_table m ON f.game_id COLLATE utf8mb4_unicode_ci = m.external_id COLLATE utf8mb4_unicode_ci AND m.edit_action = 'update' AND m.schedule_id = %d
                 WHERE f.schedule_id = %d",
                $this->schedule_id,
                $this->schedule_id
            ),
            ARRAY_A
        ) ?: [];

        // Deleted sourced games: in raw but have a delete mod (not in for_display)
        $deleted_games = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT r.*, dm.id AS delete_mod_id
                 FROM $raw_table r
                 INNER JOIN $mods_table dm ON r.game_id COLLATE utf8mb4_unicode_ci = dm.external_id COLLATE utf8mb4_unicode_ci AND dm.edit_action = 'delete' AND dm.schedule_id = %d
                 WHERE r.schedule_id = %d",
                $this->schedule_id,
                $this->schedule_id
            ),
            ARRAY_A
        ) ?: [];

        foreach ($active_games as &$g) {
            $g['row_status']    = 'active';
            $g['delete_mod_id'] = null;
        }
        unset($g);

        foreach ($deleted_games as &$g) {
            $g['row_status']    = 'deleted';
            $g['mod_id']        = null;
            $g['override_data'] = null;
        }
        unset($g);

        $games = array_merge($active_games, $deleted_games);

        usort($games, function ($a, $b) {
            return strcmp($a['game_timestamp'] ?? '', $b['game_timestamp'] ?? '');
        });

        if (empty($games)) {
            return '<table class="pp-table" id="pp-games-table"><caption>' . esc_html__('No games scheduled yet.', 'puck-press') . '</caption></table>';
        }

        $skip_keys     = ['external_id', 'game_timestamp', 'game_date_day'];
        $hidden_fields = ['promo_header', 'promo_text', 'promo_img_url', 'promo_ticket_link'];

        ob_start();
    ?>
        <table class="pp-table pp-games-table-full" id="pp-games-table">
            <thead class="pp-thead">
                <tr>
                    <th class="pp-th"><?php esc_html_e('ID', 'puck-press'); ?></th>
                    <th class="pp-th"><?php esc_html_e('Date', 'puck-press'); ?></th>
                    <th class="pp-th"><?php esc_html_e('Time', 'puck-press'); ?></th>
                    <th class="pp-th"><?php esc_html_e('Target Team', 'puck-press'); ?></th>
                    <th class="pp-th"><?php esc_html_e('Score', 'puck-press'); ?></th>
                    <th class="pp-th"><?php esc_html_e('Opponent', 'puck-press'); ?></th>
                    <th class="pp-th"><?php esc_html_e('Score', 'puck-press'); ?></th>
                    <th class="pp-th"><?php esc_html_e('Location', 'puck-press'); ?></th>
                    <th class="pp-th"><?php esc_html_e('Status', 'puck-press'); ?></th>
                    <th class="pp-th"><?php esc_html_e('H/A', 'puck-press'); ?></th>
                    <th class="pp-th"><?php esc_html_e('Source', 'puck-press'); ?></th>
                    <th class="pp-th"><?php esc_html_e('Post Summary', 'puck-press'); ?></th>
                    <th class="pp-th"><?php esc_html_e('Actions', 'puck-press'); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($games as $game) :
                    $is_deleted   = $game['row_status'] === 'deleted';
                    $is_manual    = strpos($game['game_id'], 'manual_') === 0;
                    $source_type  = $is_manual ? 'manual' : 'sourced';
                    $source_tag_class = $is_manual ? 'pp-tag-manual' : 'pp-tag-regular-season';

                    $override_keys = [];
                    if (!$is_deleted && !empty($game['override_data'])) {
                        $decoded = json_decode($game['override_data'], true);
                        if (is_array($decoded)) {
                            $override_keys = array_values(array_diff(array_keys($decoded), $skip_keys));
                        }
                    }
                    $mod_id = $game['mod_id'] ?? '';
                    $has_hidden_overrides = !$is_deleted && !empty(array_intersect($override_keys, $hidden_fields));
                ?>
                    <?php if ($is_deleted) : ?>
                    <tr class="pp-row-deleted"
                        data-id="<?php echo esc_attr($game['game_id']); ?>"
                        data-source-type="sourced"
                        data-overrides="[]">
                        <td class="pp-td pp-td-compact"><?php echo esc_html($game['game_id']); ?></td>
                        <td class="pp-td pp-td-compact"><?php echo esc_html(date('M d, Y', strtotime($game['game_timestamp']))); ?></td>
                        <td class="pp-td pp-td-compact"><?php echo esc_html($game['game_time'] ?? ''); ?></td>
                        <td class="pp-td pp-td-compact"><?php echo esc_html($game['target_team_name'] ?? ''); ?></td>
                        <td class="pp-td pp-td-compact"><?php echo $game['target_score'] !== null ? esc_html($game['target_score']) : '—'; ?></td>
                        <td class="pp-td pp-td-compact"><?php echo esc_html($game['opponent_team_name'] ?? ''); ?></td>
                        <td class="pp-td pp-td-compact"><?php echo $game['opponent_score'] !== null ? esc_html($game['opponent_score']) : '—'; ?></td>
                        <td class="pp-td pp-td-compact"><?php echo esc_html($game['venue'] ?? ''); ?></td>
                        <td class="pp-td pp-td-compact">
                            <?php if (!empty($game['game_status'])) : ?>
                                <span class="pp-tag pp-tag-<?php echo sanitize_html_class(strtolower($game['game_status'])); ?>">
                                    <?php echo esc_html(ucfirst((string) $game['game_status'])); ?>
                                </span>
                            <?php endif; ?>
                        </td>
                        <td class="pp-td pp-td-compact"><?php echo esc_html(ucfirst($game['home_or_away'] ?? '')); ?></td>
                        <td class="pp-td pp-td-compact">
                            <span class="pp-tag <?php echo esc_attr($source_tag_class); ?>">
                                <?php echo esc_html($game['source'] ?? 'No Source'); ?>
                            </span>
                        </td>
                        <td class="pp-td pp-td-compact"></td>
                        <td class="pp-td pp-td-compact">
                            <button class="pp-button-icon pp-restore-game-button" title="<?php esc_attr_e('Restore game', 'puck-press'); ?>" data-delete-mod-id="<?php echo esc_attr($game['delete_mod_id']); ?>">↩</button>
                        </td>
                    </tr>
                    <?php else : ?>
                    <tr
                        data-id="<?php echo esc_attr($game['game_id']); ?>"
                        data-source-type="<?php echo esc_attr($source_type); ?>"
                        data-mod-id="<?php echo esc_attr($mod_id); ?>"
                        data-overrides="<?php echo esc_attr(wp_json_encode($override_keys)); ?>"
                        data-home-or-away="<?php echo esc_attr(strtolower($game['home_or_away'] ?? '')); ?>"
                        data-venue="<?php echo esc_attr($game['venue'] ?? ''); ?>"
                        data-timestamp="<?php echo esc_attr(!empty($game['game_timestamp']) ? strtotime($game['game_timestamp']) : '0'); ?>"
                        data-opponent="<?php echo esc_attr($game['opponent_team_name'] ?? ''); ?>">
                        <td class="pp-td pp-td-compact"><?php echo esc_html($game['game_id']); ?></td>
                        <td class="pp-td pp-td-compact" data-field="game_date"><?php echo esc_html(date('M d, Y', strtotime($game['game_timestamp']))); ?></td>
                        <td class="pp-td pp-td-compact" data-field="game_time"><?php echo esc_html($game['game_time'] ?? ''); ?></td>
                        <td class="pp-td pp-td-compact"><?php echo esc_html($game['target_team_name']); ?></td>
                        <td class="pp-td pp-td-compact" data-field="target_score"><?php echo $game['target_score'] !== null ? esc_html($game['target_score']) : '—'; ?></td>
                        <td class="pp-td pp-td-compact"><?php echo esc_html($game['opponent_team_name']); ?></td>
                        <td class="pp-td pp-td-compact" data-field="opponent_score"><?php echo $game['opponent_score'] !== null ? esc_html($game['opponent_score']) : '—'; ?></td>
                        <td class="pp-td pp-td-compact" data-field="venue"><?php echo esc_html($game['venue']); ?></td>
                        <td class="pp-td pp-td-compact" data-field="game_status">
                            <?php if (!is_null($game['game_status'])) : ?>
                                <span class="pp-tag pp-tag-<?php echo sanitize_html_class(strtolower($game['game_status'])); ?>">
                                    <?php echo esc_html(ucfirst((string) ($game['game_status'] ?? ''))); ?>
                                </span>
                            <?php endif; ?>
                        </td>
                        <td class="pp-td pp-td-compact" data-field="home_or_away"><?php echo esc_html(ucfirst($game['home_or_away'] ?? '')); ?></td>
                        <td class="pp-td pp-td-compact">
                            <span class="pp-tag <?php echo esc_attr($source_tag_class); ?>">
                                <?php echo esc_html(isset($game['source']) ? $game['source'] : 'No Source'); ?>
                            </span>
                            <?php if ($has_hidden_overrides) : ?>
                                <span class="pp-tag pp-tag-has-hidden-edits" title="<?php esc_attr_e('Has additional edits (promo content)', 'puck-press'); ?>">+edits</span>
                            <?php endif; ?>
                        </td>
                        <td class="pp-td pp-td-compact">
                            <?php if (!empty($game['post_link'])) : ?>
                                <a href="<?php echo esc_url($game['post_link']); ?>" target="_blank" title="<?php echo esc_attr($game['post_link']); ?>" class="pp-post-link-icon pp-tag pp-tag-regular-season">📰 Post Summary</a>
                            <?php endif; ?>
                        </td>
                        <td class="pp-td pp-td-compact">
                            <div class="pp-flex-small-gap">
                                <button class="pp-button-icon" id="pp-edit-game-button" data-game-id="<?php echo esc_attr($game['game_id']); ?>">✏️</button>
                                <button class="pp-button-icon" id="pp-delete-game-button" data-game-id="<?php echo esc_attr($game['game_id']); ?>">🗑️</button>
                            </div>
                        </td>
                    </tr>
                    <?php endif; ?>
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

        $manual_game_schedule_id = (int) ($_POST['schedule_id'] ?? 1);

        // Insert the mod row with placeholder game_id
        $inserted = $wpdb->insert(
            $table_mods,
            [
                'schedule_id' => $manual_game_schedule_id,
                'external_id' => null,
                'edit_action' => 'insert',
                'edit_data'   => wp_json_encode($game_data),
                'created_at'  => $current_time,
                'updated_at'  => $current_time,
            ],
            ['%d', '%s', '%s', '%s', '%s', '%s']
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
        $manual_schedule_id = (int) ($_POST['schedule_id'] ?? 1);
        $utils = new Puck_Press_Schedule_Wpdb_Utils();
        $utils->delete_rows_for_schedule('pp_game_schedule_for_display', $manual_schedule_id);
        $importer = new Puck_Press_Schedule_Source_Importer($manual_schedule_id);
        $importer->apply_edits_and_save_to_display_table();

        // Return refreshed UI
        $games_table_card = new Puck_Press_Schedule_Admin_Games_Table_Card([], $manual_schedule_id);
        $games_table_html = $games_table_card->render_game_schedule_admin_preview();

        wp_send_json_success([
            'message'          => 'Manual game added.',
            'game_id'          => $game_data['game_id'],
            'games_table_html' => $games_table_html,
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
        $delete_schedule_id = (int) ($_POST['schedule_id'] ?? 1);
        $utils = new Puck_Press_Schedule_Wpdb_Utils();
        $utils->delete_rows_for_schedule('pp_game_schedule_for_display', $delete_schedule_id);
        $importer = new Puck_Press_Schedule_Source_Importer($delete_schedule_id);
        $importer->apply_edits_and_save_to_display_table();

        // Return refreshed UI
        $games_table_card = new Puck_Press_Schedule_Admin_Games_Table_Card([], $delete_schedule_id);
        $games_table_html = $games_table_card->render_game_schedule_admin_preview();

        wp_send_json_success([
            'message'          => 'Manual game deleted.',
            'games_table_html' => $games_table_html,
        ]);
        wp_die();
    }
}
