<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Season health: warns when live data mixes seasons or sources are
 * misconfigured, and links to the fix (set the current season, archive the
 * old season, or correct a source).
 */
class Puck_Press_Season_Health {

	private static function teams_url( array $args = array() ): string {
		return add_query_arg( array_merge( array( 'page' => 'puck-press', 'tab' => 'teams' ), $args ), admin_url( 'admin.php' ) );
	}

	private static function set_current_url( string $key ): string {
		return wp_nonce_url(
			add_query_arg( array( 'action' => 'pp_set_current_season', 'season_key' => $key ), admin_url( 'admin-post.php' ) ),
			'pp_set_current_season'
		);
	}

	/**
	 * One line per problem, as HTML.
	 *
	 * @return string[]
	 */
	public static function get_messages( ?array $issues = null ): array {
		$issues   = $issues ?? Puck_Press_Season::get_health_issues();
		$current  = Puck_Press_Season::get_current_key();
		$messages = array();

		if ( $issues['no_current'] ) {
			$msg = 'No current season is set, so live stats show each team\'s newest season.';
			if ( $issues['suggested'] !== '' ) {
				$msg .= sprintf(
					' <a href="%s" class="button button-small">Use %s</a>',
					esc_url( self::set_current_url( $issues['suggested'] ) ),
					esc_html( $issues['suggested'] )
				);
			}
			$msg       .= ' You can also choose it on the Stats page.';
			$messages[] = $msg;
		}

		// Group stale rows per team + season so each gets one archive link.
		$stale = array();
		foreach ( $issues['stale_live_seasons'] as $row ) {
			$k = $row['team_id'] . '|' . $row['season'];
			if ( ! isset( $stale[ $k ] ) ) {
				$stale[ $k ] = $row;
			} else {
				$stale[ $k ]['rows'] += $row['rows'];
			}
		}
		foreach ( $stale as $row ) {
			$messages[] = sprintf(
				'<strong>%s</strong> still has %d live stat rows from %s, which is not the %s season. <a href="%s" class="button button-small">Archive %s for %s</a>',
				esc_html( $row['team_name'] ),
				$row['rows'],
				esc_html( $row['season'] ),
				$current !== '' ? 'current (' . esc_html( $current ) . ')' : 'team\'s newest',
				esc_url( self::teams_url( array( 'pp_archive_season' => $row['season'], 'pp_archive_team' => $row['team_id'] ) ) ),
				esc_html( $row['season'] ),
				esc_html( $row['team_name'] )
			);
		}

		foreach ( $issues['duplicate_external_ids'] as $dup ) {
			$messages[] = sprintf(
				'%s team ID <strong>%s</strong> is used by more than one team: %s. League team IDs don\'t change between seasons, so each team needs its own. Check that team\'s schedule and roster sources.',
				esc_html( $dup['league'] ),
				esc_html( $dup['external_id'] ),
				esc_html( implode( ', ', $dup['teams'] ) )
			);
		}

		foreach ( $issues['off_season_sources'] as $src ) {
			$messages[] = sprintf(
				'<strong>%s</strong>: %s source "%s" is for %s, but the %s season is %s. Archive the old season or replace the source.',
				esc_html( $src['team_name'] ),
				esc_html( $src['kind'] ),
				esc_html( $src['source'] ),
				esc_html( $src['season'] ),
				$current !== '' ? 'current' : 'team\'s live',
				esc_html( $src['expected'] )
			);
		}

		foreach ( $issues['unknown_season_sources'] as $src ) {
			$messages[] = sprintf(
				'<strong>%s</strong>: %s source "%s" has no season, so its stats show in every season. Delete it and add it again with a Season Year.',
				esc_html( $src['team_name'] ),
				esc_html( $src['kind'] ),
				esc_html( $src['source'] )
			);
		}

		return $messages;
	}

	/**
	 * Warnings that involve one team, returned when one of its sources is saved.
	 *
	 * @return string[] plain text
	 */
	public static function get_team_warnings( int $team_id ): array {
		$issues = Puck_Press_Season::get_health_issues();

		$issues['no_current']             = false;
		$issues['stale_live_seasons']     = array_values( array_filter( $issues['stale_live_seasons'], fn( $r ) => $r['team_id'] === $team_id ) );
		$issues['off_season_sources']     = array_values( array_filter( $issues['off_season_sources'], fn( $r ) => $r['team_id'] === $team_id ) );
		$issues['unknown_season_sources'] = array_values( array_filter( $issues['unknown_season_sources'], fn( $r ) => $r['team_id'] === $team_id ) );
		$issues['duplicate_external_ids'] = array_values( array_filter( $issues['duplicate_external_ids'], fn( $r ) => isset( $r['teams'][ $team_id ] ) ) );

		return array_map(
			fn( $html ) => html_entity_decode( wp_strip_all_tags( preg_replace( '#<a\b[^>]*>.*?</a>#s', '', $html ) ), ENT_QUOTES ),
			self::get_messages( $issues )
		);
	}

	/**
	 * Full list for the top of the Teams page.
	 */
	public static function render_panel(): string {
		$messages = self::get_messages();
		if ( empty( $messages ) ) {
			return '';
		}

		$html  = '<div class="pp-card pp-season-health" style="margin-bottom:16px;border-left:4px solid #dba617;">';
		$html .= '<div class="pp-card-header"><div><h2 class="pp-card-title">Season health</h2>';
		$html .= '<p class="pp-card-subtitle">Live stats show only the current season. Fix these so every team\'s data matches it.</p></div></div>';
		$html .= '<div class="pp-card-content" style="padding:0 24px 16px;"><ul style="margin:0;padding-left:18px;">';
		foreach ( $messages as $msg ) {
			$html .= '<li style="margin:6px 0;">' . $msg . '</li>';
		}
		$html .= '</ul></div></div>';

		return $html;
	}

	/**
	 * Short notice on other Puck Press admin screens.
	 */
	public static function maybe_show_notice(): void {
		if ( ! current_user_can( 'manage_options' ) || ( $_GET['page'] ?? '' ) !== 'puck-press' ) {
			return;
		}
		if ( sanitize_key( $_GET['tab'] ?? 'teams' ) === 'teams' ) {
			return; // The Teams page shows the full panel.
		}

		$issues = Puck_Press_Season::get_health_issues();
		$count  = Puck_Press_Season::count_health_issues( $issues );
		if ( $count === 0 ) {
			return;
		}

		$extra = '';
		if ( $issues['no_current'] && $issues['suggested'] !== '' ) {
			$extra = sprintf(
				' <a href="%s" class="button button-small">Set current season to %s</a>',
				esc_url( self::set_current_url( $issues['suggested'] ) ),
				esc_html( $issues['suggested'] )
			);
		}

		printf(
			'<div class="notice notice-warning"><p><strong>Puck Press:</strong> %d season %s found. Live stats may not match the current season. <a href="%s">Review on the Teams page</a>.%s</p></div>',
			$count,
			$count === 1 ? 'problem' : 'problems',
			esc_url( self::teams_url() ),
			$extra
		);
	}
}
