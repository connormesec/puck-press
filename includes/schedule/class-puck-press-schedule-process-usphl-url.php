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
class Puck_Press_Schedule_Process_Usphl_Url
{
    private $raw_usphl_schedule_url;
    public $raw_schedule_data;
    private $team_id = '';

    public function __construct($raw_usphl_schedule_url)
    {
        $this->raw_usphl_schedule_url = $raw_usphl_schedule_url;
        $jsonData = $this->getJsonUsphlUrl();
        $this->raw_schedule_data = $this->extractHockeySchedule($jsonData);
    }

    public function getJsonUsphlUrl()
    {
        $schedule_request_url = $this->raw_usphl_schedule_url;

        $raw_data = @file_get_contents($schedule_request_url);
        // Parse the JSON
        $jsonData = json_decode($raw_data, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            return ['error' => 'Failed to parse JSON: ' . json_last_error_msg()];
        }
        return $jsonData;
    }

    private function extractHockeySchedule($jsonData)
    {
        // Initialize result array for the games
        $schedule = [];

        // Navigate through the nested structure to find game data
        if (!isset($jsonData['schedule']['games'])) {
            return ['error' => 'Expected data structure not found'];
        }

        $url = $jsonData['url'];
        // Break down the URL into components
        $parts = parse_url($url);
        // Parse the query string (?season=65&team=2298) into an array
        parse_str($parts['query'], $query);
        // Get the team ID
        $this->team_id = isset($query['team']) ? $query['team'] : null;
        if ($this->team_id === null) {
            return ['error' => 'Team ID not found'];
        }

        $games = $jsonData['schedule']['games'];

        // Process each game
        foreach ($games as $game) {
            $homeTeam = $game['home_team'];
            $visitingTeam = $game['away_team'];
            $target_is_home = ($this->team_id == $game['home_id']);

            if ($target_is_home) {
                $target_team_id = $game['home_id'];
                $opponent_team_id = $game['away_id'];

                $target_team_name = $homeTeam;
                $opponent_team_name = $visitingTeam;

                $target_team_logo = $game['home_smlogo'];
                $opponent_team_logo = $game['away_smlogo'];

                $target_score = $game['home_goals'];
                $opponent_score = $game['away_goals'];

                $home_or_away = 'home';
            } else {
                $target_team_id = $game['away_id'];
                $opponent_team_id = $game['home_id'];

                $target_team_name = $visitingTeam;
                $opponent_team_name = $homeTeam;

                $target_team_logo = $game['away_smlogo'];
                $opponent_team_logo = $game['home_smlogo'];

                $target_score = $game['away_goals'];
                $opponent_score = $game['home_goals'];

                $home_or_away = 'away';
            }
            $targetTeamSplit = $this->split_team_name($target_team_name);
            $opponentTeamSplit = $this->split_team_name($opponent_team_name);

            $game_info = [
                'target_team_id' => $target_team_id,
                'opponent_team_id' => $opponent_team_id,
                'target_team_name' => $targetTeamSplit['city'],
                'opponent_team_name' => $opponentTeamSplit['city'],
                'target_team_nickname' => $targetTeamSplit['name'],
                'opponent_team_nickname' => $opponentTeamSplit['name'],
                'target_team_logo' => $target_team_logo,
                'opponent_team_logo' => $opponent_team_logo,
                'target_score' => $target_score,
                'opponent_score' => $opponent_score,
                'game_status' => $game['result_string'],
                'game_date_day' => date('D, M j', strtotime($game['date'])),
                'game_time' => date("g:ia", strtotime($game['time'])),
                'game_id' => $game['game_id'],
                'home_or_away' => $home_or_away,
                'game_timestamp' => $this->format_gmt_to_timezone($game['gmt_time'], $game['timezn']),
                'venue' => $game['location']
            ];

            $schedule[] = $game_info;
        }
        return $schedule;
    }

    private function split_team_name($team)
    {
        // Special case: capture if "Jr" or "Junior" is part of the team name
        if (preg_match('/^(.+?)\s+(Jr(?:\.|)|Junior\s+\w+.*)$/i', $team, $matches)) {
            return ['city' => $matches[1], 'name' => $matches[2]];
        }

        // Default: split on last space
        if (preg_match('/^(.+)\s+(\S+)$/', $team, $matches)) {
            return ['city' => $matches[1], 'name' => $matches[2]];
        }

        return ['city' => $team, 'name' => ''];
    }

    private function format_gmt_to_timezone($gmt_time, $timezone)
    {
        // Create from format with microseconds
        $date = DateTime::createFromFormat(
            'Y-m-d H:i:s.u',
            $gmt_time,
            new DateTimeZone("GMT")
        );

        // If parsing fails, try without microseconds
        if (!$date) {
            $date = new DateTime($gmt_time, new DateTimeZone("GMT"));
        }

        $date->setTimezone(new DateTimeZone($timezone));

        // Format as 2025-08-29 22:31:00
        return $date->format("Y-m-d H:i:s");
    }
}
