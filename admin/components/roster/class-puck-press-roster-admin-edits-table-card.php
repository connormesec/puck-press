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
        return '';
    }
    
    public function render_edits_table()
    {
        $roster_db_utils = new Puck_Press_Roster_Wpdb_Utils;
        $roster_db_utils->maybe_create_or_update_table($this->table_name);
        $edits = $roster_db_utils->get_all_table_data($this->table_name, 'ARRAY_A');
        
        if ($edits == null) {
            return '<table class="pp-table" id="pp-edits-table"><caption>' . esc_html__('No roster edits', 'puck-press') . '</caption></table>';
        }

        ob_start();
?>
        <table class="pp-table" id="pp-edits-table">
            <thead class="pp-thead">
                <tr>
                    <th class="pp-th"><?php esc_html_e('Operation', 'puck-press'); ?></th>
                    <th class="pp-th"><?php esc_html_e('Player ID', 'puck-press'); ?></th>
                    <th class="pp-th"><?php esc_html_e('Field', 'puck-press'); ?></th>
                    <th class="pp-th"><?php esc_html_e('Change', 'puck-press'); ?></th>
                    <th class="pp-th"><?php esc_html_e('Source', 'puck-press'); ?></th>
                    <th class="pp-th"><?php esc_html_e('Actions', 'puck-press'); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($edits as $edit) :
                    $edit_data = json_decode($edit['edit_data'], true); // Decode the JSON into an array
                ?>
                    <tr data-edit-id="<?php echo esc_html($edit['id']) ?>">
                        <td class="pp-td"><?php echo esc_html($edit['edit_action']); ?></td>
                        <td class="pp-td"><?php echo esc_html($edit['external_id']); ?></td>
                        <td class="pp-td">
                            <?php if (!empty($edit_data)) : ?>
                                <?php foreach ($edit_data as $key => $value) : ?>
                                    <?php
                                    $display_value = (strlen($value) > 20)
                                        ? esc_html(substr($value, 0, 20)) . '...'
                                        : esc_html($value);
                                    ?>
                                    <span
                                        class="pp-tag pp-tag-<?php echo sanitize_html_class(strtolower($key)); ?>"
                                        title="<?php echo esc_attr($value); ?>">
                                        <?php echo esc_html(ucfirst($key)) . ': ' . $display_value; ?>
                                    </span>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </td>
                        <td class="pp-td">
                            <span class="pp-tag pp-tag-regular-season">
                                <?php echo esc_html(isset($edit['source']) ? $edit['source'] : 'No Source'); ?>
                            </span>
                        </td>
                        <td class="pp-td">Yo Mama</td>
                        <td class="pp-td">
                            <div class="pp-flex-small-gap">
                                <button class="pp-button-icon" id="pp-delete-edit-button" data-edit-id="<?php echo esc_attr($edit['id']); ?>">🗑️</button>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
<?php
        return ob_get_clean();
    }
    
    function ajax_refresh_roster_edits_table_card_callback(){
        $response_html = $this->render_edits_table();
        if ($response_html !== false) {
            wp_send_json_success($response_html);
        } else {
            wp_send_json_error(['message' => 'Edits table refresh failed']);
        }
        wp_die(); // Always required for AJAX handlers

    }

    function ajax_save_player_edit_callback() {
        global $wpdb;

        // Table name (make sure it's set correctly in your class/namespace)
        $table = $wpdb->prefix . 'pp_roster_mods';
    
        // Check for posted data
        if (!isset($_POST['edit_data'])) {
            wp_send_json_error(['message' => 'Missing edit_data']);
            wp_die();
        }
    
        // Parse the incoming JSON string from FormData
        $parsed_data = json_decode(stripslashes($_POST['edit_data']), true);
    
        if (json_last_error() !== JSON_ERROR_NONE || 
            !isset($parsed_data['edit_action'], $parsed_data['fields']['external_id'])) {
            wp_send_json_error(['message' => 'Invalid or incomplete edit_data']);
            wp_die();
        }
    
        // Extract and sanitize
        $edit_action = sanitize_text_field($parsed_data['edit_action']);
        $external_id = sanitize_text_field($parsed_data['fields']['external_id']); // game ID
        $edit_data_json = wp_json_encode($parsed_data['fields']);
    
        // Check if there's already a record for this external_id + action
        $existing_row_id = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM $table WHERE external_id = %s AND edit_action = %s LIMIT 1",
            $external_id, $edit_action
        ));
    
        $current_time = current_time('mysql');
    
        if ($existing_row_id) {
            // Update existing row
            $result = $wpdb->update(
                $table,
                [
                    'edit_data'   => $edit_data_json,
                    'updated_at'  => $current_time,
                ],
                ['id' => $existing_row_id]
            );
    
            if ($result !== false) {
                wp_send_json_success([
                    'message' => 'Edit updated',
                    'id' => $existing_row_id
                ]);
            } else {
                wp_send_json_error(['message' => 'Update failed']);
            }
        } else {
            // Insert new row
            $result = $wpdb->insert(
                $table,
                [
                    'external_id' => $external_id,
                    'edit_action' => $edit_action,
                    'edit_data'   => $edit_data_json,
                    'created_at'  => $current_time,
                    'updated_at'  => $current_time,
                ]
            );
    
            if ($result) {
                wp_send_json_success([
                    'message' => 'Edit recorded',
                    'id' => $wpdb->insert_id
                ]);
            } else {
                wp_send_json_error(['message' => 'Insert failed']);
            }
        }
    
        wp_die(); // Always required for AJAX handlers
    }

    function ajax_delete_player_edit_callback() {
        global $wpdb;
    
        $table = $wpdb->prefix . 'pp_roster_mods';
    
        // Ensure required fields are provided
        $id = isset($_POST['id']) ? sanitize_text_field($_POST['id']) : '';
    
        if (empty($id)) {
            wp_send_json_error(['message' => 'Missing required fields']);
            wp_die();
        }
    
        // Attempt to delete the record
        $result = $wpdb->delete(
            $table,
            [
                'id' => $id
            ],
            [
                '%s'
            ]
        );
    
        if ($result !== false) {
            wp_send_json_success(['message' => 'Edit deleted']);
        } else {
            wp_send_json_error(['message' => 'Delete failed or record not found']);
        }
    
        wp_die(); // Always terminate after AJAX callbacks
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