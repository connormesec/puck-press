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
 * The public-facing functionality of the plugin.
 *
 * Defines the plugin name, version, and two examples hooks for how to
 * enqueue the public-facing stylesheet and JavaScript.
 *
 * @package    Puck_Press
 * @subpackage Puck_Press/public/partials/schedule
 * @author     Connor Mesec <connormesec@gmail.com>
 */
class Puck_Press_Schedule_Process_Csv_Data
{

    protected string $csv_string;
    protected string $source_name;
    protected string $source_type;

    public function __construct(string $csv_string, string $source_name, string $source_type = 'csv')
    {
        $this->csv_string = $csv_string;
        $this->source_name = $source_name;
        $this->source_type = $source_type;
    }

    public function parse(): array
    {
        $lines = array_map('trim', explode("\n", $this->csv_string));
        $header = str_getcsv(array_shift($lines));

        $games = [];

        foreach ($lines as $line) {
            if (empty($line)) {
                continue;
            }

            $row = array_map('trim', str_getcsv($line));
            $data = array_combine($header, $row);

            $timestamp = strtotime($data['game_timestamp']);
            $date_time = date('Y-m-d H:i:s', $timestamp);
            $date_day = date('Y-m-d', $timestamp);

            $gameArr = [
                'source' => $this->source_name,
                'source_type' => $this->source_type,
                'game_id' => "csv_game_{$timestamp}",

                'target_team_name' => $data['target_team_name'],
                'target_team_nickname' => $data['target_team_nickname'],
                'target_team_logo' => $data['target_team_logo'],
                'target_team_id' => 0, //this must be set to 0 as a placeholder, as the team ID is not provided in the CSV, "null" will throw an error

                'opponent_team_id' => 0,
                'opponent_team_name' => $data['opponent_team_name'],
                'opponent_team_nickname' => $data['opponent_team_nickname'],
                'opponent_team_logo' => $data['opponent_team_logo'],

                'target_score' => $data['target_score'] ?? null,
                'opponent_score' => $data['opponent_score'] ?? null,

                'game_status' => Puck_Press_Schedule_Source_Importer::format_game_status($data['game_status'] ?? '', $data['game_time']),
                'game_date_day' => Puck_Press_Schedule_Source_Importer::format_game_date_day($date_day),
                'game_time' => $data['game_time'],
                'game_timestamp' => $date_time,
                'home_or_away' => $data['home_or_away'] ?? '',
                'venue' => $data['venue'] ?? '',
            ];

            $games[] = $gameArr;
        }

        return $games;
    }

    public static function validate_csv_headers($file_path) {
        $expected_headers = [
            'target_team_name', 'target_team_nickname', 'target_team_logo', 'target_score',
            'opponent_team_name', 'opponent_team_nickname', 'opponent_team_logo', 'opponent_score',
            'game_time', 'game_timestamp', 'home_or_away',
            'target_score', 'opponent_score', 'game_status', 'venue'
        ];

        if (!file_exists($file_path)) {
            return new WP_Error('file_missing', 'CSV file is missing or path is invalid.');
        }
    
        $handle = fopen($file_path, 'r');
        if (!$handle) {
            return new WP_Error('file_open_error', 'Could not open CSV file for reading.');
        }
    
        $header_line = fgetcsv($handle);
        fclose($handle);
    
        if (!$header_line || !is_array($header_line)) {
            return new WP_Error('invalid_csv', 'Could not read headers from CSV file.');
        }
    
        $headers = array_map('trim', $header_line);
    
        $missing_headers    = array_diff($expected_headers, $headers);
        $unexpected_headers = array_diff($headers, $expected_headers);
    
        if (!empty($missing_headers) || !empty($unexpected_headers)) {
            $message = '';
            if (!empty($missing_headers)) {
                $message .= 'Missing required headers: ' . implode(', ', $missing_headers) . '. ';
            }
            if (!empty($unexpected_headers)) {
                $message .= 'Unexpected headers found: ' . implode(', ', $unexpected_headers) . '.';
            }
            return new WP_Error('csv_header_validation_failed', trim($message));
        }
    
        return true;
    }
}
