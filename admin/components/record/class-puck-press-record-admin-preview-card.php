<?php

if (!defined('ABSPATH')) {
    exit;
}

class Puck_Press_Record_Admin_Preview_Card extends Puck_Press_Admin_Preview_Card_Abstract
{
    private int $schedule_id;

    public function __construct(array $options = [])
    {
        $this->schedule_id = (int) ($options['schedule_id'] ?? 1);
        parent::__construct($options);
    }

    protected function make_template_manager()
    {
        return new Puck_Press_Record_Template_Manager($this->schedule_id);
    }

    protected function make_wpdb_utils()
    {
        return new Puck_Press_Record_Wpdb_Utils();
    }

    protected function get_data_table_name(): string
    {
        return 'pp_game_schedule_for_display';
    }

    protected function get_outer_wrapper_id(): string
    {
        return 'pp-record-preview-wrapper';
    }

    protected function get_inner_preview_id(): string
    {
        return 'pp-record-preview';
    }

    public function init()
    {
        $this->data                  = $this->wpdb_utils->get_record_stats($this->schedule_id);
        $this->templates             = $this->template_manager->get_all_templates();
        $this->selected_template_key = $this->template_manager->get_current_template_key();
        $this->template_manager->enqueue_all_template_assets();
    }

    public function render_header_button_content()
    {
        ob_start();
        ?>
        <button class="pp-button pp-button-primary" id="pp-record-colorPaletteBtn">
            <i>🎨</i>
            Customize Colors
        </button>
        <?php
        return ob_get_clean();
    }
}
