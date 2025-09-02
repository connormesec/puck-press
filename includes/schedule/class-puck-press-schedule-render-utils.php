<?php

class Puck_Press_Schedule_Render_Utils
{
    protected $template_manager;
    protected $wpdb_utils;
    protected $games;
    protected $templates;
    protected $selected_template_key;

    public function __construct()
    {
        $this->load_dependencies();

        $this->template_manager = new Puck_Press_Schedule_Template_Manager();
        $this->wpdb_utils       = new Puck_Press_Schedule_Wpdb_Utils();

        $this->games     = $this->wpdb_utils->get_all_table_data('pp_game_schedule_for_display', 'ARRAY_A');
        $this->templates = $this->template_manager->get_all_templates();

        $this->selected_template_key =  $this->template_manager->get_current_template_key();
    }

    public function load_dependencies()
    {
        require_once plugin_dir_path(__FILE__) . '../../public/templates/class-puck-press-template-manager-abstract.php';
        require_once plugin_dir_path(__FILE__) . '../../public/templates/class-puck-press-schedule-template-manager.php';
        require_once plugin_dir_path(__FILE__) . '../class-puck-press-wpdb-utils-base-abstract.php';
        require_once plugin_dir_path(__FILE__) . 'class-puck-press-schedule-wpdb-utils.php';
    }

    // 🔁 Echo all templates
    public function render_all_templates()
    {
        $this->template_manager->enqueue_all_template_assets();
        echo $this->get_all_templates_html();
    }

    // 🔍 Echo one template
    public function render_template($template_name)
    {
        $this->template_manager->enqueue_template_assets($template_name);
        echo $this->get_template_html($template_name);
    }

    // 🧾 Return HTML string of all templates
    public function get_all_templates_html()
    {
        $output = '';

        foreach ($this->templates as $template) {
            $output .= $template->render($this->games);
        }

        return $output;
    }

    // 🧾 Return HTML string for a specific template
    public function get_template_html($template_name)
    {
        foreach ($this->templates as $template) {
            if ($template->get_key() === $template_name) {
                return $template->render($this->games);
            }
        }

        return '<p>Template not found: ' . esc_html($template_name) . '</p>';
    }

    public function render_current_template()
    {
        return $this->render_template($this->selected_template_key);
    }

    public function get_current_template_html()
    {
        return $this->get_template_html($this->selected_template_key);
    }
}