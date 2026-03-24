<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Puck_Press_League_News_Template_Manager extends Puck_Press_Template_Manager {

    protected function get_template_dir(): string {
        return plugin_dir_path( __FILE__ ) . 'league-news-templates';
    }

    protected function get_option_prefix(): string {
        return 'pp_league-news_template_colors_';
    }

    protected function get_current_template_option(): string {
        return 'pp_current_league_news_template';
    }
}
