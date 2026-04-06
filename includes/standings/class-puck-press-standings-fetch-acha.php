<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Puck_Press_Standings_Fetch_Acha {

    private const API_BASE = 'https://lscluster.hockeytech.com/feed/index.php?feed=statviewfeed&key=e6867b36742a0c9d&client_code=acha&site_id=2';

    private string $acha_team_id;
    private string $season_id;
    private string $division_id;
    public array $fetch_errors = array();

    public function __construct( string $acha_team_id, string $season_id, string $division_id ) {
        $this->acha_team_id = $acha_team_id;
        $this->season_id    = $season_id;
        $this->division_id  = $division_id;
    }

    public function fetch(): array {
        $div_teams = $this->fetch_division_teams();
        if ( empty( $div_teams ) ) {
            return array();
        }

        $div_team_ids = array_keys( $div_teams );
        $games        = $this->fetch_division_schedule();
        if ( $games === null ) {
            return array();
        }

        $division_name = $this->extract_division_name( $div_teams );
        $standings     = $this->compute_standings( $games, $div_teams, $div_team_ids );

        return array(
            'division_name' => $division_name,
            'standings'     => $standings,
        );
    }

    private function fetch_division_teams(): array {
        $url = self::API_BASE . "&view=teamsForSeason&season={$this->season_id}&division={$this->division_id}";
        $raw = $this->fetch_jsonp( $url );
        if ( $raw === null ) {
            $this->fetch_errors['teamsForSeason'] = 'Failed to fetch division teams.';
            return array();
        }

        $teams_map = array();
        foreach ( $raw['teams'] ?? array() as $team ) {
            $tid = (string) $team['id'];
            if ( $tid === '-1' ) {
                continue;
            }
            $teams_map[ $tid ] = array(
                'name'     => preg_replace( '/^(?:MD[1-3]|WD[1-3]|M[1-3]|W[1-3])\s+/', '', $team['name'] ?? '' ),
                'nickname' => $team['nickname'] ?? '',
                'logo'     => $team['logo'] ?? '',
            );
        }

        return $teams_map;
    }

    private function fetch_division_schedule(): ?array {
        $url = self::API_BASE
            . '&view=schedule'
            . '&team=-1'
            . "&season={$this->season_id}"
            . '&month=-1'
            . '&location=homeaway'
            . "&division_id={$this->division_id}"
            . '&league_id=1'
            . '&lang=en';

        $raw = $this->fetch_jsonp( $url );
        if ( $raw === null ) {
            $this->fetch_errors['schedule'] = 'Failed to fetch division schedule.';
            return null;
        }

        $games = array();
        $sections = $raw[0]['sections'] ?? array();
        foreach ( $sections as $section ) {
            foreach ( $section['data'] ?? array() as $g ) {
                $row  = $g['row'] ?? array();
                $prop = $g['prop'] ?? array();
                $games[] = array(
                    'home_id'     => (string) ( $prop['home_team_city']['teamLink'] ?? '' ),
                    'away_id'     => (string) ( $prop['visiting_team_city']['teamLink'] ?? '' ),
                    'home_goals'  => $row['home_goal_count'] ?? '',
                    'away_goals'  => $row['visiting_goal_count'] ?? '',
                    'status'      => $row['game_status'] ?? '',
                );
            }
        }

        return $games;
    }

    private function compute_standings( array $games, array $div_teams, array $div_team_ids ): array {
        $div_set = array_flip( $div_team_ids );
        $stats   = array();

        foreach ( $div_team_ids as $tid ) {
            $stats[ $tid ] = array(
                'gp' => 0, 'w' => 0, 'l' => 0, 'otl' => 0, 't' => 0,
                'gf' => 0, 'ga' => 0,
                'home_w' => 0, 'home_l' => 0, 'home_otl' => 0,
                'away_w' => 0, 'away_l' => 0, 'away_otl' => 0,
                'home_gf' => 0, 'home_ga' => 0, 'away_gf' => 0, 'away_ga' => 0,
                'results' => array(),
            );
        }

        foreach ( $games as $game ) {
            $status = strtoupper( trim( $game['status'] ) );
            if ( strpos( $status, 'FINAL' ) !== 0 ) {
                continue;
            }
            if ( ! isset( $div_set[ $game['home_id'] ] ) || ! isset( $div_set[ $game['away_id'] ] ) ) {
                continue;
            }

            $hg      = (int) $game['home_goals'];
            $ag      = (int) $game['away_goals'];
            $is_otso = strpos( $status, 'OT' ) !== false || strpos( $status, 'SO' ) !== false;

            foreach ( array( 'home', 'away' ) as $side ) {
                $tid = $side === 'home' ? $game['home_id'] : $game['away_id'];
                $gf  = $side === 'home' ? $hg : $ag;
                $ga  = $side === 'home' ? $ag : $hg;

                $stats[ $tid ]['gp'] += 1;
                $stats[ $tid ]['gf'] += $gf;
                $stats[ $tid ]['ga'] += $ga;

                if ( $side === 'home' ) {
                    $stats[ $tid ]['home_gf'] += $gf;
                    $stats[ $tid ]['home_ga'] += $ga;
                } else {
                    $stats[ $tid ]['away_gf'] += $gf;
                    $stats[ $tid ]['away_ga'] += $ga;
                }

                if ( $gf > $ga ) {
                    $stats[ $tid ]['w'] += 1;
                    $stats[ $tid ]['results'][] = 'W';
                    if ( $side === 'home' ) {
                        $stats[ $tid ]['home_w'] += 1;
                    } else {
                        $stats[ $tid ]['away_w'] += 1;
                    }
                } elseif ( $gf < $ga ) {
                    if ( $is_otso ) {
                        $stats[ $tid ]['otl'] += 1;
                        $stats[ $tid ]['results'][] = 'OTL';
                        if ( $side === 'home' ) {
                            $stats[ $tid ]['home_otl'] += 1;
                        } else {
                            $stats[ $tid ]['away_otl'] += 1;
                        }
                    } else {
                        $stats[ $tid ]['l'] += 1;
                        $stats[ $tid ]['results'][] = 'L';
                        if ( $side === 'home' ) {
                            $stats[ $tid ]['home_l'] += 1;
                        } else {
                            $stats[ $tid ]['away_l'] += 1;
                        }
                    }
                } else {
                    $stats[ $tid ]['t'] += 1;
                    $stats[ $tid ]['results'][] = 'T';
                }
            }
        }

        $normalized = array();
        foreach ( $stats as $tid => $s ) {
            $pts  = $s['w'] * 2 + $s['otl'] + $s['t'];
            $diff = $s['gf'] - $s['ga'];

            $results = $s['results'];
            $streak  = '';
            if ( ! empty( $results ) ) {
                $last  = end( $results );
                $count = 0;
                for ( $i = count( $results ) - 1; $i >= 0; $i-- ) {
                    if ( $results[ $i ] === $last ) {
                        ++$count;
                    } else {
                        break;
                    }
                }
                $streak = $last . $count;
            }

            $last10     = array_slice( $results, -10 );
            $last10_str = count( $last10 ) > 0
                ? count( array_filter( $last10, fn( $r ) => $r === 'W' ) )
                    . '-' . count( array_filter( $last10, fn( $r ) => $r === 'L' ) )
                    . '-' . count( array_filter( $last10, fn( $r ) => $r === 'OTL' ) )
                    . '-' . count( array_filter( $last10, fn( $r ) => $r === 'T' ) )
                : '';

            $team_info    = $div_teams[ $tid ] ?? array();
            $normalized[] = array(
                'team_id'       => $tid,
                'team_name'     => $team_info['name'] ?? '',
                'team_nickname' => $team_info['nickname'] ?? '',
                'team_logo'     => $team_info['logo'] ?? '',
                'gp'            => $s['gp'],
                'w'             => $s['w'],
                'l'             => $s['l'],
                'otl'           => $s['otl'],
                't'             => $s['t'],
                'pts'           => $pts,
                'gf'            => $s['gf'],
                'ga'            => $s['ga'],
                'diff'          => $diff,
                'home_w'        => $s['home_w'],
                'home_l'        => $s['home_l'],
                'home_otl'      => $s['home_otl'],
                'away_w'        => $s['away_w'],
                'away_l'        => $s['away_l'],
                'away_otl'      => $s['away_otl'],
                'home_gf'       => $s['home_gf'],
                'home_ga'       => $s['home_ga'],
                'away_gf'       => $s['away_gf'],
                'away_ga'       => $s['away_ga'],
                'streak'        => $streak,
                'last_10'       => $last10_str,
                'is_target'     => false,
            );
        }

        usort( $normalized, function ( $a, $b ) {
            if ( $a['pts'] !== $b['pts'] ) {
                return $b['pts'] - $a['pts'];
            }
            if ( $a['w'] !== $b['w'] ) {
                return $b['w'] - $a['w'];
            }
            return $b['diff'] - $a['diff'];
        });

        return $normalized;
    }

    private function extract_division_name( array $div_teams ): string {
        require_once plugin_dir_path( __DIR__ ) . 'schedule/class-puck-press-acha-season-discoverer.php';
        $bootstrap  = Puck_Press_Acha_Season_Discoverer::get_bootstrap();
        $divisions  = $bootstrap['divisionsAll'] ?? $bootstrap['divisions'] ?? array();
        foreach ( $divisions as $div ) {
            if ( (string) ( $div['id'] ?? '' ) === $this->division_id ) {
                return preg_replace( '/^(?:MD[1-3]|WD[1-3])\s+/', '', $div['name'] ?? '' );
            }
        }
        $first = reset( $div_teams );
        return $first ? ( $first['name'] ?? '' ) : '';
    }

    private function fetch_jsonp( string $url ): ?array {
        $response = wp_remote_get( $url, array( 'timeout' => 20 ) );
        if ( is_wp_error( $response ) ) {
            return null;
        }

        $code = wp_remote_retrieve_response_code( $response );
        if ( $code !== 200 ) {
            return null;
        }

        $body = wp_remote_retrieve_body( $response );
        $body = trim( $body );
        if ( isset( $body[0] ) && $body[0] === '(' && substr( $body, -1 ) === ')' ) {
            $body = substr( $body, 1, -1 );
        }

        $decoded = json_decode( $body, true );
        if ( json_last_error() !== JSON_ERROR_NONE ) {
            return null;
        }

        return $decoded;
    }
}
