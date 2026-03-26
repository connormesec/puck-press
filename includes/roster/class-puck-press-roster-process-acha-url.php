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
class Puck_Press_Roster_Process_Acha_Url {

	public string $team_id   = '';
	public string $season_id = '';
	public string $team_name = '';
	public array $raw_roster_data = array();

	public function __construct( string $team_id, string $season_id ) {
		$this->team_id   = $team_id;
		$this->season_id = $season_id;

		$jsonData              = $this->fetchRosterFromApi();
		$this->raw_roster_data = $this->extractHockeyroster( $jsonData );
	}

	private function fetchRosterFromApi(): array {
		$url = "https://lscluster.hockeytech.com/feed/index.php?feed=statviewfeed&view=roster&team_id={$this->team_id}&season_id={$this->season_id}&key=e6867b36742a0c9d&client_code=acha&site_id=2&league_id=-1&lang=en";

		$raw     = @file_get_contents( $url ); // phpcs:ignore
		$decoded = json_decode( substr( (string) $raw, 1, -1 ), true );

		if ( json_last_error() !== JSON_ERROR_NONE ) {
			return array( 'error' => 'Failed to parse JSON: ' . json_last_error_msg() );
		}
		$this->team_name = $decoded['teamName'] ?? '';
		return $decoded;
	}

	private function extractHockeyroster( $jsonData ) {
		// Initialize result array for the games
		$roster = array();

		// Navigate through the nested structure to find game data
		if ( ! isset( $jsonData['roster'][0]['sections'][0]['data'] ) ) {
			return array( 'error' => 'Expected data structure not found' );
		}

		$raw_roster = $jsonData['roster'][0]['sections'];
		foreach ( $raw_roster as $players ) {
			$position = $players['title'];
			if ( $position == 'Coaches' ) {
				continue; // Skip coaches
			}
			foreach ( $players['data'] as $player ) {
				$player_info = array(
					'shoots'         => $player['row']['shoots'],
					'hometown'       => $player['row']['hometown'],
					'ht'             => $player['row']['height_hyphenated'],
					'player_id'      => $player['row']['player_id'],
					'number'         => $player['row']['tp_jersey_number'],
					'pos'            => $player['row']['position'],
					'wt'             => $player['row']['w'],
					'name'           => $player['row']['name'],
					'headshot_link'  => 'https://assets.leaguestat.com/acha/240x240/' . $player['row']['player_id'] . '.jpg',
					'team_id'        => $this->team_id,
					'team_name'      => $this->team_name,
					'last_team'      => '',
					'year_in_school' => '',
					'major'          => '',
				);
				$roster[]    = $player_info;
			}
		}
		return $roster;
	}
}
