<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Puck_Press_Standings_Wpdb_Utils {

    public function get_standings_for_team( int $team_id ): ?array {
        global $wpdb;
        $table = $wpdb->prefix . 'pp_team_standings_cache';
        $row   = $wpdb->get_row(
            $wpdb->prepare( "SELECT * FROM $table WHERE team_id = %d LIMIT 1", $team_id ),
            ARRAY_A
        );
        if ( ! $row ) {
            return null;
        }
        $row['standings_data'] = json_decode( $row['standings_data'], true );
        return $row;
    }

    public function upsert_standings( int $team_id, int $source_id, string $league_type, string $division_name, array $standings ): bool {
        global $wpdb;
        $table = $wpdb->prefix . 'pp_team_standings_cache';

        $existing = $wpdb->get_var(
            $wpdb->prepare( "SELECT id FROM $table WHERE team_id = %d AND source_id = %d", $team_id, $source_id )
        );

        $data = array(
            'team_id'        => $team_id,
            'source_id'      => $source_id,
            'league_type'    => $league_type,
            'division_name'  => $division_name,
            'standings_data' => wp_json_encode( $standings ),
            'computed_at'    => current_time( 'mysql' ),
        );

        if ( $existing ) {
            return (bool) $wpdb->update( $table, $data, array( 'id' => (int) $existing ), null, array( '%d' ) );
        }

        return (bool) $wpdb->insert( $table, $data );
    }

    public function delete_standings_for_team( int $team_id ): bool {
        global $wpdb;
        return (bool) $wpdb->delete(
            $wpdb->prefix . 'pp_team_standings_cache',
            array( 'team_id' => $team_id ),
            array( '%d' )
        );
    }
}
