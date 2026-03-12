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
 * This takes the active sources, get's their data and puts it into the raw game roster for further processing
 *
 * @package    Puck_Press
 * @subpackage Puck_Press/public/partials/roster
 * @author     Connor Mesec <connormesec@gmail.com>
 */
class Puck_Press_Roster_Source_Importer {

	private int $roster_id;
	private $roster_db_utils;
	private $results = array();

	public function __construct( int $roster_id = 1 ) {
		$this->roster_id = $roster_id;
		$this->load_dependencies();
		$this->roster_db_utils = new Puck_Press_Roster_Wpdb_Utils();
	}

	private function load_dependencies() {
		require_once plugin_dir_path( __DIR__ ) . 'roster/class-puck-press-roster-wpdb-utils.php';
		require_once plugin_dir_path( __DIR__ ) . 'class-puck-press-tts-api.php';
		require_once plugin_dir_path( __DIR__ ) . 'roster/class-puck-press-roster-normalizer.php';
		require_once plugin_dir_path( __DIR__ ) . 'roster/class-puck-press-roster-process-acha-url.php';
		require_once plugin_dir_path( __DIR__ ) . 'roster/class-puck-press-roster-process-acha-stats.php';
		require_once plugin_dir_path( __DIR__ ) . 'roster/class-puck-press-roster-process-csv-data.php';
		require_once plugin_dir_path( __DIR__ ) . 'roster/class-puck-press-roster-process-usphl-url.php';
	}

	public function populate_raw_roster_table_from_sources() {
		$this->results = array(
			'success_count' => 0,
			'error_count'   => 0,
			'errors'        => array(),
			'messages'      => array(),
		);

		$this->roster_db_utils->delete_rows_for_roster( 'pp_roster_raw', $this->roster_id );
		$this->roster_db_utils->delete_rows_for_roster( 'pp_roster_stats', $this->roster_id );
		$this->roster_db_utils->delete_rows_for_roster( 'pp_roster_goalie_stats', $this->roster_id );

		$active_sources = $this->roster_db_utils->get_active_roster_sources( $this->roster_id );

		if ( empty( $active_sources ) ) {
			$this->results['messages'][] = 'No active sources to import.';
			return $this->results;
		}

		foreach ( $active_sources as $source ) {
			try {
				if ( $source->type === 'achaRosterUrl' ) {

					$raw_acha_data = new Puck_Press_Roster_Process_Acha_Url( $source->source_url_or_path );
					foreach ( $raw_acha_data->raw_roster_data as &$row ) {
						$row['source'] = $source->name;
					}

					$inserted = $this->roster_db_utils->insert_multiple_roster_rows( $raw_acha_data->raw_roster_data, $this->roster_id );

					++$this->results['success_count'];
					$this->results['messages'][] = "Imported source: {$source->name}";
					$this->results['messages'][] = $inserted;

					if ( ! empty( $source->stats_url ) ) {
						$acha_stats = new Puck_Press_Roster_Process_Acha_Stats( $source->stats_url );
						if ( is_array( $acha_stats->raw_stats_data ) && ! isset( $acha_stats->raw_stats_data['error'] ) && ! empty( $acha_stats->raw_stats_data ) ) {
							foreach ( $acha_stats->raw_stats_data as &$stat_row ) {
								$stat_row['source'] = $source->name;
							}
							unset( $stat_row );
							$stats_inserted              = $this->roster_db_utils->insert_stats_rows( $acha_stats->raw_stats_data, $this->roster_id );
							$this->results['messages'][] = "Imported skater stats for source: {$source->name}";
							$this->results['messages'][] = $stats_inserted;
						} else {
							$this->results['messages'][] = "Skater stats import skipped for source: {$source->name} — " . ( $acha_stats->raw_stats_data['error'] ?? 'unknown error or empty' );
						}
					}

					if ( empty( $source->goalie_stats_url ) ) {
						$this->results['messages'][] = "Goalie stats skipped for source: {$source->name} — no Goalie Stats URL configured.";
					} else {
						$acha_goalie_stats = new Puck_Press_Roster_Process_Acha_Stats( $source->goalie_stats_url, true );
						if ( is_array( $acha_goalie_stats->raw_goalie_stats_data ) && ! isset( $acha_goalie_stats->raw_goalie_stats_data['error'] ) && ! empty( $acha_goalie_stats->raw_goalie_stats_data ) ) {
							foreach ( $acha_goalie_stats->raw_goalie_stats_data as &$stat_row ) {
								$stat_row['source'] = $source->name;
							}
							unset( $stat_row );
							$goalie_stats_inserted       = $this->roster_db_utils->insert_goalie_stats_rows( $acha_goalie_stats->raw_goalie_stats_data, $this->roster_id );
							$this->results['messages'][] = "Imported goalie stats for source: {$source->name}";
							$this->results['messages'][] = $goalie_stats_inserted;
						} else {
							$this->results['messages'][] = "Goalie stats import skipped for source: {$source->name} — " . ( $acha_goalie_stats->raw_goalie_stats_data['error'] ?? 'unknown error or empty' );
						}
					}
				} elseif ( $source->type === 'usphlRosterUrl' ) {
					$usphl_other    = json_decode( $source->other_data ?? '{}', true );
					$raw_usphl_data = new Puck_Press_Roster_Process_Usphl_Url(
						$source->source_url_or_path,
						$usphl_other['season_id'] ?? ''
					);

					if ( ! empty( $raw_usphl_data->fetch_errors ) ) {
						foreach ( $raw_usphl_data->fetch_errors as $endpoint => $error ) {
							$this->results['errors'][]   = array(
								'source'  => $source->name,
								'message' => "USPHL /{$endpoint} fetch error: {$error}",
							);
							$this->results['messages'][] = "USPHL /{$endpoint} fetch error for {$source->name}: {$error}";
						}
					}

					foreach ( $raw_usphl_data->raw_roster_data as &$row ) {
						$row['source'] = $source->name;
					}
					unset( $row );

					$inserted = $this->roster_db_utils->insert_multiple_roster_rows( $raw_usphl_data->raw_roster_data, $this->roster_id );

					++$this->results['success_count'];
					$this->results['messages'][] = "Imported source: {$source->name}";
					$this->results['messages'][] = $inserted;

					if ( ! empty( $raw_usphl_data->raw_stats_data ) ) {
						foreach ( $raw_usphl_data->raw_stats_data as &$stat_row ) {
							$stat_row['source'] = $source->name;
						}
						unset( $stat_row );
						$stats_inserted              = $this->roster_db_utils->insert_stats_rows( $raw_usphl_data->raw_stats_data, $this->roster_id );
						$this->results['messages'][] = "Imported skater stats for source: {$source->name} (" . count( $raw_usphl_data->raw_stats_data ) . ' players)';
						$this->results['messages'][] = $stats_inserted;
					} else {
						$this->results['messages'][] = "No skater stats returned for source: {$source->name}";
					}

					if ( ! empty( $raw_usphl_data->raw_goalie_stats_data ) ) {
						foreach ( $raw_usphl_data->raw_goalie_stats_data as &$stat_row ) {
							$stat_row['source'] = $source->name;
						}
						unset( $stat_row );
						$goalie_stats_inserted       = $this->roster_db_utils->insert_goalie_stats_rows( $raw_usphl_data->raw_goalie_stats_data, $this->roster_id );
						$this->results['messages'][] = "Imported goalie stats for source: {$source->name} (" . count( $raw_usphl_data->raw_goalie_stats_data ) . ' goalies)';
						$this->results['messages'][] = $goalie_stats_inserted;
					} else {
						$this->results['messages'][] = "No goalie stats returned for source: {$source->name}";
					}
				} elseif ( $source->type === 'csv' ) {
					$csv_data    = $source->csv_data ?? null;
					$process_csv = new Puck_Press_Roster_Process_Csv_Data( $csv_data, $source->name );
					$players     = $process_csv->parse();
					$inserted    = $this->roster_db_utils->insert_multiple_roster_rows( $players, $this->roster_id );
					++$this->results['success_count'];
					$this->results['messages'][] = "Imported source: {$source->name}";
					$this->results['messages'][] = $inserted;
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

	function apply_edits_and_save_to_display_table() {
		global $wpdb;

		$table_a = 'pp_roster_raw';
		$table_b = 'pp_roster_mods';
		$table_c = 'pp_roster_for_display';

		$this->roster_db_utils->delete_rows_for_roster( $table_c, $this->roster_id );

		$prefix    = $wpdb->prefix;
		$originals = $wpdb->get_results(
			$wpdb->prepare( "SELECT * FROM {$prefix}{$table_a} WHERE roster_id = %d", $this->roster_id ),
			ARRAY_A
		) ?? array();
		$originals = $this->deduplicate_by_player_id( $originals );

		$edits_raw  = $wpdb->get_results(
			$wpdb->prepare( "SELECT * FROM {$prefix}{$table_b} WHERE roster_id = %d", $this->roster_id ),
			ARRAY_A
		) ?? array();
		$edit_map   = array();
		$delete_ids = array();
		$results    = array();

		foreach ( $edits_raw as $edit ) {
			$player_id = $edit['external_id'];
			$action    = strtolower( $edit['edit_action'] ?? 'update' );

			if ( $action === 'delete' ) {
				$delete_ids[] = $player_id;
			} elseif ( $action === 'update' ) {
				$edit_data = json_decode( $edit['edit_data'], true );
				if ( json_last_error() === JSON_ERROR_NONE && is_array( $edit_data ) ) {
					$edit_map[ $player_id ] = $edit_data;
				}
			}
		}

		foreach ( $originals as $row ) {
			$player_id = $row['player_id'];

			if ( in_array( $player_id, $delete_ids, true ) ) {
				$this->roster_db_utils->delete_row_by_player_id( $table_c, $player_id );
				$results[] = "Deleted player ID: $player_id";
				continue;
			}

			if ( isset( $edit_map[ $player_id ] ) ) {
				$row       = array_merge( $row, $edit_map[ $player_id ] );
				$results[] = "Updated player ID: $player_id";
			}

			$row['roster_id'] = $this->roster_id;
			$this->roster_db_utils->insert_or_replace_row( $table_c, $row );
		}

		foreach ( $edits_raw as $edit ) {
			if ( strtolower( $edit['edit_action'] ?? '' ) === 'insert' ) {
				$edit_data = json_decode( $edit['edit_data'], true );
				if ( json_last_error() === JSON_ERROR_NONE && is_array( $edit_data ) ) {
					$edit_data['source']    = $edit_data['source'] ?? 'Manual';
					$edit_data['roster_id'] = $this->roster_id;
					$manual_id              = $edit_data['player_id'] ?? null;
					if ( $manual_id && isset( $edit_map[ $manual_id ] ) ) {
						$edit_data = array_merge( $edit_data, $edit_map[ $manual_id ] );
					}
					$this->roster_db_utils->insert_or_replace_row( $table_c, $edit_data );
					$results[] = 'Inserted manual player: ' . ( $edit_data['player_id'] ?? 'unknown' );
				}
			}
		}

		return $results;
	}

	/**
	 * Deduplicate raw roster rows by player_id.
	 *
	 * Keeps the row with the most non-empty fields. On a tie, the last
	 * occurrence wins (most recently added, so more likely to be current).
	 */
	private function deduplicate_by_player_id( array $rows ): array {
		$grouped = array();
		foreach ( $rows as $row ) {
			$grouped[ $row['player_id'] ][] = $row;
		}

		$deduplicated = array();
		foreach ( $grouped as $duplicates ) {
			$best       = null;
			$best_score = -1;
			foreach ( $duplicates as $candidate ) {
				$score = 0;
				foreach ( $candidate as $key => $val ) {
					if ( $key === 'id' ) {
						continue;
					}
					if ( $val !== null && $val !== '' ) {
						++$score;
					}
				}
				if ( $score >= $best_score ) {
					$best       = $candidate;
					$best_score = $score;
				}
			}
			$deduplicated[] = $best;
		}

		return $deduplicated;
	}

	public function get_results() {
		return $this->results;
	}

	function sanitize_roster_display_table() {
		$this->standardize_formatting();
	}

	private function standardize_formatting() {
		global $wpdb;

		$table = "{$wpdb->prefix}pp_roster_for_display";

		$rows = $wpdb->get_results(
			$wpdb->prepare( "SELECT id, pos, shoots, ht, wt FROM $table WHERE roster_id = %d", $this->roster_id ),
			ARRAY_A
		);

		foreach ( $rows as $row ) {
			$id = (int) $row['id'];

			$normalized = array(
				'pos'    => Puck_Press_Roster_Normalizer::normalize_position( (string) ( $row['pos'] ?? '' ) ),
				'shoots' => Puck_Press_Roster_Normalizer::normalize_shoots( (string) ( $row['shoots'] ?? '' ) ),
				'ht'     => Puck_Press_Roster_Normalizer::normalize_height( (string) ( $row['ht'] ?? '' ) ),
				'wt'     => Puck_Press_Roster_Normalizer::normalize_weight( (string) ( $row['wt'] ?? '' ) ),
			);

			$updates     = array();
			$formats     = array();
			$null_fields = array();
			foreach ( $normalized as $field => $value ) {
				$current = (string) ( $row[ $field ] ?? '' );
				if ( $value === null && $current !== '' ) {
					$null_fields[] = esc_sql( $field );
				} elseif ( $value !== null && $value !== $current ) {
					$updates[ $field ] = $value;
					$formats[]         = '%s';
				}
			}

			if ( ! empty( $updates ) ) {
				$wpdb->update( $table, $updates, array( 'id' => $id ), $formats, array( '%d' ) );
			}

			if ( ! empty( $null_fields ) ) {
				$set = implode( ', ', array_map( fn( $f ) => "`$f` = NULL", $null_fields ) );
				$wpdb->query( $wpdb->prepare( "UPDATE `$table` SET $set WHERE id = %d", $id ) );
			}
		}
	}
}
