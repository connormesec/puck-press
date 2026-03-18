<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

require_once plugin_dir_path( __DIR__ ) . 'class-puck-press-wpdb-utils-base-abstract.php';

class Puck_Press_Roster_Registry_Wpdb_Utils extends Puck_Press_Wpdb_Utils_Base {

    protected $table_schemas = array(
        'pp_roster_teams' => '
            roster_id INT NOT NULL,
            team_id INT NOT NULL,
            UNIQUE KEY unique_roster_team (roster_id, team_id)
        ',
    );

    public function maybe_create_or_update_tables(): void {
        foreach ( array_keys( $this->table_schemas ) as $table ) {
            $this->maybe_create_or_update_table( $table );
        }
    }

    public function get_all_rosters(): array {
        global $wpdb;
        $table = $wpdb->prefix . 'pp_rosters';
        return $wpdb->get_results( "SELECT * FROM $table ORDER BY id ASC", ARRAY_A ) ?? array();
    }

    public function get_roster_by_id( int $id ): ?array {
        global $wpdb;
        $table = $wpdb->prefix . 'pp_rosters';
        return $wpdb->get_row(
            $wpdb->prepare( "SELECT * FROM $table WHERE id = %d", $id ),
            ARRAY_A
        );
    }

    public function get_roster_by_slug( string $slug ): ?array {
        global $wpdb;
        $table = $wpdb->prefix . 'pp_rosters';
        return $wpdb->get_row(
            $wpdb->prepare( "SELECT * FROM $table WHERE slug = %s", $slug ),
            ARRAY_A
        );
    }

    public function get_main_roster(): ?array {
        global $wpdb;
        $table = $wpdb->prefix . 'pp_rosters';
        return $wpdb->get_row(
            "SELECT * FROM $table WHERE is_main = 1 LIMIT 1",
            ARRAY_A
        );
    }

    public function get_main_roster_id(): ?int {
        $roster = $this->get_main_roster();
        return $roster ? (int) $roster['id'] : null;
    }

    public function create_roster( string $name, string $slug ): int|string {
        global $wpdb;
        $table = $wpdb->prefix . 'pp_rosters';
        $slug  = sanitize_title( $slug );

        $exists = $wpdb->get_var( $wpdb->prepare( "SELECT id FROM $table WHERE slug = %s", $slug ) );
        if ( $exists ) {
            return 'duplicate_slug';
        }

        $wpdb->suppress_errors( true );
        $result = $wpdb->insert(
            $table,
            array(
                'name'       => sanitize_text_field( $name ),
                'slug'       => $slug,
                'is_main'    => 0,
                'created_at' => current_time( 'mysql' ),
            )
        );
        $wpdb->suppress_errors( false );
        return $result ? (int) $wpdb->insert_id : 0;
    }

    public function delete_roster( int $id ): bool {
        global $wpdb;

        $roster = $this->get_roster_by_id( $id );
        if ( ! $roster ) {
            return false;
        }
        if ( (int) $roster['is_main'] === 1 ) {
            return false;
        }

        $wpdb->delete( $wpdb->prefix . 'pp_roster_teams', array( 'roster_id' => $id ), array( '%d' ) );

        return (bool) $wpdb->delete( $wpdb->prefix . 'pp_rosters', array( 'id' => $id ), array( '%d' ) );
    }

    public function seed_main_roster(): void {
        global $wpdb;
        $table  = $wpdb->prefix . 'pp_rosters';
        $exists = $wpdb->get_var( "SELECT id FROM $table WHERE is_main = 1 LIMIT 1" );
        if ( ! $exists ) {
            $wpdb->insert(
                $table,
                array(
                    'slug'       => 'default',
                    'name'       => 'Default Roster',
                    'is_main'    => 1,
                    'created_at' => current_time( 'mysql' ),
                )
            );
        }
    }

    public function get_roster_team_ids( int $roster_id ): array {
        global $wpdb;

        $roster = $this->get_roster_by_id( $roster_id );
        if ( $roster && (int) $roster['is_main'] === 1 ) {
            $teams_table = $wpdb->prefix . 'pp_teams';
            $ids         = array_map( 'intval', $wpdb->get_col( "SELECT id FROM $teams_table" ) ?? array() );
            error_log( "[PP Roster] get_roster_team_ids: roster_id=$roster_id is_main=true → team_ids from pp_teams: [" . implode( ', ', $ids ) . ']' );
            return $ids;
        }

        $table = $wpdb->prefix . 'pp_roster_teams';
        $ids   = array_map(
            'intval',
            $wpdb->get_col(
                $wpdb->prepare( "SELECT team_id FROM $table WHERE roster_id = %d", $roster_id )
            ) ?? array()
        );
        error_log( "[PP Roster] get_roster_team_ids: roster_id=$roster_id is_main=false → team_ids from pp_roster_teams: [" . implode( ', ', $ids ) . ']' );
        return $ids;
    }

    public function get_roster_teams( int $roster_id ): array {
        global $wpdb;
        $rt     = $wpdb->prefix . 'pp_roster_teams';
        $t      = $wpdb->prefix . 'pp_teams';
        return $wpdb->get_results(
            $wpdb->prepare(
                "SELECT $t.* FROM $t INNER JOIN $rt ON $t.id = $rt.team_id WHERE $rt.roster_id = %d",
                $roster_id
            ),
            ARRAY_A
        ) ?? array();
    }

    public function add_team_to_roster( int $roster_id, int $team_id ): bool {
        global $wpdb;

        $roster = $this->get_roster_by_id( $roster_id );
        if ( $roster && (int) $roster['is_main'] === 1 ) {
            return false;
        }

        $result = $wpdb->query(
            $wpdb->prepare(
                "INSERT IGNORE INTO {$wpdb->prefix}pp_roster_teams (roster_id, team_id) VALUES (%d, %d)",
                $roster_id,
                $team_id
            )
        );
        return $result !== false;
    }

    public function remove_team_from_roster( int $roster_id, int $team_id ): bool {
        global $wpdb;

        $roster = $this->get_roster_by_id( $roster_id );
        if ( $roster && (int) $roster['is_main'] === 1 ) {
            return false;
        }

        return (bool) $wpdb->delete(
            $wpdb->prefix . 'pp_roster_teams',
            array(
                'roster_id' => $roster_id,
                'team_id'   => $team_id,
            ),
            array( '%d', '%d' )
        );
    }

    public function get_roster_ids_for_team( int $team_id ): array {
        global $wpdb;

        $rt         = $wpdb->prefix . 'pp_roster_teams';
        $rosters_t  = $wpdb->prefix . 'pp_rosters';

        $main_id = (int) ( $wpdb->get_var( "SELECT id FROM $rosters_t WHERE is_main = 1 LIMIT 1" ) ?? 0 );

        $ids = array_map(
            'intval',
            $wpdb->get_col(
                $wpdb->prepare( "SELECT roster_id FROM $rt WHERE team_id = %d", $team_id )
            ) ?? array()
        );

        if ( $main_id > 0 && ! in_array( $main_id, $ids, true ) ) {
            $ids[] = $main_id;
        }

        return $ids;
    }

    public function get_available_teams_for_roster( int $roster_id ): array {
        global $wpdb;

        $existing_ids = $this->get_roster_team_ids( $roster_id );
        $teams_table  = $wpdb->prefix . 'pp_teams';

        if ( empty( $existing_ids ) ) {
            return $wpdb->get_results( "SELECT * FROM $teams_table ORDER BY id ASC", ARRAY_A ) ?? array();
        }

        $placeholders = implode( ', ', array_fill( 0, count( $existing_ids ), '%d' ) );
        return $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM $teams_table WHERE id NOT IN ($placeholders) ORDER BY id ASC",
                $existing_ids
            ),
            ARRAY_A
        ) ?? array();
    }

    public function get_roster_players_display( int $roster_id ): array {
        global $wpdb;
        $team_ids = $this->get_roster_team_ids( $roster_id );
        if ( empty( $team_ids ) ) {
            return array();
        }
        $placeholders = implode( ', ', array_fill( 0, count( $team_ids ), '%d' ) );
        return $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM {$wpdb->prefix}pp_team_players_display WHERE team_id IN ($placeholders)",
                $team_ids
            ),
            ARRAY_A
        ) ?? array();
    }

}
