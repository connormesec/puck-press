<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Overrides the WordPress avatar URL for the `puck-press` author user so
 * recap bylines render with a team logo instead of the default Gravatar
 * mystery icon — without requiring the operator to create a Gravatar
 * account or install Simple Local Avatars.
 *
 * Logo source priority:
 *   1. Yoast organization logo (wpseo_titles[company_logo])
 *   2. Most-recent target_team_logo from pp_schedule_games_display
 *
 * Falls through to default Gravatar behavior when neither source has a URL,
 * or when the user being rendered isn't the recap author account.
 */
class Puck_Press_Seo_Avatar {

	public const AUTHOR_LOGIN = 'puck-press';

	public static function init(): void {
		add_filter( 'pre_get_avatar_data', array( self::class, 'filter_avatar' ), 10, 2 );
	}

	/**
	 * @param array $args         The avatar args (url, size, default, etc.).
	 * @param mixed $id_or_email  User ID, email, WP_User, comment, or post.
	 * @return array
	 */
	public static function filter_avatar( $args, $id_or_email ): array {
		if ( ! (bool) get_option( 'pp_seo_enabled', 1 ) ) {
			return $args;
		}

		$user = self::resolve_user( $id_or_email );
		if ( ! $user || $user->user_login !== self::AUTHOR_LOGIN ) {
			return $args;
		}

		$url = self::team_avatar_url();
		if ( ! $url ) {
			return $args;
		}

		$args['url']          = $url;
		$args['found_avatar'] = true;
		return $args;
	}

	/**
	 * Public for the admin checklist — returns the URL the avatar override
	 * would emit, or null if no source is configured.
	 */
	public static function resolved_url(): ?string {
		return self::team_avatar_url();
	}

	private static function team_avatar_url(): ?string {
		$titles = get_option( 'wpseo_titles', array() );
		if ( is_array( $titles ) && ! empty( $titles['company_logo'] ) ) {
			return (string) $titles['company_logo'];
		}

		global $wpdb;
		$logo = $wpdb->get_var(
			"SELECT target_team_logo
			 FROM {$wpdb->prefix}pp_schedule_games_display
			 WHERE target_team_logo IS NOT NULL AND target_team_logo != ''
			 ORDER BY game_timestamp DESC
			 LIMIT 1"
		);
		return $logo ? (string) $logo : null;
	}

	private static function resolve_user( $id_or_email ) {
		if ( $id_or_email instanceof WP_User ) {
			return $id_or_email;
		}
		if ( is_numeric( $id_or_email ) ) {
			return get_user_by( 'id', (int) $id_or_email ) ?: null;
		}
		if ( is_string( $id_or_email ) && strpos( $id_or_email, '@' ) !== false ) {
			return get_user_by( 'email', $id_or_email ) ?: null;
		}
		if ( is_object( $id_or_email ) ) {
			$user_id = $id_or_email->user_id ?? ( $id_or_email->post_author ?? null );
			if ( $user_id ) {
				return get_user_by( 'id', (int) $user_id ) ?: null;
			}
		}
		return null;
	}
}
