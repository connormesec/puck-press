<?php
class Puck_Press_Schedule_Admin_Preview_Card extends Puck_Press_Admin_Preview_Card_Abstract
{
    protected function make_template_manager() { return new Puck_Press_Schedule_Template_Manager(); }
    protected function make_wpdb_utils()       { return new Puck_Press_Schedule_Wpdb_Utils(); }
    protected function get_data_table_name(): string  { return 'pp_game_schedule_for_display'; }
    protected function get_outer_wrapper_id(): string { return 'pp-schedule-preview-wrapper'; }
    protected function get_inner_preview_id(): string { return 'pp-game-schedule-preview'; }

    public function render_header_button_content()
    {
        ob_start();
        ?>
        <button class="pp-button pp-button-primary" id="pp-schedule-colorPaletteBtn">
            <i>🎨</i>
            Customize Colors
        </button>
        <?php
        return ob_get_clean();
    }
}
