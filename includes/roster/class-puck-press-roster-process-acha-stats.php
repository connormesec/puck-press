<?php

/**
 * Fetches and parses player stats from the ACHA/HockeyTech stats API.
 *
 * Accepts the public ACHA stats page URL (e.g. achahockey.org/stats/player-stats/51/60?...)
 * and translates it into an lscluster.hockeytech.com API call, returning normalised stat rows
 * ready for insertion into pp_roster_stats.
 *
 * @package    Puck_Press
 * @subpackage Puck_Press/includes/roster
 */
class Puck_Press_Roster_Process_Acha_Stats
{
    private $raw_stats_url;
    public $raw_stats_data;

    public function __construct( $raw_stats_url )
    {
        $this->raw_stats_url  = $raw_stats_url;
        $json_data            = $this->fetch_stats_data();
        $this->raw_stats_data = $this->extract_stats( $json_data );
    }

    private function fetch_stats_data()
    {
        $parsed = parse_url( $this->raw_stats_url );

        // Path looks like: /stats/player-stats/{team_id}/{season_id}
        $path_parts = explode( '/', trim( $parsed['path'] ?? '', '/' ) );
        $season_id  = array_pop( $path_parts );
        $team_id    = array_pop( $path_parts );

        parse_str( $parsed['query'] ?? '', $query_params );

        $conference = $query_params['conference']  ?? '-1';
        $division   = $query_params['division']    ?? '-1';
        $position   = $query_params['position']    ?? 'skaters';
        $rookie_raw = strtolower( $query_params['rookie'] ?? 'no' );
        $rookies    = ( $rookie_raw === 'yes' ) ? 1 : 0;
        $sort       = $query_params['sort']        ?? 'points';
        $stats_type = $query_params['statstype']   ?? 'standard';
        $league_id  = $query_params['league']      ?? '1';

        $api_url = 'https://lscluster.hockeytech.com/feed/index.php?' . http_build_query( [
            'feed'         => 'statviewfeed',
            'view'         => 'players',
            'season'       => $season_id,
            'team'         => $team_id,
            'position'     => $position,
            'rookies'      => $rookies,
            'statsType'    => $stats_type,
            'rosterstatus' => 'undefined',
            'site_id'      => '2',
            'first'        => '0',
            'limit'        => '200',
            'sort'         => $sort,
            'league_id'    => $league_id,
            'lang'         => 'en',
            'division'     => $division,
            'conference'   => $conference,
            'key'          => 'e6867b36742a0c9d',
            'client_code'  => 'acha',
        ] );

        $response = @file_get_contents( $api_url );
        if ( $response === false ) {
            return [ 'error' => 'Failed to fetch stats data from API.' ];
        }

        // Response is wrapped in parens: ([...])
        $json_str = trim( $response );
        if ( substr( $json_str, 0, 1 ) === '(' && substr( $json_str, -1 ) === ')' ) {
            $json_str = substr( $json_str, 1, -1 );
        }

        $data = json_decode( $json_str, true );
        if ( json_last_error() !== JSON_ERROR_NONE ) {
            return [ 'error' => 'Failed to parse stats JSON: ' . json_last_error_msg() ];
        }

        return $data;
    }

    private function extract_stats( $json_data )
    {
        if ( isset( $json_data['error'] ) ) {
            return $json_data;
        }

        if ( ! isset( $json_data[0]['sections'][0]['data'] ) ) {
            return [ 'error' => 'Expected stats data structure not found.' ];
        }

        $stats = [];

        foreach ( $json_data[0]['sections'] as $section ) {
            foreach ( $section['data'] as $player ) {
                $row = $player['row'];

                $pm = isset( $row['penalty_minutes'] ) && $row['penalty_minutes'] !== ''
                    ? intval( $row['penalty_minutes'] )
                    : null;

                $stats[] = [
                    'player_id'              => $row['player_id'] ?? '',
                    'games_played'           => isset( $row['games_played'] )           ? intval( $row['games_played'] )           : null,
                    'goals'                  => isset( $row['goals'] )                  ? intval( $row['goals'] )                  : null,
                    'assists'                => isset( $row['assists'] )                ? intval( $row['assists'] )                : null,
                    'points'                 => isset( $row['points'] )                 ? intval( $row['points'] )                 : null,
                    'points_per_game'        => isset( $row['points_per_game'] )        ? floatval( $row['points_per_game'] )      : null,
                    'power_play_goals'       => isset( $row['power_play_goals'] )       ? intval( $row['power_play_goals'] )       : null,
                    'short_handed_goals'     => isset( $row['short_handed_goals'] )     ? intval( $row['short_handed_goals'] )     : null,
                    'game_winning_goals'     => isset( $row['game_winning_goals'] )     ? intval( $row['game_winning_goals'] )     : null,
                    'shootout_winning_goals' => isset( $row['shootout_winning_goals'] ) ? intval( $row['shootout_winning_goals'] ) : null,
                    'penalty_minutes'        => $pm,
                    'shooting_percentage'    => isset( $row['shooting_percentage'] )    ? floatval( $row['shooting_percentage'] )  : null,
                    'rank'                   => isset( $row['rank'] )                   ? intval( $row['rank'] )                   : null,
                ];
            }
        }

        return $stats;
    }
}
