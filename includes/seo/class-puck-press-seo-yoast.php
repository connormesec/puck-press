<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Glue between Puck Press game data and Yoast SEO.
 *
 * - On game-summary post creation, writes Yoast title/description/keyword
 *   postmeta derived from the game row + resolved SEO config.
 * - Registers Schema.org graph pieces so single recap pages emit a
 *   SportsEvent node and the schedule page emits an ItemList of upcoming
 *   SportsEvents (graph pieces wired in init()).
 *
 * No-op when Yoast is not active or pp_seo_enabled is off — game posts are
 * still created normally, just without the auto-applied SEO metadata.
 */
class Puck_Press_Seo_Yoast {

	public static function init(): void {
		if ( ! self::is_yoast_active() ) {
			return;
		}
		if ( ! (bool) get_option( 'pp_seo_enabled', 1 ) ) {
			return;
		}

		// NOTE: schema piece class files are loaded lazily inside
		// register_graph_pieces() because they extend a Yoast namespaced
		// class that is not autoloaded yet at plugins_loaded time. By the
		// time the wpseo_schema_graph_pieces filter fires, Yoast's
		// autoloader has registered.
		add_filter(
			'wpseo_schema_graph_pieces',
			array( self::class, 'register_graph_pieces' ),
			11,
			2
		);
	}

	/**
	 * Append our SportsEvent pieces to Yoast's schema graph. Yoast injects
	 * $context and $helpers into each piece after instantiation.
	 *
	 * @param array $pieces  Existing graph pieces.
	 * @param mixed $context Yoast Meta_Tags_Context (typed loosely so we
	 *                       don't need a `use` statement that would couple
	 *                       this file to Yoast's namespace at load time).
	 * @return array
	 */
	public static function register_graph_pieces( array $pieces, $context ): array {
		require_once plugin_dir_path( __FILE__ ) . 'schema/class-puck-press-schema-sports-event.php';
		require_once plugin_dir_path( __FILE__ ) . 'schema/class-puck-press-schema-sports-event-list.php';

		$pieces[] = new Puck_Press_Schema_Sports_Event();
		$pieces[] = new Puck_Press_Schema_Sports_Event_List();
		return $pieces;
	}

	/**
	 * Apply Yoast metadata to a freshly-created game summary post.
	 *
	 * Called by Puck_Press_Game_Post_Creator::testCreatePost() after the
	 * post insert succeeds. Silently no-ops if Yoast is missing, the SEO
	 * feature is disabled, or no matching game row exists.
	 *
	 * @param int    $post_id WordPress post ID of the recap.
	 * @param string $game_id External game ID (e.g. "31408").
	 */
	public static function write_game_meta( int $post_id, string $game_id ): void {
		if ( ! self::is_yoast_active() ) {
			return;
		}
		if ( ! (bool) get_option( 'pp_seo_enabled', 1 ) ) {
			return;
		}

		require_once plugin_dir_path( __FILE__ ) . 'class-puck-press-seo-detector.php';
		require_once plugin_dir_path( __FILE__ ) . 'class-puck-press-seo-templates.php';

		$game = self::lookup_game_row( $game_id );
		if ( ! $game ) {
			return;
		}

		$config = Puck_Press_Seo_Detector::resolved_config();
		$meta   = Puck_Press_Seo_Templates::game_yoast_meta( $game, $config );

		update_post_meta( $post_id, '_yoast_wpseo_title', $meta['title'] );
		update_post_meta( $post_id, '_yoast_wpseo_metadesc', $meta['desc'] );
		update_post_meta( $post_id, '_yoast_wpseo_focuskw', $meta['focuskw'] );

		self::rebuild_indexable( $post_id );
	}

	/**
	 * Find the canonical schedule row for a game_id. Prefers the row from the
	 * main schedule (is_main = 1); falls back to the lowest-id schedule, then
	 * to any row matching the game_id.
	 */
	public static function lookup_game_row_public( string $game_id ): ?array {
		return self::lookup_game_row( $game_id );
	}

	private static function lookup_game_row( string $game_id ): ?array {
		global $wpdb;

		$display = $wpdb->prefix . 'pp_schedule_games_display';
		$schedules = $wpdb->prefix . 'pp_schedules';

		$row = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT d.*
				 FROM $display d
				 LEFT JOIN $schedules s ON s.id = d.schedule_id
				 WHERE d.game_id = %s
				 ORDER BY s.is_main DESC, s.id ASC, d.id ASC
				 LIMIT 1",
				$game_id
			),
			ARRAY_A
		);

		return $row ?: null;
	}

	/**
	 * Extract the trailing numeric external game_id from a recap post's
	 * _game_id meta. Returns null if no numeric suffix is present.
	 *
	 * pp_game_summary posts store _game_id as the full slug
	 * (e.g. "bobcats-fall-to-ice-lions-...-31408"), with the raw API game_id
	 * as the trailing digits.
	 */
	public static function extract_external_game_id( int $post_id ): ?string {
		$raw = (string) get_post_meta( $post_id, '_game_id', true );
		if ( $raw === '' ) {
			$post = get_post( $post_id );
			$raw  = $post ? (string) $post->post_name : '';
		}
		if ( preg_match( '/(\d+)$/', $raw, $m ) ) {
			return $m[1];
		}
		return null;
	}

	private static function rebuild_indexable( int $post_id ): void {
		if ( ! function_exists( 'YoastSEO' ) ) {
			return;
		}
		try {
			$container = YoastSEO()->classes;
			$builder   = $container->get( \Yoast\WP\SEO\Builders\Indexable_Builder::class );
			$repo      = $container->get( \Yoast\WP\SEO\Repositories\Indexable_Repository::class );
			$existing  = $repo->find_by_id_and_type( $post_id, 'post', false );
			$built     = $builder->build_for_id_and_type( $post_id, 'post', $existing ?: false );
			if ( $built ) {
				$built->save();
			}
		} catch ( Throwable $e ) {
			// Yoast container not ready (e.g. early in the request lifecycle).
			// Indexable will lazy-build on first frontend visit.
		}
	}

	/**
	 * True iff the Puck Press SEO integration is fully enabled — Yoast loaded
	 * AND pp_seo_enabled option set. Other modules (e.g. legacy schedule
	 * schema) check this to know whether to defer to our Yoast graph pieces.
	 */
	public static function is_active(): bool {
		return self::is_yoast_active() && (bool) get_option( 'pp_seo_enabled', 1 );
	}

	private static function is_yoast_active(): bool {
		if ( function_exists( 'YoastSEO' ) ) {
			return true;
		}
		// Fallback: is_plugin_active works in admin; on frontend we may need
		// to load the plugin.php helpers first.
		if ( ! function_exists( 'is_plugin_active' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}
		return is_plugin_active( 'wordpress-seo/wp-seo.php' );
	}
}
