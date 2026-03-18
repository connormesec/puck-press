<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Puck_Press_Stat_Leaders_Template_Manager extends Puck_Press_Template_Manager {

	protected function get_template_dir(): string {
		return plugin_dir_path( __FILE__ ) . 'stat-leaders-templates';
	}

	protected function get_option_prefix(): string {
		return 'pp_stat_leaders_template_colors_';
	}

	protected function get_current_template_option(): string {
		return 'pp_current_stat_leaders_template';
	}
}
