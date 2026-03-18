<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Puck_Press_Team_Roster_Importer {

    private int $team_id;
    private Puck_Press_Teams_Wpdb_Utils $teams_wpdb;
    private Puck_Press_Roster_Registry_Wpdb_Utils $registry_wpdb;

    public function __construct( int $team_id ) {
        $this->team_id      = $team_id;
        $this->teams_wpdb   = new Puck_Press_Teams_Wpdb_Utils();
        $this->registry_wpdb = new Puck_Press_Roster_Registry_Wpdb_Utils();
    }

    public function import_all_sources(): array {
        global $wpdb;

        $this->teams_wpdb->maybe_create_or_update_tables();

        $results = array(
            'success_count' => 0,
            'error_count'   => 0,
            'errors'        => array(),
            'messages'      => array(),
        );

        $sources_table  = $wpdb->prefix . 'pp_team_roster_sources';
        $active_sources = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM $sources_table WHERE team_id = %d AND status = 'active'",
                $this->team_id
            ),
            ARRAY_A
        ) ?? array();

        $wpdb->delete( $wpdb->prefix . 'pp_team_players_raw', array( 'team_id' => $this->team_id ), array( '%d' ) );
        $wpdb->delete( $wpdb->prefix . 'pp_team_player_stats', array( 'team_id' => $this->team_id ), array( '%d' ) );
        $wpdb->delete( $wpdb->prefix . 'pp_team_player_goalie_stats', array( 'team_id' => $this->team_id ), array( '%d' ) );

        if ( empty( $active_sources ) ) {
            $results['messages'][] = 'No active sources — raw table cleared.';
            return $results;
        }

        foreach ( $active_sources as $source ) {
            try {
                if ( $source['type'] === 'achaRosterUrl' ) {
                    $raw_acha_data = new Puck_Press_Roster_Process_Acha_Url( $source['source_url_or_path'] );

                    if ( isset( $raw_acha_data->raw_roster_data['error'] ) ) {
                        ++$results['error_count'];
                        $results['errors'][]   = array(
                            'source'  => $source['name'],
                            'message' => 'ACHA fetch error: ' . $raw_acha_data->raw_roster_data['error'],
                        );
                        $results['messages'][] = "ACHA fetch error for source {$source['name']}: " . $raw_acha_data->raw_roster_data['error'];
                        continue;
                    }

                    if ( empty( $raw_acha_data->raw_roster_data ) ) {
                        ++$results['error_count'];
                        $results['errors'][]   = array(
                            'source'  => $source['name'],
                            'message' => 'ACHA returned empty roster — check the URL and season ID.',
                        );
                        $results['messages'][] = "ACHA returned empty roster for source: {$source['name']}";
                        continue;
                    }

                    foreach ( $raw_acha_data->raw_roster_data as &$row ) {
                        $row['api_team_id']   = $row['team_id']   ?? '';
                        $row['api_team_name'] = $row['team_name'] ?? '';
                        $row['source']        = $source['name'];
                        $row['team_id']       = $this->team_id;
                    }
                    unset( $row );

                    $inserted_count = $this->insert_raw_player_rows( $raw_acha_data->raw_roster_data );
                    ++$results['success_count'];
                    $results['messages'][] = "Imported source: {$source['name']} ({$inserted_count} players)";

                    $other_data    = ! empty( $source['other_data'] ) ? json_decode( $source['other_data'], true ) : array();
                    $include_stats = ! empty( $other_data['include_stats'] );
                    $stat_period   = ! empty( $other_data['stat_period'] ) ? $other_data['stat_period'] : $source['name'];
                    $stat_period   = ! empty( $source['season_year'] ) ? $stat_period . ' ' . $source['season_year'] : $stat_period;

                    if ( $include_stats ) {
                        $team_id_acha   = $raw_acha_data->team_id;
                        $season_id_acha = $raw_acha_data->season_id;

                        if ( ! empty( $team_id_acha ) && ! empty( $season_id_acha ) ) {
                            $acha_stats = new Puck_Press_Roster_Process_Acha_Stats( $team_id_acha, $season_id_acha, false );
                            if ( is_array( $acha_stats->raw_stats_data ) && ! isset( $acha_stats->raw_stats_data['error'] ) && ! empty( $acha_stats->raw_stats_data ) ) {
                                foreach ( $acha_stats->raw_stats_data as &$stat_row ) {
                                    $stat_row['source']  = $stat_period;
                                    $stat_row['team_id'] = $this->team_id;
                                }
                                unset( $stat_row );
                                $this->insert_player_stat_rows( $acha_stats->raw_stats_data );
                                $results['messages'][] = "Imported skater stats for source: {$source['name']}";
                            } else {
                                $results['messages'][] = "Skater stats import skipped for source: {$source['name']} — " . ( $acha_stats->raw_stats_data['error'] ?? 'unknown error or empty' );
                            }

                            $acha_goalie_stats = new Puck_Press_Roster_Process_Acha_Stats( $team_id_acha, $season_id_acha, true );
                            if ( is_array( $acha_goalie_stats->raw_goalie_stats_data ) && ! isset( $acha_goalie_stats->raw_goalie_stats_data['error'] ) && ! empty( $acha_goalie_stats->raw_goalie_stats_data ) ) {
                                foreach ( $acha_goalie_stats->raw_goalie_stats_data as &$stat_row ) {
                                    $stat_row['source']  = $stat_period;
                                    $stat_row['team_id'] = $this->team_id;
                                }
                                unset( $stat_row );
                                $this->insert_player_goalie_stat_rows( $acha_goalie_stats->raw_goalie_stats_data );
                                $results['messages'][] = "Imported goalie stats for source: {$source['name']}";
                            } else {
                                $results['messages'][] = "Goalie stats import skipped for source: {$source['name']} — " . ( $acha_goalie_stats->raw_goalie_stats_data['error'] ?? 'unknown error or empty' );
                            }
                        } else {
                            $results['messages'][] = "Stats skipped for source: {$source['name']} — could not extract team/season from URL.";
                        }
                    } elseif ( ! empty( $source['stats_url'] ) ) {
                        $acha_stats = Puck_Press_Roster_Process_Acha_Stats::from_url( $source['stats_url'] );
                        if ( is_array( $acha_stats->raw_stats_data ) && ! isset( $acha_stats->raw_stats_data['error'] ) && ! empty( $acha_stats->raw_stats_data ) ) {
                            foreach ( $acha_stats->raw_stats_data as &$stat_row ) {
                                $stat_row['source']  = $stat_period;
                                $stat_row['team_id'] = $this->team_id;
                            }
                            unset( $stat_row );
                            $this->insert_player_stat_rows( $acha_stats->raw_stats_data );
                            $results['messages'][] = "Imported skater stats for source: {$source['name']}";
                        } else {
                            $results['messages'][] = "Skater stats import skipped for source: {$source['name']} — " . ( $acha_stats->raw_stats_data['error'] ?? 'unknown error or empty' );
                        }

                        if ( ! empty( $source['goalie_stats_url'] ) ) {
                            $acha_goalie_stats = Puck_Press_Roster_Process_Acha_Stats::from_url( $source['goalie_stats_url'], true );
                            if ( is_array( $acha_goalie_stats->raw_goalie_stats_data ) && ! isset( $acha_goalie_stats->raw_goalie_stats_data['error'] ) && ! empty( $acha_goalie_stats->raw_goalie_stats_data ) ) {
                                foreach ( $acha_goalie_stats->raw_goalie_stats_data as &$stat_row ) {
                                    $stat_row['source']  = $stat_period;
                                    $stat_row['team_id'] = $this->team_id;
                                }
                                unset( $stat_row );
                                $this->insert_player_goalie_stat_rows( $acha_goalie_stats->raw_goalie_stats_data );
                                $results['messages'][] = "Imported goalie stats for source: {$source['name']}";
                            } else {
                                $results['messages'][] = "Goalie stats import skipped for source: {$source['name']} — " . ( $acha_goalie_stats->raw_goalie_stats_data['error'] ?? 'unknown error or empty' );
                            }
                        } else {
                            $results['messages'][] = "Goalie stats skipped for source: {$source['name']} — no Goalie Stats URL configured.";
                        }
                    } else {
                        $results['messages'][] = "Stats skipped for source: {$source['name']} — Include Stats not enabled.";
                    }
                } elseif ( $source['type'] === 'usphlRosterUrl' ) {
                    $usphl_other    = json_decode( $source['other_data'] ?? '{}', true );
                    $stat_period    = ! empty( $usphl_other['stat_period'] ) ? $usphl_other['stat_period'] : $source['name'];
                    $stat_period    = ! empty( $source['season_year'] ) ? $stat_period . ' ' . $source['season_year'] : $stat_period;
                    $raw_usphl_data = new Puck_Press_Roster_Process_Usphl_Url(
                        $source['source_url_or_path'],
                        $usphl_other['season_id'] ?? ''
                    );

                    if ( ! empty( $raw_usphl_data->fetch_errors ) ) {
                        foreach ( $raw_usphl_data->fetch_errors as $endpoint => $error ) {
                            $results['errors'][]   = array(
                                'source'  => $source['name'],
                                'message' => "USPHL /{$endpoint} fetch error: {$error}",
                            );
                            $results['messages'][] = "USPHL /{$endpoint} fetch error for {$source['name']}: {$error}";
                        }
                    }

                    if ( empty( $raw_usphl_data->raw_roster_data ) ) {
                        ++$results['error_count'];
                        $results['errors'][]   = array(
                            'source'  => $source['name'],
                            'message' => 'USPHL returned empty roster.',
                        );
                        $results['messages'][] = "USPHL returned empty roster for source: {$source['name']}";
                        continue;
                    }

                    foreach ( $raw_usphl_data->raw_roster_data as &$row ) {
                        $row['api_team_id'] = $source['source_url_or_path'];
                        $row['source']      = $source['name'];
                        $row['team_id']     = $this->team_id;
                    }
                    unset( $row );

                    $inserted_count = $this->insert_raw_player_rows( $raw_usphl_data->raw_roster_data );
                    ++$results['success_count'];
                    $results['messages'][] = "Imported source: {$source['name']} ({$inserted_count} players)";

                    if ( ! empty( $raw_usphl_data->raw_stats_data ) ) {
                        foreach ( $raw_usphl_data->raw_stats_data as &$stat_row ) {
                            $stat_row['source']  = $stat_period;
                            $stat_row['team_id'] = $this->team_id;
                        }
                        unset( $stat_row );
                        $this->insert_player_stat_rows( $raw_usphl_data->raw_stats_data );
                        $results['messages'][] = "Imported skater stats for source: {$source['name']} (" . count( $raw_usphl_data->raw_stats_data ) . ' players)';
                    } else {
                        $results['messages'][] = "No skater stats returned for source: {$source['name']}";
                    }

                    if ( ! empty( $raw_usphl_data->raw_goalie_stats_data ) ) {
                        foreach ( $raw_usphl_data->raw_goalie_stats_data as &$stat_row ) {
                            $stat_row['source']  = $stat_period;
                            $stat_row['team_id'] = $this->team_id;
                        }
                        unset( $stat_row );
                        $this->insert_player_goalie_stat_rows( $raw_usphl_data->raw_goalie_stats_data );
                        $results['messages'][] = "Imported goalie stats for source: {$source['name']} (" . count( $raw_usphl_data->raw_goalie_stats_data ) . ' goalies)';
                    } else {
                        $results['messages'][] = "No goalie stats returned for source: {$source['name']}";
                    }
                } elseif ( $source['type'] === 'csv' ) {
                    $csv_data    = $source['csv_data'] ?? null;
                    $process_csv = new Puck_Press_Roster_Process_Csv_Data( $csv_data, $source['name'] );
                    $players     = $process_csv->parse();
                    if ( empty( $players ) ) {
                        ++$results['error_count'];
                        $results['errors'][]   = array(
                            'source'  => $source['name'],
                            'message' => 'CSV parse returned empty roster.',
                        );
                        $results['messages'][] = "CSV parse returned empty roster for source: {$source['name']}";
                        continue;
                    }
                    foreach ( $players as &$row ) {
                        $row['source']  = $source['name'];
                        $row['team_id'] = $this->team_id;
                    }
                    unset( $row );
                    $inserted_count = $this->insert_raw_player_rows( $players );
                    ++$results['success_count'];
                    $results['messages'][] = "Imported source: {$source['name']} ({$inserted_count} players)";
                } else {
                    ++$results['error_count'];
                    $results['errors'][] = array(
                        'source'  => $source['name'] ?? 'Unknown',
                        'message' => 'Unsupported source type: ' . $source['type'],
                    );
                }
            } catch ( \Throwable $e ) {
                ++$results['error_count'];
                $results['errors'][] = array(
                    'source'  => $source['name'] ?? 'Unknown',
                    'message' => $e->getMessage(),
                    'file'    => $e->getFile(),
                    'line'    => $e->getLine(),
                    'trace'   => $e->getTraceAsString(),
                );
            }
        }

        return $results;
    }

    public function rebuild_display_from_mods(): array {
        global $wpdb;

        $raw_table     = $wpdb->prefix . 'pp_team_players_raw';
        $mods_table    = $wpdb->prefix . 'pp_team_player_mods';
        $display_table = $wpdb->prefix . 'pp_team_players_display';

        $originals = $wpdb->get_results(
            $wpdb->prepare( "SELECT * FROM $raw_table WHERE team_id = %d", $this->team_id ),
            ARRAY_A
        ) ?? array();

        $originals = $this->deduplicate_by_player_id( $originals );

        $edits_raw = $wpdb->get_results(
            $wpdb->prepare( "SELECT * FROM $mods_table WHERE team_id = %d ORDER BY id ASC", $this->team_id ),
            ARRAY_A
        ) ?? array();

        $edit_map   = array();
        $delete_ids = array();
        $results    = array();

        foreach ( $edits_raw as $edit ) {
            $player_id = $edit['external_id'];
            $action    = strtolower( $edit['edit_action'] ?? 'update' );

            if ( $action === 'delete' ) {
                $delete_ids[] = $player_id;
            } elseif ( $action === 'update' ) {
                $edit_data = json_decode( $edit['edit_data'], true );
                if ( json_last_error() === JSON_ERROR_NONE && is_array( $edit_data ) ) {
                    $edit_map[ $player_id ] = $edit_data;
                }
            }
        }

        // Remove orphaned rows — team_ids that no longer exist in pp_teams.
        $wpdb->query(
            "DELETE FROM $display_table WHERE team_id NOT IN (SELECT id FROM {$wpdb->prefix}pp_teams)"
        );

        $wpdb->delete( $display_table, array( 'team_id' => $this->team_id ), array( '%d' ) );

        foreach ( $originals as $row ) {
            $player_id = $row['player_id'];

            if ( in_array( $player_id, $delete_ids, true ) ) {
                $results[] = "Deleted player ID: $player_id";
                continue;
            }

            if ( isset( $edit_map[ $player_id ] ) ) {
                $row = array_merge( $row, $edit_map[ $player_id ] );
                unset( $row['external_id'] );
                $results[] = "Updated player ID: $player_id";
            }

            $row['team_id'] = $this->team_id;
            unset( $row['id'] );
            $wpdb->insert( $display_table, $row );
        }

        foreach ( $edits_raw as $edit ) {
            if ( strtolower( $edit['edit_action'] ?? '' ) === 'insert' ) {
                $edit_data = json_decode( $edit['edit_data'], true );
                if ( json_last_error() === JSON_ERROR_NONE && is_array( $edit_data ) ) {
                    $edit_data['source']  = $edit_data['source'] ?? 'Manual';
                    $edit_data['team_id'] = $this->team_id;
                    $manual_id            = $edit_data['player_id'] ?? null;
                    if ( $manual_id && isset( $edit_map[ $manual_id ] ) ) {
                        $edit_data = array_merge( $edit_data, $edit_map[ $manual_id ] );
                    }
                    unset( $edit_data['id'] );
                    $wpdb->insert( $display_table, $edit_data );
                    $results[] = 'Inserted manual player: ' . ( $edit_data['player_id'] ?? 'unknown' );
                }
            }
        }

        $this->normalize_display_table();

        return $results;
    }

    public function rebuild_team_and_cascade(): array {
        $import_results  = $this->import_all_sources();
        $display_results = array();

        $import_skipped = isset( $import_results['success'] ) && $import_results['success'] === false;
        if ( ! $import_skipped ) {
            $display_results = $this->rebuild_display_from_mods();
        }

        return array(
            'import'  => $import_results,
            'display' => $display_results,
        );
    }

    public function get_team_roster_sources(): array {
        global $wpdb;
        $table = $wpdb->prefix . 'pp_team_roster_sources';
        return $wpdb->get_results(
            $wpdb->prepare( "SELECT * FROM $table WHERE team_id = %d ORDER BY id ASC", $this->team_id ),
            ARRAY_A
        ) ?? array();
    }

    public function add_team_roster_source( array $data ): int {
        global $wpdb;
        $table   = $wpdb->prefix . 'pp_team_roster_sources';
        $allowed = array( 'name', 'type', 'source_url_or_path', 'status', 'csv_data', 'other_data', 'last_updated' );
        $row     = array_intersect_key( $data, array_flip( $allowed ) );
        $row['team_id']    = $this->team_id;
        $row['created_at'] = current_time( 'mysql' );
        $row['status']     = $row['status'] ?? 'active';
        $result = $wpdb->insert( $table, $row );
        return $result ? (int) $wpdb->insert_id : 0;
    }

    public function delete_team_roster_source( int $source_id ): bool {
        global $wpdb;
        return (bool) $wpdb->delete(
            $wpdb->prefix . 'pp_team_roster_sources',
            array(
                'id'      => $source_id,
                'team_id' => $this->team_id,
            ),
            array( '%d', '%d' )
        );
    }

    public function update_team_roster_source_status( int $source_id, string $status ): bool {
        global $wpdb;
        return (bool) $wpdb->update(
            $wpdb->prefix . 'pp_team_roster_sources',
            array( 'status' => $status ),
            array(
                'id'      => $source_id,
                'team_id' => $this->team_id,
            ),
            array( '%s' ),
            array( '%d', '%d' )
        );
    }

    private function deduplicate_by_player_id( array $rows ): array {
        $grouped = array();
        foreach ( $rows as $row ) {
            $grouped[ $row['player_id'] ][] = $row;
        }

        $deduplicated = array();
        foreach ( $grouped as $duplicates ) {
            $best       = null;
            $best_score = -1;
            foreach ( $duplicates as $candidate ) {
                $score = 0;
                foreach ( $candidate as $key => $val ) {
                    if ( $key === 'id' ) {
                        continue;
                    }
                    if ( $val !== null && $val !== '' ) {
                        ++$score;
                    }
                }
                if ( $score >= $best_score ) {
                    $best       = $candidate;
                    $best_score = $score;
                }
            }
            $deduplicated[] = $best;
        }

        return $deduplicated;
    }

    private function normalize_display_table(): void {
        global $wpdb;

        $table = $wpdb->prefix . 'pp_team_players_display';

        $rows = $wpdb->get_results(
            $wpdb->prepare( "SELECT id, pos, shoots, ht, wt FROM $table WHERE team_id = %d", $this->team_id ),
            ARRAY_A
        );

        foreach ( $rows as $row ) {
            $id = (int) $row['id'];

            $normalized = array(
                'pos'    => Puck_Press_Roster_Normalizer::normalize_position( (string) ( $row['pos'] ?? '' ) ),
                'shoots' => Puck_Press_Roster_Normalizer::normalize_shoots( (string) ( $row['shoots'] ?? '' ) ),
                'ht'     => Puck_Press_Roster_Normalizer::normalize_height( (string) ( $row['ht'] ?? '' ) ),
                'wt'     => Puck_Press_Roster_Normalizer::normalize_weight( (string) ( $row['wt'] ?? '' ) ),
            );

            $updates     = array();
            $formats     = array();
            $null_fields = array();
            foreach ( $normalized as $field => $value ) {
                $current = (string) ( $row[ $field ] ?? '' );
                if ( $value === null && $current !== '' ) {
                    $null_fields[] = esc_sql( $field );
                } elseif ( $value !== null && $value !== $current ) {
                    $updates[ $field ] = $value;
                    $formats[]         = '%s';
                }
            }

            if ( ! empty( $updates ) ) {
                $wpdb->update( $table, $updates, array( 'id' => $id ), $formats, array( '%d' ) );
            }

            if ( ! empty( $null_fields ) ) {
                $set = implode( ', ', array_map( fn( $f ) => "`$f` = NULL", $null_fields ) );
                $wpdb->query( $wpdb->prepare( "UPDATE `$table` SET $set WHERE id = %d", $id ) );
            }
        }
    }

    private function insert_raw_player_rows( array $rows ): int {
        global $wpdb;
        $table          = $wpdb->prefix . 'pp_team_players_raw';
        $allowed_fields = array(
            'team_id', 'api_team_id', 'api_team_name', 'source', 'player_id', 'headshot_link', 'number',
            'name', 'pos', 'ht', 'wt', 'shoots', 'hometown',
            'last_team', 'year_in_school', 'major',
        );
        $count = 0;
        foreach ( $rows as $row ) {
            if ( ! is_array( $row ) ) {
                continue;
            }
            $filtered            = array_intersect_key( $row, array_flip( $allowed_fields ) );
            $filtered['team_id'] = $this->team_id;
            if ( $wpdb->insert( $table, $filtered ) ) {
                ++$count;
            }
        }
        return $count;
    }

    private function insert_player_stat_rows( array $rows ): void {
        global $wpdb;
        $table          = $wpdb->prefix . 'pp_team_player_stats';
        $allowed_fields = array(
            'team_id', 'player_id', 'source', 'games_played', 'goals', 'assists',
            'points', 'points_per_game', 'power_play_goals', 'short_handed_goals',
            'game_winning_goals', 'shootout_winning_goals', 'penalty_minutes',
            'shooting_percentage', 'stat_rank',
        );
        foreach ( $rows as $row ) {
            $filtered = array_intersect_key( $row, array_flip( $allowed_fields ) );
            $filtered['team_id'] = $this->team_id;
            $wpdb->insert( $table, $filtered );
        }
    }

    private function insert_player_goalie_stat_rows( array $rows ): void {
        global $wpdb;
        $table          = $wpdb->prefix . 'pp_team_player_goalie_stats';
        $allowed_fields = array(
            'team_id', 'player_id', 'source', 'games_played', 'wins', 'losses',
            'overtime_losses', 'shootout_losses', 'shootout_wins', 'shots_against',
            'saves', 'save_percentage', 'goals_against_average', 'goals_against',
            'goals', 'assists', 'penalty_minutes', 'stat_rank',
        );
        foreach ( $rows as $row ) {
            $filtered = array_intersect_key( $row, array_flip( $allowed_fields ) );
            $filtered['team_id'] = $this->team_id;
            $wpdb->insert( $table, $filtered );
        }
    }
}
