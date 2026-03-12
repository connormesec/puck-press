<?php

class Puck_Press_Roster_Admin_Groups_Card extends Puck_Press_Admin_Groups_Card_Abstract {

	protected function get_domain_label(): string {
		return 'Roster';
	}

	protected function get_create_action(): string {
		return 'pp_create_roster_group';
	}

	protected function get_delete_action(): string {
		return 'pp_delete_roster_group';
	}

	protected function get_shortcode_hint(): string {
		return '[pp-roster roster="..."]';
	}

	protected function make_wpdb_utils(): Puck_Press_Group_Aware_Wpdb_Utils {
		return new Puck_Press_Roster_Wpdb_Utils();
	}
}
