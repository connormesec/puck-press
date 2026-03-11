<?php
class Puck_Press_Schedule_Template_Manager extends Puck_Press_Template_Manager
{
    private int $schedule_id;

    public function __construct(int $schedule_id = 1)
    {
        $this->schedule_id = $schedule_id;
        parent::__construct();
    }

    protected function get_template_dir(): string
    {
        return plugin_dir_path(__FILE__) . 'schedule-templates';
    }

    protected function get_option_prefix(): string
    {
        return "pp_schedule_{$this->schedule_id}_template_colors_";
    }

    protected function get_current_template_option(): string
    {
        return "pp_schedule_{$this->schedule_id}_current_template";
    }

    public function get_current_template_key(): string
    {
        $value = get_option($this->get_current_template_option(), '');
        if (!empty($value)) return $value;

        if ($this->schedule_id === 1) {
            $legacy = get_option('pp_current_schedule_template', '');
            if (!empty($legacy)) return $legacy;
        }

        return '';
    }

    public function enqueue_current_template_assets($handle_prefix = 'puck-press')
    {
        parent::enqueue_current_template_assets("puck-press-sched-{$this->schedule_id}");
    }

    public function enqueue_all_template_assets($handle_prefix = 'puck-press')
    {
        parent::enqueue_all_template_assets("puck-press-sched-{$this->schedule_id}");
    }

    public function get_all_template_colors(): array
    {
        $colors = [];
        foreach ($this->templates as $key => $class_name) {
            $colors[$key] = $class_name::get_schedule_colors($this->schedule_id);
        }
        return $colors;
    }

    public function get_all_template_fonts(): array
    {
        $fonts = [];
        foreach ($this->templates as $key => $class_name) {
            $fonts[$key] = $class_name::get_schedule_fonts($this->schedule_id);
        }
        return $fonts;
    }

    public function get_current_template_colors(): array
    {
        $key = $this->get_current_template_key();
        if (empty($key)) return [];

        $option = $this->get_option_prefix() . $key;
        $saved  = get_option($option, null);

        if ($saved === null && $this->schedule_id === 1) {
            $legacy_option = 'pp_schedule_template_colors_' . $key;
            $saved = get_option($legacy_option, null);
        }

        if (!is_array($saved)) {
            if (isset($this->templates[$key])) {
                return $this->templates[$key]::get_default_colors();
            }
            return [];
        }

        return $saved;
    }
}
