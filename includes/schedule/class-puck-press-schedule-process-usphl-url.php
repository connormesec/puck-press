<?php

/**
 * Class Puck_Press_Schedule_Process_Usphl_Url
 *
 * Fetches and normalizes game schedule data from the USPHL (United States
 * Premier Hockey League) JSON API.
 *
 * =========================================================================
 * USPHL API OVERVIEW
 * =========================================================================
 *
 * The user provides a direct URL to the USPHL schedule API endpoint. Unlike ACHA,
 * no URL parsing is needed — the URL is used as-is. The response is plain JSON
 * (not JSONP-wrapped).
 *
 * The response structure:
 *   {
 *     "url": "...?season=65&team=2298",   ← team ID is extracted from here
 *     "schedule": {
 *       "games": [ { ...game fields... }, ... ]
 *     }
 *   }
 *
 * Relevant fields per game object:
 *   - game_id        : Unique game identifier
 *   - home_team      : Full home team name (e.g. "Boston Jr. Eagles")
 *   - away_team      : Full away team name
 *   - home_id        : Home team's league ID (used to detect home/away)
 *   - away_id        : Away team's league ID
 *   - home_goals     : Home team goals scored
 *   - away_goals     : Away team goals scored
 *   - home_smlogo    : URL to the home team's small logo image
 *   - away_smlogo    : URL to the away team's small logo image
 *   - result_string  : Game status/result string (e.g. "W 4-2", "L 1-3", "")
 *   - date           : ISO date string (e.g. "2025-09-13")
 *   - time           : Local time string (e.g. "19:30:00")
 *   - gmt_time       : GMT datetime with microseconds (e.g. "2025-09-14 00:30:00.000000")
 *   - timezn         : PHP timezone identifier (e.g. "America/New_York")
 *   - location       : Venue/rink name
 *
 * Unlike ACHA, USPHL provides team names as a single "City Nickname" string rather
 * than separate city and nickname fields. The split_team_name() helper handles this.
 *
 * =========================================================================
 * ADDING A NEW FIELD FROM THE USPHL API
 * =========================================================================
 *
 * 1. Add the field to $canonical_game_schema in class-puck-press-schedule-source-importer.php.
 * 2. Add the DB column to $table_schemas in class-puck-press-schedule-wpdb-utils.php.
 * 3. Map the value in normalize() below, reading from the $game array.
 * 4. Return null from normalize() in the ACHA and CSV processors for this field.
 *
 * @package    Puck_Press
 * @subpackage Puck_Press/includes/schedule
 */
class Puck_Press_Schedule_Process_Usphl_Url
{
    private $raw_usphl_schedule_url;

    /**
     * Normalized game records ready for insertion into pp_game_schedule_raw.
     * Each element conforms to the canonical schema in Puck_Press_Schedule_Source_Importer.
     *
     * @var array
     */
    public $raw_schedule_data;

    /**
     * The league-assigned ID of the tracked team, extracted from the API response URL.
     * Used in normalize() to distinguish home vs away.
     *
     * @var string
     */
    private $team_id = '';

    public function __construct( $raw_usphl_schedule_url )
    {
        $this->raw_usphl_schedule_url = $raw_usphl_schedule_url;
        $jsonData                     = $this->getJsonUsphlUrl();
        $this->raw_schedule_data      = $this->extractHockeySchedule( $jsonData );
    }

    /**
     * Fetches and decodes the USPHL schedule JSON from the provided URL.
     *
     * @return array Decoded JSON response, or an array with an 'error' key on failure.
     */
    public function getJsonUsphlUrl()
    {
        $raw_data = @file_get_contents( $this->raw_usphl_schedule_url );
        $jsonData = json_decode( $raw_data, true );

        if ( json_last_error() !== JSON_ERROR_NONE ) {
            return [ 'error' => 'Failed to parse JSON: ' . json_last_error_msg() ];
        }

        return $jsonData;
    }

    /**
     * Loops through raw API games and produces a normalized array for each one.
     *
     * The team ID is extracted from the API's 'url' field (the URL it reports
     * that was used to generate the response), then used in normalize() to
     * determine home vs away for each game.
     *
     * @param array $jsonData Decoded USPHL API response.
     * @return array          Array of normalized game arrays.
     */
    private function extractHockeySchedule( array $jsonData ): array
    {
        if ( ! isset( $jsonData['schedule']['games'] ) ) {
            return [ 'error' => 'Expected data structure not found' ];
        }

        // The team ID isn't returned as a top-level field — USPHL embeds it in the
        // response's 'url' field as a query parameter (?team=XXXX).
        $parts = parse_url( $jsonData['url'] );
        parse_str( $parts['query'], $query );
        $this->team_id = $query['team'] ?? null;

        if ( $this->team_id === null ) {
            return [ 'error' => 'Team ID not found in USPHL response URL' ];
        }

        $schedule = [];

        foreach ( $jsonData['schedule']['games'] as $raw_game ) {
            // normalize() maps this USPHL-specific game structure to the canonical schema.
            $schedule[] = $this->normalize( $raw_game );
        }

        return $schedule;
    }

    /**
     * Maps a single raw USPHL game entry to the canonical game schema.
     *
     * This is the ONLY place where USPHL field names are translated to canonical
     * field names. If the USPHL API changes a field name, or if you need to add a
     * new field, this is the method to update.
     *
     * @param array $game One element from $jsonData['schedule']['games'].
     * @return array      Canonical game array. See $canonical_game_schema in the importer.
     */
    private function normalize( array $game ): array
    {
        // Determine whether our tracked team is home or away.
        // home_id / away_id hold the league-assigned team IDs.
        $target_is_home = ( $this->team_id == $game['home_id'] );

        if ( $target_is_home ) {
            $target_team_id    = $game['home_id'];
            $opponent_team_id  = $game['away_id'];
            $target_full_name  = $game['home_team'];
            $opponent_full_name = $game['away_team'];
            $target_logo       = $game['home_smlogo'];
            $opponent_logo     = $game['away_smlogo'];
            $target_score      = $game['home_goals'];
            $opponent_score    = $game['away_goals'];
            $home_or_away      = 'home';
        } else {
            $target_team_id    = $game['away_id'];
            $opponent_team_id  = $game['home_id'];
            $target_full_name  = $game['away_team'];
            $opponent_full_name = $game['home_team'];
            $target_logo       = $game['away_smlogo'];
            $opponent_logo     = $game['home_smlogo'];
            $target_score      = $game['away_goals'];
            $opponent_score    = $game['home_goals'];
            $home_or_away      = 'away';
        }

        // USPHL provides a single combined name string (e.g. "Boston Jr. Eagles").
        // split_team_name() separates it into city ("Boston Jr.") and nickname ("Eagles").
        $target_split   = $this->split_team_name( $target_full_name );
        $opponent_split = $this->split_team_name( $opponent_full_name );

        // gmt_time + timezn together give us an exact moment in time. Convert to the
        // team's local timezone so game_timestamp reflects the local game time.
        $game_timestamp = $this->format_gmt_to_timezone( $game['gmt_time'], $game['timezn'] );

        // result_string is the USPHL result (e.g. "W 4-2", "L 1-3", or "" for upcoming).
        $game_status = $game['result_string'] ?: null;

        // Null-guard: upcoming games have no result_string. If status is null and the
        // score is 0, treat it as null (no score recorded yet). A real 0–0 final
        // always has a non-null game_status, so this is safe.
        $target_score   = ( ! empty( $game_status ) || $target_score   != 0 ) ? $target_score   : null;
        $opponent_score = ( ! empty( $game_status ) || $opponent_score != 0 ) ? $opponent_score : null;

        return [
            'game_id'                => $game['game_id'],
            'target_team_id'         => $target_team_id,
            'target_team_name'       => $target_split['city'],
            'target_team_nickname'   => $target_split['name'],
            'target_team_logo'       => $target_logo,
            'opponent_team_id'       => $opponent_team_id,
            'opponent_team_name'     => $opponent_split['city'],
            'opponent_team_nickname' => $opponent_split['name'],
            'opponent_team_logo'     => $opponent_logo,
            'target_score'           => $target_score,
            'opponent_score'         => $opponent_score,
            'game_status'            => $game_status,
            // 'date' is ISO (e.g. "2025-09-13"), 'time' is local (e.g. "19:30:00").
            'game_date_day'          => date( 'D, M j', strtotime( $game['date'] ) ),
            'game_time'              => date( 'g:ia', strtotime( $game['time'] ) ),
            'game_timestamp'         => $game_timestamp,
            'home_or_away'           => $home_or_away,
            // USPHL uses 'location' for the venue name.
            'venue'                  => $game['location'],
        ];
    }

    // =========================================================================
    // Helpers specific to USPHL
    // =========================================================================

    /**
     * Splits a combined USPHL team name into city and nickname components.
     *
     * USPHL provides a single string like "Boston Jr. Eagles" rather than
     * separate city and nickname fields. This heuristic splits on the last word
     * as the nickname, with special handling for "Jr." and "Junior" team names
     * where the last meaningful word is part of the city name.
     *
     * Examples:
     *   "Boston Eagles"        → city: "Boston",     name: "Eagles"
     *   "Boston Jr. Eagles"    → city: "Boston Jr.", name: "Eagles"
     *   "New York Junior Hawks"→ city: "New York",   name: "Junior Hawks"
     *
     * @param string $team Full team name from USPHL API.
     * @return array       ['city' => string, 'name' => string]
     */
    private function split_team_name( string $team ): array
    {
        // Special case: "Jr." or "Junior" signals a youth team where the descriptor
        // is part of the qualifier, not the nickname. Capture everything after it as nickname.
        if ( preg_match( '/^(.+?)\s+(Jr(?:\.|)|Junior\s+\w+.*)$/i', $team, $matches ) ) {
            return [ 'city' => $matches[1], 'name' => $matches[2] ];
        }

        // Default: everything up to the last space is city, last word is nickname.
        if ( preg_match( '/^(.+)\s+(\S+)$/', $team, $matches ) ) {
            return [ 'city' => $matches[1], 'name' => $matches[2] ];
        }

        return [ 'city' => $team, 'name' => '' ];
    }

    /**
     * Converts a USPHL GMT datetime string to a local MySQL datetime string.
     *
     * USPHL provides game times in GMT with microseconds (e.g. "2025-09-14 00:30:00.000000")
     * alongside a PHP timezone identifier (e.g. "America/New_York"). This converts the
     * GMT time to the local timezone so game_timestamp reflects when the game actually
     * starts for the home team.
     *
     * @param string $gmt_time  GMT datetime from USPHL API (with optional microseconds).
     * @param string $timezone  PHP timezone identifier (e.g. "America/New_York").
     * @return string           MySQL datetime string (Y-m-d H:i:s) in local time.
     */
    private function format_gmt_to_timezone( string $gmt_time, string $timezone ): string
    {
        // Try parsing with microseconds first (USPHL includes them on most responses).
        $date = DateTime::createFromFormat(
            'Y-m-d H:i:s.u',
            $gmt_time,
            new DateTimeZone( 'GMT' )
        );

        // Fall back to parsing without microseconds if the above fails.
        if ( ! $date ) {
            $date = new DateTime( $gmt_time, new DateTimeZone( 'GMT' ) );
        }

        $date->setTimezone( new DateTimeZone( $timezone ) );

        return $date->format( 'Y-m-d H:i:s' );
    }
}
