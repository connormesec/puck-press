<?php
class Puck_Press_Slider_Template_Manager extends Puck_Press_Template_Manager
{
    protected function get_template_dir(): string
    {
        return plugin_dir_path(__FILE__) . 'slider-templates';
    }

    protected function get_option_prefix(): string
    {
        return 'pp_slider_template_colors_';
    }

    protected function get_current_template_option(): string
    {
        return 'pp_current_slider_template';
    }
}