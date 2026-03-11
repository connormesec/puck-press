<?php

/**
 * Manages color and font settings for the /player/{slug} detail page.
 *
 * Colors are stored in WordPress options and injected as CSS custom properties
 * on the frontend by Puck_Press_Public::enqueue_roster_assets().
 *
 * Template key is 'pd', so CSS vars are --pp-pd-{colorKey} — matching the
 * property names already used in puck-press-public.css.
 */
class Puck_Press_Player_Detail_Colors {

	const COLORS_OPTION = 'pp_player_detail_colors';
	const FONTS_OPTION  = 'pp_player_detail_fonts';

	public static function get_default_colors(): array {
		return array(
			'accent'      => '#009DA5',
			'body-bg'     => '#F4F4F4',
			'text'        => '#1A1A2E',
			'number-bg'   => '#000000',
			'number-text' => '#FFFFFF',
		);
	}

	public static function get_color_labels(): array {
		return array(
			'accent'      => 'Accent Color (tabs, links, labels)',
			'body-bg'     => 'Page Background',
			'text'        => 'Body Text',
			'number-bg'   => 'Jersey Number Background',
			'number-text' => 'Jersey Number Text',
		);
	}

	public static function get_default_fonts(): array {
		return array( 'player-font' => '' ); }
	public static function get_font_labels(): array {
		return array( 'player-font' => 'Player Page Font' ); }

	public static function get_colors(): array {
		$saved = get_option( self::COLORS_OPTION, array() );
		return array_merge( self::get_default_colors(), is_array( $saved ) ? $saved : array() );
	}

	public static function get_fonts(): array {
		$saved = get_option( self::FONTS_OPTION, array() );
		return array_merge( self::get_default_fonts(), is_array( $saved ) ? $saved : array() );
	}

	/**
	 * Returns `:root { --pp-pd-accent: ...; --pp-pd-banner-bg: ...; ... }` string
	 * ready to pass to wp_add_inline_style().
	 */
	public static function get_inline_css(): string {
		$colors = self::get_colors();
		$fonts  = self::get_fonts();

		$css = ':root {';
		foreach ( $colors as $key => $val ) {
			$css .= "--pp-pd-{$key}: {$val};";
		}
		$font = $fonts['player-font'] ?? '';
		if ( ! empty( $font ) ) {
			$safe = str_replace( array( "'", '"', ';', '}' ), '', $font );
			$css .= "--pp-pd-font-family: '{$safe}', sans-serif;";
		}
		$css .= '}';

		return $css;
	}

	public static function save( array $colors, array $fonts ): void {
		$clean_colors = array();
		foreach ( self::get_default_colors() as $key => $_ ) {
			if ( isset( $colors[ $key ] ) ) {
				$hex = sanitize_hex_color( $colors[ $key ] );
				if ( $hex ) {
					$clean_colors[ $key ] = $hex;
				}
			}
		}

		$clean_fonts = array();
		foreach ( self::get_default_fonts() as $key => $_ ) {
			if ( isset( $fonts[ $key ] ) ) {
				$clean_fonts[ $key ] = sanitize_text_field( $fonts[ $key ] );
			}
		}

		update_option( self::COLORS_OPTION, $clean_colors );
		update_option( self::FONTS_OPTION, $clean_fonts );
	}
}
