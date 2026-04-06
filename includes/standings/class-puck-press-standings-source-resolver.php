<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Puck_Press_Standings_Source_Resolver {

    public static function get_regular_season_source( int $wp_team_id ): ?array {
        global $wpdb;
        $table   = $wpdb->prefix . 'pp_team_sources';
        $sources = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM $table WHERE team_id = %d AND status = 'active' AND type IN ('achaGameScheduleUrl', 'usphlGameScheduleUrl') ORDER BY id DESC",
                $wp_team_id
            ),
            ARRAY_A
        );

        if ( empty( $sources ) ) {
            return null;
        }

        $acha_sources  = array();
        $usphl_sources = array();

        foreach ( $sources as $source ) {
            if ( $source['type'] === 'achaGameScheduleUrl' ) {
                $acha_sources[] = $source;
            } elseif ( $source['type'] === 'usphlGameScheduleUrl' ) {
                $usphl_sources[] = $source;
            }
        }

        if ( ! empty( $acha_sources ) ) {
            return self::resolve_acha_source( $acha_sources );
        }

        if ( ! empty( $usphl_sources ) ) {
            return $usphl_sources[0];
        }

        return null;
    }

    private static function resolve_acha_source( array $sources ): ?array {
        require_once plugin_dir_path( __DIR__ ) . 'schedule/class-puck-press-acha-season-discoverer.php';
        $bootstrap = Puck_Press_Acha_Season_Discoverer::get_bootstrap();
        if ( empty( $bootstrap ) ) {
            return $sources[0];
        }

        $regular_ids = array_flip( array_column( $bootstrap['regularSeasons'] ?? array(), 'id' ) );

        $season_start_dates = array();
        foreach ( $bootstrap['seasons'] ?? array() as $s ) {
            $season_start_dates[ (string) $s['id'] ] = $s['start_date'] ?? '1970-01-01';
        }

        $candidates = array();
        foreach ( $sources as $source ) {
            $other     = json_decode( $source['other_data'] ?? '{}', true );
            $season_id = (string) ( $other['season_id'] ?? '' );
            if ( isset( $regular_ids[ $season_id ] ) ) {
                $source['_start_date'] = $season_start_dates[ $season_id ] ?? '1970-01-01';
                $candidates[]          = $source;
            }
        }

        if ( empty( $candidates ) ) {
            return null;
        }

        usort( $candidates, fn( $a, $b ) => strcmp( $b['_start_date'], $a['_start_date'] ) );
        $best = $candidates[0];
        unset( $best['_start_date'] );
        return $best;
    }
}
