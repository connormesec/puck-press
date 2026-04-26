<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Auto-derives team identity for SEO from the most recent game row in
 * pp_schedule_games_display. Operator overrides (pp_seo_* options) take
 * precedence when set.
 */
class Puck_Press_Seo_Detector {

	/**
	 * Returns the resolved SEO config: detected values overlaid with operator
	 * overrides. This is what the rest of the SEO module reads.
	 */
	public static function resolved_config(): array {
		$detected = self::detect();
		return array(
			'school_name'     => $detected['school_name'],
			'team_nickname'   => $detected['team_nickname'],
			'team_short_name' => self::override( 'pp_seo_team_short_name', $detected['team_short_name'] ),
			'league'          => $detected['league'],
			'division'        => $detected['division'],
			'primary_keyword' => self::override( 'pp_seo_primary_keyword', $detected['primary_keyword'] ),
			'city'            => (string) get_option( 'pp_seo_city', '' ),
			'state'           => (string) get_option( 'pp_seo_state', '' ),
			'enabled'         => (bool) get_option( 'pp_seo_enabled', 1 ),
		);
	}

	/**
	 * Returns only the auto-detected values (no overrides applied). Used by
	 * the settings UI to show "what we detected" alongside override fields.
	 */
	public static function detect(): array {
		global $wpdb;

		$row = $wpdb->get_row(
			"SELECT target_team_name, target_team_nickname, source_type, source
			 FROM {$wpdb->prefix}pp_schedule_games_display
			 WHERE target_team_name IS NOT NULL AND target_team_name != ''
			 ORDER BY game_timestamp DESC
			 LIMIT 1",
			ARRAY_A
		);

		if ( ! $row ) {
			return self::empty_detection();
		}

		$school   = (string) ( $row['target_team_name'] ?? '' );
		$nickname = (string) ( $row['target_team_nickname'] ?? '' );

		return array(
			'school_name'     => $school,
			'team_nickname'   => $nickname,
			'team_short_name' => self::derive_short_name( $school, $nickname ),
			'league'          => self::derive_league( (string) ( $row['source_type'] ?? '' ) ),
			'division'        => self::derive_division( (string) ( $row['source'] ?? '' ) ),
			'primary_keyword' => self::derive_keyword( $school ),
		);
	}

	private static function derive_short_name( string $school, string $nickname ): string {
		if ( $school === '' && $nickname === '' ) {
			return '';
		}
		if ( $nickname === '' ) {
			return $school;
		}
		// Initials of capitalised words. "Montana State University" → "MSU".
		$initials = '';
		foreach ( preg_split( '/\s+/', $school ) as $word ) {
			if ( $word !== '' && preg_match( '/^[A-Z]/', $word ) ) {
				$initials .= mb_substr( $word, 0, 1 );
			}
		}
		if ( strlen( $initials ) >= 2 ) {
			return trim( "$initials $nickname" );
		}
		return trim( "$school $nickname" );
	}

	private static function derive_league( string $source_type ): string {
		if ( stripos( $source_type, 'acha' ) === 0 ) {
			return 'ACHA';
		}
		if ( stripos( $source_type, 'usphl' ) === 0 ) {
			return 'USPHL';
		}
		return '';
	}

	private static function derive_division( string $source ): string {
		if ( preg_match( '/\b(?:Division\s*([123])|D([123]))\b/i', $source, $m ) ) {
			$num = $m[1] !== '' ? $m[1] : $m[2];
			return "D$num";
		}
		if ( preg_match( '/\bDivision\s*(I{1,3})\b/i', $source, $m ) ) {
			$map = array(
				'I'   => 'D1',
				'II'  => 'D2',
				'III' => 'D3',
			);
			$key = strtoupper( $m[1] );
			return $map[ $key ] ?? '';
		}
		return '';
	}

	private static function derive_keyword( string $school ): string {
		if ( $school === '' ) {
			return '';
		}
		$stripped = preg_replace( '/\b(?:University|College|State College|Institute of Technology)\b/i', '', $school );
		$stripped = preg_replace( '/\s+/', ' ', trim( $stripped ) );
		if ( $stripped === '' ) {
			return '';
		}
		return $stripped . ' hockey';
	}

	private static function empty_detection(): array {
		return array(
			'school_name'     => '',
			'team_nickname'   => '',
			'team_short_name' => '',
			'league'          => '',
			'division'        => '',
			'primary_keyword' => '',
		);
	}

	private static function override( string $option_key, string $default ): string {
		$val = trim( (string) get_option( $option_key, '' ) );
		return $val !== '' ? $val : $default;
	}
}
