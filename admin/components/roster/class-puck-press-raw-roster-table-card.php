<?php
class Puck_Press_Raw_Roster_Table_Card extends Puck_Press_Admin_Card_Abstract
{
    private $table_name = 'pp_roster_raw';
    private $roster_db_utils;

    public function init(): void
    {
        $this->roster_db_utils = new Puck_Press_Roster_Wpdb_Utils();
        $this->roster_db_utils->maybe_create_or_update_table($this->table_name);
    }

    public function render_content()
    {
        $this->init(); // <-- Call it here if needed
        return $this->render_roster_admin_preview();
    }

    public function render_header_button_content()
    {
        return '';
    }

    public function render_roster_admin_preview()
    {
        $this->roster_db_utils = new Puck_Press_Roster_Wpdb_Utils();
        $roster = $this->roster_db_utils->get_all_table_data($this->table_name, 'ARRAY_A');
        if ($roster == null) {
            return '<table class="pp-table" id="pp-roster-table"><caption>' . esc_html__('No players added yet.', 'puck-press') . '</caption></table>';
        }

        ob_start();
?>
        <table class="pp-table" id="pp-roster-table">
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
                    <th class="pp-th"><?php esc_html_e('Headshot Link', 'puck-press'); ?></th>
                    <th class="pp-th"><?php esc_html_e('Last Team', 'puck-press'); ?></th>
                    <th class="pp-th"><?php esc_html_e('Year', 'puck-press'); ?></th>
                    <th class="pp-th"><?php esc_html_e('Major', 'puck-press'); ?></th>
                    <th class="pp-th"><?php esc_html_e('Actions', 'puck-press'); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($roster as $player) : ?>
                    <tr data-id="<?php echo esc_html($player['source']) ?>" data-player-primary-key="<?php echo esc_html($player['id']) ?>">
                        <td class="pp-td"><?php echo esc_html($player['player_id']); ?></td>
                        <td class="pp-td"><?php echo esc_html($player['source']); ?></td>
                        <td class="pp-td"><?php echo esc_html($player['number']); ?></td>
                        <td class="pp-td"><?php echo esc_html($player['name']); ?></td>
                        <td class="pp-td"><?php echo esc_html($player['pos']); ?></td>
                        <td class="pp-td"><?php echo esc_html($player['ht']); ?></td>
                        <td class="pp-td"><?php echo esc_html($player['wt']); ?></td>
                        <td class="pp-td"><?php echo esc_html($player['shoots']); ?></td>
                        <td class="pp-td"><?php echo esc_html($player['hometown']); ?></td>
                        <td class="pp-td"><?php echo esc_html($player['headshot_link']); ?></td>
                        <td class="pp-td"><?php echo esc_html($player['last_team']); ?></td>
                        <td class="pp-td"><?php echo esc_html($player['year_in_school']); ?></td>
                        <td class="pp-td"><?php echo esc_html($player['major']); ?></td>
                        <td class="pp-td">
                            <div class="pp-flex-small-gap">
                                <button class="pp-button-icon" id="pp-edit-player-button" data-player-id="<?php echo esc_attr($player['player_id']); ?>">✏️</button>
                                <button class="pp-button-icon" id="pp-delete-player-button" data-player-id="<?php echo esc_attr($player['player_id']); ?>">🗑️</button>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
<?php
        return ob_get_clean();
    }
}
