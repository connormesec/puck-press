<?php
class Puck_Press_Schedule_Admin_Slider_Preview_Card extends Puck_Press_Admin_Preview_Card_Abstract
{
    protected function make_template_manager() { return new Puck_Press_Slider_Template_Manager(); }
    protected function make_wpdb_utils()       { return new Puck_Press_Schedule_Wpdb_Utils(); }
    protected function get_data_table_name(): string  { return 'pp_game_schedule_for_display'; }
    protected function get_outer_wrapper_id(): string { return 'pp-game-slider-preview-wrapper'; }
    protected function get_inner_preview_id(): string { return 'pp-game-slider-preview'; }

    public function render_header_button_content()
    {
        ob_start();
        ?>
        <div class="pp-shortcode-container">
            <div class="pp-shortcode-label">Slider Shortcode</div>
            <div class="pp-shortcode-input-group">
                <input
                    type="text"
                    id="pp-slider-shortcode"
                    name="pp-slider-shortcode"
                    class="pp-shortcode-input"
                    value="[pp-slider]"
                    spellcheck="false"
                    aria-label="shortcode"
                    onfocus="this.select();"
                    readonly>
                <button class="pp-shortcode-copy-btn" aria-label="Copy URL">
                    <svg class="pp-shortcode-copy-icon" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 5H6a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2v-1M8 5a2 2 0 002 2h2a2 2 0 002-2M8 5a2 2 0 012-2h2a2 2 0 012 2m0 0h2a2 2 0 012 2v3m2 4H10m0 0l3-3m-3 3l3 3" />
                    </svg>
                </button>
                <div class="pp-shortcode-tooltip">Copied!</div>
            </div>
        </div>
        <button class="pp-button pp-button-primary" id="pp-schedule-slider-colorPaletteBtn">
            <i>🎨</i>
            Customize Colors
        </button>
        <?php
        return ob_get_clean();
    }
}
