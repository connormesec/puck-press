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
class Puck_Press_Roster_Process_Csv_Data
{

    protected string $csv_string;
    protected string $source_name;

    public function __construct(string $csv_string, string $source_name)
    {
        $this->csv_string = $csv_string;
        $this->source_name = $source_name;
    }

    public function parse(): array
    {
        $lines = array_map('trim', explode("\n", $this->csv_string));
        $header = str_getcsv(array_shift($lines));

        $players = [];

        foreach ($lines as $line) {
            if (empty($line)) {
                continue;
            }

            $row = array_map('trim', str_getcsv($line));
            $data = array_combine($header, $row);

            $playerArr = [
                'source' => $this->source_name,
                'player_id' => "csv_player_{$data['name']}",
                'name' => $data['name'],
                'headshot_link' => $data['headshot_link'],
                'number' => $data['number'],
                'pos' => $data['pos'],
                'ht' => $data['ht'],
                'wt' => $data['wt'],
                'shoots' => $data['shoots'],
                'hometown' => $data['hometown'],
                'last_team' => $data['last_team'],
                'year_in_school' => $data['year_in_school'],
                'major' => $data['major']
            ];

            $players[] = $playerArr;
        }

        return $players;
    }

    public static function validate_csv_headers($file_path)
    {
        $expected_headers = [
            'name',
            'headshot_link',
            'number',
            'pos',
            'ht',
            'wt',
            'shoots',
            'hometown',
            'last_team',
            'year_in_school',
            'major'
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
