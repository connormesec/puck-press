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
class Puck_Press_Roster_Process_Acha_Url
{
    private $raw_acha_roster_url;
    public $raw_roster_data;
    private $team_id = '';
    private $season_id = '';

    public function __construct($raw_acha_roster_url)
    {
        $this->raw_acha_roster_url = $raw_acha_roster_url;
        $jsonData = $this->getRawDataFromAchaUrl();
        $this->raw_roster_data = $this->extractHockeyroster($jsonData);
    }

    public function getRawDataFromAchaUrl()
    {
        $team_and_roster_id = $this->_get_string_between($this->raw_acha_roster_url, 'roster/', '?division');
        $team_and_roster_exploded = explode("/", $team_and_roster_id);
        $this->team_id = $team_and_roster_exploded[0];
        $this->season_id = $team_and_roster_exploded[1];

        $roster_request_url = "https://lscluster.hockeytech.com/feed/index.php?feed=statviewfeed&view=roster&team_id={$this->team_id}&season_id={$this->season_id}&key=e6867b36742a0c9d&client_code=acha&site_id=2&league_id=-1&lang=en";

        $angularData = @file_get_contents($roster_request_url);
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

    private function extractHockeyroster($jsonData)
    {
        // Initialize result array for the games
        $roster = [];

        // Navigate through the nested structure to find game data
        if (!isset($jsonData['roster'][0]['sections'][0]['data'])) {
            return ['error' => 'Expected data structure not found'];
        }

        $raw_roster = $jsonData['roster'][0]['sections'];
        foreach ($raw_roster as $players) {
            $position = $players['title'];
            if ($position == 'Coaches') {
                continue; // Skip coaches
            }
            foreach ($players['data'] as $player) {
                $player_info = [
                    'shoots' => $player['row']['shoots'],
                    'hometown' => $player['row']['hometown'],
                    'ht' => $player['row']['height_hyphenated'],
                    'player_id' => $player['row']['player_id'],
                    'number' => $player['row']['tp_jersey_number'],
                    'pos' => $player['row']['position'],
                    'wt' => $player['row']['w'],
                    'name' => $player['row']['name'],
                    'headshot_link' => 'https://assets.leaguestat.com/acha/240x240/' . $player['row']['player_id'] . '.jpg',
                    'last_team' => '',
                    'year_in_school' => '',
                    'major' => ''
                ];
                $roster[] = $player_info;
            }
        }
        return $roster;
    }
}
