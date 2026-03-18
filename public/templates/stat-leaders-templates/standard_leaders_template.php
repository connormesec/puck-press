<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class StandardLeadersTemplate extends PuckPressTemplate {

	public static function get_key(): string {
		return 'leaders';
	}

	public static function get_label(): string {
		return 'Standard Stat Leaders';
	}

	protected static function get_directory(): string {
		return 'stat-leaders-templates';
	}

	public static function forceResetColors(): bool {
		return false;
	}

	public static function get_default_colors(): array {
		return array(
			'widget_bg'       => '#FFFFFF',
			'header_text'     => '#1A1A2E',
			'more_link_color' => '#1565C0',
			'label_bg'        => '#F5F5F5',
			'label_text'      => '#5F6368',
			'card_bg_default' => '#8B1A2E',
			'card_text'       => '#FFFFFF',
			'card_subtext'    => '#E8D0D5',
			'card_stat'       => '#FFFFFF',
		);
	}

	public static function get_color_labels(): array {
		return array(
			'widget_bg'       => 'Widget Background',
			'header_text'     => 'Header Text',
			'more_link_color' => '"More" Link Color',
			'label_bg'        => 'Stat Label Background',
			'label_text'      => 'Stat Label Text',
			'card_bg_default' => 'Card Background (no team color set)',
			'card_text'       => 'Player Name Text',
			'card_subtext'    => 'Team Name Text',
			'card_stat'       => 'Stat Value Text',
		);
	}

	public static function get_default_fonts(): array {
		return array( 'leaders_font' => '' );
	}

	public static function get_font_labels(): array {
		return array( 'leaders_font' => 'Leaders Font' );
	}

	// Override to read from the correct option key (underscores match manager prefix).
	public static function get_template_colors(): array {
		return get_option( 'pp_stat_leaders_template_colors_' . static::get_key(), static::get_default_colors() );
	}

	public static function get_template_fonts(): array {
		return get_option( 'pp_stat_leaders_template_fonts_' . static::get_key(), static::get_default_fonts() );
	}

	public function render_with_options( array $data, array $options ): string {
		$inline_css = self::get_inline_css();
		$css_block  = $inline_css ? '<style>' . $inline_css . '</style>' : '';
		return $css_block . $this->build_widget( $data );
	}

	private function build_widget( array $data ): string {
		$rows        = $data['rows']        ?? array();
		$more_link   = $data['more_link']   ?? '';
		$show_team   = $data['show_team']   ?? true;
		$show_header = $data['show_header'] ?? true;
		$team_colors = $data['team_colors'] ?? array();
		$colors      = self::get_template_colors();
		$default_bg  = $colors['card_bg_default'] ?? '#8B1A2E';

		$html = '<div class="pp-stat-leaders-container leaders_leaders_container">';
		if ( $show_header ) {
			$html .= '<div class="pp-leaders-header">';
			$html .= '<h4 class="pp-leaders-title">Stat Leaders</h4>';
			if ( ! empty( $more_link ) ) {
				$html .= '<a class="pp-leaders-more-link" href="' . esc_url( $more_link ) . '">More &rarr;</a>';
			}
			$html .= '</div>';
		}

		if ( empty( $rows ) ) {
			$html .= '<div class="pp-leaders-empty">No data available</div>';
		} else {
			foreach ( $rows as $row ) {
				$bg    = $this->get_card_bg( $row['team'] ?? '', $team_colors, $default_bg );
				$html .= '<div class="pp-leaders-row">';
				$html .= '<div class="pp-leaders-label">' . esc_html( $row['label'] ) . '</div>';
				$html .= '<div class="pp-leaders-card" style="background:' . esc_attr( $bg ) . ';">';
				$html .= '<div class="pp-leaders-card-left">';
				$html .= '<span class="pp-leaders-player-name">' . esc_html( $row['player'] ) . '</span>';
				if ( $show_team && ! empty( $row['team'] ) ) {
					$html .= '<span class="pp-leaders-team-name">' . esc_html( $row['team'] ) . '</span>';
				}
				$html .= '</div>';
				$html .= '<span class="pp-leaders-stat-value">' . esc_html( $row['value'] ) . '</span>';
				$html .= '</div>';
				$html .= '</div>';
			}
		}

		$html .= '</div>';
		return $html;
	}

	private function get_card_bg( string $team, array $team_colors, string $default ): string {
		return ! empty( $team_colors[ $team ] ) ? $team_colors[ $team ] : $default;
	}
}
