<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Yoast schema graph piece: emits a Schema.org SportsEvent for a single
 * pp_game_summary recap page.
 *
 * Activates only on singular pp_game_summary URLs. Pulls the game row from
 * pp_schedule_games_display by extracting the trailing numeric game_id from
 * the post's _game_id meta (which stores the full slug, e.g.
 * "bobcats-fall-to-ice-lions-...-31408").
 */
class Puck_Press_Schema_Sports_Event extends \Yoast\WP\SEO\Generators\Schema\Abstract_Schema_Piece {

	public function is_needed() {
		if ( ! isset( $this->context->indexable ) ) {
			return false;
		}
		return $this->context->indexable->object_type === 'post'
			&& $this->context->indexable->object_sub_type === 'pp_game_summary';
	}

	public function generate() {
		$post_id  = (int) $this->context->indexable->object_id;
		$game_id  = Puck_Press_Seo_Yoast::extract_external_game_id( $post_id );
		if ( ! $game_id ) {
			return false;
		}

		$game = Puck_Press_Seo_Yoast::lookup_game_row_public( $game_id );
		if ( ! $game ) {
			return false;
		}

		$config    = Puck_Press_Seo_Detector::resolved_config();
		$post_url  = (string) $this->context->canonical;
		$image_url = get_the_post_thumbnail_url( $post_id, 'full' );
		$image_url = $image_url ? (string) $image_url : null;

		return Puck_Press_Seo_Templates::sports_event( $game, $config, $post_url, $image_url );
	}
}
