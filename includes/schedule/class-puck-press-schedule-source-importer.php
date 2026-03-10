<?php

/**
 * Class Puck_Press_Schedule_Source_Importer
 *
 * Orchestrates fetching game data from all active sources and merging it into
 * the display table. This class owns the canonical game schema — the single
 * definition of what fields a game record must contain.
 *
 * =========================================================================
 * HOW TO ADD A NEW SOURCE TYPE (e.g. a new league API)
 * =========================================================================
 *
 * 1. Create a new processor class in includes/schedule/, e.g.:
 *       class-puck-press-schedule-process-{sourcename}-url.php
 *
 * 2. In that class, implement a public normalize(array $raw_game): array method.
 *    - $raw_game is whatever shape ONE game comes in from your API/source.
 *    - The returned array must use ONLY the keys defined in $canonical_game_schema below.
 *    - Return null (not an empty string) for any field your source doesn't provide.
 *    - See the existing ACHA or USPHL processors for a working example.
 *
 * 3. Your class's main method (e.g. extractHockeySchedule) should loop through
 *    the raw games and call $this->normalize($raw_game) for each one, collecting
 *    results into $this->raw_schedule_data.
 *
 * 4. Register the new source type in this file:
 *    a. Add a require_once in load_dependencies() for your new processor file.
 *    b. Add an elseif branch in populate_raw_schedule_table_from_sources() that
 *       instantiates your processor and reads its ->raw_schedule_data property.
 *
 * 5. If your source introduces a new game field not yet in $canonical_game_schema:
 *    a. Add the field here in $canonical_game_schema with 'required' and 'description'.
 *    b. Add the column to $table_schemas in class-puck-press-schedule-wpdb-utils.php
 *       (both the raw and for_display tables).
 *    c. Return the value from normalize() in your processor.
 *    d. Return null from normalize() in all other processors that don't have it.
 *
 * =========================================================================
 *
 * @package    Puck_Press
 * @subpackage Puck_Press/includes/schedule
 */
class Puck_Press_Schedule_Source_Importer
{

    private $schedule_db_utils;
    private $results = [];

    /**
     * CANONICAL GAME SCHEMA
     * =====================
     * This is the single source of truth for what fields a normalized game record
     * must contain. Every source processor MUST return an array with exactly these
     * keys from its normalize() method.
     *
     * 'required' => true  — the field must be present and non-null/non-empty.
     * 'required' => false — the field is optional; return null if unavailable.
     *
     * Note: 'source' and 'source_type' are NOT listed here. They are appended by
     * the importer after normalization because they come from the source record
     * in the DB, not from the game data itself.
     *
     * ADDING A NEW FIELD:
     * Add it here first, then add the DB column, then implement in each processor.
     */
    public static $canonical_game_schema = [
        // --- Identity ---
        'game_id' => [
            'required'    => true,
            'description' => 'Unique identifier for the game. Must be globally unique across all sources. '
                           . 'For league sources use the league\'s own game ID. For custom sources prefix '
                           . 'with the source type (e.g. "csv_game_1234567890").',
        ],

        // --- Tracked team (the team this plugin is set up for) ---
        'target_team_id' => [
            'required'    => true,
            'description' => 'League-assigned ID for the team this plugin is tracking. '
                           . 'Used to distinguish home vs away. Use "0" as a placeholder if not available.',
        ],
        'target_team_name' => [
            'required'    => true,
            'description' => 'City or school name of the tracked team (e.g. "Boston"). '
                           . 'Should NOT include the nickname/mascot.',
        ],
        'target_team_nickname' => [
            'required'    => false,
            'description' => 'Mascot or nickname of the tracked team (e.g. "Eagles"). '
                           . 'Return null if the source does not provide this separately.',
        ],
        'target_team_logo' => [
            'required'    => false,
            'description' => 'Full URL to the tracked team\'s logo image. Return null if unavailable.',
        ],

        // --- Opponent team ---
        'opponent_team_id' => [
            'required'    => true,
            'description' => 'League-assigned ID for the opponent. Use "0" as a placeholder if unavailable.',
        ],
        'opponent_team_name' => [
            'required'    => true,
            'description' => 'City or school name of the opponent (e.g. "New York").',
        ],
        'opponent_team_nickname' => [
            'required'    => false,
            'description' => 'Mascot or nickname of the opponent. Return null if unavailable.',
        ],
        'opponent_team_logo' => [
            'required'    => false,
            'description' => 'Full URL to the opponent\'s logo image. Return null if unavailable.',
        ],

        // --- Score & status (null for upcoming games) ---
        'target_score' => [
            'required'    => false,
            'description' => 'Goals scored by the tracked team. Return null if the game has not been played.',
        ],
        'opponent_score' => [
            'required'    => false,
            'description' => 'Goals scored by the opponent. Return null if the game has not been played.',
        ],
        'game_status' => [
            'required'    => false,
            'description' => 'Final status string for completed games (e.g. "FINAL", "FINAL OT", "FINAL SO"). '
                           . 'Return null for upcoming games — use game_time for those instead.',
        ],

        // --- Date & time ---
        'game_date_day' => [
            'required'    => true,
            'description' => 'Human-readable date formatted as "Fri, Sep 13". Used for display grouping. '
                           . 'Use Puck_Press_Schedule_Source_Importer::format_game_date_day() to produce this.',
        ],
        'game_time' => [
            'required'    => false,
            'description' => 'Scheduled tip-off time formatted as "7:30 PM". Return null if not available '
                           . 'or if the game status already conveys the result (completed games).',
        ],
        'game_timestamp' => [
            'required'    => false,
            'description' => 'MySQL datetime string (Y-m-d H:i:s) representing the game date+time. '
                           . 'Used for chronological sorting and upcoming-vs-past logic in templates. '
                           . 'Return null only if the date cannot be determined at all.',
        ],

        // --- Location & format ---
        'home_or_away' => [
            'required'    => true,
            'description' => 'Whether the tracked team is playing at home or away. Must be exactly "home" or "away".',
        ],
        'venue' => [
            'required'    => false,
            'description' => 'Name of the rink or venue where the game is played. Return null if unavailable.',
        ],
    ];

    public function __construct()
    {
        $this->load_dependencies();
        $this->schedule_db_utils = new Puck_Press_Schedule_Wpdb_Utils();
    }

    private function load_dependencies()
    {
        require_once plugin_dir_path( dirname( __FILE__ ) ) . 'schedule/class-puck-press-schedule-wpdb-utils.php';
        require_once plugin_dir_path( dirname( __FILE__ ) ) . 'class-puck-press-tts-api.php';
        require_once plugin_dir_path( dirname( __FILE__ ) ) . 'schedule/class-puck-press-schedule-process-acha-url.php';
        require_once plugin_dir_path( dirname( __FILE__ ) ) . 'schedule/class-puck-press-schedule-process-usphl-url.php';
        require_once plugin_dir_path( dirname( __FILE__ ) ) . 'schedule/class-puck-press-schedule-process-csv-data.php';
        // When adding a new source type, require_once its processor file here.
    }

    /**
     * Validates a single normalized game array against the canonical schema.
     *
     * Called after each processor's normalize() to catch missing required fields
     * before they reach the database. Returns an array of human-readable error
     * strings; an empty array means the game is valid.
     *
     * @param array  $game        The normalized game array from a processor's normalize().
     * @param string $source_name The source name, used in error messages for traceability.
     * @return array              List of validation error strings. Empty = valid.
     */
    private function validate_normalized_game( array $game, string $source_name ): array
    {
        $errors = [];

        foreach ( self::$canonical_game_schema as $field => $rules ) {
            $missing = ! array_key_exists( $field, $game )
                    || $game[ $field ] === ''
                    || $game[ $field ] === null;

            if ( $rules['required'] && $missing ) {
                $errors[] = "Source '{$source_name}': Required field '{$field}' is missing or empty. "
                          . $rules['description'];
            }
        }

        // Enforce the home_or_away enum value if it is present.
        if ( isset( $game['home_or_away'] ) && ! in_array( $game['home_or_away'], [ 'home', 'away' ], true ) ) {
            $errors[] = "Source '{$source_name}': 'home_or_away' must be \"home\" or \"away\", "
                      . "got \"{$game['home_or_away']}\".";
        }

        return $errors;
    }

    /**
     * Fetches game data from all active sources, validates each game against the
     * canonical schema, and inserts valid games into the raw schedule table.
     *
     * Each source type has its own processor class that handles fetching and
     * normalizing data. The processor's normalize() method is responsible for
     * mapping source-specific field names to the canonical schema defined above.
     *
     * @return array Result summary with success_count, error_count, errors, messages.
     */
    public function populate_raw_schedule_table_from_sources()
    {
        $this->results = [
            'success_count' => 0,
            'error_count'   => 0,
            'errors'        => [],
            'messages'      => [],
        ];

        $active_sources = $this->schedule_db_utils->get_active_schedule_sources() ?? [];

        if ( empty( $active_sources ) ) {
            $this->results['messages'][] = 'No active sources to import.';
            return $this->results;
        }

        foreach ( $active_sources as $source ) {
            try {

                // ----------------------------------------------------------------
                // ACHA source
                // Processor: class-puck-press-schedule-process-acha-url.php
                // ----------------------------------------------------------------
                if ( $source->type === 'achaGameScheduleUrl' ) {

                    $processor = new Puck_Press_Schedule_Process_Acha_Url(
                        $source->source_url_or_path,
                        $source->season
                    );

                    $games = $this->stamp_source( $processor->raw_schedule_data, $source );
                    $this->validate_and_insert( $games, $source->name );

                // ----------------------------------------------------------------
                // USPHL source
                // Processor: class-puck-press-schedule-process-usphl-url.php
                // ----------------------------------------------------------------
                } elseif ( $source->type === 'usphlGameScheduleUrl' ) {

                    $usphl_other = json_decode( $source->other_data ?? '{}', true );
                    $processor   = new Puck_Press_Schedule_Process_Usphl_Url(
                        $source->source_url_or_path,
                        $usphl_other['season_id'] ?? ''
                    );

                    $games = $this->stamp_source( $processor->raw_schedule_data, $source );
                    $this->validate_and_insert( $games, $source->name );

                // ----------------------------------------------------------------
                // CSV source
                // Processor: class-puck-press-schedule-process-csv-data.php
                // ----------------------------------------------------------------
                } elseif ( $source->type === 'csv' ) {

                    $processor = new Puck_Press_Schedule_Process_Csv_Data(
                        $source->csv_data ?? '',
                        $source->name,
                        $source->type
                    );

                    $games = $this->stamp_source( $processor->parse(), $source );
                    $this->validate_and_insert( $games, $source->name );

                // ----------------------------------------------------------------
                // Custom (manual) game — stored as a source record with game data
                // in other_data JSON. Kept for backwards compatibility; new manual
                // games should be added via the mods table (edit_action = 'insert').
                // ----------------------------------------------------------------
                } elseif ( $source->type === 'customGame' ) {

                    $other_data    = json_decode( $source->other_data, true );
                    $date_day      = $this->format_game_date_day( $other_data['gameDate'], $other_data['gameTime'] );
                    $game_timestamp = $this->get_game_timestamp( $other_data['gameDate'], $other_data['gameTime'] );

                    $game = [
                        'source'                 => $source->name,
                        'source_type'            => $source->type,
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
                        'game_status'            => $this->format_game_status( $other_data['game_status'], $other_data['gameTime'] ),
                        'game_date_day'          => $date_day,
                        'game_time'              => date( 'g:i a', strtotime( $other_data['gameTime'] ) ),
                        'game_timestamp'         => $game_timestamp,
                        'home_or_away'           => $other_data['home_or_away'],
                        'venue'                  => $other_data['venue'],
                    ];

                    $this->validate_and_insert( [ $game ], $source->name );

                // ----------------------------------------------------------------
                // Unknown source type — log and skip.
                // ----------------------------------------------------------------
                } else {

                    $this->results['error_count']++;
                    $this->results['errors'][] = [
                        'source'  => $source->name ?? 'Unknown',
                        'message' => 'Unsupported source type: ' . $source->type,
                    ];
                }

            } catch ( \Throwable $e ) {
                $this->results['error_count']++;
                $this->results['errors'][] = [
                    'source'  => $source->name ?? 'Unknown',
                    'message' => $e->getMessage(),
                    'file'    => $e->getFile(),
                    'line'    => $e->getLine(),
                    'trace'   => $e->getTraceAsString(),
                ];
            }
        }

        return $this->results;
    }

    /**
     * Appends 'source' and 'source_type' to every game in the array.
     *
     * These two fields come from the source record in the DB (not from the game
     * data itself), so they are added here rather than inside the processor.
     *
     * @param array  $games  Array of normalized game arrays from a processor.
     * @param object $source The source row from pp_schedule_data_sources.
     * @return array         The same games with source + source_type stamped on each.
     */
    private function stamp_source( array $games, object $source ): array
    {
        foreach ( $games as &$game ) {
            $game['source']      = $source->name;
            $game['source_type'] = $source->type;
        }
        return $games;
    }

    /**
     * Validates each game against the canonical schema, then inserts the valid ones.
     *
     * Invalid games are skipped and their errors are logged to $this->results.
     * Valid games are bulk-inserted into pp_game_schedule_raw.
     *
     * @param array  $games       Array of normalized (and source-stamped) game arrays.
     * @param string $source_name Source name for error messages.
     */
    private function validate_and_insert( array $games, string $source_name ): void
    {
        $valid_games = [];

        foreach ( $games as $game ) {
            $errors = $this->validate_normalized_game( $game, $source_name );

            if ( ! empty( $errors ) ) {
                // Log each validation error but don't halt the entire import.
                $this->results['error_count'] += count( $errors );
                foreach ( $errors as $error ) {
                    $this->results['errors'][] = [ 'source' => $source_name, 'message' => $error ];
                }
                continue;
            }

            $valid_games[] = $game;
        }

        if ( empty( $valid_games ) ) {
            return;
        }

        $inserted = $this->schedule_db_utils->insert_multiple_game_schedule_rows( $valid_games );

        $this->results['success_count']++;
        $this->results['messages'][] = "Imported source: {$source_name}";
        $this->results['messages'][] = $inserted;
    }

    /**
     * Merges the raw game table with the mods table and writes the result to the
     * display table. This is the read path for the frontend shortcode.
     *
     * Mod actions:
     *   'update' — overwrite specific fields on an existing raw game.
     *   'delete' — exclude a raw game from the display table entirely.
     *   'insert' — add a game that has no raw record (manual/custom games).
     *              external_id should be null for insert mods.
     */
    function apply_edits_and_save_to_display_table()
    {
        $table_a = 'pp_game_schedule_raw';
        $table_b = 'pp_game_schedule_mods';
        $table_c = 'pp_game_schedule_for_display';

        $originals = $this->schedule_db_utils->get_all_table_data( $table_a, 'ARRAY_A' ) ?? [];

        $edits = $this->schedule_db_utils->get_all_table_data( $table_b, 'ARRAY_A' );

        $edit_map   = []; // game_id => merged field overrides
        $delete_ids = []; // game_ids to exclude
        $insert_mods = []; // full game arrays to inject (no raw record)
        $results    = [];

        foreach ( $edits as $edit ) {
            $game_id = $edit['external_id'];
            $action  = strtolower( $edit['edit_action'] ?? 'update' );
            $edit_data = json_decode( $edit['edit_data'], true );

            if ( json_last_error() !== JSON_ERROR_NONE || ! is_array( $edit_data ) ) {
                continue;
            }

            if ( $action === 'delete' ) {
                $delete_ids[] = $game_id;
            } elseif ( $action === 'update' ) {
                $edit_map[ $game_id ] = $edit_data;
            } elseif ( $action === 'insert' ) {
                // Insert mods have no raw record — they are injected directly into
                // the display table. external_id is null; edit_data holds the full game.
                $insert_mods[] = $edit_data;
            }
        }

        // Merge originals with their update/delete mods.
        foreach ( $originals as $row ) {
            $game_id = $row['game_id'];

            if ( in_array( $game_id, $delete_ids, true ) ) {
                $this->schedule_db_utils->delete_row_by_game_id( $table_c, $game_id );
                $results[] = "Deleted game ID: $game_id";
                continue;
            }

            if ( isset( $edit_map[ $game_id ] ) ) {
                $row = array_merge( $row, $edit_map[ $game_id ] );
                $results[] = "Updated game ID: $game_id";
            }

            $this->schedule_db_utils->insert_or_replace_row( $table_c, $row );
        }

        // Inject manually-added games (insert mods) into the display table.
        foreach ( $insert_mods as $manual_game ) {
            $this->schedule_db_utils->insert_or_replace_row( $table_c, $manual_game );
            $results[] = 'Inserted manual game: ' . ( $manual_game['game_id'] ?? 'unknown' );
        }

        return $results;
    }

    public function get_results()
    {
        return $this->results;
    }

    // =========================================================================
    // Shared date/time helpers
    // These are public static so processor classes can call them when normalizing.
    // =========================================================================

    /**
     * Formats a date and optional time into the display string used for grouping.
     * Output example: "Fri, Sep 13"
     *
     * @param string      $gameDate A date string parseable by DateTime (e.g. "2025-09-13").
     * @param string|null $gameTime Optional time string appended before parsing.
     * @return string|null
     */
    public static function format_game_date_day( $gameDate, $gameTime = null )
    {
        $datetime_string = $gameDate;
        if ( ! empty( $gameTime ) ) {
            $datetime_string .= ' ' . $gameTime;
        }

        try {
            $date = new DateTime( $datetime_string );
        } catch ( Exception ) {
            return null;
        }

        return $date->format( 'D, M j' );
    }

    /**
     * Normalizes a raw game status string.
     *
     * If the status looks like a time string, it is returned as the display time
     * (the caller should store it in game_time, not game_status). Otherwise it is
     * normalized to uppercase canonical strings like "FINAL", "FINAL OT", etc.
     *
     * @param string      $gameStatus Raw status from the source API.
     * @param string|null $gameTime   Fallback time string if status is not a known final.
     * @return string
     */
    public static function format_game_status( $gameStatus, $gameTime = null )
    {
        $normalizedStatus = strtolower( str_replace( [ ' ', '_', '/' ], '-', $gameStatus ) );

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

    /**
     * Returns a MySQL datetime string (Y-m-d H:i:s) for a given date + optional time.
     * Used as the game_timestamp value for sorting.
     *
     * @param string      $gameDate Date string.
     * @param string|null $gameTime Optional time string.
     * @return string|null
     */
    public static function get_game_timestamp( $gameDate, $gameTime = null )
    {
        $datetime_string = $gameDate;
        if ( ! empty( $gameTime ) ) {
            $datetime_string .= ' ' . $gameTime;
        }

        try {
            $date = new DateTime( $datetime_string );
            return $date->format( 'Y-m-d H:i:s' );
        } catch ( Exception ) {
            return null;
        }
    }
}
