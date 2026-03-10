<?php

/**
 * The public-facing functionality of the plugin.
 *
 * @link       https://https://github.com/connormesec/
 * @since      1.0.0
 *
 * @package    Puck_Press
 * @subpackage Puck_Press/public
 */

/**
 * This takes the active sources, get's their data and puts it into the raw game roster for further processing
 *
 * 
 *
 * @package    Puck_Press
 * @subpackage Puck_Press/public/partials/roster
 * @author     Connor Mesec <connormesec@gmail.com>
 */
class Puck_Press_Roster_Source_Importer
{

    private $roster_db_utils;
    private $results = [];

    public function __construct()
    {
        $this->load_dependencies();
        $this->roster_db_utils = new Puck_Press_Roster_Wpdb_Utils();
    }

    private function load_dependencies()
    {
        require_once plugin_dir_path(dirname(__FILE__)) . 'roster/class-puck-press-roster-wpdb-utils.php';
        require_once plugin_dir_path(dirname(__FILE__)) . 'class-puck-press-tts-api.php';
        require_once plugin_dir_path(dirname(__FILE__)) . 'roster/class-puck-press-roster-normalizer.php';
        require_once plugin_dir_path(dirname(__FILE__)) . 'roster/class-puck-press-roster-process-acha-url.php';
        require_once plugin_dir_path(dirname(__FILE__)) . 'roster/class-puck-press-roster-process-acha-stats.php';
        require_once plugin_dir_path(dirname(__FILE__)) . 'roster/class-puck-press-roster-process-csv-data.php';
        require_once plugin_dir_path(dirname(__FILE__)) . 'roster/class-puck-press-roster-process-usphl-url.php';
    }

    public function populate_raw_roster_table_from_sources()
    {
        $this->results = [
            'success_count' => 0,
            'error_count'   => 0,
            'errors'        => [],
            'messages'      => [],
        ];

        $active_sources = $this->roster_db_utils->get_active_roster_sources();

        if (empty($active_sources)) {
            $this->results['messages'][] = 'No active sources to import.';
            return $this->results;
        }
        
        foreach ($active_sources as $source) {
            try {
                if ($source->type === 'achaRosterUrl') {

                    $raw_acha_data = new Puck_Press_Roster_Process_Acha_Url($source->source_url_or_path);
                    //append source name to each row
                    foreach ($raw_acha_data->raw_roster_data as &$row) {
                        $row['source'] = $source->name;
                    }

                    $inserted = $this->roster_db_utils->insert_multiple_roster_rows($raw_acha_data->raw_roster_data);

                    $this->results['success_count']++;
                    $this->results['messages'][] = "Imported source: {$source->name}";
                    $this->results['messages'][] = $inserted;

                    // Import skater stats if a stats URL is configured
                    if ( ! empty( $source->stats_url ) ) {
                        $acha_stats = new Puck_Press_Roster_Process_Acha_Stats( $source->stats_url );
                        if ( is_array( $acha_stats->raw_stats_data ) && ! isset( $acha_stats->raw_stats_data['error'] ) && ! empty( $acha_stats->raw_stats_data ) ) {
                            foreach ( $acha_stats->raw_stats_data as &$stat_row ) {
                                $stat_row['source'] = $source->name;
                            }
                            unset( $stat_row );
                            $stats_inserted = $this->roster_db_utils->insert_stats_rows( $acha_stats->raw_stats_data );
                            $this->results['messages'][] = "Imported skater stats for source: {$source->name}";
                            $this->results['messages'][] = $stats_inserted;
                        } else {
                            $this->results['messages'][] = "Skater stats import skipped for source: {$source->name} — " . ( $acha_stats->raw_stats_data['error'] ?? 'unknown error or empty' );
                        }
                    }

                    // Import goalie stats if a goalie stats URL is configured
                    if ( empty( $source->goalie_stats_url ) ) {
                        $this->results['messages'][] = "Goalie stats skipped for source: {$source->name} — no Goalie Stats URL configured.";
                    } else {
                        $acha_goalie_stats = new Puck_Press_Roster_Process_Acha_Stats( $source->goalie_stats_url, true );
                        if ( is_array( $acha_goalie_stats->raw_goalie_stats_data ) && ! isset( $acha_goalie_stats->raw_goalie_stats_data['error'] ) && ! empty( $acha_goalie_stats->raw_goalie_stats_data ) ) {
                            foreach ( $acha_goalie_stats->raw_goalie_stats_data as &$stat_row ) {
                                $stat_row['source'] = $source->name;
                            }
                            unset( $stat_row );
                            $goalie_stats_inserted = $this->roster_db_utils->insert_goalie_stats_rows( $acha_goalie_stats->raw_goalie_stats_data );
                            $this->results['messages'][] = "Imported goalie stats for source: {$source->name}";
                            $this->results['messages'][] = $goalie_stats_inserted;
                        } else {
                            $this->results['messages'][] = "Goalie stats import skipped for source: {$source->name} — " . ( $acha_goalie_stats->raw_goalie_stats_data['error'] ?? 'unknown error or empty' );
                        }
                    }
                } elseif ($source->type === 'usphlRosterUrl') {
                    $usphl_other    = json_decode( $source->other_data ?? '{}', true );
                    $raw_usphl_data = new Puck_Press_Roster_Process_Usphl_Url(
                        $source->source_url_or_path,
                        $usphl_other['season_id'] ?? ''
                    );

                    // Log any fetch errors so they surface in the refresh response.
                    if ( ! empty( $raw_usphl_data->fetch_errors ) ) {
                        foreach ( $raw_usphl_data->fetch_errors as $endpoint => $error ) {
                            $this->results['errors'][]   = [
                                'source'  => $source->name,
                                'message' => "USPHL /{$endpoint} fetch error: {$error}",
                            ];
                            $this->results['messages'][] = "USPHL /{$endpoint} fetch error for {$source->name}: {$error}";
                        }
                    }

                    //append source name to each row
                    foreach ($raw_usphl_data->raw_roster_data as &$row) {
                        $row['source'] = $source->name;
                    }
                    unset( $row );

                    $inserted = $this->roster_db_utils->insert_multiple_roster_rows($raw_usphl_data->raw_roster_data);

                    $this->results['success_count']++;
                    $this->results['messages'][] = "Imported source: {$source->name}";
                    $this->results['messages'][] = $inserted;

                    // Skater stats from /get_skaters (or inline fallback from /get_roster).
                    if ( ! empty( $raw_usphl_data->raw_stats_data ) ) {
                        foreach ( $raw_usphl_data->raw_stats_data as &$stat_row ) {
                            $stat_row['source'] = $source->name;
                        }
                        unset( $stat_row );
                        $stats_inserted = $this->roster_db_utils->insert_stats_rows( $raw_usphl_data->raw_stats_data );
                        $this->results['messages'][] = "Imported skater stats for source: {$source->name} (" . count( $raw_usphl_data->raw_stats_data ) . " players)";
                        $this->results['messages'][] = $stats_inserted;
                    } else {
                        $this->results['messages'][] = "No skater stats returned for source: {$source->name}";
                    }

                    // Goalie stats from /get_goalies endpoint.
                    if ( ! empty( $raw_usphl_data->raw_goalie_stats_data ) ) {
                        foreach ( $raw_usphl_data->raw_goalie_stats_data as &$stat_row ) {
                            $stat_row['source'] = $source->name;
                        }
                        unset( $stat_row );
                        $goalie_stats_inserted = $this->roster_db_utils->insert_goalie_stats_rows( $raw_usphl_data->raw_goalie_stats_data );
                        $this->results['messages'][] = "Imported goalie stats for source: {$source->name} (" . count( $raw_usphl_data->raw_goalie_stats_data ) . " goalies)";
                        $this->results['messages'][] = $goalie_stats_inserted;
                    } else {
                        $this->results['messages'][] = "No goalie stats returned for source: {$source->name}";
                    }
                } elseif ($source->type === 'csv') {
                    $csv_data = $source->csv_data ?? null;
                    $process_csv = new Puck_Press_Roster_Process_Csv_Data($csv_data, $source->name);
                    $players = $process_csv->parse();
                    $inserted = $this->roster_db_utils->insert_multiple_roster_rows($players);
                    $this->results['success_count']++;
                    $this->results['messages'][] = "Imported source: {$source->name}";
                    $this->results['messages'][] = $inserted;
                } else {
                    $this->results['error_count']++;
                    $this->results['errors'][] = [
                        'source' => $source->name ?? 'Unknown',
                        'message' => 'Unsupported source type: ' . $source->type
                    ];
                }
            } catch (\Throwable $e) {
                $this->results['error_count']++;
                $this->results['errors'][] = [
                    'source'  => $source->name ?? 'Unknown',
                    'message' => $e->getMessage(),
                    'file'    => $e->getFile(),
                    'line'    => $e->getLine(),
                    'trace'   => $e->getTraceAsString()
                ];
            }
        }
        return $this->results;
    }

    function apply_edits_and_save_to_display_table()
    {

        $table_a = 'pp_roster_raw';
        $table_b = 'pp_roster_mods'; // Edits
        $table_c = 'pp_roster_for_display'; // Result

        // Clear display table for a clean rebuild
        $this->roster_db_utils->truncate_table($table_c);

        // Fetch all base data
        $originals = $this->roster_db_utils->get_all_table_data($table_a, 'ARRAY_A') ?? [];
        $originals = $this->deduplicate_by_player_id($originals);

        // Fetch all edits
        $edits = $this->roster_db_utils->get_all_table_data($table_b, 'ARRAY_A');
        $edit_map = [];
        $delete_ids = [];
        $results = [];

        foreach ($edits as $edit) {
            $player_id = $edit['external_id'];
            $action = strtolower($edit['edit_action'] ?? 'update');

            if ($action === 'delete') {
                $delete_ids[] = $player_id;
            } elseif ($action === 'update') {
                $edit_data = json_decode($edit['edit_data'], true);
                if (json_last_error() === JSON_ERROR_NONE && is_array($edit_data)) {
                    $edit_map[$player_id] = $edit_data;
                }
            }
        }
        // Merge and insert into table_c
        foreach ($originals as $row) {
            $player_id = $row['player_id'];

            /// Skip this row if it's marked for deletion
            if (in_array($player_id, $delete_ids, true)) {
                // Optionally delete it from table_c if already exists
                $this->roster_db_utils->delete_row_by_player_id($table_c, $player_id);
                $results[] = "Deleted player ID: $player_id";
                continue;
            }

            // Apply edits if available
            if (isset($edit_map[$player_id])) {
                $row = array_merge($row, $edit_map[$player_id]);
                $results[] = "Updated player ID: $player_id";
            }

            // Insert or update into table_c
            $this->roster_db_utils->insert_or_replace_row($table_c, $row);
        }

        // Handle manual (insert) players — they don't exist in raw data
        foreach ($edits as $edit) {
            if (strtolower($edit['edit_action'] ?? '') === 'insert') {
                $edit_data = json_decode($edit['edit_data'], true);
                if (json_last_error() === JSON_ERROR_NONE && is_array($edit_data)) {
                    $edit_data['source'] = $edit_data['source'] ?? 'Manual';
                    // Apply any subsequent edits saved for this manual player
                    $manual_id = $edit_data['player_id'] ?? null;
                    if ($manual_id && isset($edit_map[$manual_id])) {
                        $edit_data = array_merge($edit_data, $edit_map[$manual_id]);
                    }
                    $this->roster_db_utils->insert_or_replace_row($table_c, $edit_data);
                    $results[] = "Inserted manual player: " . ($edit_data['player_id'] ?? 'unknown');
                }
            }
        }

        return $results;
    }

    /**
     * Deduplicate raw roster rows by player_id.
     *
     * Keeps the row with the most non-empty fields. On a tie, the last
     * occurrence wins (most recently added, so more likely to be current).
     */
    private function deduplicate_by_player_id(array $rows): array
    {
        $grouped = [];
        foreach ($rows as $row) {
            $grouped[$row['player_id']][] = $row;
        }

        $deduplicated = [];
        foreach ($grouped as $duplicates) {
            $best       = null;
            $best_score = -1;
            foreach ($duplicates as $candidate) {
                $score = 0;
                foreach ($candidate as $key => $val) {
                    if ($key === 'id') {
                        continue;
                    }
                    if ($val !== null && $val !== '') {
                        $score++;
                    }
                }
                // >= so that on a tie the last duplicate wins
                if ($score >= $best_score) {
                    $best       = $candidate;
                    $best_score = $score;
                }
            }
            $deduplicated[] = $best;
        }

        return $deduplicated;
    }

    public function get_results()
    {
        return $this->results;
    }

    function sanitize_roster_display_table()
    {
        $this->standardize_formatting();
    }

    private function standardize_formatting()
    {
        global $wpdb;

        $table = "{$wpdb->prefix}pp_roster_for_display";

        $rows = $wpdb->get_results("SELECT id, pos, shoots, ht, wt FROM $table", ARRAY_A);

        foreach ($rows as $row) {
            $id = (int) $row['id'];

            $normalized = [
                'pos'    => Puck_Press_Roster_Normalizer::normalize_position((string) ($row['pos']    ?? '')),
                'shoots' => Puck_Press_Roster_Normalizer::normalize_shoots(  (string) ($row['shoots'] ?? '')),
                'ht'     => Puck_Press_Roster_Normalizer::normalize_height(  (string) ($row['ht']     ?? '')),
                'wt'     => Puck_Press_Roster_Normalizer::normalize_weight(  (string) ($row['wt']     ?? '')),
            ];

            $updates     = [];
            $formats     = [];
            $null_fields = [];
            foreach ($normalized as $field => $value) {
                $current = (string) ($row[$field] ?? '');
                if ($value === null && $current !== '') {
                    // Explicit NULL — handled via direct query below
                    $null_fields[] = esc_sql($field);
                } elseif ($value !== null && $value !== $current) {
                    $updates[$field] = $value;
                    $formats[]       = '%s';
                }
            }

            if (!empty($updates)) {
                $wpdb->update($table, $updates, ['id' => $id], $formats, ['%d']);
            }

            if (!empty($null_fields)) {
                $set = implode(', ', array_map(fn($f) => "`$f` = NULL", $null_fields));
                $wpdb->query($wpdb->prepare("UPDATE `$table` SET $set WHERE id = %d", $id));
            }
        }
    }
}
