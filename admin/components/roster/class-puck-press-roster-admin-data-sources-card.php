<?php
class Puck_Press_Roster_Admin_Data_Sources_Card extends Puck_Press_Admin_Card_Abstract {

	private $table_name = 'pp_roster_data_sources';
	private $roster_db_utils;

	public function __construct( array $args = array() ) {
		parent::__construct( $args );
	}

	/**
	 * Initialize any heavy things like DB setup.
	 */
	public function init(): void {
		$this->roster_db_utils = new Puck_Press_Roster_Wpdb_Utils();
		$this->roster_db_utils->maybe_create_or_update_table( $this->table_name );
		$this->roster_db_utils->maybe_create_or_update_table( 'pp_roster_for_display' );
		$this->roster_db_utils->maybe_create_or_update_table( 'pp_roster_raw' );
		$this->roster_db_utils->maybe_create_or_update_table( 'pp_roster_stats' );
		$this->roster_db_utils->maybe_create_or_update_table( 'pp_roster_goalie_stats' );
	}

	public function render_content() {
		$this->init(); // <-- Call it here if needed
		ob_start();
		?>
		<div id="pp-data-sources-table">
			<?php echo $this->render_game_roster_data_sources(); ?>
		</div>
		<?php
		return ob_get_clean();
	}

	public function render_header_button_content() {
		ob_start();
		?>
		<button class="pp-button pp-button-primary" id="pp-add-source-button">
			+ Add Source
		</button>
		<?php
		return ob_get_clean();
	}

	public function render_game_roster_data_sources() {
		global $wpdb;
		$wp_table_name = $wpdb->prefix . $this->table_name;
		// Check if table exists
		$table_exists = $wpdb->get_var(
			$wpdb->prepare(
				'SHOW TABLES LIKE %s',
				$wpdb->esc_like( $wp_table_name )
			)
		);

		if ( $table_exists !== $wp_table_name ) {
			return '<p>' . esc_html__( 'Data Sources table does not exist.', 'puck-press' ) . '</p>';
		}

		$data_sources = $this->roster_db_utils->get_all_table_data( $this->table_name );

		if ( empty( $data_sources ) ) {
			ob_start();
			?>
			<table class="pp-table" id="pp-sources-table">
				<thead class="pp-thead">
					<tr>
						<th class="pp-th"><?php esc_html_e( 'Name', 'puck-press' ); ?></th>
						<th class="pp-th"><?php esc_html_e( 'Type', 'puck-press' ); ?></th>
						<th class="pp-th"><?php esc_html_e( 'URL', 'puck-press' ); ?></th>
						<th class="pp-th"><?php esc_html_e( 'Last Updated', 'puck-press' ); ?></th>
						<th class="pp-th"><?php esc_html_e( 'Status', 'puck-press' ); ?></th>
						<th class="pp-th"><?php esc_html_e( 'Actions', 'puck-press' ); ?></th>
					</tr>
				</thead>
				<tbody>
				</tbody>
			</table>
			<p id="kill-me-please"><?php echo esc_html( 'No data sources yet..', 'puck-press' ); ?></p>
			<?php
			return ob_get_clean();
		}

		ob_start();
		?>
		<table class="pp-table" id="pp-sources-table">
			<thead class="pp-thead">
				<tr>
					<th class="pp-th"><?php esc_html_e( 'Name', 'puck-press' ); ?></th>
					<th class="pp-th"><?php esc_html_e( 'Type', 'puck-press' ); ?></th>
					<th class="pp-th"><?php esc_html_e( 'URL', 'puck-press' ); ?></th>
					<th class="pp-th"><?php esc_html_e( 'Last Updated', 'puck-press' ); ?></th>
					<th class="pp-th"><?php esc_html_e( 'Status', 'puck-press' ); ?></th>
					<th class="pp-th"><?php esc_html_e( 'Actions', 'puck-press' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( $data_sources as $source ) : ?>
					<tr data-id="<?php echo esc_html( $source->id ); ?>">
						<td class="pp-td" id="pp-sched-source-name"><?php echo esc_html( $source->name ); ?></td>
						<td class="pp-td">
							<span class="pp-tag pp-tag-<?php echo esc_html( $source->type ); ?>"><?php echo esc_html( $source->type ); ?></span>
							<?php if ( ! empty( $source->stats_url ) ) : ?>
								<span class="pp-tag" style="background:#e8f4fd;color:#1a6fa8;margin-left:4px;" title="<?php echo esc_attr( $source->stats_url ); ?>">Skater Stats</span>
							<?php endif; ?>
							<?php if ( ! empty( $source->goalie_stats_url ) ) : ?>
								<span class="pp-tag" style="background:#fde8f4;color:#a81a6f;margin-left:4px;" title="<?php echo esc_attr( $source->goalie_stats_url ); ?>">Goalie Stats</span>
							<?php endif; ?>
						</td>
						<td class="pp-td">
						<?php
						if ( $source->type === 'usphlRosterUrl' ) {
							$other   = json_decode( $source->other_data ?? '{}', true );
							$display = 'Team: ' . esc_html( $source->source_url_or_path );
							if ( ! empty( $other['season_id'] ) ) {
								$display .= ' / Season: ' . esc_html( $other['season_id'] );
							}
							echo $display;
						} else {
							echo esc_html( $source->source_url_or_path );
						}
						?>
						</td>
						<td class="pp-td"><?php echo esc_html( date( 'M d, Y h:i A', strtotime( $source->last_updated ) ) ); ?></td>
						<td class="pp-td">
							<label class="pp-data-source-toggle-switch">
								<input type="checkbox" <?php echo esc_html( $source->status === 'active' ? 'checked' : '' ); ?> data-id="<?php echo esc_html( $source->id ); ?>">
								<span class="pp-slider"></span>
							</label>
							<span style="margin-left: 10px;"><?php echo esc_html( ucfirst( $source->status ) ); ?></span>
						</td>
						<td class="pp-td">
							<div class="pp-flex-small-gap">
								<button class="pp-button-icon" id="pp-delete-source" data-id="<?php echo esc_html( $source->id ); ?>">🗑️</button>
							</div>
						</td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
		<?php
		return ob_get_clean();
	}

	public function ajax_delete_roster_source_callback() {
		$data_source_id = isset( $_POST['source_id'] ) ? intval( $_POST['source_id'] ) : 0;

		if ( ! $data_source_id ) {
			wp_send_json_error( array( 'message' => 'Invalid data source ID' ) );
			wp_die();
		}

		global $wpdb;
		$result = $wpdb->delete( $this->get_table_name(), array( 'id' => $data_source_id ) );

		if ( $result === false ) {
			wp_send_json_error(
				array(
					'message' => 'Failed to delete data source from database',
					'error'   => $wpdb->last_error,
				)
			);
		} else {
			wp_send_json_success( array( 'message' => 'Data source deleted successfully' ) );
		}

		wp_die();
	}

	public function ajax_update_roster_source_status_callback() {
		$source_id = isset( $_POST['source_id'] ) ? intval( $_POST['source_id'] ) : 0;
		$status    = sanitize_text_field( $_POST['status'] ?? '' );

		if ( ! $source_id || ! in_array( $status, array( 'active', 'inactive' ), true ) ) {
			wp_send_json_error( array( 'message' => 'Invalid request data' ) );
			wp_die();
		}

		global $wpdb;
		$updated = $wpdb->update(
			$this->get_table_name(),
			array( 'status' => $status ),
			array( 'id' => $source_id )
		);

		if ( $updated !== false ) {
			wp_send_json_success( array( 'message' => 'Status updated' ) );
		} else {
			wp_send_json_error( array( 'message' => 'Failed to update status' ) );
		}

		wp_die();
	}

	public function ajax_add_roster_source() {
		$data = $this->sanitize_add_data_source_request( $_POST, $_FILES );

		if ( is_wp_error( $data ) ) {
			wp_send_json_error( array( 'message' => $data->get_error_message() ) );
			wp_die();
		}

		global $wpdb;
		$result = $wpdb->insert( $this->get_table_name(), $data );

		if ( $result ) {
			wp_send_json_success(
				array(
					'message' => 'Data source added',
					'id'      => $wpdb->insert_id,
				)
			);
		} else {
			wp_send_json_error( array( 'message' => 'Insert failed' ) );
		}

		wp_die();
	}

	private function sanitize_add_data_source_request( $post, $files ) {
		$name   = sanitize_text_field( $post['name'] ?? '' );
		$type   = sanitize_text_field( $post['type'] ?? '' );
		$active = isset( $post['active'] ) ? intval( $post['active'] ) : 0;

		$url = $csv_content = $other_data = $stats_url = $goalie_stats_url = null;

		switch ( $type ) {
			case 'achaRosterUrl':
				$url              = esc_url_raw( $post['url'] ?? '' );
				$stats_url        = ! empty( $post['stats_url'] ) ? esc_url_raw( $post['stats_url'] ) : null;
				$goalie_stats_url = ! empty( $post['goalie_stats_url'] ) ? esc_url_raw( $post['goalie_stats_url'] ) : null;
				break;

			case 'usphlRosterUrl':
				$url        = sanitize_text_field( $post['team_id'] ?? '' );
				$season_id  = sanitize_text_field( $post['season_id'] ?? '' );
				$other_data = ! empty( $season_id ) ? wp_json_encode( array( 'season_id' => $season_id ) ) : null;
				break;

			case 'csv':
				if ( ! isset( $files['csv'] ) ) {
					return new WP_Error( 'csv_upload_error', 'CSV file not set. FILES: ' . print_r( $files, true ) );
				}

				if ( $files['csv']['error'] !== UPLOAD_ERR_OK ) {
					$error_code = $files['csv']['error'];
					return new WP_Error(
						'csv_upload_error',
						"CSV file upload failed. Error Code: $error_code. FILES: " . print_r( $files['csv'], true )
					);
				}

				$validation_result = Puck_Press_Roster_Process_Csv_Data::validate_csv_headers( $files['csv']['tmp_name'] );

				if ( is_wp_error( $validation_result ) ) {
					return $validation_result; // return early with error to user
				}

				$csv_content = file_get_contents( $files['csv']['tmp_name'] );
				$url         = sanitize_file_name( $files['csv']['name'] );
				break;

			default:
				return new WP_Error( 'invalid_type', 'Invalid type or file upload error' );
		}

		return array(
			'name'               => $name,
			'type'               => $type,
			'source_url_or_path' => $url,
			'stats_url'          => $stats_url,
			'goalie_stats_url'   => $goalie_stats_url,
			'csv_data'           => $csv_content,
			'other_data'         => $other_data,
			'status'             => $active ? 'active' : 'inactive',
			'created_at'         => current_time( 'mysql' ),
			'last_updated'       => current_time( 'mysql' ),
		);
	}

	private function get_table_name() {
		global $wpdb;
		return $wpdb->prefix . $this->table_name;
	}
}
