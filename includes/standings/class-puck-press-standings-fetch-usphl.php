<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Puck_Press_Standings_Fetch_Usphl {

    private string $team_id;
    private string $season_id;
    public array $fetch_errors = array();

    public function __construct( string $team_id, string $season_id = '' ) {
        $this->team_id   = $team_id;
        $this->season_id = $season_id;
    }

    public function fetch(): array {
        $params = array(
            'auth_key'       => Puck_Press_Tts_Api::TTS_AUTH_KEY,
            'auth_timestamp' => (string) time(),
            'body_md5'       => Puck_Press_Tts_Api::TTS_BODY_MD5,
            'league_id'      => Puck_Press_Tts_Api::TTS_LEAGUE_ID,
        );
        if ( ! empty( $this->season_id ) ) {
            $params['season_id'] = $this->season_id;
        }

        $url      = Puck_Press_Tts_Api::build_signed_url( 'get_standings', $params );
        $response = wp_remote_get( $url, array( 'timeout' => 20 ) );

        if ( is_wp_error( $response ) ) {
            $this->fetch_errors['get_standings'] = 'HTTP error: ' . $response->get_error_message();
            return array();
        }

        $code = wp_remote_retrieve_response_code( $response );
        $body = wp_remote_retrieve_body( $response );

        if ( $code !== 200 ) {
            $this->fetch_errors['get_standings'] = "HTTP {$code}: " . wp_strip_all_tags( $body );
            return array();
        }

        $decoded = json_decode( $body, true );
        if ( json_last_error() !== JSON_ERROR_NONE ) {
            $this->fetch_errors['get_standings'] = 'JSON parse error: ' . json_last_error_msg();
            return array();
        }

        return $this->find_conference( $decoded );
    }

    private function find_conference( array $data ): array {
        $leagues = $data['standings']['leagues'] ?? array();

        foreach ( $leagues as $league ) {
            foreach ( $league['levels'] ?? array() as $level ) {
                foreach ( $level['conferences'] ?? array() as $conf ) {
                    $teams = $conf['teams'] ?? array();
                    foreach ( $teams as $team ) {
                        if ( (string) $team['id'] === $this->team_id ) {
                            return array(
                                'division_name' => $conf['conf_name'] ?? '',
                                'standings'     => $this->normalize_teams( $teams ),
                            );
                        }
                    }
                }
            }
        }

        $this->fetch_errors['get_standings'] = "Team {$this->team_id} not found in any conference.";
        return array();
    }

    private function normalize_teams( array $teams ): array {
        $normalized = array();
        foreach ( $teams as $team ) {
            $normalized[] = array(
                'team_id'       => (string) $team['id'],
                'team_name'     => $team['team_name'] ?? $team['name'] ?? '',
                'team_nickname' => $team['club'] ?? '',
                'team_logo'     => $team['smlogo'] ?? '',
                'gp'            => (int) ( $team['games_played'] ?? 0 ),
                'w'             => (int) ( $team['total_wins'] ?? $team['wins'] ?? 0 ),
                'rw'            => (int) ( $team['wins'] ?? 0 ),
                'otw'           => (int) ( $team['otwins'] ?? 0 ),
                'sow'           => (int) ( $team['so_wins'] ?? 0 ),
                'l'             => (int) ( $team['losses'] ?? 0 ),
                'otl'           => (int) ( $team['otlosses'] ?? 0 ),
                'sol'           => (int) ( $team['so_losses'] ?? 0 ),
                't'             => (int) ( $team['ties'] ?? 0 ),
                'pts'           => (int) ( $team['pts'] ?? 0 ),
                'gf'            => (int) ( $team['goals_for'] ?? 0 ),
                'ga'            => (int) ( $team['goals_against'] ?? 0 ),
                'diff'          => (int) ( $team['plusminus'] ?? 0 ),
                'home_w'        => (int) ( $team['home_total_wins'] ?? $team['home_wins'] ?? 0 ),
                'home_l'        => (int) ( $team['home_losses'] ?? 0 ),
                'home_otl'      => (int) ( $team['home_otlosses'] ?? 0 ),
                'away_w'        => (int) ( $team['away_total_wins'] ?? $team['away_wins'] ?? 0 ),
                'away_l'        => (int) ( $team['away_losses'] ?? 0 ),
                'away_otl'      => (int) ( $team['away_otlosses'] ?? 0 ),
                'home_gf'       => null,
                'home_ga'       => null,
                'away_gf'       => null,
                'away_ga'       => null,
                'streak'        => $team['streak'] ?? '',
                'last_10'       => $team['past_10'] ?? '',
                'is_target'     => false,
            );
        }
        return $normalized;
    }
}
