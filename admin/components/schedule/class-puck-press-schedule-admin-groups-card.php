<?php

class Puck_Press_Schedule_Admin_Groups_Card extends Puck_Press_Admin_Groups_Card_Abstract {

	protected function get_domain_label(): string {
		return 'Schedule';
	}

	protected function get_create_action(): string {
		return 'pp_create_schedule_group';
	}

	protected function get_delete_action(): string {
		return 'pp_delete_schedule_group';
	}

	protected function get_shortcode_hint(): string {
		return '[pp-schedule schedule="..."]';
	}

	protected function make_wpdb_utils(): Puck_Press_Group_Aware_Wpdb_Utils {
		return new Puck_Press_Schedule_Wpdb_Utils();
	}
}
