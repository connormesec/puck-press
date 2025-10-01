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
 * This takes the active sources, get's their data and puts it into the raw game schedule for further processing
 *
 * 
 *
 * @package    Puck_Press
 * @subpackage Puck_Press/public/partials/schedule
 * @author     Connor Mesec <connormesec@gmail.com>
 */
class Puck_Press_Schedule_Source_Importer
{

    private $schedule_db_utils;
    private $results = [];

    public function __construct()
    {
        $this->load_dependencies();
        $this->schedule_db_utils = new Puck_Press_Schedule_Wpdb_Utils();
    }

    private function load_dependencies()
    {
        require_once plugin_dir_path(dirname(__FILE__)) . 'schedule/class-puck-press-schedule-wpdb-utils.php';
        require_once plugin_dir_path(dirname(__FILE__)) . 'schedule/class-puck-press-schedule-process-acha-url.php';
        require_once plugin_dir_path(dirname(__FILE__)) . 'schedule/class-puck-press-schedule-process-usphl-url.php';
        require_once plugin_dir_path(dirname(__FILE__)) . 'schedule/class-puck-press-schedule-process-csv-data.php';
    }

    public function populate_raw_schedule_table_from_sources()
    {
        $this->results = [
            'success_count' => 0,
            'error_count'   => 0,
            'errors'        => [],
            'messages'      => [],
        ];

        $active_sources = $this->schedule_db_utils->get_active_schedule_sources() ?? [];

        if (empty($active_sources)) {
            $this->results['messages'][] = 'No active sources to import.';
            return $this->results;
        }

        foreach ($active_sources as $source) {
            try {
                if ($source->type === 'achaGameScheduleUrl') {

                    $raw_acha_data = new Puck_Press_Schedule_Process_Acha_Url($source->source_url_or_path, $source->season);
                    //append schedule name to each row
                    foreach ($raw_acha_data->raw_schedule_data as &$row) {
                        $row['source'] = $source->name;
                        $row['source_type'] = $source->type;
                    }

                    $inserted = $this->schedule_db_utils->insert_multiple_game_schedule_rows($raw_acha_data->raw_schedule_data);

                    $this->results['success_count']++;
                    $this->results['messages'][] = "Imported source: {$source->name}";
                    $this->results['messages'][] = $inserted;

                } elseif ($source->type === 'usphlGameScheduleUrl') {
                    $raw_usphl_data = new Puck_Press_Schedule_Process_Usphl_Url($source->source_url_or_path);
                    //append schedule name to each row
                    foreach ($raw_usphl_data->raw_schedule_data as &$row) {
                        $row['source'] = $source->name;
                        $row['source_type'] = $source->type;
                    }

                    $inserted = $this->schedule_db_utils->insert_multiple_game_schedule_rows($raw_usphl_data->raw_schedule_data);

                    $this->results['success_count']++;
                    $this->results['messages'][] = "Imported source: {$source->name}";
                    $this->results['messages'][] = $inserted;
                } elseif ($source->type === 'customGame') {
                    $other_data = json_decode($source->other_data, true);
                    $date_day = $this->format_game_date_day($other_data['gameDate'], $other_data['gameTime']);
                    $game_timestamp = $this->get_game_timestamp($other_data['gameDate'], $other_data['gameTime']);
                    $gameArr = [
                        [
                            'source' => $source->name,
                            'source_type' => $source->type,
                            'game_id' => "custom_game_{$game_timestamp}_{$source->name}",
                            'target_team_name' => $other_data['target']['name'],
                            'target_team_nickname' => $other_data['target']['nickname'],
                            'target_team_logo' => $other_data['target']['logo'],
                            'target_team_id' => $other_data['target']['id'],
                            'opponent_team_id' => $other_data['opponent']['id'],
                            'opponent_team_name' => $other_data['opponent']['name'],
                            'opponent_team_nickname' => $other_data['opponent']['nickname'],
                            'opponent_team_logo' => $other_data['opponent']['logo'],
                            'target_score' => $other_data['target_score'],
                            'opponent_score' => $other_data['opponent_score'],
                            'game_status' => $this->format_game_status($other_data['game_status'], $other_data['gameTime']),
                            'game_date_day' => $date_day,
                            'game_time' => date("g:i a", strtotime($other_data['gameTime'])),
                            'game_timestamp' => $game_timestamp,
                            'home_or_away' => $other_data['home_or_away'],
                            'venue' => $other_data['venue']
                        ]
                    ];
                    $inserted = $this->schedule_db_utils->insert_multiple_game_schedule_rows($gameArr);
                    $this->results['success_count']++;
                    $this->results['messages'][] = "Imported source: {$source->name}";
                    $this->results['messages'][] = $inserted;
                    $this->results['messages'][] = $other_data;
                } elseif ($source->type === 'csv') {
                    $csv_data = $source->csv_data ?? null;
                    $process_csv = new Puck_Press_Schedule_Process_Csv_Data($csv_data, $source->name, $source->type);
                    $games = $process_csv->parse();
                    $inserted = $this->schedule_db_utils->insert_multiple_game_schedule_rows($games);
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

        $table_a = 'pp_game_schedule_raw';
        $table_b = 'pp_game_schedule_mods'; // Edits
        $table_c = 'pp_game_schedule_for_display'; // Result

        // Fetch all base data
        $originals = $this->schedule_db_utils->get_all_table_data($table_a, 'ARRAY_A');
        if (empty($originals)) {
            return false;
        }

        // Fetch all edits
        $edits = $this->schedule_db_utils->get_all_table_data($table_b, 'ARRAY_A');
        $edit_map = [];
        $delete_ids = [];
        $results = [];

        foreach ($edits as $edit) {
            $game_id = $edit['external_id'];
            $action = strtolower($edit['edit_action'] ?? 'update');

            if ($action === 'delete') {
                $delete_ids[] = $game_id;
            } elseif ($action === 'update') {
                $edit_data = json_decode($edit['edit_data'], true);
                if (json_last_error() === JSON_ERROR_NONE && is_array($edit_data)) {
                    $edit_map[$game_id] = $edit_data;
                }
            }
        }
        // Merge and insert into table_c
        foreach ($originals as $row) {
            $game_id = $row['game_id'];

            /// Skip this row if it's marked for deletion
            if (in_array($game_id, $delete_ids, true)) {
                // Optionally delete it from table_c if already exists
                $this->schedule_db_utils->delete_row_by_game_id($table_c, $game_id);
                $results[] = "Deleted game ID: $game_id";
                continue;
            }

            // Apply edits if available
            if (isset($edit_map[$game_id])) {
                $row = array_merge($row, $edit_map[$game_id]);
                $results[] = "Updated game ID: $game_id";
            }

            // Insert or update into table_c
            $this->schedule_db_utils->insert_or_replace_row($table_c, $row);
        }

        return $results;
    }

    public function get_results()
    {
        return $this->results;
    }

    public static function format_game_date_day($gameDate, $gameTime = null)
    {
        // Combine date and optional time into a datetime string
        $datetime_string = $gameDate;

        if (! empty($gameTime)) {
            $datetime_string .= ' ' . $gameTime;
        }

        try {
            $date = new DateTime($datetime_string);
        } catch (Exception $e) {
            return null; // or handle error however you prefer
        }

        // Format: "Fri, Sep 13"
        return $date->format('D, M j');
    }

    public static function format_game_status($gameStatus, $gameTime = null)
    {
        // Normalize the status
        $normalizedStatus = strtolower(str_replace([' ', '_'], '-', $gameStatus));

        // Check for known final statuses
        switch ($normalizedStatus) {
            case 'final':
                return 'FINAL';
            case 'final-ot':
                return 'FINAL OT';
            case 'final-so':
                return 'FINAL SO';
            default:
                // If gameTime exists and looks like a valid time, format it nicely
                if ($gameTime) {
                    // Only strip known timezone abbreviations (MST, PST, EDT, etc.), but NOT AM/PM
                    $cleanTime = preg_replace('/\s+(?!AM|PM)([A-Z]{2,4})$/i', '', $gameTime);

                    $parsedTime = strtotime($cleanTime);
                    if ($parsedTime) {
                        return date('g:i A', $parsedTime); // e.g., "7:30 PM"
                    }

                    return $gameTime; // Fallback
                }

                return $gameStatus;
        }
    }

    function get_game_timestamp($gameDate, $gameTime = null)
    {
        $datetime_string = $gameDate;

        if (! empty($gameTime)) {
            $datetime_string .= ' ' . $gameTime;
        }

        try {
            $date = new DateTime($datetime_string);
            return $date->format('Y-m-d H:i:s'); // MySQL datetime format
        } catch (Exception $e) {
            return null;
        }
    }

    function sanitize_raw_games_table()
    {
        $this->pp_remove_prefixes_from_team_names();
    }

    function pp_remove_prefixes_from_team_names()
    {
        global $wpdb;

        $table = "{$wpdb->prefix}pp_game_schedule_raw"; // Change to your actual table name
        $prefixes = ['MD1 ', 'MD2 ', 'MD3 ', 'M1 ', 'M2 ', 'M3 ', 'WD1 ', 'WD2 ', 'WD3 ', 'W1 ', 'W2 ', 'W3 '];

        // Fetch all rows
        $rows = $wpdb->get_results("SELECT id, target_team_name, opponent_team_name FROM $table", ARRAY_A);

        foreach ($rows as $row) {
            $id = $row['id'];

            // Remove prefixes
            $targetTeam = $this->pp_remove_prefixes($row['target_team_name'], $prefixes);
            $opponentTeam = $this->pp_remove_prefixes($row['opponent_team_name'], $prefixes);

            // Only update if there's a change
            if ($targetTeam !== $row['target_team_name'] || $opponentTeam !== $row['opponent_team_name']) {
                $wpdb->update(
                    $table,
                    [
                        'target_team_name' => $targetTeam,
                        'opponent_team_name' => $opponentTeam
                    ],
                    ['id' => $id],
                    ['%s', '%s'],
                    ['%d']
                );
            }
        }
    }

    function pp_remove_prefixes($string, $prefixes)
    {
        foreach ($prefixes as $prefix) {
            if (str_starts_with($string, $prefix)) {
                return substr($string, strlen($prefix));
            }
        }
        return $string;
    }
}
