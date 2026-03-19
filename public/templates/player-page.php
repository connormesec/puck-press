<?php
/**
 * Full-page player detail template.
 *
 * Loaded by Puck_Press_Public::maybe_load_player_template() via the
 * template_include filter when the pp_player query var is set.
 *
 * URL pattern: /player/{slug}
 */

// Load roster dependencies.
require_once PLUGIN_DIR_PATH . 'includes/roster/class-puck-press-roster-render-utils.php';
require_once PLUGIN_DIR_PATH . 'includes/roster/class-puck-press-roster-player-detail.php';

$player_slug = sanitize_text_field( get_query_var( 'pp_player' ) );

// ── Look up player ────────────────────────────────────────────────────────────
$player = Puck_Press_Roster_Player_Detail::find_by_slug( $player_slug );

if ( ! $player ) {
	status_header( 404 );
	get_header();
	echo '<div style="padding:4rem 2rem;text-align:center;">'
		. '<h1>Player not found</h1>'
		. '<p><a href="' . esc_url( home_url() ) . '">&larr; Back to site</a></p>'
		. '</div>';
	get_footer();
	return;
}

// ── Look up stats ─────────────────────────────────────────────────────────────
global $wpdb;
$is_goalie   = ( strtoupper( $player['pos'] ?? '' ) === 'G' );
$team_id     = (int) ( $player['team_id'] ?? 0 );
$stats_table = $is_goalie
	? "{$wpdb->prefix}pp_team_player_goalie_stats"
	: "{$wpdb->prefix}pp_team_player_stats";

$current_stats_rows = $wpdb->get_results(
	$wpdb->prepare(
		"SELECT * FROM {$stats_table} WHERE player_id = %s AND team_id = %d ORDER BY source ASC",
		$player['player_id'],
		$team_id
	),
	ARRAY_A
) ?? array();

// Combine rows that share the same source (e.g. two ACHA Nationals rosters).
$aggregated_rows = array();
foreach ( $current_stats_rows as $row ) {
	$key = $row['source'] ?? '';
	if ( ! isset( $aggregated_rows[ $key ] ) ) {
		$aggregated_rows[ $key ] = $row;
	} else {
		$sum_fields = $is_goalie
			? array( 'games_played', 'wins', 'losses', 'overtime_losses', 'shootout_losses', 'shootout_wins', 'shots_against', 'saves', 'goals_against' )
			: array( 'games_played', 'goals', 'assists', 'points', 'penalty_minutes', 'power_play_goals', 'short_handed_goals', 'game_winning_goals', 'shootout_winning_goals' );
		foreach ( $sum_fields as $field ) {
			$aggregated_rows[ $key ][ $field ] = ( (int) ( $aggregated_rows[ $key ][ $field ] ?? 0 ) ) + ( (int) ( $row[ $field ] ?? 0 ) );
		}
		$gp = (int) $aggregated_rows[ $key ]['games_played'];
		if ( $is_goalie ) {
			$sa = (int) $aggregated_rows[ $key ]['shots_against'];
			$sv = (int) $aggregated_rows[ $key ]['saves'];
			$ga = (int) $aggregated_rows[ $key ]['goals_against'];
			$aggregated_rows[ $key ]['save_percentage']      = $sa > 0 ? round( $sv / $sa * 100, 2 ) : 0;
			$aggregated_rows[ $key ]['goals_against_average'] = $gp > 0 ? round( $ga / $gp, 2 ) : 0;
		} else {
			$pts = (int) $aggregated_rows[ $key ]['points'];
			$aggregated_rows[ $key ]['points_per_game'] = $gp > 0 ? round( $pts / $gp, 2 ) : 0;
		}
	}
}
$current_stats_rows = array_values( $aggregated_rows );

// ── Look up archived stats (previous seasons) ─────────────────────────────────
require_once PLUGIN_DIR_PATH . 'includes/archive/class-puck-press-archive-manager.php';
$archive_manager = new Puck_Press_Archive_Manager();
$archived_stats  = $is_goalie
	? $archive_manager->get_player_goalie_archives( $player['player_id'] )
	: $archive_manager->get_player_skater_archives( $player['player_id'] );

$all_stats_rows = array();
foreach ( $current_stats_rows as $row ) {
	$row['season']  = ! empty( $row['source'] ) ? $row['source'] : 'Current';
	$all_stats_rows[] = $row;
}
foreach ( $archived_stats as $archived_row ) {
	$archived_row['season'] = trim( ( $archived_row['season_key'] ?? 'Archived' ) . ' ' . ( $archived_row['source'] ?? '' ) );
	$all_stats_rows[]       = $archived_row;
}

// ── Render ────────────────────────────────────────────────────────────────────
get_header();

// JSON-LD Person schema (reuses the static helper from the render utils class).
echo Puck_Press_Roster_Render_Utils::build_player_schema( $player );

// Player detail HTML (CSS vars already injected by enqueue_roster_assets() via
// wp_add_inline_style before wp_head fired).
echo Puck_Press_Roster_Player_Detail::render( $player, $all_stats_rows );

get_footer();
