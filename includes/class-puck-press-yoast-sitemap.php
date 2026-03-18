<?php

/**
 * Integrates player pages with Yoast SEO's XML sitemap.
 *
 * Shows an admin notice if Yoast is not active. When Yoast is active,
 * registers a players-sitemap.xml entry so Google can discover all
 * /player/{slug} pages, which are invisible to Yoast by default because
 * they are generated from a custom DB table rather than wp_posts.
 *
 * Also detects when WordPress's rewrite rule cache is stale (i.e. the
 * pp_player rule is missing) and prompts the admin to flush permalinks.
 */
class Puck_Press_Yoast_Sitemap {

	public static function init(): void {
		add_action( 'admin_notices', array( self::class, 'maybe_show_yoast_notice' ) );
		add_action( 'admin_notices', array( self::class, 'maybe_show_permalink_notice' ) );
		add_action( 'admin_post_pp_flush_permalinks', array( self::class, 'handle_flush_permalinks' ) );

		if ( ! self::is_yoast_active() ) {
			return;
		}

		add_filter( 'wpseo_sitemap_index', array( self::class, 'add_to_sitemap_index' ) );

		// Register the 'players' type with Yoast so requests for
		// players-sitemap.xml are recognised and routed correctly.
		// Must run after Yoast's own init (priority 20).
		add_action( 'init', array( self::class, 'register_sitemap_type' ), 20 );
	}

	// ── Admin notices ─────────────────────────────────────────────────────────

	public static function maybe_show_yoast_notice(): void {
		if ( self::is_yoast_active() ) {
			return;
		}
		echo '<div class="notice notice-warning">'
			. '<p><strong>Puck Press:</strong> Yoast SEO is not active. '
			. 'Player pages will not appear in the XML sitemap until Yoast SEO is installed and activated.</p>'
			. '</div>';
	}

	/**
	 * Shows a notice when the pp_player rewrite rule is missing from
	 * WordPress's cached rules — the symptom is player URLs returning 404.
	 * Provides a one-click "Flush now" link so the admin doesn't need to
	 * know about Settings → Permalinks.
	 */
	public static function maybe_show_permalink_notice(): void {
		if ( self::player_rule_is_cached() ) {
			return;
		}

		$flush_url = wp_nonce_url(
			admin_url( 'admin-post.php?action=pp_flush_permalinks' ),
			'pp_flush_permalinks'
		);

		$divi_note = ( get_template() === 'Divi' )
			? ' If Divi styling breaks after flushing, also clear its CSS cache via <strong>Divi → Theme Options → Builder → Clear Static CSS</strong>.'
			: '';

		echo '<div class="notice notice-warning">'
			. '<p><strong>Puck Press:</strong> Player page URLs are not active — '
			. 'the rewrite rules need to be flushed. '
			. '<a href="' . esc_url( $flush_url ) . '"><strong>Flush permalinks now</strong></a>.'
			. $divi_note
			. '</p>'
			. '</div>';
	}

	/**
	 * Handles the flush-permalinks admin action, then redirects back to
	 * whichever admin page the user was on.
	 */
	public static function handle_flush_permalinks(): void {
		check_admin_referer( 'pp_flush_permalinks' );
		flush_rewrite_rules();
		$redirect = wp_get_referer() ?: admin_url();
		wp_safe_redirect( $redirect );
		exit;
	}

	// ── Yoast sitemap integration ─────────────────────────────────────────────

	/**
	 * Registers the 'players' sitemap type with Yoast.
	 * This is what makes players-sitemap.xml a valid URL rather than a 404.
	 */
	public static function register_sitemap_type(): void {
		global $wpseo_sitemaps;
		if ( ! isset( $wpseo_sitemaps ) ) {
			return;
		}
		$wpseo_sitemaps->register_sitemap( 'players', array( self::class, 'serve_players_sitemap' ) );
	}

	/**
	 * Called by Yoast when players-sitemap.xml is requested.
	 * Outputs the full XML document and exits.
	 */
	public static function serve_players_sitemap(): void {
		global $wpdb;

		$players = $wpdb->get_results(
			"SELECT name FROM {$wpdb->prefix}pp_team_players_display",
			ARRAY_A
		);

		header( 'Content-Type: application/xml; charset=UTF-8' );

		echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
		echo '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";

		foreach ( $players as $player ) {
			$slug = sanitize_title( $player['name'] );
			echo '<url>'
				. '<loc>' . esc_url( home_url( '/player/' . $slug ) ) . '</loc>'
				. '<changefreq>weekly</changefreq>'
				. '<priority>0.6</priority>'
				. '</url>' . "\n";
		}

		echo '</urlset>';
		exit;
	}

	/**
	 * Appends a players-sitemap.xml entry to the Yoast sitemap index.
	 */
	public static function add_to_sitemap_index( string $index ): string {
		$index .= '<sitemap>'
				. '<loc>' . esc_url( home_url( '/players-sitemap.xml' ) ) . '</loc>'
				. '<lastmod>' . date( 'c' ) . '</lastmod>'
				. '</sitemap>';
		return $index;
	}

	// ── Helpers ───────────────────────────────────────────────────────────────

	/**
	 * Returns true when the pp_player rewrite rule is present in the
	 * cached rules, meaning player URLs will resolve correctly.
	 */
	private static function player_rule_is_cached(): bool {
		$rules = get_option( 'rewrite_rules', array() );
		foreach ( $rules as $rewrite ) {
			if ( strpos( $rewrite, 'pp_player' ) !== false ) {
				return true;
			}
		}
		return false;
	}

	private static function is_yoast_active(): bool {
		return is_plugin_active( 'wordpress-seo/wp-seo.php' );
	}
}
