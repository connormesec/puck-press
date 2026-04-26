<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Yoast schema graph piece: emits a Schema.org ItemList of upcoming
 * SportsEvents on any page containing the [pp-schedule] shortcode.
 *
 * Capped at 10 upcoming games. Past games are excluded — they each have
 * their own recap page emitting a single SportsEvent piece, so listing them
 * here would duplicate the entity in two places.
 */
class Puck_Press_Schema_Sports_Event_List extends \Yoast\WP\SEO\Generators\Schema\Abstract_Schema_Piece {

	private const MAX_UPCOMING = 10;

	public function is_needed() {
		if ( ! isset( $this->context->indexable ) ) {
			return false;
		}
		if ( $this->context->indexable->object_type !== 'post' ) {
			return false;
		}
		$post = isset( $this->context->post ) ? $this->context->post : null;
		if ( ! $post || empty( $post->post_content ) ) {
			return false;
		}
		return has_shortcode( $post->post_content, 'pp-schedule' );
	}

	public function generate() {
		$games = self::fetch_upcoming( self::MAX_UPCOMING );
		if ( empty( $games ) ) {
			return false;
		}

		$config    = Puck_Press_Seo_Detector::resolved_config();
		$list_url  = (string) $this->context->canonical;
		$list_name = trim( ( $config['team_short_name'] ?? '' ) . ' Schedule' );
		if ( $list_name === 'Schedule' ) {
			$list_name = ( $config['primary_keyword'] !== '' ? ucfirst( $config['primary_keyword'] ) : 'Hockey' ) . ' Schedule';
		}

		return Puck_Press_Seo_Templates::sports_event_item_list( $games, $config, $list_url, $list_name );
	}

	private static function fetch_upcoming( int $limit ): array {
		global $wpdb;
		$display   = $wpdb->prefix . 'pp_schedule_games_display';
		$schedules = $wpdb->prefix . 'pp_schedules';

		// Pin to the main schedule (or lowest-id fallback) so we don't pick up
		// rows from rival teams' perspectives.
		$schedule_id = (int) $wpdb->get_var(
			"SELECT id FROM $schedules ORDER BY is_main DESC, id ASC LIMIT 1"
		);
		if ( ! $schedule_id ) {
			return array();
		}

		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT *
				 FROM $display
				 WHERE schedule_id = %d
				   AND game_timestamp >= NOW()
				   AND ( game_status IS NULL OR game_status NOT REGEXP %s )
				 ORDER BY game_timestamp ASC
				 LIMIT %d",
				$schedule_id,
				'^Final',
				$limit
			),
			ARRAY_A
		);

		return $rows ?: array();
	}
}
