<?php

/**
 * Class Puck_Press_Schedule_Process_Acha_Url
 *
 * Fetches and normalizes game schedule data from the ACHA (American Collegiate
 * Hockey Association) API via HockeyTech's statviewfeed endpoint.
 *
 * =========================================================================
 * ACHA API OVERVIEW
 * =========================================================================
 *
 * The ACHA schedule page URL (provided by the user) encodes the team ID and
 * season ID in its path, and the division ID in a query parameter. This class
 * parses those values from the URL and constructs a direct API call.
 *
 * Base API endpoint:
 *   https://lscluster.hockeytech.com/feed/index.php
 *   ?feed=statviewfeed&view=schedule
 *   &team={team_id}&season={season_id}&month=-1&location=homeaway
 *   &key=e6867b36742a0c9d&client_code=acha&site_id=2&league_id=1
 *   &division_id={division_id}&lang=en
 *
 * The response is a JSON array wrapped in parentheses (JSONP-style), so the
 * outer characters are stripped before decoding.
 *
 * Relevant fields in each game entry ($game['row']):
 *   - game_id           : Unique game identifier (used as our game_id)
 *   - home_team_city    : Home team city name
 *   - visiting_team_city: Visiting team city name
 *   - home_goal_count   : Home team goals
 *   - visiting_goal_count: Visiting team goals
 *   - game_status       : Either a time string ("7:30 PM") or a status ("Final")
 *   - date_with_day     : Partial date string ("Fri, Sep 13") — no year
 *   - venue_name        : Rink/venue name
 *
 * Relevant fields in $game['prop']:
 *   - home_team_city.teamLink    : Home team's league ID (used to detect home/away)
 *   - visiting_team_city.teamLink: Visiting team's league ID
 *
 * Logo/nickname data comes from a separate API call (retrieveLogoData) and is
 * keyed by team ID. It is fetched once per import, not once per game.
 *
 * =========================================================================
 * ADDING A NEW FIELD FROM THE ACHA API
 * =========================================================================
 *
 * 1. Add the field to $canonical_game_schema in class-puck-press-schedule-source-importer.php.
 * 2. Add the DB column to $table_schemas in class-puck-press-schedule-wpdb-utils.php.
 * 3. Map the value in normalize() below, reading from $row or $prop as needed.
 * 4. Return null from normalize() in the USPHL and CSV processors for this field.
 *
 * @package    Puck_Press
 * @subpackage Puck_Press/includes/schedule
 */
class Puck_Press_Schedule_Process_Acha_Url
{
    private $raw_acha_schedule_url;
    private $season_year = '';

    /**
     * Normalized game records ready for insertion into pp_game_schedule_raw.
     * Each element conforms to the canonical schema in Puck_Press_Schedule_Source_Importer.
     *
     * @var array
     */
    public $raw_schedule_data;

    private $team_id     = '';
    private $season_id   = '';
    private $division_id;

    /**
     * Logo and nickname data keyed by team ID, fetched once from the ACHA teams API.
     * Structure per entry: ['id' => '...', 'nickname' => '...', 'logo' => '...', ...]
     *
     * @var array
     */
    private $team_logo_data = [];

    /**
     * Division name prefixes that ACHA prepends to team names in their API responses.
     * These are stripped during normalization so templates display clean team names.
     *
     * e.g. "MD1 Boston University" → "Boston University"
     *
     * @var string[]
     */
    private static $acha_division_prefixes = [
        'MD1 ', 'MD2 ', 'MD3 ',
        'M1 ',  'M2 ',  'M3 ',
        'WD1 ', 'WD2 ', 'WD3 ',
        'W1 ',  'W2 ',  'W3 ',
    ];

    public function __construct( $raw_acha_schedule_url, $season_year = '' )
    {
        $this->team_logo_data        = $this->retrieveLogoData();
        $this->raw_acha_schedule_url = $raw_acha_schedule_url;
        $this->season_year           = $season_year;

        $jsonData               = $this->getRawDataFromAchaUrl();
        $this->raw_schedule_data = $this->extractHockeySchedule( $jsonData );
    }

    /**
     * Parses the team ID, season ID, and division ID from the ACHA schedule URL,
     * then fetches the raw schedule JSON from the HockeyTech API.
     *
     * @return array Decoded JSON response from the ACHA API.
     */
    public function getRawDataFromAchaUrl()
    {
        // The URL path contains: /schedule/{team_id}/{season_id}/all-months
        $team_and_schedule_id      = $this->_get_string_between( $this->raw_acha_schedule_url, 'schedule/', '/all-months' );
        $team_and_schedule_exploded = explode( '/', $team_and_schedule_id );
        $this->team_id             = $team_and_schedule_exploded[0];
        $this->season_id           = $team_and_schedule_exploded[1];

        // Division ID is a query parameter: ?division_id=X&...
        $this->division_id = $this->_get_string_between( $this->raw_acha_schedule_url, 'division_id=', '&' );

        $schedule_request_url = "https://lscluster.hockeytech.com/feed/index.php"
            . "?feed=statviewfeed&view=schedule"
            . "&team={$this->team_id}&season={$this->season_id}&month=-1&location=homeaway"
            . "&key=e6867b36742a0c9d&client_code=acha&site_id=2&league_id=1"
            . "&division_id={$this->division_id}&lang=en";

        // The ACHA API wraps its JSON in parentheses (JSONP). Strip them before decoding.
        $angularData = @file_get_contents( $schedule_request_url );
        $raw_data    = substr( $angularData, 1, -1 );
        $jsonData    = json_decode( $raw_data, true );

        if ( json_last_error() !== JSON_ERROR_NONE ) {
            return [ 'error' => 'Failed to parse JSON: ' . json_last_error_msg() ];
        }

        return $jsonData;
    }

    /**
     * Loops through raw API games and produces a normalized array for each one.
     *
     * Each game entry in the ACHA response has two keys:
     *   - 'row'  : flat field values (scores, date, status, team names)
     *   - 'prop' : metadata including teamLink IDs used to detect home vs away
     *
     * @param array $jsonData Decoded API response.
     * @return array          Array of normalized game arrays.
     */
    private function extractHockeySchedule( array $jsonData ): array
    {
        if ( ! isset( $jsonData[0]['sections'][0]['data'] ) ) {
            return [ 'error' => 'Expected data structure not found' ];
        }

        $schedule = [];

        foreach ( $jsonData[0]['sections'][0]['data'] as $raw_game ) {
            // normalize() maps this ACHA-specific game structure to the canonical schema.
            $schedule[] = $this->normalize( $raw_game );
        }

        return $schedule;
    }

    /**
     * Maps a single raw ACHA game entry to the canonical game schema.
     *
     * This is the ONLY place where ACHA field names are translated to canonical
     * field names. If the ACHA API changes a field name, or if you need to add a
     * new field, this is the method to update.
     *
     * @param array $raw_game One element from $jsonData[0]['sections'][0]['data'].
     *                        Contains 'row' (values) and 'prop' (metadata/links).
     * @return array          Canonical game array. See $canonical_game_schema in the importer.
     */
    private function normalize( array $raw_game ): array
    {
        $row  = $raw_game['row'];
        $prop = $raw_game['prop'];

        // Determine whether our tracked team is home or away.
        // $prop['home_team_city']['teamLink'] holds the league ID of the home team.
        // We compare it to $this->team_id (extracted from the source URL) to decide.
        $target_is_home = ( $this->team_id == $prop['home_team_city']['teamLink'] );

        if ( $target_is_home ) {
            $target_team_id    = $prop['home_team_city']['teamLink'];
            $opponent_team_id  = $prop['visiting_team_city']['teamLink'];
            $target_city_name  = $row['home_team_city'];
            $opponent_city_name = $row['visiting_team_city'];
            $target_score      = $row['home_goal_count'];
            $opponent_score    = $row['visiting_goal_count'];
            $home_or_away      = 'home';
        } else {
            $target_team_id    = $prop['visiting_team_city']['teamLink'];
            $opponent_team_id  = $prop['home_team_city']['teamLink'];
            $target_city_name  = $row['visiting_team_city'];
            $opponent_city_name = $row['home_team_city'];
            $target_score      = $row['visiting_goal_count'];
            $opponent_score    = $row['home_goal_count'];
            $home_or_away      = 'away';
        }

        // ACHA prepends division codes to team names (e.g. "MD1 Boston University").
        // Strip them so templates display clean names.
        $target_city_name   = $this->strip_acha_division_prefix( $target_city_name );
        $opponent_city_name = $this->strip_acha_division_prefix( $opponent_city_name );

        // Logo/nickname data is fetched separately from the ACHA teams API.
        // It is keyed by team ID. Null-coalesce in case a team ID is missing.
        $target_nickname   = $this->team_logo_data[ $target_team_id ]['nickname']   ?? null;
        $target_logo       = $this->team_logo_data[ $target_team_id ]['logo']        ?? null;
        $opponent_nickname = $this->team_logo_data[ $opponent_team_id ]['nickname']  ?? null;
        $opponent_logo     = $this->team_logo_data[ $opponent_team_id ]['logo']       ?? null;

        // game_status in ACHA is either a time string ("7:30 PM MST") or a result
        // string ("Final", "Final OT"). parse_game_status_and_time splits these apart.
        $parsed = $this->parse_game_status_and_time( $row['game_status'] );

        // date_with_day is a partial string like "Fri, Sep 13" — no year included.
        // We infer the year from the season string (e.g. "2024-2025") and which half
        // of the season the month falls in.
        $game_timestamp = $this->build_wp_datetime_from_year_and_date( $row['date_with_day'] );

        return [
            'game_id'                => $row['game_id'],
            'target_team_id'         => $target_team_id,
            'target_team_name'       => $target_city_name,
            'target_team_nickname'   => $target_nickname,
            'target_team_logo'       => $target_logo,
            'opponent_team_id'       => $opponent_team_id,
            'opponent_team_name'     => $opponent_city_name,
            'opponent_team_nickname' => $opponent_nickname,
            'opponent_team_logo'     => $opponent_logo,
            'target_score'           => $target_score,
            'opponent_score'         => $opponent_score,
            'game_status'            => $parsed['game_status'],  // null for upcoming games
            'game_date_day'          => $row['date_with_day'],   // "Fri, Sep 13"
            'game_time'              => $parsed['game_time'],     // null for completed games
            'game_timestamp'         => $game_timestamp,
            'home_or_away'           => $home_or_away,
            'venue'                  => $row['venue_name'],
        ];
    }

    /**
     * Strips ACHA division prefix codes from a team name.
     *
     * ACHA includes division codes like "MD1", "WD2", etc. at the start of team
     * names in their API responses. These are meaningless for display purposes.
     *
     * To add new prefixes, update self::$acha_division_prefixes above.
     *
     * @param string $name Raw team name from the ACHA API.
     * @return string      Team name with any leading division prefix removed.
     */
    private function strip_acha_division_prefix( string $name ): string
    {
        foreach ( self::$acha_division_prefixes as $prefix ) {
            if ( str_starts_with( $name, $prefix ) ) {
                return substr( $name, strlen( $prefix ) );
            }
        }
        return $name;
    }

    /**
     * Fetches logo URLs and nicknames for all ACHA teams from a separate API endpoint.
     *
     * This is called once per import (in the constructor) and the result is stored
     * in $this->team_logo_data, keyed by team ID, so normalize() can look up logo
     * and nickname for any team without making per-game HTTP calls.
     *
     * TODO: This fires on every refresh. Consider caching in a WP option or transient
     *       and refreshing via cron a few times per season instead.
     *
     * @return array Logo data keyed by team ID.
     */
    private function retrieveLogoData()
    {
        $team_logo_request_url = "https://lscluster.hockeytech.com/feed/index.php"
            . "?feed=statviewfeed&view=teamsForSeason&season=-1&division=-1"
            . "&key=e6867b36742a0c9d&client_code=acha&site_id=2";

        $paren_wrapped_jsonData = @file_get_contents( $team_logo_request_url );
        $raw_json = substr( $paren_wrapped_jsonData, 1, -1 );
        $jsonData = json_decode( $raw_json, true );

        if ( json_last_error() !== JSON_ERROR_NONE ) {
            return [ 'error' => 'Failed to parse JSON: ' . json_last_error_msg() ];
        }

        // Re-key the teams array by team ID for O(1) lookups in normalize().
        $by_id = [];
        foreach ( $jsonData['teams'] as $team ) {
            $by_id[ $team['id'] ] = $team;
        }
        return $by_id;
    }

    // =========================================================================
    // Date/time helpers specific to ACHA
    // =========================================================================

    /**
     * Combines the ACHA partial date string ("Fri, Sep 13") with an inferred year
     * and returns a MySQL datetime string for use as game_timestamp.
     *
     * ACHA's schedule API omits the year from game dates. We infer it from the
     * season string (e.g. "2024-2025"): months Sep–Dec belong to the first year,
     * months Jan–Aug belong to the second year.
     *
     * @param string $date_str Partial date from ACHA API (e.g. "Fri, Sep 13").
     * @return string|null     MySQL datetime (Y-m-d H:i:s) or null on failure.
     */
    private function build_wp_datetime_from_year_and_date( string $date_str ): ?string
    {
        $year           = $this->get_season_year_for_date( $date_str );
        $full_date_str  = $date_str . ' ' . $year;
        $timestamp      = strtotime( $full_date_str );

        if ( $timestamp === false ) {
            return null;
        }

        return date( 'Y-m-d H:i:s', $timestamp );
    }

    /**
     * Determines which calendar year a game date belongs to within a season range.
     *
     * Hockey seasons span two calendar years (e.g. 2024-2025). Games in Sep–Dec
     * belong to the first year; games in Jan–Aug belong to the second year.
     *
     * @param string $date_str Partial date string (e.g. "Fri, Sep 13").
     * @return string          Four-digit year (e.g. "2024" or "2025").
     */
    private function get_season_year_for_date( string $date_str ): string
    {
        list( $start_year, $end_year ) = explode( '-', $this->season_year );

        $date  = strtotime( $date_str . ' ' . $start_year );
        $month = (int) date( 'n', $date );

        // Sep (9) through Dec (12) → first half of season → start year
        // Jan (1) through Aug (8)  → second half of season → end year
        return $month >= 9 ? $start_year : $end_year;
    }

    /**
     * Splits an ACHA game_status string into separate game_status and game_time values.
     *
     * The ACHA API uses the same field for two different meanings:
     *   - A time string ("7:30 PM", "13:00") → upcoming game
     *   - A result string ("Final", "Postponed") → completed/cancelled game
     *
     * Returns an array with 'game_status' and 'game_time'. For upcoming games,
     * game_status is null and game_time is set. For completed games, the reverse.
     *
     * @param string $external_status Raw game_status from ACHA API.
     * @return array ['game_status' => string|null, 'game_time' => string|null]
     */
    private function parse_game_status_and_time( string $external_status ): array
    {
        $normalized  = trim( $external_status );

        // Matches 12-hour ("7:30 PM", "07:30PM") and 24-hour ("13:00") time formats,
        // optionally followed by a timezone abbreviation ("MST", "EST", etc.)
        $time_pattern = '/^((0?[1-9]|1[0-2]):[0-5][0-9]\s?[APap][Mm]|([01]?[0-9]|2[0-3]):[0-5][0-9])(\s?[A-Za-z]{2,4})?$/';

        if ( preg_match( $time_pattern, $normalized ) ) {
            return [
                'game_status' => null,
                'game_time'   => $normalized,
            ];
        }

        // Not a time string — treat it as a game status (e.g. "Final", "Postponed").
        return [
            'game_status' => $normalized,
            'game_time'   => null,
        ];
    }

    /**
     * Extracts a substring found between two delimiter strings.
     *
     * Used to parse team ID, season ID, and division ID out of the ACHA URL.
     *
     * @param string $string Full string to search within.
     * @param string $start  Left delimiter.
     * @param string $end    Right delimiter.
     * @return string        Substring between the delimiters, or '' if not found.
     */
    private function _get_string_between( string $string, string $start, string $end ): string
    {
        $string = ' ' . $string;
        $ini    = strpos( $string, $start );
        if ( $ini == 0 ) return '';
        $ini += strlen( $start );
        $len = strpos( $string, $end, $ini ) - $ini;
        return substr( $string, $ini, $len );
    }
}
