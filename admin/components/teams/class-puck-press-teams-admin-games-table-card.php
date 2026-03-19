<?php
class Puck_Press_Teams_Admin_Games_Table_Card extends Puck_Press_Admin_Card_Abstract {

	private int $team_id;

	public function __construct( array $args = array(), int $team_id = 0 ) {
		parent::__construct( $args );
		$this->team_id = $team_id;
	}

	public function render_content() {
		return $this->render_team_games_admin_preview();
	}

	public function render_header_button_content() {
		return '
            <button class="pp-button pp-button-secondary" id="pp-bulk-edit-schedule-btn">Bulk Edit Games</button>
            <button class="pp-button pp-button-primary" id="pp-add-game-button">+ Add Game</button>
        ';
	}

	public function render_team_games_admin_preview() {
		global $wpdb;
		$display_table = $wpdb->prefix . 'pp_team_games_display';
		$mods_table    = $wpdb->prefix . 'pp_team_game_mods';
		$raw_table     = $wpdb->prefix . 'pp_team_games_raw';

		// Active games: from display table, LEFT JOIN update mods for override highlighting
		$active_games = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT f.*, m.edit_data AS override_data, m.id AS mod_id
                 FROM $display_table f
                 LEFT JOIN $mods_table m ON f.game_id COLLATE utf8mb4_unicode_ci = m.external_id COLLATE utf8mb4_unicode_ci AND m.edit_action = 'update' AND m.team_id = %d
                 WHERE f.team_id = %d",
				$this->team_id,
				$this->team_id
			),
			ARRAY_A
		) ?: array();

		// Deleted sourced games: in raw but have a delete mod
		$deleted_games = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT r.*, dm.id AS delete_mod_id
                 FROM $raw_table r
                 INNER JOIN $mods_table dm ON r.game_id COLLATE utf8mb4_unicode_ci = dm.external_id COLLATE utf8mb4_unicode_ci AND dm.edit_action = 'delete' AND dm.team_id = %d
                 WHERE r.team_id = %d",
				$this->team_id,
				$this->team_id
			),
			ARRAY_A
		) ?: array();

		foreach ( $active_games as &$g ) {
			$g['row_status']    = 'active';
			$g['delete_mod_id'] = null;
		}
		unset( $g );

		foreach ( $deleted_games as &$g ) {
			$g['row_status']    = 'deleted';
			$g['mod_id']        = null;
			$g['override_data'] = null;
		}
		unset( $g );

		$games = array_merge( $active_games, $deleted_games );

		usort(
			$games,
			function ( $a, $b ) {
				return strcmp( $a['game_timestamp'] ?? '', $b['game_timestamp'] ?? '' );
			}
		);

		if ( empty( $games ) ) {
			return '<table class="pp-table" id="pp-games-table"><caption>' . esc_html__( 'No games scheduled yet.', 'puck-press' ) . '</caption></table>';
		}

		$skip_keys     = array( 'external_id', 'game_timestamp', 'game_date_day' );
		$hidden_fields = array( 'promo_header', 'promo_text', 'promo_img_url', 'promo_ticket_link' );

		ob_start();
		?>
		<table class="pp-table pp-games-table-full" id="pp-games-table">
			<thead class="pp-thead">
				<tr>
					<th class="pp-th"><?php esc_html_e( 'ID', 'puck-press' ); ?></th>
					<th class="pp-th"><?php esc_html_e( 'Date', 'puck-press' ); ?></th>
					<th class="pp-th"><?php esc_html_e( 'Time', 'puck-press' ); ?></th>
					<th class="pp-th"><?php esc_html_e( 'Target Team', 'puck-press' ); ?></th>
					<th class="pp-th"><?php esc_html_e( 'Score', 'puck-press' ); ?></th>
					<th class="pp-th"><?php esc_html_e( 'Opponent', 'puck-press' ); ?></th>
					<th class="pp-th"><?php esc_html_e( 'Score', 'puck-press' ); ?></th>
					<th class="pp-th"><?php esc_html_e( 'Location', 'puck-press' ); ?></th>
					<th class="pp-th"><?php esc_html_e( 'Status', 'puck-press' ); ?></th>
					<th class="pp-th"><?php esc_html_e( 'H/A', 'puck-press' ); ?></th>
					<th class="pp-th"><?php esc_html_e( 'Source', 'puck-press' ); ?></th>
					<th class="pp-th"><?php esc_html_e( 'Post Summary', 'puck-press' ); ?></th>
					<th class="pp-th"><?php esc_html_e( 'Actions', 'puck-press' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php
				foreach ( $games as $game ) :
					$is_deleted       = $game['row_status'] === 'deleted';
					$is_manual        = strpos( $game['game_id'], 'manual_' ) === 0;
					$source_type      = $is_manual ? 'manual' : 'sourced';
					$source_tag_class = $is_manual ? 'pp-tag-manual' : 'pp-tag-regular-season';

					$override_keys = array();
					if ( ! $is_deleted && ! empty( $game['override_data'] ) ) {
						$decoded = json_decode( $game['override_data'], true );
						if ( is_array( $decoded ) ) {
							$keys = array_diff( array_keys( $decoded ), $skip_keys );
							// Translate game_date_day → game_date to match the td data-field.
							if ( isset( $decoded['game_date_day'] ) ) {
								$keys[] = 'game_date';
							}
							$override_keys = array_values( $keys );
						}
					}
					$mod_id               = $game['mod_id'] ?? '';
					$has_hidden_overrides = ! $is_deleted && ! empty( array_intersect( $override_keys, $hidden_fields ) );
					?>
					<?php if ( $is_deleted ) : ?>
					<tr class="pp-row-deleted"
						data-id="<?php echo esc_attr( $game['game_id'] ); ?>"
						data-source-type="sourced"
						data-overrides="[]">
						<td class="pp-td pp-td-compact"><?php echo esc_html( $game['game_id'] ); ?></td>
						<td class="pp-td pp-td-compact"><?php echo esc_html( date( 'M d, Y', strtotime( $game['game_timestamp'] ) ) ); ?></td>
						<td class="pp-td pp-td-compact"><?php echo esc_html( $game['game_time'] ?? '' ); ?></td>
						<td class="pp-td pp-td-compact"><?php echo esc_html( $game['target_team_name'] ?? '' ); ?></td>
						<td class="pp-td pp-td-compact"><?php echo $game['target_score'] !== null ? esc_html( $game['target_score'] ) : '—'; ?></td>
						<td class="pp-td pp-td-compact"><?php echo esc_html( $game['opponent_team_name'] ?? '' ); ?></td>
						<td class="pp-td pp-td-compact"><?php echo $game['opponent_score'] !== null ? esc_html( $game['opponent_score'] ) : '—'; ?></td>
						<td class="pp-td pp-td-compact"><?php echo esc_html( $game['venue'] ?? '' ); ?></td>
						<td class="pp-td pp-td-compact">
							<?php if ( ! empty( $game['game_status'] ) ) : ?>
								<span class="pp-tag pp-tag-<?php echo sanitize_html_class( strtolower( $game['game_status'] ) ); ?>">
									<?php echo esc_html( ucfirst( (string) $game['game_status'] ) ); ?>
								</span>
							<?php endif; ?>
						</td>
						<td class="pp-td pp-td-compact"><?php echo esc_html( ucfirst( $game['home_or_away'] ?? '' ) ); ?></td>
						<td class="pp-td pp-td-compact">
							<span class="pp-tag <?php echo esc_attr( $source_tag_class ); ?>">
								<?php echo esc_html( $game['source'] ?? 'No Source' ); ?>
							</span>
						</td>
						<td class="pp-td pp-td-compact"></td>
						<td class="pp-td pp-td-compact">
							<button class="pp-button-icon pp-restore-game-button" title="<?php esc_attr_e( 'Restore game', 'puck-press' ); ?>" data-delete-mod-id="<?php echo esc_attr( $game['delete_mod_id'] ); ?>">↩</button>
						</td>
					</tr>
					<?php else : ?>
					<tr
						data-id="<?php echo esc_attr( $game['game_id'] ); ?>"
						data-source-type="<?php echo esc_attr( $source_type ); ?>"
						data-mod-id="<?php echo esc_attr( $mod_id ); ?>"
						data-overrides="<?php echo esc_attr( wp_json_encode( $override_keys ) ); ?>"
						data-home-or-away="<?php echo esc_attr( strtolower( $game['home_or_away'] ?? '' ) ); ?>"
						data-venue="<?php echo esc_attr( $game['venue'] ?? '' ); ?>"
						data-timestamp="<?php echo esc_attr( ! empty( $game['game_timestamp'] ) ? strtotime( $game['game_timestamp'] ) : '0' ); ?>"
						data-opponent="<?php echo esc_attr( $game['opponent_team_name'] ?? '' ); ?>">
						<td class="pp-td pp-td-compact"><?php echo esc_html( $game['game_id'] ); ?></td>
						<td class="pp-td pp-td-compact" data-field="game_date"><?php echo esc_html( date( 'M d, Y', strtotime( $game['game_timestamp'] ) ) ); ?></td>
						<td class="pp-td pp-td-compact" data-field="game_time"><?php echo esc_html( $game['game_time'] ?? '' ); ?></td>
						<td class="pp-td pp-td-compact"><?php echo esc_html( $game['target_team_name'] ); ?></td>
						<td class="pp-td pp-td-compact" data-field="target_score"><?php echo $game['target_score'] !== null ? esc_html( $game['target_score'] ) : '—'; ?></td>
						<td class="pp-td pp-td-compact"><?php echo esc_html( $game['opponent_team_name'] ); ?></td>
						<td class="pp-td pp-td-compact" data-field="opponent_score"><?php echo $game['opponent_score'] !== null ? esc_html( $game['opponent_score'] ) : '—'; ?></td>
						<td class="pp-td pp-td-compact" data-field="venue"><?php echo esc_html( $game['venue'] ); ?></td>
						<td class="pp-td pp-td-compact" data-field="game_status">
							<?php if ( ! is_null( $game['game_status'] ) ) : ?>
								<span class="pp-tag pp-tag-<?php echo sanitize_html_class( strtolower( $game['game_status'] ) ); ?>">
									<?php echo esc_html( ucfirst( (string) ( $game['game_status'] ?? '' ) ) ); ?>
								</span>
							<?php endif; ?>
						</td>
						<td class="pp-td pp-td-compact" data-field="home_or_away"><?php echo esc_html( ucfirst( $game['home_or_away'] ?? '' ) ); ?></td>
						<td class="pp-td pp-td-compact">
							<span class="pp-tag <?php echo esc_attr( $source_tag_class ); ?>">
								<?php echo esc_html( isset( $game['source'] ) ? $game['source'] : 'No Source' ); ?>
							</span>
							<?php if ( $has_hidden_overrides ) : ?>
								<span class="pp-tag pp-tag-has-hidden-edits" title="<?php esc_attr_e( 'Has additional edits (promo content)', 'puck-press' ); ?>">+edits</span>
							<?php endif; ?>
						</td>
						<td class="pp-td pp-td-compact">
							<?php if ( ! empty( $game['post_link'] ) ) : ?>
								<a href="<?php echo esc_url( $game['post_link'] ); ?>" target="_blank" title="<?php echo esc_attr( $game['post_link'] ); ?>" class="pp-post-link-icon pp-tag pp-tag-regular-season">📰 Post Summary</a>
							<?php endif; ?>
						</td>
						<td class="pp-td pp-td-compact">
							<div class="pp-flex-small-gap">
								<button class="pp-button-icon pp-edit-game-btn" data-game-id="<?php echo esc_attr( $game['game_id'] ); ?>">✏️</button>
								<button class="pp-button-icon pp-delete-game-btn" data-game-id="<?php echo esc_attr( $game['game_id'] ); ?>">🗑️</button>
							</div>
						</td>
					</tr>
					<?php endif; ?>
				<?php endforeach; ?>
			</tbody>
		</table>
		<?php
		return ob_get_clean();
	}
}
