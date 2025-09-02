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
 * @subpackage Puck_Press/public/partials/roster
 * @author     Connor Mesec <connormesec@gmail.com>
 */
class Puck_Press_Roster_Process_Usphl_Url
{
    private $raw_usphl_roster_url;
    public $raw_roster_data;

    public function __construct($raw_usphl_roster_url)
    {
        $this->raw_usphl_roster_url = $raw_usphl_roster_url;
        $jsonData = $this->getJsonUsphlUrl();
        $this->raw_roster_data = $this->extractHockeyRoster($jsonData);
    }

    public function getJsonUsphlUrl()
    {
        $roster_request_url = $this->raw_usphl_roster_url;
        $raw_data = @file_get_contents($roster_request_url);
        // Parse the JSON
        $jsonData = json_decode($raw_data, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            return ['error' => 'Failed to parse JSON: ' . json_last_error_msg()];
        }
        return $jsonData;
    }

    private function extractHockeyRoster($jsonData)
    {
        // Initialize result array for the games
        $roster = [];

        // Navigate through the nested structure to find game data
        if (!isset($jsonData['roster']['players'])) {
            return ['error' => 'Expected data structure not found'];
        }

        $raw_roster = $jsonData['roster']['players'];
        
        foreach ($raw_roster as $player) {
            $player_info = [
                'shoots' => $player['shoots'],
                'hometown' => $player['display_hometown'],
                'ht' => $player['height'],
                'player_id' => $player['player_id'],
                'number' => $player['jersey'],
                'pos' => $player['plays'],
                'wt' => $player['weight'],
                'name' => $player['player_name'],
                'headshot_link' => $player['player_image'],
                'last_team' => '',
                'year_in_school' => '',
                'major' => ''
            ];
            $roster[] = $player_info;
        }
        return $roster;
    }
}
