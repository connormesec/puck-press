<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Puck_Press_Divi_Page_Builder {

	private const DIVI_VERSION = '0.7';
	private const OPTION_PAGES = 'pp_team_%d_divi_pages';
	private const OPTION_MAX_WIDTH        = 'pp_divi_page_max_width';
	private const OPTION_PADDING          = 'pp_divi_page_padding';
	private const OPTION_HEADER_COLOR     = 'pp_divi_page_header_color';
	private const OPTION_HEADER_TEXT_COLOR = 'pp_divi_page_header_text_color';
	private const OPTION_HEADER_FONT_SIZE  = 'pp_divi_page_header_font_size';
	private const OPTION_HEADER_FONT       = 'pp_divi_page_header_font';
	private const OPTION_SCHOOL_URL        = 'pp_divi_page_school_url';

	// -------------------------------------------------------------------------
	// Divi shortcode helpers
	// -------------------------------------------------------------------------

	private static function css_padding_to_divi( string $padding ): string {
		$parts = preg_split( '/\s+/', trim( $padding ) );
		switch ( count( $parts ) ) {
			case 1:
				return $parts[0] . '|' . $parts[0] . '|' . $parts[0] . '|' . $parts[0] . '|false|false';
			case 2:
				return $parts[0] . '|' . $parts[1] . '|' . $parts[0] . '|' . $parts[1] . '|false|false';
			case 3:
				return $parts[0] . '|' . $parts[1] . '|' . $parts[2] . '|' . $parts[1] . '|false|false';
			default:
				return $parts[0] . '|' . $parts[1] . '|' . $parts[2] . '|' . $parts[3] . '|false|false';
		}
	}

	private static function divi_section( string $bg_color, string $max_width, string $padding, string $margin = '', string ...$rows ): string {
		$v            = self::DIVI_VERSION;
		$rows         = implode( '', $rows );
		$divi_padding = self::css_padding_to_divi( $padding );
		$p_parts      = explode( '|', $divi_padding );
		if ( count( $p_parts ) >= 4 ) {
			$p_parts[1] = '10px';
			$p_parts[3] = '10px';
			$divi_padding = implode( '|', $p_parts );
		}
		$margin_attr  = $margin !== '' ? ' custom_margin="' . esc_attr( $margin ) . '"' : '';
		return '[et_pb_section fb_built="1" fullwidth="off" _builder_version="' . $v . '" _module_preset="default" background_color="' . esc_attr( $bg_color ) . '" custom_padding="' . esc_attr( $divi_padding ) . '"' . $margin_attr . ']'
			. $rows
			. '[/et_pb_section]';
	}

	private static function divi_row( string $max_width, string ...$modules ): string {
		$v       = self::DIVI_VERSION;
		$modules = implode( '', $modules );
		return '[et_pb_row _builder_version="' . $v . '" _module_preset="default" theme_builder_area="post_content" max_width="' . esc_attr( $max_width ) . '" custom_padding="' . esc_attr( '0px||0px||false|false' ) . '" width="100%"]'
			. '[et_pb_column _builder_version="' . $v . '" _module_preset="default" type="4_4" theme_builder_area="post_content"]'
			. $modules
			. '[/et_pb_column][/et_pb_row]';
	}

	private static function divi_text( string $html ): string {
		$v = self::DIVI_VERSION;
		return '[et_pb_text _builder_version="' . $v . '"]' . $html . '[/et_pb_text]';
	}

	private static function divi_code( string $content ): string {
		$v = self::DIVI_VERSION;
		return '[et_pb_code _builder_version="' . $v . '"]' . $content . '[/et_pb_code]';
	}

	private static function divi_heading_text( string $html, string $color, string $font_size, string $font ): string {
		$v     = self::DIVI_VERSION;
		$attrs = ' _builder_version="' . $v . '"';
		if ( $color !== '' ) {
			$attrs .= ' header_2_text_color="' . esc_attr( $color ) . '"';
		}
		if ( $font_size !== '' ) {
			$attrs .= ' header_2_font_size="' . esc_attr( $font_size ) . '"';
		}
		if ( $font !== '' ) {
			$attrs .= ' header_2_font="' . esc_attr( $font ) . '||||||||"';
		}
		$attrs .= ' custom_margin="||0px||false|false"';
		return '[et_pb_text' . $attrs . ']' . $html . '[/et_pb_text]';
	}

	// -------------------------------------------------------------------------
	// Slug generation
	// -------------------------------------------------------------------------

	private static function slug_available( string $slug ): bool {
		return ! get_page_by_path( $slug, OBJECT, 'page' );
	}

	// -------------------------------------------------------------------------
	// Data helpers
	// -------------------------------------------------------------------------

	public static function get_accent_color( int $team_id ): string {
		global $wpdb;

		$team = $wpdb->get_row(
			$wpdb->prepare( "SELECT slug FROM {$wpdb->prefix}pp_teams WHERE id = %d LIMIT 1", $team_id ),
			ARRAY_A
		);
		if ( ! $team ) {
			return '#000000';
		}

		$schedule_slug = $team['slug'] . '-schedule';
		$schedule      = $wpdb->get_row(
			$wpdb->prepare( "SELECT id FROM {$wpdb->prefix}pp_schedules WHERE slug = %s LIMIT 1", $schedule_slug ),
			ARRAY_A
		);
		if ( ! $schedule ) {
			return '#000000';
		}

		$schedule_id  = (int) $schedule['id'];
		$template_mgr = new Puck_Press_Schedule_Template_Manager( $schedule_id );
		$template_key = $template_mgr->get_current_template_key();

		if ( ! $template_key ) {
			return '#000000';
		}

		$colors = get_option( 'pp_schedule_' . $schedule_id . '_template_colors_' . $template_key, array() );
		if ( is_array( $colors ) && ! empty( $colors['accent'] ) ) {
			return sanitize_hex_color( $colors['accent'] ) ?: '#000000';
		}

		$templates = $template_mgr->get_all_templates();
		if ( isset( $templates[ $template_key ] ) ) {
			$defaults = $templates[ $template_key ]->get_default_colors();
			if ( ! empty( $defaults['accent'] ) ) {
				return sanitize_hex_color( $defaults['accent'] ) ?: '#000000';
			}
		}

		return '#000000';
	}

	private static function get_team_logo( int $team_id ): string {
		global $wpdb;
		$logo = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT target_team_logo FROM {$wpdb->prefix}pp_team_games_display WHERE team_id = %d AND target_team_logo != '' LIMIT 1",
				$team_id
			)
		);
		return $logo ?: '';
	}

	// -------------------------------------------------------------------------
	// Page creation
	// -------------------------------------------------------------------------

	private static function create_page( string $title, string $slug, string $content, string $custom_css = '' ): int|WP_Error {
		$post_id = wp_insert_post(
			array(
				'post_type'    => 'page',
				'post_title'   => sanitize_text_field( $title ),
				'post_name'    => sanitize_title( $slug ),
				'post_content' => $content,
				'post_status'  => 'publish',
			),
			true
		);

		if ( is_wp_error( $post_id ) ) {
			return $post_id;
		}

		self::set_divi_meta( $post_id, $custom_css );
		do_action( 'et_save_post', $post_id );

		return $post_id;
	}

	private static function set_divi_meta( int $post_id, string $custom_css = '' ): void {
		update_post_meta( $post_id, '_et_pb_use_builder',         'on' );
		update_post_meta( $post_id, '_et_builder_version',        self::DIVI_VERSION );
		update_post_meta( $post_id, '_et_pb_built_for_post_type', 'page' );
		update_post_meta( $post_id, '_et_pb_show_page_creation',  'off' );
		update_post_meta( $post_id, '_et_pb_page_layout',         'et_full_width_page' );
		if ( $custom_css !== '' ) {
			update_post_meta( $post_id, '_et_pb_custom_css', $custom_css );
		}
	}

	// -------------------------------------------------------------------------
	// HTML builders
	// -------------------------------------------------------------------------

	private static function build_nav_header_html(
		string $team_name,
		string $team_slug,
		string $logo_url,
		string $schedule_url,
		string $stats_url,
		string $roster_url,
		string $h_text_color,
		string $h_font,
		string $school_url
	): string {
		$logo_html = '';
		if ( $logo_url !== '' ) {
			$logo_html = '<img src="' . esc_url( $logo_url ) . '" alt="' . esc_attr( $team_name ) . '" style="height:50px;width:auto;display:block;">';
		}

		$nav_class = 'pp-nav-' . sanitize_html_class( $team_slug );
		$sep       = '<span style="color:' . esc_attr( $h_text_color ) . ';margin:0 8px;font-weight:400;"> / </span>';
		$h2_style  = 'margin:0;text-transform:uppercase;letter-spacing:2px;'
			. 'color:' . esc_attr( $h_text_color ) . ';'
			. 'font-size:1.9rem;'
			. 'padding-bottom:0px;'
			. ( $h_font !== '' ? 'font-family:\'' . esc_attr( $h_font ) . '\',sans-serif;' : '' );

		$links = array();
		if ( $school_url !== '' ) {
			$links[] = '<a href="' . esc_url( $school_url ) . '" class="' . esc_attr( $nav_class ) . '" target="_blank" rel="noopener">School Site</a>';
		}
		$links[] = '<a href="' . esc_url( $schedule_url ) . '" class="' . esc_attr( $nav_class ) . '">Schedule</a>';
		$links[] = '<a href="' . esc_url( $stats_url ) . '" class="' . esc_attr( $nav_class ) . '">Statistics</a>';
		$links[] = '<a href="' . esc_url( $roster_url ) . '" class="' . esc_attr( $nav_class ) . '">Roster</a>';

		return '<div style="display:flex;align-items:center;justify-content:space-between;padding:12px 0;">'
			. '<div style="display:flex;align-items:center;gap:12px;">'
			. $logo_html
			. '<a href="' . esc_url( trailingslashit( home_url( $team_slug ) ) ) . '" style="text-decoration:none;">'
			. '<h2 style="' . $h2_style . '">' . esc_html( $team_name ) . '</h2>'
			. '</a>'
			. '</div>'
			. '<nav style="display:flex;align-items:center;">'
			. implode( $sep, $links )
			. '</nav>'
			. '</div>';
	}

	private static function build_nav_css( string $team_slug, string $text_color ): string {
		$cls = '.pp-nav-' . sanitize_html_class( $team_slug );
		return $cls . '{'
			. 'color:' . $text_color . ';'
			. 'text-decoration:none;'
			. 'font-weight:600;'
			. 'font-size:0.875rem;'
			. 'letter-spacing:0.5px;'
			. '}'
			. $cls . ':hover{'
			. 'opacity:0.75;'
			. '}';
	}

	private static function build_schedule_heading_html(): string {
		return '<h2 style="text-transform:uppercase;margin:0;font-weight:700;">Schedule</h2>';
	}

	private static function build_section_heading_html( string $label ): string {
		return '<h2 style="text-transform:uppercase;margin:0 0 12px;font-weight:700;">'
			. esc_html( $label )
			. '</h2>';
	}

	// -------------------------------------------------------------------------
	// Page content assemblers
	// -------------------------------------------------------------------------

	private static function build_hero_section( string $h_color, string $padding ): string {
		$v            = self::DIVI_VERSION;
		$divi_padding = self::css_padding_to_divi( $padding );
		return '[et_pb_section fb_built="1" fullwidth="off" _builder_version="' . $v . '" _module_preset="default"'
			. ' use_background_color_gradient="on"'
			. ' background_color_gradient_start="' . esc_attr( $h_color ) . '"'
			. ' background_color_gradient_end="#111111"'
			. ' background_color_gradient_direction="160deg"'
			. ' min_height="180px"'
			. ' custom_margin="40px||0px||false|false"'
			. ' custom_padding="' . esc_attr( $divi_padding ) . '"'
			. ' parallax="on"'
			. ' parallax_method="off"'
			. '][/et_pb_section]';
	}

	private static function build_header_section( string $h_color, string $max_width, string $header_html ): string {
		return self::divi_section(
			$h_color,
			$max_width,
			'15px 0px',
			'||20px||false|false',
			self::divi_row( $max_width, self::divi_code( $header_html ) )
		);
	}

	private static function build_home_content(
		string $max_width,
		string $padding,
		string $schedule_heading_html,
		string $schedule_slug,
		int $team_id,
		string $header_color,
		string $header_font_size,
		string $header_font
	): string {
		$section_hero = self::build_hero_section( $header_color, $padding );

		$section_2 = self::divi_section(
			'rgba(0,0,0,0)',
			$max_width,
			$padding,
			'',
			self::divi_row(
				$max_width,
				self::divi_heading_text( $schedule_heading_html, $header_color, $header_font_size, $header_font ),
				self::divi_code( '[pp-slider schedule="' . esc_attr( $schedule_slug ) . '"]' )
			)
		);

		$section_3 = self::divi_section(
			'rgba(0,0,0,0)',
			$max_width,
			$padding,
			'',
			self::divi_row(
				$max_width,
				self::divi_heading_text( self::build_section_heading_html( 'Latest News' ), $header_color, $header_font_size, $header_font ),
				self::divi_code( '[pp-post-slider post_type="pp_insta_post,pp_game_summary" team="' . $team_id . '" count="6"]' )
			)
		);

		return $section_2 . $section_3 . $section_hero;
	}

	private static function build_schedule_content( string $max_width, string $padding, string $team_name, string $schedule_slug, string $header_color, string $header_font_size, string $header_font ): string {
		return self::divi_section(
			'rgba(0,0,0,0)',
			$max_width,
			$padding,
			'',
			self::divi_row(
				$max_width,
				self::divi_heading_text( self::build_section_heading_html( 'Schedule' ), $header_color, $header_font_size, $header_font ),
				self::divi_code( '[pp-schedule schedule="' . esc_attr( $schedule_slug ) . '"]' )
			)
		);
	}

	private static function build_roster_content( string $max_width, string $padding, string $team_name, string $roster_slug, string $header_color, string $header_font_size, string $header_font ): string {
		return self::divi_section(
			'rgba(0,0,0,0)',
			$max_width,
			$padding,
			'',
			self::divi_row(
				$max_width,
				self::divi_heading_text( self::build_section_heading_html( 'Roster' ), $header_color, $header_font_size, $header_font ),
				self::divi_code( '[pp-roster roster="' . esc_attr( $roster_slug ) . '"]' )
			)
		);
	}

	private static function build_stats_content( string $max_width, string $padding, string $team_name, int $team_id, string $header_color, string $header_font_size, string $header_font ): string {
		return self::divi_section(
			'rgba(0,0,0,0)',
			$max_width,
			$padding,
			'',
			self::divi_row(
				$max_width,
				self::divi_heading_text( self::build_section_heading_html( 'Statistics' ), $header_color, $header_font_size, $header_font ),
				self::divi_code( '[pp-stats team="' . $team_id . '"]' )
			)
		);
	}

	// -------------------------------------------------------------------------
	// Public API
	// -------------------------------------------------------------------------

	public function generate_all( int $team_id, string $max_width, string $padding, string $header_color = '', string $header_font_size = '1.4rem', string $header_font = '', string $header_text_color = '#ffffff', string $school_url = '' ): array|WP_Error {
		global $wpdb;

		$team = $wpdb->get_row(
			$wpdb->prepare( "SELECT id, slug, name FROM {$wpdb->prefix}pp_teams WHERE id = %d LIMIT 1", $team_id ),
			ARRAY_A
		);
		if ( ! $team ) {
			return new WP_Error( 'team_not_found', 'Team not found.' );
		}

		$team_name     = $team['name'];
		$team_slug     = $team['slug'];
		$schedule_slug = $team_slug . '-schedule';
		$roster_slug   = $team_slug . '-roster';
		$accent        = self::get_accent_color( $team_id );
		$logo_url      = self::get_team_logo( $team_id );
		$h_color       = $header_color !== '' ? $header_color : $accent;
		$h_size        = $header_font_size;
		$h_font        = $header_font;

		$slugs = array(
			'home'     => $team_slug,
			'schedule' => $team_slug . '-schedule',
			'roster'   => $team_slug . '-roster',
			'stats'    => $team_slug . '-stats',
		);

		foreach ( $slugs as $type => $slug ) {
			if ( ! self::slug_available( $slug ) ) {
				return new WP_Error(
					'slug_taken',
					sprintf( 'A page with slug "%s" already exists. Delete it first or rename the team.', $slug )
				);
			}
		}

		// Pre-compute all page URLs from the known slugs so the header
		// can be built once and shared across all four pages.
		$schedule_url = trailingslashit( home_url( $slugs['schedule'] ) );
		$roster_url   = trailingslashit( home_url( $slugs['roster'] ) );
		$stats_url    = trailingslashit( home_url( $slugs['stats'] ) );

		$nav_css        = self::build_nav_css( $team_slug, $header_text_color );
		$header_html    = self::build_nav_header_html( $team_name, $team_slug, $logo_url, $schedule_url, $stats_url, $roster_url, $header_text_color, $h_font, $school_url );
		$header_section = self::build_header_section( $h_color, $max_width, $header_html );

		$created = array();

		// 1. Schedule page
		$sched_content = $header_section . self::build_schedule_content( $max_width, $padding, $team_name, $schedule_slug, $h_color, $h_size, $h_font );
		$sched_id      = self::create_page( $team_name . ' Schedule', $slugs['schedule'], $sched_content, $nav_css );
		if ( is_wp_error( $sched_id ) ) {
			return $sched_id;
		}
		$created['schedule'] = $sched_id;

		// 2. Roster page
		$roster_content = $header_section . self::build_roster_content( $max_width, $padding, $team_name, $roster_slug, $h_color, $h_size, $h_font );
		$roster_id      = self::create_page( $team_name . ' Roster', $slugs['roster'], $roster_content, $nav_css );
		if ( is_wp_error( $roster_id ) ) {
			wp_delete_post( $created['schedule'], true );
			return $roster_id;
		}
		$created['roster'] = $roster_id;

		// 3. Stats page
		$stats_content = $header_section . self::build_stats_content( $max_width, $padding, $team_name, $team_id, $h_color, $h_size, $h_font );
		$stats_id      = self::create_page( $team_name . ' Stats', $slugs['stats'], $stats_content, $nav_css );
		if ( is_wp_error( $stats_id ) ) {
			wp_delete_post( $created['schedule'], true );
			wp_delete_post( $created['roster'], true );
			return $stats_id;
		}
		$created['stats'] = $stats_id;

		// 4. Home page
		$schedule_heading = self::build_schedule_heading_html();
		$home_content     = $header_section . self::build_home_content( $max_width, $padding, $schedule_heading, $schedule_slug, $team_id, $h_color, $h_size, $h_font );
		$home_id          = self::create_page( $team_name, $slugs['home'], $home_content, $nav_css );
		if ( is_wp_error( $home_id ) ) {
			wp_delete_post( $created['schedule'], true );
			wp_delete_post( $created['roster'], true );
			wp_delete_post( $created['stats'], true );
			return $home_id;
		}
		$created['home'] = $home_id;

		update_option( sprintf( self::OPTION_PAGES, $team_id ), wp_json_encode( $created ) );

		return $created;
	}

	public function delete_all( int $team_id ): void {
		$ids = $this->get_page_ids( $team_id );
		foreach ( $ids as $post_id ) {
			if ( $post_id > 0 ) {
				wp_delete_post( $post_id, true );
			}
		}
		delete_option( sprintf( self::OPTION_PAGES, $team_id ) );
	}

	public function get_page_ids( int $team_id ): array {
		$raw = get_option( sprintf( self::OPTION_PAGES, $team_id ), '' );
		if ( ! $raw ) {
			return array();
		}
		$decoded = json_decode( $raw, true );
		return is_array( $decoded ) ? $decoded : array();
	}

	public static function get_default_max_width(): string {
		return get_option( self::OPTION_MAX_WIDTH, '1080px' );
	}

	public static function get_default_padding(): string {
		return get_option( self::OPTION_PADDING, '10px 0px' );
	}

	public static function get_default_header_color(): string {
		return get_option( self::OPTION_HEADER_COLOR, '' );
	}

	public static function get_default_header_text_color(): string {
		return get_option( self::OPTION_HEADER_TEXT_COLOR, '#ffffff' );
	}

	public static function get_default_school_url(): string {
		return get_option( self::OPTION_SCHOOL_URL, '' );
	}

	public static function get_default_header_font_size(): string {
		return get_option( self::OPTION_HEADER_FONT_SIZE, '1.4rem' );
	}

	public static function get_default_header_font(): string {
		return get_option( self::OPTION_HEADER_FONT, '' );
	}

	public static function save_defaults( string $max_width, string $padding, string $header_color = '', string $header_font_size = '1.4rem', string $header_font = '', string $header_text_color = '#ffffff', string $school_url = '' ): void {
		update_option( self::OPTION_MAX_WIDTH, sanitize_text_field( $max_width ) );
		update_option( self::OPTION_PADDING, sanitize_text_field( $padding ) );
		update_option( self::OPTION_HEADER_COLOR, sanitize_hex_color( $header_color ) ?: '' );
		update_option( self::OPTION_HEADER_FONT_SIZE, sanitize_text_field( $header_font_size ) );
		update_option( self::OPTION_HEADER_FONT, sanitize_text_field( $header_font ) );
		update_option( self::OPTION_HEADER_TEXT_COLOR, sanitize_hex_color( $header_text_color ) ?: '#ffffff' );
		update_option( self::OPTION_SCHOOL_URL, esc_url_raw( $school_url ) );
	}
}
