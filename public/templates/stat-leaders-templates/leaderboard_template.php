<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class LeaderboardTemplate extends PuckPressTemplate {

	public static function get_key(): string {
		return 'leaderboard';
	}

	public static function get_label(): string {
		return 'Leaderboard';
	}

	protected static function get_directory(): string {
		return 'stat-leaders-templates';
	}

	public static function forceResetColors(): bool {
		return false;
	}

	public static function get_top_n(): int {
		return 5;
	}

	public static function wants_combined_categories(): bool {
		return true;
	}

	public static function get_default_colors(): array {
		return array(
			'bg'              => '#F6F3EF',
			'tab_text'        => '#9B9B9B',
			'tab_active_text' => '#1A2E5A',
			'tab_underline'   => '#2563EB',
			'tab_divider'     => '#E0DDD8',
			'rank_text'       => '#B0AAA2',
			'number_text'     => '#1A2E5A',
			'name_text'       => '#1A2E5A',
			'position_border' => '#D1CECC',
			'position_text'   => '#6B7280',
			'stat_value'      => '#2563EB',
			'row_divider'     => '#E8E4DF',
			'more_link_color' => '#2563EB',
			'header_text'     => '#1A2E5A',
		);
	}

	public static function get_color_labels(): array {
		return array(
			'bg'              => 'Background',
			'tab_text'        => 'Inactive Tab Text',
			'tab_active_text' => 'Active Tab Text',
			'tab_underline'   => 'Active Tab Underline',
			'tab_divider'     => 'Tab Separator',
			'rank_text'       => 'Rank Number',
			'number_text'     => 'Jersey Number',
			'name_text'       => 'Player Name',
			'position_border' => 'Position Badge Border',
			'position_text'   => 'Position Badge Text',
			'stat_value'      => 'Stat Value',
			'row_divider'     => 'Row Divider',
			'more_link_color' => '"More" Link',
			'header_text'     => 'Header Text',
		);
	}

	public static function get_default_fonts(): array {
		return array( 'leaderboard_font' => '' );
	}

	public static function get_font_labels(): array {
		return array( 'leaderboard_font' => 'Leaderboard Font' );
	}

	public static function get_js_dependencies(): array {
		return array();
	}

	// Override — base class infers the wrong option key from 'stat-leaders-templates' directory name.
	public static function get_template_colors(): array {
		return get_option( 'pp_stat_leaders_template_colors_' . static::get_key(), static::get_default_colors() );
	}

	public static function get_template_fonts(): array {
		return get_option( 'pp_stat_leaders_template_fonts_' . static::get_key(), static::get_default_fonts() );
	}

	public function render_with_options( array $data, array $options ): string {
		// :root scope is required so the admin JS live-preview
		// (document.documentElement.style.setProperty) can override these variables.
		// Do NOT pass a scoped selector here — it would block the live updates.
		$inline_css = self::get_inline_css();
		$css_block  = $inline_css ? '<style>' . $inline_css . '</style>' : '';
		return $css_block . $this->build( $data );
	}

	private function build( array $data ): string {
		$categories  = isset( $data['categories'] ) && is_array( $data['categories'] ) ? $data['categories'] : array();
		$show_header = $data['show_header'] ?? true;
		$more_link   = isset( $data['more_link'] ) && is_string( $data['more_link'] ) ? $data['more_link'] : '';

		$html = '<div class="pp-stat-leaders-leaderboard-container leaderboard_leaders_container">';

		if ( $show_header ) {
			$html .= '<div class="pp-lb-header">';
			$html .= '<h4 class="pp-lb-title">Stat Leaders</h4>';
			if ( $more_link !== '' ) {
				$html .= '<a class="pp-lb-more-link" href="' . esc_url( $more_link ) . '">More &rarr;</a>';
			}
			$html .= '</div>';
		}

		if ( empty( $categories ) ) {
			$html .= '<div class="pp-lb-empty">No data available.</div>';
			$html .= '</div>';
			return $html;
		}

		// Tab bar
		$html .= '<div class="pp-lb-tabs" role="tablist">';
		foreach ( $categories as $index => $category ) {
			$label     = isset( $category['label'] ) ? (string) $category['label'] : '';
			$is_active = $index === 0;
			$class     = $is_active ? 'pp-lb-tab pp-lb-tab--active' : 'pp-lb-tab';
			$aria      = $is_active ? 'true' : 'false';
			if ( $index > 0 ) {
				$html .= '<span class="pp-lb-tab-divider" aria-hidden="true"></span>';
			}
			$html .= '<button class="' . esc_attr( $class ) . '" role="tab" aria-selected="' . esc_attr( $aria ) . '" data-tab="' . esc_attr( (string) $index ) . '">' . esc_html( $label ) . '</button>';
		}
		$html .= '</div>';

		// Panels
		$html .= '<div class="pp-lb-panels">';
		foreach ( $categories as $index => $category ) {
			$is_active = $index === 0;
			$players   = isset( $category['players'] ) && is_array( $category['players'] ) ? $category['players'] : array();
			$class     = $is_active ? 'pp-lb-panel pp-lb-panel--active' : 'pp-lb-panel';
			$hidden    = $is_active ? '' : ' hidden';

			$html .= '<div class="' . esc_attr( $class ) . '" data-panel="' . esc_attr( (string) $index ) . '"' . $hidden . '>';

			if ( empty( $players ) ) {
				$html .= '<div class="pp-lb-empty">No data available.</div>';
			} else {
				$html .= '<div class="pp-lb-table">';
				foreach ( $players as $rank_zero => $player ) {
					$html .= $this->build_row( $player, $rank_zero + 1 );
				}
				$html .= '</div>';
			}

			$html .= '</div>';
		}
		$html .= '</div>';

		$html .= '</div>';
		return $html;
	}

	private function build_row( array $player, int $rank ): string {
		$name     = (string) ( $player['name']     ?? '' );
		$value    = (string) ( $player['value']    ?? '' );
		$position = (string) ( $player['position'] ?? '' );
		$number   = (string) ( $player['number']   ?? '' );

		$html  = '<div class="pp-lb-row">';
		$html .= '<span class="pp-lb-rank">' . esc_html( sprintf( '%02d', $rank ) ) . '</span>';
		if ( $number !== '' ) {
			$html .= '<span class="pp-lb-number">' . esc_html( '#' . $number ) . '</span>';
		}
		$html .= '<span class="pp-lb-name">' . esc_html( $name ) . '</span>';
		$html .= '<span class="pp-lb-spacer"></span>';
		if ( $position !== '' ) {
			$html .= '<span class="pp-lb-position">' . esc_html( $position ) . '</span>';
		}
		$html .= '<span class="pp-lb-value">' . esc_html( $value ) . '</span>';
		$html .= '</div>';

		return $html;
	}
}
