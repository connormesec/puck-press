<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Puck_Press_Team_Source_Importer {

    private Puck_Press_Teams_Wpdb_Utils $teams_utils;
    private Puck_Press_Schedule_Materializer $materializer;
    private int $team_id;
    private array $results = array();

    public function __construct( int $team_id ) {
        $this->load_dependencies();
        $this->teams_utils  = new Puck_Press_Teams_Wpdb_Utils();
        $this->materializer = new Puck_Press_Schedule_Materializer();
        $this->team_id      = $team_id;
    }

    private function load_dependencies(): void {
        require_once plugin_dir_path( __FILE__ ) . '../teams/class-puck-press-teams-wpdb-utils.php';
        require_once plugin_dir_path( __FILE__ ) . 'class-puck-press-schedules-wpdb-utils.php';
        require_once plugin_dir_path( __FILE__ ) . 'class-puck-press-schedule-materializer.php';
        require_once plugin_dir_path( __FILE__ ) . '../class-puck-press-tts-api.php';
        require_once plugin_dir_path( __FILE__ ) . 'class-puck-press-schedule-process-acha-url.php';
        require_once plugin_dir_path( __FILE__ ) . 'class-puck-press-schedule-process-usphl-url.php';
        require_once plugin_dir_path( __FILE__ ) . 'class-puck-press-schedule-process-csv-data.php';
    }

    public function import_all_sources(): array {
        $this->results = array(
            'success_count' => 0,
            'error_count'   => 0,
            'errors'        => array(),
            'messages'      => array(),
        );

        $active_sources = $this->teams_utils->get_active_team_sources( $this->team_id );

        $this->teams_utils->delete_raw_for_team( $this->team_id );

        if ( empty( $active_sources ) ) {
            $this->results['messages'][] = 'No active sources to import.';
            return $this->results;
        }

        foreach ( $active_sources as $source ) {
            $source = (object) $source;
            try {
                if ( $source->type === 'achaGameScheduleUrl' ) {
                    $processor = new Puck_Press_Schedule_Process_Acha_Url(
                        $source->source_url_or_path,
                        $source->season
                    );
                    $games = $this->stamp_source( $processor->raw_schedule_data, $source );
                    $this->validate_and_insert( $games, $source->name );

                } elseif ( $source->type === 'usphlGameScheduleUrl' ) {
                    $usphl_other = json_decode( $source->other_data ?? '{}', true );
                    $processor   = new Puck_Press_Schedule_Process_Usphl_Url(
                        $source->source_url_or_path,
                        $usphl_other['season_id'] ?? ''
                    );
                    $games = $this->stamp_source( $processor->raw_schedule_data, $source );
                    $this->validate_and_insert( $games, $source->name );

                } elseif ( $source->type === 'csv' ) {
                    $processor = new Puck_Press_Schedule_Process_Csv_Data(
                        $source->csv_data ?? '',
                        $source->name,
                        $source->type
                    );
                    $games = $this->stamp_source( $processor->parse(), $source );
                    $this->validate_and_insert( $games, $source->name );

                } elseif ( $source->type === 'customGame' ) {
                    $other_data     = json_decode( $source->other_data, true );
                    $date_day       = self::format_game_date_day( $other_data['gameDate'], $other_data['gameTime'] );
                    $game_timestamp = self::get_game_timestamp( $other_data['gameDate'], $other_data['gameTime'] );

                    $game = array(
                        'source'                 => $source->name,
                        'source_type'            => $source->type,
                        'team_id'                => $this->team_id,
                        'game_id'                => "custom_game_{$game_timestamp}_{$source->name}",
                        'target_team_name'       => $other_data['target']['name'],
                        'target_team_nickname'   => $other_data['target']['nickname'],
                        'target_team_logo'       => $other_data['target']['logo'],
                        'target_team_id'         => $other_data['target']['id'],
                        'opponent_team_id'       => $other_data['opponent']['id'],
                        'opponent_team_name'     => $other_data['opponent']['name'],
                        'opponent_team_nickname' => $other_data['opponent']['nickname'],
                        'opponent_team_logo'     => $other_data['opponent']['logo'],
                        'target_score'           => $other_data['target_score'],
                        'opponent_score'         => $other_data['opponent_score'],
                        'game_status'            => self::format_game_status( $other_data['game_status'], $other_data['gameTime'] ),
                        'game_date_day'          => $date_day,
                        'game_time'              => date( 'g:i a', strtotime( $other_data['gameTime'] ) ),
                        'game_timestamp'         => $game_timestamp,
                        'home_or_away'           => $other_data['home_or_away'],
                        'venue'                  => $other_data['venue'],
                    );
                    $this->validate_and_insert( array( $game ), $source->name );

                } else {
                    ++$this->results['error_count'];
                    $this->results['errors'][] = array(
                        'source'  => $source->name ?? 'Unknown',
                        'message' => 'Unsupported source type: ' . $source->type,
                    );
                }
            } catch ( \Throwable $e ) {
                ++$this->results['error_count'];
                $this->results['errors'][] = array(
                    'source'  => $source->name ?? 'Unknown',
                    'message' => $e->getMessage(),
                    'file'    => $e->getFile(),
                    'line'    => $e->getLine(),
                    'trace'   => $e->getTraceAsString(),
                );
            }
        }

        return $this->results;
    }

    public function rebuild_display_and_cascade(): void {
        $this->rebuild_display_from_mods();

        $schedule_ids = $this->materializer->get_schedule_ids_for_team( $this->team_id );
        foreach ( array_unique( $schedule_ids ) as $sid ) {
            $this->materializer->materialize_schedule( (int) $sid );
        }
    }

    public function rebuild_display_from_mods(): void {
        global $wpdb;

        $team_id = $this->team_id;

        $originals = $wpdb->get_results(
            $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}pp_team_games_raw WHERE team_id = %d", $team_id ),
            ARRAY_A
        ) ?? array();

        $edits = $wpdb->get_results(
            $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}pp_team_game_mods WHERE team_id = %d", $team_id ),
            ARRAY_A
        ) ?? array();

        $this->teams_utils->delete_display_for_team( $team_id );

        $edit_map    = array();
        $delete_ids  = array();
        $insert_mods = array();

        foreach ( $edits as $edit ) {
            $game_id   = $edit['external_id'];
            $action    = strtolower( $edit['edit_action'] ?? 'update' );
            $edit_data = json_decode( $edit['edit_data'], true );

            if ( json_last_error() !== JSON_ERROR_NONE || ! is_array( $edit_data ) ) {
                continue;
            }

            if ( $action === 'delete' ) {
                $delete_ids[] = $game_id;
            } elseif ( $action === 'update' ) {
                $edit_map[ $game_id ] = $edit_data;
            } elseif ( $action === 'insert' ) {
                $insert_mods[] = $edit_data;
            }
        }

        foreach ( $originals as $row ) {
            $game_id = $row['game_id'];
            if ( in_array( $game_id, $delete_ids, true ) ) {
                continue;
            }
            if ( isset( $edit_map[ $game_id ] ) ) {
                $row = array_merge( $row, $edit_map[ $game_id ] );
            }
            $row['team_id'] = $team_id;
            $this->teams_utils->insert_or_replace_row( 'pp_team_games_display', $row );
        }

        foreach ( $insert_mods as $manual_game ) {
            $manual_game['team_id'] = $team_id;
            $this->teams_utils->insert_or_replace_row( 'pp_team_games_display', $manual_game );
        }
    }

    public function rebuild_team_and_cascade(): array {
        $import_results = $this->import_all_sources();

        $has_active   = ! in_array( 'No active sources to import.', $import_results['messages'] ?? array(), true );
        $import_ok    = ( $import_results['success_count'] ?? 0 ) > 0 || ! $has_active;

        if ( $import_ok ) {
            $this->rebuild_display_from_mods();
        }

        $schedule_ids = $this->materializer->get_schedule_ids_for_team( $this->team_id );
        foreach ( array_unique( $schedule_ids ) as $sid ) {
            $this->materializer->materialize_schedule( (int) $sid );
        }

        return $import_results;
    }

    public function get_results(): array {
        return $this->results;
    }

    private function stamp_source( array $games, object $source ): array {
        foreach ( $games as &$game ) {
            $game['source']      = $source->name;
            $game['source_type'] = $source->type;
            $game['team_id']     = $this->team_id;
        }
        return $games;
    }

    private function validate_and_insert( array $games, string $source_name ): void {
        $valid_games = array();

        foreach ( $games as $game ) {
            $errors = array();
            foreach ( self::$canonical_game_schema as $field => $rules ) {
                $missing = ! array_key_exists( $field, $game )
                        || $game[ $field ] === ''
                        || $game[ $field ] === null;
                if ( $rules['required'] && $missing ) {
                    $errors[] = "Source '{$source_name}': Required field '{$field}' is missing or empty.";
                }
            }
            if ( isset( $game['home_or_away'] ) && ! in_array( $game['home_or_away'], array( 'home', 'away' ), true ) ) {
                $errors[] = "Source '{$source_name}': 'home_or_away' must be \"home\" or \"away\".";
            }

            if ( ! empty( $errors ) ) {
                $this->results['error_count'] += count( $errors );
                foreach ( $errors as $error ) {
                    $this->results['errors'][] = array( 'source' => $source_name, 'message' => $error );
                }
                continue;
            }

            $valid_games[] = $game;
        }

        if ( empty( $valid_games ) ) {
            return;
        }

        $inserted = $this->teams_utils->insert_multiple_team_game_raw_rows( $this->team_id, $valid_games );
        ++$this->results['success_count'];
        $this->results['messages'][] = "Imported source: {$source_name}";
        $this->results['messages'][] = $inserted;
    }

    public static $canonical_game_schema = array(
        'game_id'                => array( 'required' => true,  'description' => 'Unique identifier for the game.' ),
        'target_team_id'         => array( 'required' => true,  'description' => 'League-assigned ID for the tracked team.' ),
        'target_team_name'       => array( 'required' => true,  'description' => 'City or school name of the tracked team.' ),
        'target_team_nickname'   => array( 'required' => false, 'description' => 'Mascot or nickname of the tracked team.' ),
        'target_team_logo'       => array( 'required' => false, 'description' => 'Full URL to the tracked team logo.' ),
        'opponent_team_id'       => array( 'required' => true,  'description' => 'League-assigned ID for the opponent.' ),
        'opponent_team_name'     => array( 'required' => true,  'description' => 'City or school name of the opponent.' ),
        'opponent_team_nickname' => array( 'required' => false, 'description' => 'Mascot or nickname of the opponent.' ),
        'opponent_team_logo'     => array( 'required' => false, 'description' => 'Full URL to the opponent logo.' ),
        'target_score'           => array( 'required' => false, 'description' => 'Goals scored by the tracked team.' ),
        'opponent_score'         => array( 'required' => false, 'description' => 'Goals scored by the opponent.' ),
        'game_status'            => array( 'required' => false, 'description' => 'Final status string for completed games.' ),
        'game_date_day'          => array( 'required' => true,  'description' => 'Human-readable date formatted as "Fri, Sep 13".' ),
        'game_time'              => array( 'required' => false, 'description' => 'Scheduled time formatted as "7:30 PM".' ),
        'game_timestamp'         => array( 'required' => false, 'description' => 'MySQL datetime string (Y-m-d H:i:s).' ),
        'home_or_away'           => array( 'required' => true,  'description' => 'Must be exactly "home" or "away".' ),
        'venue'                  => array( 'required' => false, 'description' => 'Name of the rink or venue.' ),
    );

    public static function format_game_date_day( string $gameDate, ?string $gameTime = null ): ?string {
        $datetime_string = $gameDate;
        if ( ! empty( $gameTime ) ) {
            $datetime_string .= ' ' . $gameTime;
        }
        try {
            $date = new DateTime( $datetime_string );
        } catch ( Exception $e ) {
            return null;
        }
        return $date->format( 'D, M j' );
    }

    public static function format_game_status( string $gameStatus, ?string $gameTime = null ): string {
        $normalizedStatus = strtolower( str_replace( array( ' ', '_', '/' ), '-', $gameStatus ) );
        switch ( $normalizedStatus ) {
            case 'final':
                return 'FINAL';
            case 'final-ot':
                return 'FINAL OT';
            case 'final-so':
                return 'FINAL SO';
            default:
                if ( $gameTime ) {
                    $cleanTime  = preg_replace( '/\s+(?!AM|PM)([A-Z]{2,4})$/i', '', $gameTime );
                    $parsedTime = strtotime( $cleanTime );
                    if ( $parsedTime ) {
                        return date( 'g:i A', $parsedTime );
                    }
                    return $gameTime;
                }
                return $gameStatus;
        }
    }

    public static function get_game_timestamp( string $gameDate, ?string $gameTime = null ): ?string {
        $datetime_string = $gameDate;
        if ( ! empty( $gameTime ) ) {
            $datetime_string .= ' ' . $gameTime;
        }
        try {
            $date = new DateTime( $datetime_string );
            return $date->format( 'Y-m-d H:i:s' );
        } catch ( Exception $e ) {
            return null;
        }
    }

}
