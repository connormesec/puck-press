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
class Puck_Press_Schedule_Process_Acha_Url
{
    private $raw_acha_schedule_url;
    private $season_year = '';
    public $raw_schedule_data;
    private $team_id = '';
    private $season_id = '';
    private $division_id;
    private $team_logo_data = [];

    public function __construct($raw_acha_schedule_url, $season_year = '')
    {
        $this->team_logo_data = $this->retrieveLogoData();
        $this->raw_acha_schedule_url = $raw_acha_schedule_url;
        $this->season_year = $season_year;
        $jsonData = $this->getRawDataFromAchaUrl();
        $this->raw_schedule_data = $this->extractHockeySchedule($jsonData);
    }

    public function getRawDataFromAchaUrl()
    {
        $team_and_schedule_id = $this->_get_string_between($this->raw_acha_schedule_url, 'schedule/', '/all-months');
        $team_and_schedule_exploded = explode("/", $team_and_schedule_id);
        $this->team_id = $team_and_schedule_exploded[0];
        $this->season_id = $team_and_schedule_exploded[1];
        $this->division_id = $this->_get_string_between($this->raw_acha_schedule_url, 'division_id=', '&');

        $schedule_request_url = "https://lscluster.hockeytech.com/feed/index.php?feed=statviewfeed&view=schedule&team=" . $this->team_id . "&season=" . $this->season_id . "&month=-1&location=homeaway&key=e6867b36742a0c9d&client_code=acha&site_id=2&league_id=1&division_id=" . $this->division_id  . "&lang=en";

        $angularData = @file_get_contents($schedule_request_url);
        $raw_data = substr($angularData, 1, -1);
        // Parse the JSON
        $jsonData = json_decode($raw_data, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            return ['error' => 'Failed to parse JSON: ' . json_last_error_msg()];
        }
        return $jsonData;
    }

    private function _get_string_between($string, $start, $end)
    {
        $string = ' ' . $string;
        $ini = strpos($string, $start);
        if ($ini == 0) return '';
        $ini += strlen($start);
        $len = strpos($string, $end, $ini) - $ini;
        return substr($string, $ini, $len);
    }

    private function extractHockeySchedule($jsonData)
    {
        // Initialize result array for the games
        $schedule = [];

        // Navigate through the nested structure to find game data
        if (!isset($jsonData[0]['sections'][0]['data'])) {
            return ['error' => 'Expected data structure not found'];
        }

        $games = $jsonData[0]['sections'][0]['data'];

        // Process each game
        foreach ($games as $game) {
            $row = $game['row'];
            $prop = $game['prop'];
            $homeTeam = $row['home_team_city'];
            $visitingTeam = $row['visiting_team_city'];
            $target_is_home = ($this->team_id == $prop['home_team_city']['teamLink']);
            $parsed_status_and_time = $this->parse_game_status_and_time($row['game_status']);
            if ($target_is_home) {
                $target_team_id = $prop['home_team_city']['teamLink'];
                $opponent_team_id = $prop['visiting_team_city']['teamLink'];

                $target_city_name = $homeTeam;
                $opponent_city_name = $visitingTeam;

                $target_score = $row['home_goal_count'];
                $opponent_score = $row['visiting_goal_count'];

                $home_or_away = 'home';
            } else {
                $target_team_id = $prop['visiting_team_city']['teamLink'];
                $opponent_team_id = $prop['home_team_city']['teamLink'];

                $target_city_name = $visitingTeam;
                $opponent_city_name = $homeTeam;

                $target_score = $row['visiting_goal_count'];
                $opponent_score = $row['home_goal_count'];

                $home_or_away = 'away';
            }
            $game_info = [
                'target_team_id' => $target_team_id,
                'opponent_team_id' => $opponent_team_id,
                'target_team_name' => $target_city_name,
                'opponent_team_name' => $opponent_city_name,
                'target_team_nickname' => $this->team_logo_data[$target_team_id]['nickname'],
                'opponent_team_nickname' => $this->team_logo_data[$opponent_team_id]['nickname'],
                'target_team_logo' => $this->team_logo_data[$target_team_id]['logo'],
                'opponent_team_logo' => $this->team_logo_data[$opponent_team_id]['logo'],
                'target_score' => $target_score,
                'opponent_score' => $opponent_score,
                'game_status' => $parsed_status_and_time['game_status'],
                'game_date_day' => $row['date_with_day'],
                'game_time' => $parsed_status_and_time['game_time'],
                'game_id' => $row['game_id'],
                'home_or_away' => $home_or_away,
                'game_timestamp' => $this->build_wp_datetime_from_year_and_date($row['date_with_day']),
                'venue' => $row['venue_name']
            ];

            $schedule[] = $game_info;
        }
        return $schedule;
    }

    //TODO : Put this function on a cron job to run 2x a year or so and store results in the database
    private function retrieveLogoData()
    {
        $team_logo_request_url = "https://lscluster.hockeytech.com/feed/index.php?feed=statviewfeed&view=teamsForSeason&season=-1&division=-1&key=e6867b36742a0c9d&client_code=acha&site_id=2";
        $paren_wrapped_jsonData = @file_get_contents($team_logo_request_url);
        $raw_json = substr($paren_wrapped_jsonData, 1, -1); // Remove the outer parentheses
        $jsonData = json_decode($raw_json, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            return ['error' => 'Failed to parse JSON: ' . json_last_error_msg()];
        }
        $a = array();
        foreach ($jsonData['teams'] as $key => $value) {
            $a[$value['id']] = $value;
        }
        return $a;
    }

    private function build_wp_datetime_from_year_and_date($date_str) {
        $year = $this->get_season_year_for_date($date_str);
        // Combine year with the partial date string
        $full_date_str = $date_str . ' ' . $year;
    
        // Parse the full string into a timestamp
        $timestamp = strtotime($full_date_str);
    
        if ($timestamp === false) {
            return null; // Invalid date
        }
    
        // Format as 'YYYY-MM-DD HH:MM:SS' for WordPress / MySQL
        return date('Y-m-d H:i:s', $timestamp);
    }

    private function get_season_year_for_date($date_str)
    {
        // Extract the start and end years from the season string
        list($start_year, $end_year) = explode('-', $this->season_year);

        // Convert the formatted date string (e.g., "Fri, Sep 20") to a timestamp
        $date = strtotime($date_str . ' ' . $start_year); // Add the year to the date string
        if ($date === false) {
            return "Invalid date format"; // Handle invalid date format
        }

        // Get the month of the provided date
        $month = (int) date('n', $date); // Numeric month (1-12)

        // Determine the season year based on the month
        if ($month >= 9) {  // If the month is from September to December
            return $start_year; // It belongs to the first year of the season
        } else { // If the month is from January to April
            return $end_year; // It belongs to the second year of the season
        }
    }

    private function parse_game_status_and_time($external_status) {
        $normalized = trim($external_status);
    
        // Regex for times like "7:30 PM", "13:00", "07:30PM"
        $time_pattern = '/^((0?[1-9]|1[0-2]):[0-5][0-9]\s?[APap][Mm]|([01]?[0-9]|2[0-3]):[0-5][0-9])(\s?[A-Za-z]{2,4})?$/';
    
        if (preg_match($time_pattern, $normalized)) {
            return [
                'game_status' => null, // or null
                'game_time' => $normalized
            ];
        }
    
        // Otherwise assume it's a status (like "Final", "Postponed", etc.)
        return [
            'game_status' => $normalized,
            'game_time' => null
        ];
    }
}
