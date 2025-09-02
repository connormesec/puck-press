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
        require_once plugin_dir_path(dirname(__FILE__)) . 'roster/class-puck-press-roster-process-acha-url.php';
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
                } elseif ($source->type === 'usphlRosterUrl') {
                    $raw_usphl_data = new Puck_Press_Roster_Process_Usphl_Url($source->source_url_or_path);
                    //append source name to each row
                    foreach ($raw_usphl_data->raw_roster_data as &$row) {
                        $row['source'] = $source->name;
                    }

                    $inserted = $this->roster_db_utils->insert_multiple_roster_rows($raw_usphl_data->raw_roster_data);

                    $this->results['success_count']++;
                    $this->results['messages'][] = "Imported source: {$source->name}";
                    $this->results['messages'][] = $inserted;
                } elseif ($source->type === 'customPlayer') {
                    $other_data = json_decode($source->other_data, true);

                    $playerArr = [
                        [
                            'source' => $source->name,
                            'player_id' => "custom_player_{$other_data['name']}",
                            'name' => $other_data['name'],
                            'headshot_link' => $other_data['headshot_link'],
                            'number' => $other_data['number'],
                            'pos' => $other_data['pos'],
                            'ht' => $other_data['ht'],
                            'wt' => $other_data['wt'],
                            'shoots' => $other_data['shoots'],
                            'hometown' => $other_data['hometown'],
                            'last_team' => $other_data['last_team'],
                            'year_in_school' => $other_data['year'],
                            'major' => $other_data['major']
                        ]
                    ];
                    $inserted = $this->roster_db_utils->insert_multiple_roster_rows($playerArr);
                    $this->results['success_count']++;
                    $this->results['messages'][] = "Imported source: {$source->name}";
                    $this->results['messages'][] = $inserted;
                    $this->results['messages'][] = $other_data;
                    $this->results['messages'][] = $playerArr;
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

        // Fetch all base data
        $originals = $this->roster_db_utils->get_all_table_data($table_a, 'ARRAY_A');
        if (empty($originals)) {
            return false;
        }

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

        return $results;
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

        $rows = $wpdb->get_results("SELECT id, pos FROM $table", ARRAY_A);

        foreach ($rows as $row) {
            $id = (int) $row['id'];
            $originalPos = isset($row['pos']) ? trim($row['pos']) : '';
            $position = $this->normalizePosition($originalPos);

            if ($position !== $originalPos) {
                $wpdb->update(
                    $table,
                    ['pos' => $position],
                    ['id' => $id],
                    ['%s'],
                    ['%d']
                );
            }
        }
    }

    function normalizePosition(string $pos): string
    {
        $pos = strtolower(trim($pos));

        $forwards = ['f', 'forward', 'forwards'];
        $defense  = ['d', 'defense', 'defenceman', 'defender'];
        $goalies  = ['g', 'goalie', 'goaltender'];

        if (in_array($pos, $forwards, true)) {
            return 'F';
        }

        if (in_array($pos, $defense, true)) {
            return 'D';
        }

        if (in_array($pos, $goalies, true)) {
            return 'G';
        }

        // Leave as-is if not recognized
        return $pos;
    }
}
