<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Pure builders for Yoast title/description/keyword strings and Schema.org
 * JSON-LD nodes from a game row + resolved SEO config. No I/O — callers pass
 * in data, get strings/arrays back.
 *
 * Game row shape: an associative array matching wp_pp_schedule_games_display
 * columns (target_team_name, target_team_nickname, target_score,
 * opponent_team_name, opponent_team_nickname, opponent_score, game_status,
 * game_timestamp, home_or_away, venue, target_team_logo, opponent_team_logo).
 */
class Puck_Press_Seo_Templates {

	/**
	 * Build Yoast metadata strings for a completed game summary post.
	 *
	 * @param array $game   Row from pp_schedule_games_display.
	 * @param array $config Resolved config from Puck_Press_Seo_Detector::resolved_config().
	 * @return array{title: string, desc: string, focuskw: string}
	 */
	public static function game_yoast_meta( array $game, array $config ): array {
		$team_short  = $config['team_short_name'] ?: trim( ( $game['target_team_name'] ?? '' ) . ' ' . ( $game['target_team_nickname'] ?? '' ) );
		$opp_nick    = (string) ( $game['opponent_team_nickname'] ?? '' );
		$opp_school  = (string) ( $game['opponent_team_name'] ?? '' );
		$opp_full    = trim( $opp_school . ' ' . $opp_nick );
		$status      = (string) ( $game['game_status'] ?? '' );
		$completed   = self::is_completed( $status );
		$ts          = (string) ( $game['game_timestamp'] ?? '' );
		$short_date  = self::format_date( $ts, 'M j' );
		$long_date   = self::format_date( $ts, 'M j, Y' );
		$tscore      = (int) ( $game['target_score'] ?? 0 );
		$oscore      = (int) ( $game['opponent_score'] ?? 0 );
		$result_word = self::result_word( $tscore, $oscore );

		if ( $completed ) {
			$title = sprintf( '%s vs %s | %d-%d %s | %s', $team_short, $opp_nick, $tscore, $oscore, $status, $short_date );
		} else {
			$title = sprintf( '%s vs %s | %s', $team_short, $opp_nick, $short_date );
		}
		$desc = self::build_event_description( $game, $config, $completed );

		$focuskw = trim( $team_short . ' vs ' . $opp_nick );

		return array(
			'title'   => $title,
			'desc'    => $desc,
			'focuskw' => $focuskw,
		);
	}

	/**
	 * Build a Schema.org SportsEvent node for a single game.
	 *
	 * @param array       $game      Row from pp_schedule_games_display.
	 * @param array       $config    Resolved config.
	 * @param string|null $post_url  Permalink of the recap post. Used for @id and subjectOf.
	 * @param string|null $image_url Featured image URL of the recap. Optional — Google
	 *                               can render this inline in event rich results.
	 * @return array
	 */
	public static function sports_event( array $game, array $config, ?string $post_url = null, ?string $image_url = null ): array {
		$home_away    = (string) ( $game['home_or_away'] ?? '' );
		$target_team  = self::team_node( $game, 'target', $config );
		$opponent     = self::team_node( $game, 'opponent', $config );
		$home         = $home_away === 'home' ? $target_team : $opponent;
		$away         = $home_away === 'home' ? $opponent : $target_team;
		$status       = (string) ( $game['game_status'] ?? '' );
		$completed    = self::is_completed( $status );
		$tscore       = isset( $game['target_score'] ) ? (int) $game['target_score'] : null;
		$oscore       = isset( $game['opponent_score'] ) ? (int) $game['opponent_score'] : null;

		$node = array(
			'@type'        => 'SportsEvent',
			'name'         => $target_team['name'] . ' vs ' . $opponent['name'],
			'description'  => self::build_event_description( $game, $config, $completed ),
			'startDate'    => self::format_iso_date( (string) ( $game['game_timestamp'] ?? '' ) ),
			'eventStatus'  => $completed
				? 'https://schema.org/EventCompleted'
				: 'https://schema.org/EventScheduled',
			'eventAttendanceMode' => 'https://schema.org/OfflineEventAttendanceMode',
			'homeTeam'     => $home,
			'awayTeam'     => $away,
			'sport'        => 'Ice Hockey',
		);

		$organizer = self::build_organizer( (string) ( $config['league'] ?? '' ) );
		if ( $organizer !== null ) {
			$node['organizer'] = $organizer;
		}

		if ( $image_url ) {
			$node['image'] = $image_url;
		}

		if ( $post_url ) {
			$node['@id']       = $post_url . '#sportsevent';
			$node['subjectOf'] = array( '@id' => $post_url );
		}

		$venue = (string) ( $game['venue'] ?? '' );
		if ( $venue !== '' ) {
			$location = array(
				'@type' => 'Place',
				'name'  => $venue,
			);
			if ( $config['city'] !== '' || $config['state'] !== '' ) {
				$address = array( '@type' => 'PostalAddress' );
				if ( $config['city'] !== '' ) {
					$address['addressLocality'] = $config['city'];
				}
				if ( $config['state'] !== '' ) {
					$address['addressRegion'] = $config['state'];
				}
				$address['addressCountry'] = 'US';
				$location['address']       = $address;
			}
			$node['location'] = $location;
		}

		if ( $completed && $tscore !== null && $oscore !== null ) {
			$home_score = $home_away === 'home' ? $tscore : $oscore;
			$away_score = $home_away === 'home' ? $oscore : $tscore;
			$node['competitor'] = array(
				array_merge( $home, array( 'score' => $home_score ) ),
				array_merge( $away, array( 'score' => $away_score ) ),
			);
		}

		return $node;
	}

	/**
	 * Build a Schema.org ItemList of SportsEvent items, one per upcoming game.
	 *
	 * @param array  $games     Rows from pp_schedule_games_display.
	 * @param array  $config    Resolved config.
	 * @param string $list_url  URL of the schedule page (for @id).
	 * @param string $list_name Name of the list (e.g. "MSU Bobcats Schedule").
	 * @return array
	 */
	public static function sports_event_item_list( array $games, array $config, string $list_url, string $list_name ): array {
		$elements = array();
		$pos      = 1;
		foreach ( $games as $game ) {
			$post_url = ! empty( $game['post_link'] ) ? (string) $game['post_link'] : null;
			$elements[] = array(
				'@type'    => 'ListItem',
				'position' => $pos++,
				'item'     => self::sports_event( $game, $config, $post_url ),
			);
		}

		return array(
			'@type'           => 'ItemList',
			'@id'             => $list_url . '#schedule-list',
			'name'            => $list_name,
			'numberOfItems'   => count( $elements ),
			'itemListElement' => $elements,
		);
	}

	// ── helpers ─────────────────────────────────────────────────────────────

	private static function team_node( array $game, string $prefix, array $config ): array {
		$school = (string) ( $game[ "{$prefix}_team_name" ] ?? '' );
		$nick   = (string) ( $game[ "{$prefix}_team_nickname" ] ?? '' );
		$logo   = (string) ( $game[ "{$prefix}_team_logo" ] ?? '' );
		$name   = trim( $school . ' ' . $nick );
		if ( $name === '' ) {
			$name = $school !== '' ? $school : $nick;
		}
		$node = array(
			'@type' => 'SportsTeam',
			'name'  => $name,
		);
		if ( $logo !== '' ) {
			$node['logo'] = $logo;
		}
		return $node;
	}

	/**
	 * Single source of truth for the event description, used by both the
	 * Yoast meta description (postmeta) and the SportsEvent JSON-LD node.
	 */
	private static function build_event_description( array $game, array $config, bool $completed ): string {
		$team_short = $config['team_short_name'] ?: trim( ( $game['target_team_name'] ?? '' ) . ' ' . ( $game['target_team_nickname'] ?? '' ) );
		$opp_nick   = (string) ( $game['opponent_team_nickname'] ?? '' );
		$opp_school = (string) ( $game['opponent_team_name'] ?? '' );
		$opp_full   = trim( $opp_school . ' ' . $opp_nick );
		$long_date  = self::format_date( (string) ( $game['game_timestamp'] ?? '' ), 'M j, Y' );
		$tscore     = (int) ( $game['target_score'] ?? 0 );
		$oscore     = (int) ( $game['opponent_score'] ?? 0 );

		if ( $completed ) {
			return sprintf(
				'Recap of %s vs %s, %s. Final %d-%d (%s).%s',
				$team_short,
				$opp_full !== '' ? $opp_full : $opp_nick,
				$long_date,
				$tscore,
				$oscore,
				self::result_word( $tscore, $oscore ),
				$config['primary_keyword'] !== '' ? ' Read the full ' . $config['primary_keyword'] . ' game summary.' : ''
			);
		}
		return sprintf(
			'%s vs %s on %s.%s',
			$team_short,
			$opp_full !== '' ? $opp_full : $opp_nick,
			$long_date,
			$config['primary_keyword'] !== '' ? ' Follow ' . $config['primary_keyword'] . ' all season.' : ''
		);
	}

	/**
	 * Map a detected league code to a SportsOrganization node. Returns null
	 * for leagues we don't know about (operator hasn't set one, or it's
	 * something custom).
	 */
	private static function build_organizer( string $league ): ?array {
		$registry = array(
			'ACHA'  => array(
				'name' => 'American Collegiate Hockey Association',
				'url'  => 'https://www.achahockey.org/',
			),
			'USPHL' => array(
				'name' => 'United States Premier Hockey League',
				'url'  => 'https://www.usphl.com/',
			),
		);
		if ( ! isset( $registry[ $league ] ) ) {
			return null;
		}
		return array(
			'@type' => 'SportsOrganization',
			'name'  => $registry[ $league ]['name'],
			'url'   => $registry[ $league ]['url'],
		);
	}

	private static function is_completed( string $status ): bool {
		return $status !== '' && stripos( $status, 'Final' ) === 0;
	}

	private static function result_word( int $tscore, int $oscore ): string {
		if ( $tscore > $oscore ) {
			return 'win';
		}
		if ( $tscore < $oscore ) {
			return 'loss';
		}
		return 'tie';
	}

	private static function format_date( string $mysql_dt, string $format ): string {
		if ( $mysql_dt === '' ) {
			return '';
		}
		$ts = strtotime( $mysql_dt );
		return $ts ? date_i18n( $format, $ts ) : '';
	}

	/**
	 * Render an ISO 8601 date string. If the time portion is 00:00:00, return
	 * date-only (Schema.org accepts both); otherwise full datetime in the
	 * site's timezone.
	 */
	private static function format_iso_date( string $mysql_dt ): string {
		if ( $mysql_dt === '' ) {
			return '';
		}
		if ( substr( $mysql_dt, -8 ) === '00:00:00' ) {
			return substr( $mysql_dt, 0, 10 );
		}
		try {
			$dt = new DateTime( $mysql_dt, wp_timezone() );
			return $dt->format( DateTime::ATOM );
		} catch ( Exception $e ) {
			return substr( $mysql_dt, 0, 10 );
		}
	}
}
