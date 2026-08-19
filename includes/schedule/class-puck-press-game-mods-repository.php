<?php
/**
 * Canonical write path for game edits.
 *
 * Every edit path (single-game modal, bulk edit, per-field revert) must go
 * through this class so normalization, diff-vs-source, and empty/NULL
 * semantics live in exactly one place.
 *
 * Storage model recap:
 * - Sourced games: raw rows in pp_team_games_raw; user overrides are an
 *   'update' mod whose edit_data JSON is array_merge()d over the raw row on
 *   rebuild. A JSON null value is a tombstone: "user cleared this field".
 * - Manual games: an 'insert' mod whose edit_data IS the row. No override
 *   concept — edits merge straight into the mod.
 */
class Puck_Press_Game_Mods_Repository {

	private int $team_id;
	private string $mods_table;

	/**
	 * Fields with no pp_team_games_raw column. Their source baseline is
	 * always ''; an empty submitted value means "no override" (remove key),
	 * never a tombstone.
	 */
	private const DISPLAY_ONLY_FIELDS = array( 'promo_header', 'promo_text', 'promo_img_url', 'promo_ticket_link', 'post_link' );

	private const URL_FIELDS = array( 'promo_img_url', 'promo_ticket_link', 'post_link' );

	private const STATUS_MAP = array(
		'final'    => 'FINAL',
		'final-ot' => 'FINAL OT',
		'final-so' => 'FINAL SO',
		'none'     => null,
	);

	public function __construct( int $team_id ) {
		global $wpdb;
		$this->team_id    = $team_id;
		$this->mods_table = $wpdb->prefix . 'pp_team_game_mods';
	}

	public function is_manual( string $game_id ): bool {
		return strpos( $game_id, 'manual_' ) === 0;
	}

	public function is_deleted( string $game_id ): bool {
		global $wpdb;
		return (bool) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT id FROM {$this->mods_table} WHERE team_id = %d AND external_id = %s AND edit_action = 'delete' LIMIT 1",
				$this->team_id,
				$game_id
			)
		);
	}

	/**
	 * THE write path. $fields maps field name => raw submitted string, and
	 * must contain only the fields the user actually changed.
	 *
	 * Sourced games: per-field diff against the raw row — a value equal to
	 * source removes the key from the mod, a differing value merges it in.
	 * Manual games: normalized values merge straight into the insert mod.
	 *
	 * Does NOT rebuild the display table; the caller rebuilds once after all
	 * edits (important for bulk, which edits many games per request).
	 */
	public function apply_field_edits( string $game_id, array $fields ): void {
		if ( $this->is_manual( $game_id ) ) {
			$this->merge_into_insert_mod( $game_id, $this->normalize_fields( $fields, $this->get_insert_mod_data( $game_id ) ) );
			return;
		}

		$raw   = $this->get_raw_game( $game_id );
		$set   = array();
		$unset = array();

		foreach ( $this->normalize_fields( $fields, $raw ) as $key => $value ) {
			if ( $this->equals_source( $key, $value, $raw ) ) {
				$unset[] = $key;
			} else {
				$set[ $key ] = $value;
			}
		}

		// game_date_day + game_timestamp travel together: if the date part
		// matched source but the timestamp key was produced by a time edit,
		// keep them consistent (normalize_fields already pairs them).
		$this->patch_update_mod( $game_id, $set, $unset );
	}

	/**
	 * Revert path: remove override keys. 'game_date' is the virtual td field —
	 * the stored keys are game_date_day + game_timestamp.
	 */
	public function clear_overrides( string $game_id, array $fields ): void {
		$unset = array();
		foreach ( $fields as $field ) {
			if ( 'game_date' === $field ) {
				$unset[] = 'game_date_day';
				$unset[] = 'game_timestamp';
			} else {
				$unset[] = $field;
			}
		}
		$this->patch_update_mod( $game_id, array(), $unset );
	}

	/**
	 * Repair pass for one 'update' mod row: drops keys whose value already
	 * equals the source (spurious overrides left behind by the old
	 * delete-and-rebuild save path), and reports — without changing — any
	 * date override whose date part differs from the raw row, since a shifted
	 * date may be intentional and only the admin can tell (see the compounding
	 * strtotime/wp_date bug this replaces).
	 *
	 * Returns array{cleaned_keys: string[], mod_deleted: bool, date_shift: ?array}.
	 */
	public function clean_spurious_overrides( array $mod ): array {
		global $wpdb;

		$game_id   = (string) $mod['external_id'];
		$raw       = $this->get_raw_game( $game_id );
		$edit_data = json_decode( $mod['edit_data'], true ) ?: array();
		$cleaned   = array();

		foreach ( $edit_data as $key => $value ) {
			if ( in_array( $key, array( 'external_id', 'game_timestamp', 'game_date_day' ), true ) ) {
				continue; // date pair handled below; external_id is inert
			}
			if ( $this->equals_source( $key, $value, $raw ) ) {
				$cleaned[] = $key;
				unset( $edit_data[ $key ] );
			}
		}

		// Date pair: identical to raw → spurious, drop both. Different date
		// part → report only.
		$date_shift = null;
		$mod_ts     = (string) ( $edit_data['game_timestamp'] ?? '' );
		$raw_ts     = (string) ( $raw['game_timestamp'] ?? '' );
		if ( '' !== $mod_ts && '' !== $raw_ts ) {
			if ( $mod_ts === $raw_ts && (string) ( $edit_data['game_date_day'] ?? '' ) === (string) ( $raw['game_date_day'] ?? '' ) ) {
				$cleaned[] = 'game_timestamp';
				unset( $edit_data['game_timestamp'], $edit_data['game_date_day'] );
			} elseif ( substr( $mod_ts, 0, 10 ) !== substr( $raw_ts, 0, 10 ) ) {
				$date_shift = array(
					'mod_id'   => (int) $mod['id'],
					'game_id'  => $game_id,
					'raw_date' => substr( $raw_ts, 0, 10 ),
					'mod_date' => substr( $mod_ts, 0, 10 ),
				);
			}
		}

		// Orphaned timestamp: only exists to back date/time overrides.
		if ( isset( $edit_data['game_timestamp'] ) && ! isset( $edit_data['game_date_day'] ) && ! isset( $edit_data['game_time'] ) ) {
			$cleaned[] = 'game_timestamp';
			unset( $edit_data['game_timestamp'] );
			$date_shift = null;
		}

		$mod_deleted = false;
		if ( ! empty( $cleaned ) ) {
			if ( empty( array_diff( array_keys( $edit_data ), array( 'external_id' ) ) ) ) {
				$wpdb->delete( $this->mods_table, array( 'id' => (int) $mod['id'] ), array( '%d' ) );
				$mod_deleted = true;
				$date_shift  = null;
			} else {
				$wpdb->update(
					$this->mods_table,
					array(
						'edit_data'  => wp_json_encode( $edit_data ),
						'updated_at' => current_time( 'mysql' ),
					),
					array( 'id' => (int) $mod['id'] ),
					array( '%s', '%s' ),
					array( '%d' )
				);
			}
		}

		return array(
			'cleaned_keys' => $cleaned,
			'mod_deleted'  => $mod_deleted,
			'date_shift'   => $date_shift,
		);
	}

	// ── Normalization ─────────────────────────────────────────────────────

	/**
	 * Convert raw submitted strings into canonical stored values. Dates and
	 * times are naive site-local strings — never run them through
	 * strtotime()+wp_date(), which mixes UTC parsing with site-TZ formatting
	 * and shifts dates on negative-offset timezones.
	 *
	 * $baseline provides the existing timestamp when only one of date/time is
	 * being edited (raw row for sourced games, insert-mod data for manual).
	 */
	private function normalize_fields( array $fields, array $baseline = array() ): array {
		$out = array();

		$base_ts   = (string) ( $baseline['game_timestamp'] ?? '' );
		$base_date = substr( $base_ts, 0, 10 );
		$base_time = substr( $base_ts, 11, 5 );

		$date_input = isset( $fields['game_date'] ) ? sanitize_text_field( $fields['game_date'] ) : null;
		$time_input = isset( $fields['game_time'] ) ? sanitize_text_field( $fields['game_time'] ) : null;

		if ( null !== $date_input && ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $date_input ) ) {
			$date_input = null; // ignore malformed dates rather than guessing
		}
		if ( null !== $time_input && ! preg_match( '/^\d{2}:\d{2}$/', $time_input ) ) {
			$time_input = null;
		}

		if ( null !== $date_input || null !== $time_input ) {
			$date = $date_input ?? ( $base_date ?: current_time( 'Y-m-d' ) );
			$time = $time_input ?? ( $base_time ?: '00:00' );

			require_once plugin_dir_path( __DIR__ ) . 'schedule/class-puck-press-team-source-importer.php';

			if ( null !== $date_input ) {
				$out['game_date_day'] = Puck_Press_Team_Source_Importer::format_game_date_day( $date );
			}
			if ( null !== $time_input ) {
				// 'H:i' input → 'g:i A' display. Pure time-of-day, no date involved.
				$out['game_time'] = date( 'g:i A', strtotime( '1970-01-01 ' . $time_input ) );
			}
			$out['game_timestamp'] = $date . ' ' . $time . ':00';
		}

		foreach ( $fields as $field => $input ) {
			switch ( $field ) {
				case 'game_date':
				case 'game_time':
					break; // handled above

				case 'game_status':
					$status = sanitize_text_field( $input );
					if ( array_key_exists( $status, self::STATUS_MAP ) ) {
						$out['game_status'] = self::STATUS_MAP[ $status ];
					}
					// Unknown select values (e.g. a raw time string that has no
					// option) are ignored so an untouched select can't clobber
					// or fabricate an override.
					break;

				case 'home_or_away':
					$ha = sanitize_text_field( $input );
					if ( in_array( $ha, array( 'home', 'away' ), true ) ) {
						$out['home_or_away'] = $ha;
					}
					break;

				case 'target_score':
				case 'opponent_score':
					$out[ $field ] = ( '' === $input ) ? null : (int) $input;
					break;

				default:
					$out[ $field ] = in_array( $field, self::URL_FIELDS, true )
						? esc_url_raw( $input )
						: sanitize_text_field( $input );
			}
		}

		return $out;
	}

	/**
	 * '' (or null) on a display-only field means "no override" — raw has no
	 * such column, so its baseline is always ''. For real raw columns the
	 * normalized value is compared against the raw value as strings; a null
	 * value (cleared score/status over a non-empty raw value) never equals a
	 * non-empty source, so it is stored as a tombstone.
	 */
	private function equals_source( string $key, $value, array $raw ) {
		if ( in_array( $key, self::DISPLAY_ONLY_FIELDS, true ) ) {
			return (string) $value === '';
		}
		return (string) $value === (string) ( $raw[ $key ] ?? '' );
	}

	// ── Mod persistence ───────────────────────────────────────────────────

	/**
	 * Merge $set into / remove $unset from the game's 'update' mod. Never
	 * deletes-and-rebuilds the whole mod, so overrides on fields not part of
	 * this edit always survive. Deletes the mod row when edit_data empties.
	 */
	private function patch_update_mod( string $game_id, array $set, array $unset ): void {
		global $wpdb;

		$mod       = $this->get_mod( $game_id, 'update' );
		$edit_data = $mod ? ( json_decode( $mod['edit_data'], true ) ?: array() ) : array();

		foreach ( $unset as $key ) {
			unset( $edit_data[ $key ] );
		}
		// If nothing date/time-related remains, drop the timestamp too — it
		// only exists to back game_date_day / game_time overrides.
		if ( isset( $edit_data['game_timestamp'] ) && ! isset( $set['game_timestamp'] )
			&& ! isset( $edit_data['game_date_day'] ) && ! isset( $edit_data['game_time'] ) ) {
			unset( $edit_data['game_timestamp'] );
		}

		$edit_data = array_merge( $edit_data, $set );

		if ( empty( array_diff( array_keys( $edit_data ), array( 'external_id' ) ) ) ) {
			if ( $mod ) {
				$wpdb->delete( $this->mods_table, array( 'id' => (int) $mod['id'] ), array( '%d' ) );
			}
			return;
		}

		if ( $mod ) {
			$wpdb->update(
				$this->mods_table,
				array(
					'edit_data'  => wp_json_encode( $edit_data ),
					'updated_at' => current_time( 'mysql' ),
				),
				array( 'id' => (int) $mod['id'] ),
				array( '%s', '%s' ),
				array( '%d' )
			);
		} else {
			require_once plugin_dir_path( __DIR__ ) . 'teams/class-puck-press-teams-wpdb-utils.php';
			( new Puck_Press_Teams_Wpdb_Utils() )->upsert_team_game_mod( $this->team_id, $game_id, 'update', $edit_data );
		}
	}

	private function merge_into_insert_mod( string $game_id, array $normalized ): void {
		global $wpdb;

		$mod = $this->get_mod( $game_id, 'insert' );
		if ( ! $mod ) {
			return; // manual game without an insert mod shouldn't exist; nothing to edit
		}

		$edit_data = json_decode( $mod['edit_data'], true ) ?: array();
		$edit_data = array_merge( $edit_data, $normalized );

		$wpdb->update(
			$this->mods_table,
			array(
				'edit_data'  => wp_json_encode( $edit_data ),
				'updated_at' => current_time( 'mysql' ),
			),
			array( 'id' => (int) $mod['id'] ),
			array( '%s', '%s' ),
			array( '%d' )
		);
	}

	// ── Lookups ───────────────────────────────────────────────────────────

	public function get_raw_game( string $game_id ): array {
		global $wpdb;
		return $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$wpdb->prefix}pp_team_games_raw WHERE game_id = %s AND team_id = %d LIMIT 1",
				$game_id,
				$this->team_id
			),
			ARRAY_A
		) ?: array();
	}

	private function get_insert_mod_data( string $game_id ): array {
		$mod = $this->get_mod( $game_id, 'insert' );
		return $mod ? ( json_decode( $mod['edit_data'], true ) ?: array() ) : array();
	}

	private function get_mod( string $game_id, string $action ): ?array {
		global $wpdb;
		$row = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$this->mods_table} WHERE team_id = %d AND external_id = %s AND edit_action = %s LIMIT 1",
				$this->team_id,
				$game_id,
				$action
			),
			ARRAY_A
		);
		return $row ?: null;
	}
}
