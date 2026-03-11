<?php
class Puck_Press_Schedule_Admin_Edits_Table_Card extends Puck_Press_Admin_Card_Abstract {

	public $table_name = 'pp_game_schedule_mods';
	private int $schedule_id;

	public function __construct( array $args = array(), int $schedule_id = 1 ) {
		parent::__construct( $args );
		$this->schedule_id = $schedule_id;
	}

	public function render_content() {
		return $this->render_edits_table();
	}
	public function render_header_button_content() {
		return '';
	}

	public function render_edits_table() {
		global $wpdb;
		$schedule_db_utils = new Puck_Press_Schedule_Wpdb_Utils();
		$schedule_db_utils->maybe_create_or_update_table( $this->table_name );
		$table = $wpdb->prefix . $this->table_name;
		$edits = $wpdb->get_results(
			$wpdb->prepare( "SELECT * FROM $table WHERE schedule_id = %d ORDER BY id ASC", $this->schedule_id ),
			ARRAY_A
		);

		if ( $edits == null ) {
			return '<table class="pp-table" id="pp-schedule-edits-table"><caption>' . esc_html__( 'No game edits', 'puck-press' ) . '</caption></table>';
		}

		ob_start();
		?>
		<table class="pp-table" id="pp-schedule-edits-table">
			<thead class="pp-thead">
				<tr>
					<th class="pp-th"><?php esc_html_e( 'Operation', 'puck-press' ); ?></th>
					<th class="pp-th"><?php esc_html_e( 'Game ID', 'puck-press' ); ?></th>
					<th class="pp-th"><?php esc_html_e( 'Field', 'puck-press' ); ?></th>
					<th class="pp-th"><?php esc_html_e( 'Actions', 'puck-press' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php
				foreach ( $edits as $edit ) :
					$edit_data = json_decode( $edit['edit_data'], true );
					$can_edit  = ( $edit['edit_action'] === 'update' );
					?>
					<tr data-edit-id="<?php echo esc_html( $edit['id'] ); ?>">
						<td class="pp-td"><?php echo esc_html( $edit['edit_action'] ); ?></td>
						<td class="pp-td"><?php echo esc_html( $edit['external_id'] ); ?></td>
						<td class="pp-td">
							<?php if ( ! empty( $edit_data ) ) : ?>
								<?php foreach ( $edit_data as $key => $value ) : ?>
									<?php
									// Skip internal computed/raw fields from the tag display
									if ( in_array( $key, array( 'game_timestamp', 'game_date_day', 'external_id' ), true ) ) {
										continue;
									}
									$display_value = ( strlen( (string) $value ) > 20 )
										? esc_html( substr( (string) $value, 0, 20 ) ) . '...'
										: esc_html( (string) $value );
									?>
									<span
										class="pp-tag pp-tag-<?php echo sanitize_html_class( strtolower( $key ) ); ?>"
										title="<?php echo esc_attr( (string) $value ); ?>">
										<?php echo esc_html( ucfirst( $key ) ) . ': ' . $display_value; ?>
									</span>
								<?php endforeach; ?>
							<?php endif; ?>
						</td>
						<td class="pp-td">
							<div class="pp-flex-small-gap">
								<?php if ( $can_edit ) : ?>
									<button
										class="pp-button-icon"
										id="pp-edit-edit-button"
										data-game-id="<?php echo esc_attr( $edit['external_id'] ); ?>">✏️</button>
								<?php endif; ?>
								<button class="pp-button-icon" id="pp-delete-edit-button" data-edit-id="<?php echo esc_attr( $edit['id'] ); ?>">🗑️</button>
							</div>
						</td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
		<?php
		return ob_get_clean();
	}

	function ajax_refresh_edits_table_card_callback() {
		$response_html = $this->render_edits_table();
		if ( $response_html !== false ) {
			wp_send_json_success( $response_html );
		} else {
			wp_send_json_error( array( 'message' => 'Edits table refresh failed' ) );
		}
		wp_die();
	}

	function ajax_get_game_data_callback() {
		global $wpdb;

		$game_id = sanitize_text_field( $_POST['game_id'] ?? '' );
		if ( empty( $game_id ) ) {
			wp_send_json_error( array( 'message' => 'Missing game_id' ) );
			wp_die();
		}

		$display_table = $wpdb->prefix . 'pp_game_schedule_for_display';
		$game          = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM $display_table WHERE game_id = %s LIMIT 1",
				$game_id
			),
			ARRAY_A
		);

		$mods_table = $wpdb->prefix . 'pp_game_schedule_mods';
		$mod        = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM $mods_table WHERE external_id = %s AND edit_action = 'update' LIMIT 1",
				$game_id
			),
			ARRAY_A
		);

		$existing_edit = $mod ? json_decode( $mod['edit_data'], true ) : array();

		wp_send_json_success(
			array(
				'game'          => $game,
				'existing_edit' => $existing_edit,
			)
		);
		wp_die();
	}

	function ajax_save_game_edit_callback() {
		global $wpdb;

		$table = $wpdb->prefix . 'pp_game_schedule_mods';

		if ( ! isset( $_POST['edit_data'] ) ) {
			wp_send_json_error( array( 'message' => 'Missing edit_data' ) );
			wp_die();
		}

		$parsed_data = json_decode( stripslashes( $_POST['edit_data'] ), true );

		if ( json_last_error() !== JSON_ERROR_NONE ||
			! isset( $parsed_data['edit_action'], $parsed_data['fields']['external_id'] ) ) {
			wp_send_json_error( array( 'message' => 'Invalid or incomplete edit_data' ) );
			wp_die();
		}

		$edit_action = sanitize_text_field( $parsed_data['edit_action'] );
		$external_id = sanitize_text_field( $parsed_data['fields']['external_id'] );

		// Recompute game_timestamp and game_date_day when date/time are provided
		if ( ! empty( $parsed_data['fields']['game_date'] ) ) {
			$game_date = sanitize_text_field( $parsed_data['fields']['game_date'] );
			$game_time = sanitize_text_field( $parsed_data['fields']['game_time'] ?? '' );

			$parsed_data['fields']['game_timestamp'] = Puck_Press_Schedule_Source_Importer::get_game_timestamp( $game_date, $game_time );
			$parsed_data['fields']['game_date_day']  = Puck_Press_Schedule_Source_Importer::format_game_date_day( $game_date, $game_time );

			// Format game_time for display (e.g. "7:30 PM"); clear it if a final status is set
			$raw_status = sanitize_text_field( $parsed_data['fields']['game_status'] ?? '' );
			if ( $raw_status !== '' && $raw_status !== 'none' ) {
				$parsed_data['fields']['game_time'] = null;
			} elseif ( ! empty( $game_time ) ) {
				$parsed_data['fields']['game_time'] = date( 'g:i A', strtotime( $game_time ) );
			}
		}

		// Sanitize post_link as a URL
		if ( isset( $parsed_data['fields']['post_link'] ) ) {
			$parsed_data['fields']['post_link'] = esc_url_raw( $parsed_data['fields']['post_link'] );
		}

		// Format game_status for display
		if ( isset( $parsed_data['fields']['game_status'] ) && $parsed_data['fields']['game_status'] !== '' ) {
			$raw_status = $parsed_data['fields']['game_status'];
			if ( $raw_status === 'none' ) {
				$parsed_data['fields']['game_status'] = null;
			} else {
				$parsed_data['fields']['game_status'] = Puck_Press_Schedule_Source_Importer::format_game_status( $raw_status, null );
			}
		}

		$existing_row_id = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT id FROM $table WHERE external_id = %s AND edit_action = %s LIMIT 1",
				$external_id,
				$edit_action
			)
		);

		$current_time = current_time( 'mysql' );

		if ( $existing_row_id ) {
			// Merge incoming fields into the existing edit_data so that prior
			// intentional overrides on this game are preserved across multiple edits.
			$row_id        = intval( $existing_row_id );
			$existing_json = $wpdb->get_var(
				$wpdb->prepare(
					"SELECT edit_data FROM $table WHERE id = %d",
					$row_id
				)
			);
			// Guard against null — json_decode(null) emits a deprecation notice in
			// PHP 8.1+ which would corrupt the JSON response before wp_send_json_*.
			$existing_fields = ! empty( $existing_json ) ? ( json_decode( $existing_json, true ) ?: array() ) : array();
			$merged_fields   = array_merge( $existing_fields, $parsed_data['fields'] );
			$edit_data_json  = wp_json_encode( $merged_fields );

			$result = $wpdb->update(
				$table,
				array(
					'edit_data'  => $edit_data_json,
					'updated_at' => $current_time,
				),
				array( 'id' => $row_id ),
				array( '%s', '%s' ),
				array( '%d' )
			);

			if ( $result !== false ) {
				wp_send_json_success(
					array(
						'message' => 'Edit updated',
						'id'      => $row_id,
					)
				);
			} else {
				wp_send_json_error(
					array(
						'message'  => 'Update failed',
						'db_error' => $wpdb->last_error,
					)
				);
			}
		} else {
			$edit_data_json   = wp_json_encode( $parsed_data['fields'] );
			$save_schedule_id = (int) ( $_POST['schedule_id'] ?? 1 );
			$result           = $wpdb->insert(
				$table,
				array(
					'schedule_id' => $save_schedule_id,
					'external_id' => $external_id,
					'edit_action' => $edit_action,
					'edit_data'   => $edit_data_json,
					'created_at'  => $current_time,
					'updated_at'  => $current_time,
				)
			);

			if ( $result ) {
				wp_send_json_success(
					array(
						'message' => 'Edit recorded',
						'id'      => $wpdb->insert_id,
					)
				);
			} else {
				wp_send_json_error( array( 'message' => 'Insert failed' ) );
			}
		}

		wp_die();
	}

	function ajax_delete_game_edit_callback() {
		global $wpdb;

		$table = $wpdb->prefix . 'pp_game_schedule_mods';

		$id = isset( $_POST['id'] ) ? sanitize_text_field( $_POST['id'] ) : '';

		if ( empty( $id ) ) {
			wp_send_json_error( array( 'message' => 'Missing required fields' ) );
			wp_die();
		}

		$result = $wpdb->delete(
			$table,
			array( 'id' => $id ),
			array( '%s' )
		);

		if ( $result !== false ) {
			wp_send_json_success( array( 'message' => 'Edit deleted' ) );
		} else {
			wp_send_json_error( array( 'message' => 'Delete failed or record not found' ) );
		}

		wp_die();
	}

	function ajax_revert_game_field_callback() {
		global $wpdb;

		$table  = $wpdb->prefix . 'pp_game_schedule_mods';
		$mod_id = intval( $_POST['mod_id'] ?? 0 );
		$fields = isset( $_POST['fields'] ) ? (array) $_POST['fields'] : array();

		if ( $mod_id <= 0 || empty( $fields ) ) {
			wp_send_json_error( array( 'message' => 'Missing mod_id or fields' ) );
			wp_die();
		}

		$fields = array_map( 'sanitize_key', $fields );

		// When reverting date or time, also drop the server-computed companions
		if ( in_array( 'game_date', $fields, true ) || in_array( 'game_time', $fields, true ) ) {
			$fields = array_unique( array_merge( $fields, array( 'game_date', 'game_time', 'game_timestamp', 'game_date_day' ) ) );
		}

		$mod = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM $table WHERE id = %d LIMIT 1",
				$mod_id
			),
			ARRAY_A
		);

		if ( ! $mod ) {
			wp_send_json_error( array( 'message' => 'Mod record not found' ) );
			wp_die();
		}

		$edit_data = json_decode( $mod['edit_data'], true ) ?: array();

		foreach ( $fields as $field ) {
			unset( $edit_data[ $field ] );
		}

		// Check if any meaningful (non-internal) fields remain
		$internal  = array( 'external_id', 'game_timestamp', 'game_date_day' );
		$remaining = array_diff_key( $edit_data, array_flip( $internal ) );

		if ( empty( $remaining ) ) {
			$wpdb->delete( $table, array( 'id' => $mod_id ), array( '%d' ) );
		} else {
			$wpdb->update(
				$table,
				array(
					'edit_data'  => wp_json_encode( $edit_data ),
					'updated_at' => current_time( 'mysql' ),
				),
				array( 'id' => $mod_id )
			);
		}

		wp_send_json_success( array( 'message' => 'Field reverted' ) );
		wp_die();
	}

	function ajax_reset_all_edits_callback() {
		global $wpdb;

		$schedule_id = (int) ( $_POST['schedule_id'] ?? 1 );
		$table_mods  = $wpdb->prefix . 'pp_game_schedule_mods';
		$wpdb->delete( $table_mods, array( 'schedule_id' => $schedule_id ), array( '%d' ) );

		// Rebuild for_display from existing raw data — no external API calls needed.
		$utils = new Puck_Press_Schedule_Wpdb_Utils();
		$utils->delete_rows_for_schedule( 'pp_game_schedule_for_display', $schedule_id );
		$importer = new Puck_Press_Schedule_Source_Importer( $schedule_id );
		$importer->apply_edits_and_save_to_display_table();

		$games_table_card = new Puck_Press_Schedule_Admin_Games_Table_Card( array(), $schedule_id );
		$games_table_html = $games_table_card->render_game_schedule_admin_preview();

		$preview_card = Puck_Press_Schedule_Admin_Preview_Card::create_and_init( $schedule_id );
		$preview_html = $preview_card->get_all_templates_html();

		$slider_card = Puck_Press_Schedule_Admin_Slider_Preview_Card::create_and_init( $schedule_id );
		$slider_html = $slider_card->get_all_templates_html();

		$this->schedule_id = $schedule_id;
		$edits_table_html  = $this->render_edits_table();

		wp_send_json_success(
			array(
				'message'                       => 'All edits reset.',
				'refreshed_game_table_ui'       => $games_table_html,
				'refreshed_game_preview_html'   => $preview_html,
				'refreshed_slider_preview_html' => $slider_html,
				'refreshed_edits_table_html'    => $edits_table_html,
			)
		);
		wp_die();
	}

	public function ajax_bulk_update_schedule_field_callback() {
		global $wpdb;

		check_ajax_referer( 'pp_bulk_schedule_nonce', 'nonce' );

		$allowed_fields = array( 'promo_ticket_link', 'venue', 'promo_header', 'promo_text', 'promo_img_url' );
		$field          = sanitize_key( $_POST['field'] ?? '' );
		if ( ! in_array( $field, $allowed_fields, true ) ) {
			wp_send_json_error( array( 'message' => 'Invalid field.' ) );
			wp_die();
		}

		$url_fields = array( 'promo_ticket_link', 'promo_img_url' );
		$raw_value  = stripslashes( $_POST['value'] ?? '' );
		$value      = in_array( $field, $url_fields, true ) ? esc_url_raw( $raw_value ) : sanitize_text_field( $raw_value );

		$game_ids = json_decode( stripslashes( $_POST['game_ids'] ?? '[]' ), true );
		if ( ! is_array( $game_ids ) || empty( $game_ids ) ) {
			wp_send_json_error( array( 'message' => 'No games selected.' ) );
			wp_die();
		}

		$table    = $wpdb->prefix . 'pp_game_schedule_mods';
		$internal = array( 'external_id', 'game_timestamp', 'game_date_day' );
		$now      = current_time( 'mysql' );

		foreach ( $game_ids as $game_id ) {
			$game_id = sanitize_text_field( $game_id );

			// Skip deleted games
			$has_delete = $wpdb->get_var(
				$wpdb->prepare(
					"SELECT id FROM $table WHERE external_id = %s AND edit_action = 'delete' LIMIT 1",
					$game_id
				)
			);
			if ( $has_delete ) {
				continue;
			}

			$existing = $wpdb->get_row(
				$wpdb->prepare(
					"SELECT id, edit_data FROM $table WHERE external_id = %s AND edit_action = 'update' LIMIT 1",
					$game_id
				),
				ARRAY_A
			);

			if ( $existing ) {
				$edit_data = ! empty( $existing['edit_data'] ) ? ( json_decode( $existing['edit_data'], true ) ?: array() ) : array();
				if ( $value !== '' ) {
					$edit_data[ $field ] = $value;
				} else {
					unset( $edit_data[ $field ] );
				}
				$remaining = array_diff_key( $edit_data, array_flip( $internal ) );
				if ( empty( $remaining ) ) {
					$wpdb->delete( $table, array( 'id' => $existing['id'] ), array( '%d' ) );
				} else {
					$wpdb->update(
						$table,
						array(
							'edit_data'  => wp_json_encode( $edit_data ),
							'updated_at' => $now,
						),
						array( 'id' => $existing['id'] )
					);
				}
			} elseif ( $value !== '' ) {
				$wpdb->insert(
					$table,
					array(
						'external_id' => $game_id,
						'edit_action' => 'update',
						'edit_data'   => wp_json_encode(
							array(
								'external_id' => $game_id,
								$field        => $value,
							)
						),
						'created_at'  => $now,
						'updated_at'  => $now,
					)
				);
			}
		}

		$bulk_schedule_id = (int) ( $_POST['schedule_id'] ?? 1 );
		$utils            = new Puck_Press_Schedule_Wpdb_Utils();
		$utils->delete_rows_for_schedule( 'pp_game_schedule_for_display', $bulk_schedule_id );
		$importer = new Puck_Press_Schedule_Source_Importer( $bulk_schedule_id );
		$importer->apply_edits_and_save_to_display_table();

		$games_table_html      = ( new Puck_Press_Schedule_Admin_Games_Table_Card( array(), $bulk_schedule_id ) )->render_game_schedule_admin_preview();
		$schedule_preview_html = Puck_Press_Schedule_Admin_Preview_Card::create_and_init( $bulk_schedule_id )->get_all_templates_html();
		$slider_preview_html   = Puck_Press_Schedule_Admin_Slider_Preview_Card::create_and_init( $bulk_schedule_id )->get_all_templates_html();

		wp_send_json_success(
			array(
				'games_table_html'      => $games_table_html,
				'schedule_preview_html' => $schedule_preview_html,
				'slider_preview_html'   => $slider_preview_html,
			)
		);
		wp_die();
	}

	function console_log( $output, $with_script_tags = true ) {
		$js_code = 'console.log(' . json_encode( $output, JSON_HEX_TAG ) . ');';
		if ( $with_script_tags ) {
			$js_code = '<script>' . $js_code . '</script>';
		}
		echo $js_code;
	}
}
