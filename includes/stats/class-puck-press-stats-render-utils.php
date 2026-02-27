<?php

if (!defined('ABSPATH')) {
    exit;
}

class Puck_Press_Stats_Render_Utils
{
    private $template_manager;
    private $wpdb_utils;

    public function __construct()
    {
        $this->load_dependencies();
        $this->template_manager = new Puck_Press_Stats_Template_Manager();
        $this->wpdb_utils       = new Puck_Press_Stats_Wpdb_Utils();
    }

    private function load_dependencies(): void
    {
        require_once plugin_dir_path(__FILE__) . '../../public/templates/class-puck-press-template-manager-abstract.php';
        require_once plugin_dir_path(__FILE__) . '../../public/templates/class-puck-press-stats-template-manager.php';
        require_once plugin_dir_path(__FILE__) . 'class-puck-press-stats-wpdb-utils.php';
    }

    /**
     * Render the currently-active stats template.
     *
     * @return string HTML output.
     */
    public function get_current_template_html(): string
    {
        $template = $this->template_manager->get_current_template();
        if (!$template) {
            return '';
        }

        return $template->render($this->wpdb_utils->get_stats_data());
    }
}
