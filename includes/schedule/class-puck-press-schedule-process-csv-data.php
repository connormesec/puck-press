<?php

/**
 * Class Puck_Press_Schedule_Process_Csv_Data
 *
 * Parses and normalizes game schedule data from a CSV string stored in the
 * pp_schedule_data_sources table (csv_data column).
 *
 * =========================================================================
 * CSV FORMAT
 * =========================================================================
 *
 * The CSV must have a header row. Column names must match exactly the field
 * names listed in $expected_headers below. All columns are required in the
 * header, though individual values may be empty.
 *
 * Required columns:
 *   target_team_name      — City/school name of the tracked team
 *   target_team_nickname  — Mascot/nickname of the tracked team
 *   target_team_logo      — URL to tracked team logo
 *   target_score          — Goals scored by tracked team (empty if not played)
 *   opponent_team_name    — City/school name of the opponent
 *   opponent_team_nickname— Mascot/nickname of the opponent
 *   opponent_team_logo    — URL to opponent logo
 *   opponent_score        — Goals scored by opponent (empty if not played)
 *   game_time             — Local time string (e.g. "7:30 PM")
 *   game_timestamp        — Full datetime parseable by strtotime (e.g. "2025-09-13 19:30:00")
 *   home_or_away          — "home" or "away"
 *   game_status           — Status string (e.g. "Final", "Final OT", or "" for upcoming)
 *   venue                 — Rink/venue name
 *
 * Note: game_id is generated automatically from the game_timestamp, so it does
 * not need to be a column in the CSV.
 *
 * =========================================================================
 * ADDING A NEW FIELD TO THE CSV FORMAT
 * =========================================================================
 *
 * 1. Add the field to $canonical_game_schema in class-puck-press-schedule-source-importer.php.
 * 2. Add the DB column to $table_schemas in class-puck-press-schedule-wpdb-utils.php.
 * 3. Add the column name to $expected_headers in validate_csv_headers() below.
 * 4. Map $data['your_column_name'] in normalize() below.
 * 5. Return null from normalize() in the ACHA and USPHL processors for this field.
 *
 * @package    Puck_Press
 * @subpackage Puck_Press/includes/schedule
 */
class Puck_Press_Schedule_Process_Csv_Data {

	protected string $csv_string;
	protected string $source_name;
	protected string $source_type;

	public function __construct( string $csv_string, string $source_name, string $source_type = 'csv' ) {
		$this->csv_string  = $csv_string;
		$this->source_name = $source_name;
		$this->source_type = $source_type;
	}

	/**
	 * Parses the CSV string and returns an array of normalized game records.
	 *
	 * The first row is treated as the header. Each subsequent non-empty row is
	 * combined with the header to form an associative array, then passed through
	 * normalize() to produce a canonical game record.
	 *
	 * @return array Array of normalized game arrays conforming to the canonical schema.
	 */
	public function parse(): array {
		$lines  = array_map( 'trim', explode( "\n", $this->csv_string ) );
		$header = str_getcsv( array_shift( $lines ) );
		$games  = array();

		foreach ( $lines as $line ) {
			if ( empty( $line ) ) {
				continue;
			}

			// Combine the header row with the data row to get named fields.
			$row  = array_map( 'trim', str_getcsv( $line ) );
			$data = array_combine( $header, $row );

			$games[] = $this->normalize( $data );
		}

		return $games;
	}

	/**
	 * Maps a single parsed CSV row to the canonical game schema.
	 *
	 * This is the ONLY place where CSV column names are translated to canonical
	 * field names. If the expected CSV format changes, or if you add a new column,
	 * this is the method to update.
	 *
	 * Unlike ACHA and USPHL (which receive raw API data), the CSV columns are
	 * already named to match the canonical schema closely. The main work here is
	 * deriving game_id and game_date_day from game_timestamp, and running
	 * game_status through the shared normalizer.
	 *
	 * @param array $data Associative array of one CSV row (column name => value).
	 * @return array      Canonical game array. See $canonical_game_schema in the importer.
	 */
	private function normalize( array $data ): array {
		// game_timestamp is the full datetime string from the CSV (e.g. "2025-09-13 19:30:00").
		// We derive a Unix timestamp from it to generate game_id and game_date_day.
		$timestamp = strtotime( $data['game_timestamp'] );
		$date_time = date( 'Y-m-d H:i:s', $timestamp );  // normalized MySQL format
		$date_day  = date( 'Y-m-d', $timestamp );         // date-only, passed to format_game_date_day

		return array(
			// CSV has no league-provided game ID, so we generate a stable one from the timestamp.
			// This means if the same game appears in two CSV imports it gets the same ID,
			// which prevents duplicate rows (the UNIQUE KEY on game_id in the raw table handles this).
			'game_id'                => "csv_game_{$timestamp}",

			// CSV sources don't have league-assigned team IDs. Use 0 as a required placeholder;
			// the DB column is NOT NULL so null would cause an insert error.
			'target_team_id'         => 0,
			'target_team_name'       => $data['target_team_name'],
			'target_team_nickname'   => $data['target_team_nickname'] ?: null,
			'target_team_logo'       => $data['target_team_logo'] ?: null,

			'opponent_team_id'       => 0,
			'opponent_team_name'     => $data['opponent_team_name'],
			'opponent_team_nickname' => $data['opponent_team_nickname'] ?: null,
			'opponent_team_logo'     => $data['opponent_team_logo'] ?: null,

			// Empty string in CSV means the game hasn't been played — store as null.
			'target_score'           => $data['target_score'] !== '' ? $data['target_score'] : null,
			'opponent_score'         => $data['opponent_score'] !== '' ? $data['opponent_score'] : null,

			// Run through the shared status normalizer so "Final OT" → "FINAL OT", etc.
			'game_status'            => Puck_Press_Schedule_Source_Importer::format_game_status(
				$data['game_status'] ?? '',
				$data['game_time']
			),

			// game_date_day must match the "Fri, Sep 13" format expected by the templates.
			'game_date_day'          => Puck_Press_Schedule_Source_Importer::format_game_date_day( $date_day ),
			'game_time'              => $data['game_time'] ?: null,
			'game_timestamp'         => $date_time,

			'home_or_away'           => $data['home_or_away'] ?? '',
			'venue'                  => $data['venue'] ?? null,
		);
	}

	/**
	 * Validates that a CSV file's headers match the expected column list.
	 *
	 * Called before import to surface format errors to the user rather than
	 * silently producing malformed game records.
	 *
	 * @param string $file_path Absolute path to the CSV file.
	 * @return true|WP_Error    true on success, WP_Error with a message on failure.
	 */
	public static function validate_csv_headers( string $file_path ) {
		// These must exactly match the columns that normalize() reads from $data.
		// If you add a new column to normalize(), add it here too.
		$expected_headers = array(
			'target_team_name',
			'target_team_nickname',
			'target_team_logo',
			'target_score',
			'opponent_team_name',
			'opponent_team_nickname',
			'opponent_team_logo',
			'opponent_score',
			'game_time',
			'game_timestamp',
			'home_or_away',
			'game_status',
			'venue',
		);

		if ( ! file_exists( $file_path ) ) {
			return new WP_Error( 'file_missing', 'CSV file is missing or path is invalid.' );
		}

		$handle = fopen( $file_path, 'r' );
		if ( ! $handle ) {
			return new WP_Error( 'file_open_error', 'Could not open CSV file for reading.' );
		}

		$header_line = fgetcsv( $handle );
		fclose( $handle );

		if ( ! $header_line || ! is_array( $header_line ) ) {
			return new WP_Error( 'invalid_csv', 'Could not read headers from CSV file.' );
		}

		$headers            = array_map( 'trim', $header_line );
		$missing_headers    = array_diff( $expected_headers, $headers );
		$unexpected_headers = array_diff( $headers, $expected_headers );

		if ( ! empty( $missing_headers ) || ! empty( $unexpected_headers ) ) {
			$message = '';
			if ( ! empty( $missing_headers ) ) {
				$message .= 'Missing required headers: ' . implode( ', ', $missing_headers ) . '. ';
			}
			if ( ! empty( $unexpected_headers ) ) {
				$message .= 'Unexpected headers found: ' . implode( ', ', $unexpected_headers ) . '.';
			}
			return new WP_Error( 'csv_header_validation_failed', trim( $message ) );
		}

		return true;
	}
}
