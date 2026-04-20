<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class TabbedLeadersTemplate extends PuckPressTemplate {

	public static function get_key(): string {
		return 'tabbed';
	}

	public static function get_label(): string {
		return 'Tabbed Top 3 Leaders';
	}

	protected static function get_directory(): string {
		return 'stat-leaders-templates';
	}

	public static function forceResetColors(): bool {
		return false;
	}

	public static function get_default_colors(): array {
		return array(
			'bg'              => '#FFFFFF',
			'tab_text'        => '#5F6368',
			'tab_active_text' => '#0D0D0D',
			'tab_underline'   => '#007AFF',
			'card_bg'         => '#F5F7F8',
			'player_name'     => '#0D0D0D',
			'player_meta'     => '#1565C0',
			'stat_value'      => '#0D0D0D',
			'more_link_color' => '#007AFF',
			'header_text'     => '#0D0D0D',
		);
	}

	public static function get_color_labels(): array {
		return array(
			'bg'              => 'Background',
			'tab_text'        => 'Tab Text',
			'tab_active_text' => 'Active Tab Text',
			'tab_underline'   => 'Active Tab Underline',
			'card_bg'         => 'Card Background',
			'player_name'     => 'Player Name',
			'player_meta'     => 'Jersey / Position Label',
			'stat_value'      => 'Stat Value',
			'more_link_color' => '"More" Link Color',
			'header_text'     => 'Header Text',
		);
	}

	public static function get_default_fonts(): array {
		return array( 'tabbed_font' => '' );
	}

	public static function get_font_labels(): array {
		return array( 'tabbed_font' => 'Leaders Font' );
	}

	public static function get_template_colors(): array {
		return get_option( 'pp_stat_leaders_template_colors_' . static::get_key(), static::get_default_colors() );
	}

	public static function get_template_fonts(): array {
		return get_option( 'pp_stat_leaders_template_fonts_' . static::get_key(), static::get_default_fonts() );
	}

	public static function get_js_dependencies(): array {
		return array();
	}

	public function render_with_options( array $data, array $options ): string {
		$inline_css = self::get_inline_css( '.pp-stat-leaders-tabbed-container' );
		$css_block  = $inline_css ? '<style>' . $inline_css . '</style>' : '';
		return $css_block . $this->build_tabbed_widget( $data );
	}

	private function build_tabbed_widget( array $data ): string {
		$categories  = isset( $data['categories'] ) && is_array( $data['categories'] ) ? $data['categories'] : array();
		$show_header = $data['show_header'] ?? true;
		$more_link   = isset( $data['more_link'] ) && is_string( $data['more_link'] ) ? $data['more_link'] : '';
		$instance_id = 'pp-tabbed-' . substr( md5( uniqid( '', true ) ), 0, 8 );

		$html  = '<div class="pp-stat-leaders-tabbed-container tabbed_leaders_container" data-instance="' . esc_attr( $instance_id ) . '">';

		if ( $show_header ) {
			$html .= '<div class="pp-tabbed-header">';
			$html .= '<h4 class="pp-tabbed-title">Stat Leaders</h4>';
			if ( $more_link !== '' ) {
				$html .= '<a class="pp-tabbed-more-link" href="' . esc_url( $more_link ) . '">More &rarr;</a>';
			}
			$html .= '</div>';
		}

		if ( empty( $categories ) ) {
			$html .= '<div class="pp-tabbed-empty">No data available.</div>';
			$html .= '</div>';
			return $html;
		}

		$html .= '<div class="pp-tabbed-tabs" role="tablist">';
		foreach ( $categories as $index => $category ) {
			$is_active  = $index === 0;
			$label      = isset( $category['label'] ) ? (string) $category['label'] : '';
			$aria       = $is_active ? 'true' : 'false';
			$class      = $is_active ? 'pp-tabbed-tab pp-tab-active' : 'pp-tabbed-tab';
			$html      .= '<button class="' . esc_attr( $class ) . '" role="tab" aria-selected="' . esc_attr( $aria ) . '" data-tab="' . esc_attr( (string) $index ) . '">' . esc_html( $label ) . '</button>';
		}
		$html .= '</div>';

		$html .= '<div class="pp-tabbed-panels">';
		foreach ( $categories as $index => $category ) {
			$is_active = $index === 0;
			$players   = isset( $category['players'] ) && is_array( $category['players'] ) ? $category['players'] : array();
			$class     = $is_active ? 'pp-tabbed-panel pp-panel-active' : 'pp-tabbed-panel';
			$hidden    = $is_active ? '' : ' hidden';

			$html .= '<div class="' . esc_attr( $class ) . '" data-panel="' . esc_attr( (string) $index ) . '"' . $hidden . '>';
			$html .= '<div class="pp-tabbed-cards">';

			if ( empty( $players ) ) {
				$html .= '<div class="pp-tabbed-empty">No data available.</div>';
			} else {
				$show_team = (bool) ( $data['show_team'] ?? true );
				foreach ( $players as $player ) {
					$html .= $this->build_card( $player, $show_team );
				}
			}

			$html .= '</div>';
			$html .= '</div>';
		}
		$html .= '</div>';

		$html .= '</div>';
		return $html;
	}

	private function build_card( array $player, bool $show_team = true ): string {
		$name     = (string) ( $player['name']     ?? '' );
		$team     = (string) ( $player['team']     ?? '' );
		$value    = (string) ( $player['value']    ?? '' );
		$headshot = (string) ( $player['headshot'] ?? '' );
		$position = (string) ( $player['position'] ?? '' );
		$number   = (string) ( $player['number']   ?? '' );

		$src = $headshot !== '' ? $headshot : self::HEADSHOT_FALLBACK;

		if ( $number !== '' && $position !== '' ) {
			$meta = '#' . $number . ' &ndash; ' . esc_html( $position );
		} elseif ( $position !== '' ) {
			$meta = esc_html( $position );
		} else {
			$meta = '';
		}

		$html  = '<div class="pp-tabbed-card">';
		$html .= '<img class="pp-tabbed-headshot" src="' . esc_url( $src ) . '" alt="' . esc_attr( $name ) . '" loading="lazy">';
		$html .= '<div class="pp-tabbed-card-body">';
		if ( $meta !== '' ) {
			$html .= '<span class="pp-tabbed-player-meta">' . $meta . '</span>';
		}
		$html .= '<span class="pp-tabbed-player-name">' . esc_html( $name ) . '</span>';
		if ( $show_team && $team !== '' ) {
			$html .= '<span class="pp-tabbed-player-team">' . esc_html( $team ) . '</span>';
		}
		$html .= '</div>';
		$html .= '<span class="pp-tabbed-stat-value">' . esc_html( $value ) . '</span>';
		$html .= '</div>';

		return $html;
	}
}
