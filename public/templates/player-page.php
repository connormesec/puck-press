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

global $wpdb;

$player_slug = sanitize_text_field( get_query_var( 'pp_player' ) );

// ── Look up player ────────────────────────────────────────────────────────────
$all_players = $wpdb->get_results(
    "SELECT * FROM {$wpdb->prefix}pp_roster_for_display",
    ARRAY_A
);

$player = null;
foreach ( $all_players as $row ) {
    if ( sanitize_title( $row['name'] ) === $player_slug ) {
        $player = $row;
        break;
    }
}

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
$is_goalie   = ( strtoupper( $player['pos'] ?? '' ) === 'G' );
$stats_table = $is_goalie
    ? "{$wpdb->prefix}pp_roster_goalie_stats"
    : "{$wpdb->prefix}pp_roster_stats";

$stats = $wpdb->get_row(
    $wpdb->prepare(
        "SELECT * FROM {$stats_table} WHERE player_id = %s LIMIT 1",
        $player['player_id']
    ),
    ARRAY_A
) ?? [];

// ── Render ────────────────────────────────────────────────────────────────────
get_header();

// JSON-LD Person schema (reuses the static helper from the render utils class).
echo Puck_Press_Roster_Render_Utils::build_player_schema( $player );

// Player detail HTML (CSS vars already injected by enqueue_roster_assets() via
// wp_add_inline_style before wp_head fired).
echo Puck_Press_Roster_Player_Detail::render( $player, $stats );

get_footer();
