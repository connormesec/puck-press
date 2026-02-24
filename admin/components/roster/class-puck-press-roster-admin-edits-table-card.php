<?php
class Puck_Press_Roster_Admin_Edits_Table_Card extends Puck_Press_Admin_Card_Abstract
{
    public $table_name = 'pp_roster_mods';

    public function render_content()
    {
        return $this->render_edits_table();
    }

    public function render_header_button_content()
    {
        return '<button class="pp-button pp-button-primary" id="pp-add-player-button">+ Add Player</button>';
    }

    public function render_edits_table()
    {
        global $wpdb;
        $display_table = $wpdb->prefix . 'pp_roster_for_display';
        $mods_table    = $wpdb->prefix . 'pp_roster_mods';
        $raw_table     = $wpdb->prefix . 'pp_roster_raw';

        // Active players: from for_display, LEFT JOIN update mods for override highlighting
        $active_players = $wpdb->get_results(
            "SELECT f.*, m.edit_data AS override_data, m.id AS mod_id
             FROM $display_table f
             LEFT JOIN $mods_table m ON f.player_id = m.external_id AND m.edit_action = 'update'",
            ARRAY_A
        ) ?: [];

        // Deleted sourced players: in raw but have a delete mod
        $deleted_players = $wpdb->get_results(
            "SELECT r.*, dm.id AS delete_mod_id
             FROM $raw_table r
             INNER JOIN $mods_table dm ON r.player_id = dm.external_id AND dm.edit_action = 'delete'",
            ARRAY_A
        ) ?: [];

        foreach ($active_players as &$p) {
            $p['row_status']    = 'active';
            $p['delete_mod_id'] = null;
        }
        unset($p);

        foreach ($deleted_players as &$p) {
            $p['row_status']    = 'deleted';
            $p['mod_id']        = null;
            $p['override_data'] = null;
        }
        unset($p);

        $players = array_merge($active_players, $deleted_players);

        usort($players, function ($a, $b) {
            return strcmp($a['name'] ?? '', $b['name'] ?? '');
        });

        if (empty($players)) {
            return '<table class="pp-table pp-roster-table-full" id="pp-roster-edits-table"><caption>' . esc_html__('No players in roster. Add a source or click "+ Add Player" to get started.', 'puck-press') . '</caption></table>';
        }

        $skip_keys = ['external_id'];

        ob_start();
    ?>
        <table class="pp-table pp-roster-table-full" id="pp-roster-edits-table">
            <thead class="pp-thead">
                <tr>
                    <th class="pp-th"><?php esc_html_e('ID', 'puck-press'); ?></th>
                    <th class="pp-th"><?php esc_html_e('Source', 'puck-press'); ?></th>
                    <th class="pp-th"><?php esc_html_e('#', 'puck-press'); ?></th>
                    <th class="pp-th"><?php esc_html_e('Name', 'puck-press'); ?></th>
                    <th class="pp-th"><?php esc_html_e('Pos', 'puck-press'); ?></th>
                    <th class="pp-th"><?php esc_html_e('Ht', 'puck-press'); ?></th>
                    <th class="pp-th"><?php esc_html_e('Wt', 'puck-press'); ?></th>
                    <th class="pp-th"><?php esc_html_e('Shoots', 'puck-press'); ?></th>
                    <th class="pp-th"><?php esc_html_e('Hometown', 'puck-press'); ?></th>
                    <th class="pp-th"><?php esc_html_e('Last Team', 'puck-press'); ?></th>
                    <th class="pp-th"><?php esc_html_e('Year', 'puck-press'); ?></th>
                    <th class="pp-th"><?php esc_html_e('Major', 'puck-press'); ?></th>
                    <th class="pp-th"><?php esc_html_e('Headshot', 'puck-press'); ?></th>
                    <th class="pp-th"><?php esc_html_e('Hero Image', 'puck-press'); ?></th>
                    <th class="pp-th"><?php esc_html_e('Actions', 'puck-press'); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($players as $player) :
                    $is_deleted  = $player['row_status'] === 'deleted';
                    $is_manual   = strpos($player['player_id'], 'manual_') === 0;
                    $source_type = $is_manual ? 'manual' : 'sourced';
                    $source_tag_class = $is_manual ? 'pp-tag-manual' : 'pp-tag-regular-season';

                    $override_keys = [];
                    if (!$is_deleted && !$is_manual && !empty($player['override_data'])) {
                        $decoded = json_decode($player['override_data'], true);
                        if (is_array($decoded)) {
                            $non_empty = array_filter($decoded, function ($v) { return $v !== null && $v !== ''; });
                            $override_keys = array_values(array_diff(array_keys($non_empty), $skip_keys));
                        }
                    }
                    $mod_id = $player['mod_id'] ?? '';
                ?>
                    <?php if ($is_deleted) : ?>
                    <tr class="pp-row-deleted"
                        data-player-id="<?php echo esc_attr($player['player_id']); ?>"
                        data-source-type="sourced"
                        data-mod-id=""
                        data-overrides="[]">
                        <td class="pp-td" data-field="player_id"><?php echo esc_html($player['player_id']); ?></td>
                        <td class="pp-td"><span class="pp-tag pp-tag-regular-season"><?php echo esc_html($player['source']); ?></span></td>
                        <td class="pp-td" data-field="number"><?php echo esc_html($player['number']); ?></td>
                        <td class="pp-td" data-field="name"><?php echo esc_html($player['name']); ?></td>
                        <td class="pp-td" data-field="pos"><?php echo esc_html($player['pos']); ?></td>
                        <td class="pp-td" data-field="ht"><?php echo esc_html($player['ht']); ?></td>
                        <td class="pp-td" data-field="wt"><?php echo esc_html($player['wt']); ?></td>
                        <td class="pp-td" data-field="shoots"><?php echo esc_html($player['shoots']); ?></td>
                        <td class="pp-td" data-field="hometown"><?php echo esc_html($player['hometown']); ?></td>
                        <td class="pp-td" data-field="last_team"><?php echo esc_html($player['last_team']); ?></td>
                        <td class="pp-td" data-field="year_in_school"><?php echo esc_html($player['year_in_school']); ?></td>
                        <td class="pp-td" data-field="major"><?php echo esc_html($player['major']); ?></td>
                        <td class="pp-td" data-field="headshot_link">
                            <?php if (!empty($player['headshot_link'])): ?>
                                <img src="<?php echo esc_url($player['headshot_link']); ?>" style="max-width:60px;height:auto;border-radius:2px;" alt="headshot">
                            <?php else: ?>
                                <span style="color:#999">&mdash;</span>
                            <?php endif; ?>
                        </td>
                        <td class="pp-td">
                            <?php if (!empty($player['hero_image_url'])): ?>
                                <img src="<?php echo esc_url($player['hero_image_url']); ?>" style="max-width:60px;height:auto;border-radius:2px;" alt="hero">
                            <?php else: ?>
                                <span style="color:#999">&mdash;</span>
                            <?php endif; ?>
                        </td>
                        <td class="pp-td">
                            <div class="pp-flex-small-gap">
                                <button class="pp-button-icon pp-restore-player-btn" title="Restore player"
                                    data-delete-mod-id="<?php echo esc_attr($player['delete_mod_id']); ?>">&#x21A9;</button>
                            </div>
                        </td>
                    </tr>
                    <?php else : ?>
                    <tr data-player-id="<?php echo esc_attr($player['player_id']); ?>"
                        data-source-type="<?php echo esc_attr($source_type); ?>"
                        data-mod-id="<?php echo esc_attr($mod_id); ?>"
                        data-overrides="<?php echo esc_attr(wp_json_encode($override_keys)); ?>">
                        <td class="pp-td"><?php echo esc_html($player['player_id']); ?></td>
                        <td class="pp-td"><span class="pp-tag <?php echo esc_attr($source_tag_class); ?>"><?php echo esc_html($player['source']); ?></span></td>
                        <td class="pp-td" data-field="number"><?php echo esc_html($player['number']); ?></td>
                        <td class="pp-td" data-field="name"><?php echo esc_html($player['name']); ?></td>
                        <td class="pp-td" data-field="pos"><?php echo esc_html($player['pos']); ?></td>
                        <td class="pp-td" data-field="ht"><?php echo esc_html($player['ht']); ?></td>
                        <td class="pp-td" data-field="wt"><?php echo esc_html($player['wt']); ?></td>
                        <td class="pp-td" data-field="shoots"><?php echo esc_html($player['shoots']); ?></td>
                        <td class="pp-td" data-field="hometown"><?php echo esc_html($player['hometown']); ?></td>
                        <td class="pp-td" data-field="last_team"><?php echo esc_html($player['last_team']); ?></td>
                        <td class="pp-td" data-field="year_in_school"><?php echo esc_html($player['year_in_school']); ?></td>
                        <td class="pp-td" data-field="major"><?php echo esc_html($player['major']); ?></td>
                        <td class="pp-td" data-field="headshot_link">
                            <?php if (!empty($player['headshot_link'])): ?>
                                <img src="<?php echo esc_url($player['headshot_link']); ?>" style="max-width:60px;height:auto;border-radius:2px;" alt="headshot">
                            <?php else: ?>
                                <span style="color:#999">&mdash;</span>
                            <?php endif; ?>
                        </td>
                        <td class="pp-td" data-field="hero_image_url">
                            <?php if (!empty($player['hero_image_url'])): ?>
                                <img src="<?php echo esc_url($player['hero_image_url']); ?>" style="max-width:60px;height:auto;border-radius:2px;" alt="hero">
                            <?php else: ?>
                                <span style="color:#999">&mdash;</span>
                            <?php endif; ?>
                        </td>
                        <td class="pp-td">
                            <div class="pp-flex-small-gap">
                                <button class="pp-button-icon pp-edit-player-btn" title="Edit player"
                                    data-player-id="<?php echo esc_attr($player['player_id']); ?>">&#x270F;&#xFE0F;</button>
                                <button class="pp-button-icon pp-delete-player-btn" title="Delete player"
                                    data-player-id="<?php echo esc_attr($player['player_id']); ?>"
                                    data-source-type="<?php echo esc_attr($source_type); ?>">&#x1F5D1;&#xFE0F;</button>
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

    function ajax_refresh_roster_edits_table_card_callback()
    {
        $response_html = $this->render_edits_table();
        if ($response_html !== false) {
            wp_send_json_success($response_html);
        } else {
            wp_send_json_error(['message' => 'Edits table refresh failed']);
        }
        wp_die();
    }

    function ajax_get_player_data_callback()
    {
        global $wpdb;
        $player_id = sanitize_text_field($_POST['player_id'] ?? '');
        if (empty($player_id)) {
            wp_send_json_error(['message' => 'Missing player_id']);
            wp_die();
        }
        $display_table = $wpdb->prefix . 'pp_roster_for_display';
        $player = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $display_table WHERE player_id = %s LIMIT 1",
            $player_id
        ), ARRAY_A);
        if ($player) {
            wp_send_json_success(['player' => $player]);
        } else {
            wp_send_json_error(['message' => 'Player not found']);
        }
        wp_die();
    }

    function ajax_save_player_edit_callback()
    {
        global $wpdb;
        $table = $wpdb->prefix . 'pp_roster_mods';

        if (!isset($_POST['edit_data'])) {
            wp_send_json_error(['message' => 'Missing edit_data']);
            wp_die();
        }

        $parsed_data = json_decode(stripslashes($_POST['edit_data']), true);

        if (json_last_error() !== JSON_ERROR_NONE ||
            !isset($parsed_data['edit_action'], $parsed_data['fields']['external_id'])) {
            wp_send_json_error(['message' => 'Invalid or incomplete edit_data']);
            wp_die();
        }

        $edit_action   = sanitize_text_field($parsed_data['edit_action']);
        $external_id   = sanitize_text_field($parsed_data['fields']['external_id']);
        $edit_data_json = wp_json_encode($parsed_data['fields']);
        $current_time  = current_time('mysql');

        $existing_row_id = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM $table WHERE external_id = %s AND edit_action = %s LIMIT 1",
            $external_id, $edit_action
        ));

        if ($existing_row_id) {
            // Merge with existing edit_data to preserve prior overrides
            $existing_json = $wpdb->get_var($wpdb->prepare(
                "SELECT edit_data FROM $table WHERE id = %d",
                $existing_row_id
            ));
            $existing_fields = json_decode($existing_json, true) ?: [];
            $merged_fields   = array_merge($existing_fields, $parsed_data['fields']);
            $edit_data_json  = wp_json_encode($merged_fields);

            $wpdb->update(
                $table,
                ['edit_data' => $edit_data_json, 'updated_at' => $current_time],
                ['id' => $existing_row_id]
            );
        } else {
            $wpdb->insert($table, [
                'external_id' => $external_id,
                'edit_action' => $edit_action,
                'edit_data'   => $edit_data_json,
                'created_at'  => $current_time,
                'updated_at'  => $current_time,
            ]);
        }

        // Rebuild and normalize the display table
        $importer = new Puck_Press_Roster_Source_Importer();
        $importer->apply_edits_and_save_to_display_table();
        $importer->sanitize_roster_display_table();

        wp_send_json_success([
            'message'           => 'Edit saved',
            'roster_table_html' => $this->render_edits_table(),
        ]);
        wp_die();
    }

    function ajax_delete_player_edit_callback()
    {
        global $wpdb;
        $table = $wpdb->prefix . 'pp_roster_mods';
        $id    = isset($_POST['id']) ? sanitize_text_field($_POST['id']) : '';

        if (empty($id)) {
            wp_send_json_error(['message' => 'Missing required fields']);
            wp_die();
        }

        $result = $wpdb->delete($table, ['id' => $id], ['%s']);

        if ($result !== false) {
            // Rebuild and normalize the display table
            $importer = new Puck_Press_Roster_Source_Importer();
            $importer->apply_edits_and_save_to_display_table();
            $importer->sanitize_roster_display_table();

            wp_send_json_success([
                'message'           => 'Edit deleted',
                'roster_table_html' => $this->render_edits_table(),
            ]);
        } else {
            wp_send_json_error(['message' => 'Delete failed or record not found']);
        }

        wp_die();
    }

    function ajax_revert_player_field_callback()
    {
        global $wpdb;
        $table  = $wpdb->prefix . 'pp_roster_mods';
        $mod_id = intval($_POST['mod_id'] ?? 0);
        $fields = isset($_POST['fields']) ? (array) $_POST['fields'] : [];

        if (!$mod_id || empty($fields)) {
            wp_send_json_error(['message' => 'Missing mod_id or fields']);
            wp_die();
        }

        $existing_json = $wpdb->get_var($wpdb->prepare(
            "SELECT edit_data FROM $table WHERE id = %d AND edit_action = 'update'",
            $mod_id
        ));

        if ($existing_json === null) {
            wp_send_json_error(['message' => 'Mod record not found']);
            wp_die();
        }

        $edit_data = json_decode($existing_json, true) ?: [];

        foreach ($fields as $field) {
            $field = sanitize_key($field);
            unset($edit_data[$field]);
        }

        // Remove metadata key
        $meaningful_keys = array_diff(array_keys($edit_data), ['external_id']);

        if (empty($meaningful_keys)) {
            // No fields left — delete the entire mod record
            $wpdb->delete($table, ['id' => $mod_id], ['%d']);
        } else {
            $wpdb->update(
                $table,
                ['edit_data' => wp_json_encode($edit_data), 'updated_at' => current_time('mysql')],
                ['id' => $mod_id]
            );
        }

        // Rebuild and normalize the display table
        $importer = new Puck_Press_Roster_Source_Importer();
        $importer->apply_edits_and_save_to_display_table();
        $importer->sanitize_roster_display_table();

        wp_send_json_success([
            'message'           => 'Field reverted',
            'roster_table_html' => $this->render_edits_table(),
        ]);
        wp_die();
    }

    function ajax_add_manual_player_callback()
    {
        global $wpdb;
        $table = $wpdb->prefix . 'pp_roster_mods';

        $name         = sanitize_text_field($_POST['name'] ?? '');
        if (empty($name)) {
            wp_send_json_error(['message' => 'Player name is required']);
            wp_die();
        }

        $player_data = [
            'player_id'      => 'manual_placeholder',
            'source'         => 'Manual',
            'name'           => $name,
            'number'         => sanitize_text_field($_POST['number'] ?? ''),
            'pos'            => sanitize_text_field($_POST['pos'] ?? ''),
            'ht'             => sanitize_text_field($_POST['ht'] ?? ''),
            'wt'             => sanitize_text_field($_POST['wt'] ?? ''),
            'shoots'         => sanitize_text_field($_POST['shoots'] ?? ''),
            'hometown'       => sanitize_text_field($_POST['hometown'] ?? ''),
            'last_team'      => sanitize_text_field($_POST['last_team'] ?? ''),
            'year_in_school' => sanitize_text_field($_POST['year_in_school'] ?? ''),
            'major'          => sanitize_text_field($_POST['major'] ?? ''),
            'headshot_link'  => esc_url_raw($_POST['headshot_link'] ?? ''),
            'hero_image_url' => esc_url_raw($_POST['hero_image_url'] ?? ''),
        ];

        $current_time = current_time('mysql');
        $wpdb->insert($table, [
            'external_id' => null,
            'edit_action' => 'insert',
            'edit_data'   => wp_json_encode($player_data),
            'created_at'  => $current_time,
            'updated_at'  => $current_time,
        ]);

        $mod_id = $wpdb->insert_id;
        $player_data['player_id'] = 'manual_' . $mod_id;

        $wpdb->update(
            $table,
            [
                'external_id' => 'manual_' . $mod_id,
                'edit_data'   => wp_json_encode($player_data),
                'updated_at'  => $current_time,
            ],
            ['id' => $mod_id]
        );

        // Rebuild and normalize the display table
        $importer = new Puck_Press_Roster_Source_Importer();
        $importer->apply_edits_and_save_to_display_table();
        $importer->sanitize_roster_display_table();

        wp_send_json_success([
            'message'           => 'Player added',
            'roster_table_html' => $this->render_edits_table(),
        ]);
        wp_die();
    }

    function ajax_delete_manual_player_callback()
    {
        global $wpdb;
        $table     = $wpdb->prefix . 'pp_roster_mods';
        $player_id = sanitize_text_field($_POST['player_id'] ?? '');

        if (empty($player_id) || strpos($player_id, 'manual_') !== 0) {
            wp_send_json_error(['message' => 'Invalid player_id']);
            wp_die();
        }

        // Delete the insert mod row for this manual player
        $result = $wpdb->delete($table, ['external_id' => $player_id, 'edit_action' => 'insert']);

        if ($result !== false) {
            $importer = new Puck_Press_Roster_Source_Importer();
            $importer->apply_edits_and_save_to_display_table();
            $importer->sanitize_roster_display_table();

            wp_send_json_success([
                'message'           => 'Player deleted',
                'roster_table_html' => $this->render_edits_table(),
            ]);
        } else {
            wp_send_json_error(['message' => 'Delete failed']);
        }

        wp_die();
    }

    function ajax_reset_all_roster_edits_callback() {
        global $wpdb;

        $table_mods = $wpdb->prefix . 'pp_roster_mods';
        $wpdb->query( "TRUNCATE TABLE $table_mods" );

        $utils = new Puck_Press_Roster_Wpdb_Utils();
        $utils->reset_table( 'pp_roster_for_display' );

        $importer = new Puck_Press_Roster_Source_Importer();
        $importer->apply_edits_and_save_to_display_table();
        $importer->sanitize_roster_display_table();

        wp_send_json_success( [
            'message'           => 'All roster edits reset.',
            'roster_table_html' => $this->render_edits_table(),
        ] );
        wp_die();
    }
}
