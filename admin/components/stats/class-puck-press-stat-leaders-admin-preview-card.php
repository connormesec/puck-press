<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Puck_Press_Stat_Leaders_Admin_Preview_Card extends Puck_Press_Admin_Preview_Card_Abstract {

	protected function make_template_manager() {
		return new Puck_Press_Stat_Leaders_Template_Manager();
	}

	protected function make_wpdb_utils() {
		return new Puck_Press_Stat_Leaders_Wpdb_Utils();
	}

	protected function get_data_table_name(): string {
		return '';
	}

	protected function get_outer_wrapper_id(): string {
		return 'pp-stat-leaders-preview-wrapper';
	}

	protected function get_inner_preview_id(): string {
		return 'pp-stat-leaders-preview';
	}

	public function init() {
		$team_colors = get_option( 'pp_stat_leaders_team_colors', array() );
		$more_link   = get_option( 'pp_stat_leaders_more_link', '' );
		$show_team   = (bool) get_option( 'pp_stat_leaders_show_team', 1 );

		$this->data = array(
			'skater_rows' => $this->wpdb_utils->get_skater_leaders(),
			'goalie_rows' => $this->wpdb_utils->get_goalie_leaders(),
			'show_team'   => $show_team,
			'more_link'   => is_string( $more_link ) ? $more_link : '',
			'team_colors' => is_array( $team_colors ) ? $team_colors : array(),
		);

		$this->templates             = $this->template_manager->get_all_templates();
		$this->selected_template_key = $this->template_manager->get_current_template_key();
		$this->template_manager->enqueue_all_template_assets();
	}

	public function get_all_templates_html(): string {
		$output = '';
		foreach ( $this->templates as $template ) {
			$skater_data = array(
				'rows'        => $this->data['skater_rows'],
				'show_team'   => $this->data['show_team'],
				'more_link'   => $this->data['more_link'],
				'team_colors' => $this->data['team_colors'],
			);
			$goalie_data = array(
				'rows'        => $this->data['goalie_rows'],
				'show_team'   => $this->data['show_team'],
				'more_link'   => $this->data['more_link'],
				'team_colors' => $this->data['team_colors'],
			);
			$output .= '<div class="pp-stat-leaders-preview-stack">';
			$output .= $template->render( $skater_data );
			$output .= $template->render( $goalie_data );
			$output .= '</div>';
		}
		return $output;
	}

	public function get_current_template_html(): string {
		$key      = $this->selected_template_key;
		$template = null;
		foreach ( $this->templates as $t ) {
			if ( $t->get_key() === $key ) {
				$template = $t;
				break;
			}
		}
		if ( ! $template ) {
			return '';
		}
		$skater_data = array(
			'rows'        => $this->data['skater_rows'],
			'show_team'   => $this->data['show_team'],
			'more_link'   => $this->data['more_link'],
			'team_colors' => $this->data['team_colors'],
		);
		$goalie_data = array(
			'rows'        => $this->data['goalie_rows'],
			'show_team'   => $this->data['show_team'],
			'more_link'   => $this->data['more_link'],
			'team_colors' => $this->data['team_colors'],
		);
		$output  = '<div class="pp-stat-leaders-preview-stack">';
		$output .= $template->render( $skater_data );
		$output .= $template->render( $goalie_data );
		$output .= '</div>';
		return $output;
	}

	public function render_content() {
		$outer_id = $this->get_outer_wrapper_id();
		$inner_id = $this->get_inner_preview_id();
		ob_start();
		?>
		<div id="<?php echo esc_attr( $outer_id ); ?>" class="loading">
			<div class="spinner-container">
				<span class="spinner is-active big-spinner"></span>
			</div>
			<div id="<?php echo esc_attr( $inner_id ); ?>" style="max-width:400px;">
				<?php echo $this->get_all_templates_html(); ?>
			</div>
		</div>
		<?php
		return ob_get_clean();
	}

	public function render_header_button_content() {
		ob_start();
		?>
		<button class="pp-button pp-button-primary" id="pp-stat-leaders-colorPaletteBtn">
			<i>🎨</i> Customize Colors
		</button>
		<?php
		return ob_get_clean();
	}
}
