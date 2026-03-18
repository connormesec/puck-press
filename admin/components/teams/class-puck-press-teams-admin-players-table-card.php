<?php

class Puck_Press_Teams_Admin_Players_Table_Card extends Puck_Press_Admin_Card_Abstract {

    const HEADSHOT_FALLBACK = 'data:image/svg+xml,%3C%3Fxml%20version%3D%221.0%22%20encoding%3D%22utf-8%22%3F%3E%3C!--%20License%3A%20MIT.%20Made%20by%20vmware%3A%20https%3A%2F%2Fgithub.com%2Fvmware%2Fclarity-assets%20--%3E%3Csvg%20fill%3D%22%23cdcccc%22%20width%3D%22800px%22%20height%3D%22800px%22%20viewBox%3D%220%200%2036%2036%22%20version%3D%221.1%22%20%20preserveAspectRatio%3D%22xMidYMid%20meet%22%20xmlns%3D%22http%3A%2F%2Fwww.w3.org%2F2000%2Fsvg%22%20xmlns%3Axlink%3D%22http%3A%2F%2Fwww.w3.org%2F1999%2Fxlink%22%3E%3Ctitle%3Eavatar-solid%3C%2Ftitle%3E%3Cpath%20d%3D%22M30.61%2C24.52a17.16%2C17.16%2C0%2C0%2C0-25.22%2C0%2C1.51%2C1.51%2C0%2C0%2C0-.39%2C1v6A1.5%2C1.5%2C0%2C0%2C0%2C6.5%2C33h23A1.5%2C1.5%2C0%2C0%2C0%2C31%2C31.5v-6A1.51%2C1.51%2C0%2C0%2C0%2C30.61%2C24.52Z%22%20class%3D%22clr-i-solid%20clr-i-solid-path-1%22%3E%3C%2Fpath%3E%3Ccircle%20cx%3D%2218%22%20cy%3D%2210%22%20r%3D%227%22%20class%3D%22clr-i-solid%20clr-i-solid-path-2%22%3E%3C%2Fcircle%3E%3Crect%20x%3D%220%22%20y%3D%220%22%20width%3D%2236%22%20height%3D%2236%22%20fill-opacity%3D%220%22%2F%3E%3C%2Fsvg%3E';

    private int $team_id;

    public function __construct( int $team_id = 0 ) {
        parent::__construct( array(
            'title' => 'Players',
            'id'    => 'roster-players',
        ) );
        $this->team_id = $team_id;
    }

    public function render_header_button_content() {
        return '
            <button class="pp-button pp-button-secondary" id="pp-bulk-edit-team-players-btn">Bulk Edit Players</button>
            <button class="pp-button pp-button-primary" id="pp-add-player-btn">+ Add Player</button>
        ';
    }

    public function render_content() {
        ob_start();
        echo $this->render_players_table();
        echo $this->render_add_player_modal();
        echo $this->render_edit_player_modal();
        return ob_get_clean();
    }

    public function render_players_table() {
        global $wpdb;
        $display_table = $wpdb->prefix . 'pp_team_players_display';
        $mods_table    = $wpdb->prefix . 'pp_team_player_mods';
        $raw_table     = $wpdb->prefix . 'pp_team_players_raw';

        $active_players = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT f.*, m.edit_data AS override_data, m.id AS mod_id
                 FROM $display_table f
                 LEFT JOIN $mods_table m ON f.player_id COLLATE utf8mb4_unicode_ci = m.external_id COLLATE utf8mb4_unicode_ci AND m.edit_action = 'update' AND m.team_id = %d
                 WHERE f.team_id = %d",
                $this->team_id,
                $this->team_id
            ),
            ARRAY_A
        ) ?: array();

        $deleted_players = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT r.*, dm.id AS delete_mod_id
                 FROM $raw_table r
                 INNER JOIN $mods_table dm ON r.player_id COLLATE utf8mb4_unicode_ci = dm.external_id COLLATE utf8mb4_unicode_ci AND dm.edit_action = 'delete' AND dm.team_id = %d
                 WHERE r.team_id = %d",
                $this->team_id,
                $this->team_id
            ),
            ARRAY_A
        ) ?: array();

        foreach ( $active_players as &$p ) {
            $p['row_status']    = 'active';
            $p['delete_mod_id'] = null;
        }
        unset( $p );

        foreach ( $deleted_players as &$p ) {
            $p['row_status']    = 'deleted';
            $p['mod_id']        = null;
            $p['override_data'] = null;
        }
        unset( $p );

        $players = array_merge( $active_players, $deleted_players );

        usort(
            $players,
            function ( $a, $b ) {
                $na = (int) ( $a['number'] ?? 999 );
                $nb = (int) ( $b['number'] ?? 999 );
                if ( $na !== $nb ) {
                    return $na - $nb;
                }
                return strcmp( $a['name'] ?? '', $b['name'] ?? '' );
            }
        );

        $count = count( array_filter( $players, fn( $p ) => $p['row_status'] === 'active' ) );

        $skip_keys = array( 'external_id', 'player_id', 'team_id', 'source', 'created_at', 'updated_at' );

        ob_start();
        ?>
        <div id="pp-team-players-table-wrapper">
        <p class="pp-card-subtitle" style="margin-bottom:12px;"><?php echo esc_html( $count . ' player' . ( $count !== 1 ? 's' : '' ) ); ?></p>
        <?php if ( empty( $players ) ) : ?>
            <p style="color:#888;"><?php esc_html_e( 'No players yet.', 'puck-press' ); ?></p>
        <?php else : ?>
            <table class="pp-table" id="pp-team-players-edits-table">
                <thead class="pp-thead">
                    <tr>
                        <th class="pp-th"><?php esc_html_e( 'ID', 'puck-press' ); ?></th>
                        <th class="pp-th"><?php esc_html_e( 'Source', 'puck-press' ); ?></th>
                        <th class="pp-th"><?php esc_html_e( '#', 'puck-press' ); ?></th>
                        <th class="pp-th"><?php esc_html_e( 'Name', 'puck-press' ); ?></th>
                        <th class="pp-th"><?php esc_html_e( 'Pos', 'puck-press' ); ?></th>
                        <th class="pp-th"><?php esc_html_e( 'Ht', 'puck-press' ); ?></th>
                        <th class="pp-th"><?php esc_html_e( 'Wt', 'puck-press' ); ?></th>
                        <th class="pp-th"><?php esc_html_e( 'Shoots', 'puck-press' ); ?></th>
                        <th class="pp-th"><?php esc_html_e( 'Hometown', 'puck-press' ); ?></th>
                        <th class="pp-th"><?php esc_html_e( 'Last Team', 'puck-press' ); ?></th>
                        <th class="pp-th"><?php esc_html_e( 'Year', 'puck-press' ); ?></th>
                        <th class="pp-th"><?php esc_html_e( 'Major', 'puck-press' ); ?></th>
                        <th class="pp-th"><?php esc_html_e( 'Headshot', 'puck-press' ); ?></th>
                        <th class="pp-th"><?php esc_html_e( 'Hero Image', 'puck-press' ); ?></th>
                        <th class="pp-th"><?php esc_html_e( 'Actions', 'puck-press' ); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    foreach ( $players as $player ) :
                        $is_deleted       = $player['row_status'] === 'deleted';
                        $is_manual        = strpos( $player['player_id'], 'manual_' ) === 0;
                        $source_type      = $is_manual ? 'manual' : 'sourced';
                        $source_tag_class = $is_manual ? 'pp-tag-manual' : 'pp-tag-regular-season';

                        $override_keys = array();
                        if ( ! $is_deleted && ! empty( $player['override_data'] ) ) {
                            $decoded = json_decode( $player['override_data'], true );
                            if ( is_array( $decoded ) ) {
                                $override_keys = array_values( array_diff( array_keys( $decoded ), $skip_keys ) );
                            }
                        }
                        $mod_id = $player['mod_id'] ?? '';
                        ?>
                        <?php if ( $is_deleted ) : ?>
                        <tr class="pp-row-deleted"
                            data-player-id="<?php echo esc_attr( $player['player_id'] ); ?>"
                            data-source-type="sourced"
                            data-overrides="[]">
                            <td class="pp-td pp-td-compact"><?php echo esc_html( $player['player_id'] ); ?></td>
                            <td class="pp-td pp-td-compact">
                                <span class="pp-tag <?php echo esc_attr( $source_tag_class ); ?>">
                                    <?php echo esc_html( $player['source'] ?? 'sourced' ); ?>
                                </span>
                            </td>
                            <td class="pp-td pp-td-compact"><?php echo esc_html( $player['number'] ?? '' ); ?></td>
                            <td class="pp-td pp-td-compact"><?php echo esc_html( $player['name'] ?? '' ); ?></td>
                            <td class="pp-td pp-td-compact"><?php echo esc_html( $player['pos'] ?? '' ); ?></td>
                            <td class="pp-td pp-td-compact"><?php echo esc_html( $player['ht'] ?? '' ); ?></td>
                            <td class="pp-td pp-td-compact"><?php echo esc_html( $player['wt'] ?? '' ); ?></td>
                            <td class="pp-td pp-td-compact"><?php echo esc_html( $player['shoots'] ?? '' ); ?></td>
                            <td class="pp-td pp-td-compact"><?php echo esc_html( $player['hometown'] ?? '' ); ?></td>
                            <td class="pp-td pp-td-compact"><?php echo esc_html( $player['last_team'] ?? '' ); ?></td>
                            <td class="pp-td pp-td-compact"><?php echo esc_html( $player['year_in_school'] ?? '' ); ?></td>
                            <td class="pp-td pp-td-compact"><?php echo esc_html( $player['major'] ?? '' ); ?></td>
                            <td class="pp-td pp-td-compact">
                                <?php $hs = ! empty( $player['headshot_link'] ) ? esc_url( $player['headshot_link'] ) : self::HEADSHOT_FALLBACK; ?>
                                <img src="<?php echo $hs; ?>" onerror="this.onerror=null;this.src='<?php echo self::HEADSHOT_FALLBACK; ?>';" style="width:40px;height:40px;object-fit:cover;border-radius:4px;" alt="">
                            </td>
                            <td class="pp-td pp-td-compact">
                                <?php if ( ! empty( $player['hero_image_url'] ) ) : ?>
                                    <img src="<?php echo esc_url( $player['hero_image_url'] ); ?>" style="width:40px;height:40px;object-fit:cover;border-radius:4px;" alt="">
                                <?php endif; ?>
                            </td>
                            <td class="pp-td pp-td-compact">
                                <button class="pp-button-icon pp-restore-team-player-btn" title="<?php esc_attr_e( 'Restore player', 'puck-press' ); ?>" data-delete-mod-id="<?php echo esc_attr( $player['delete_mod_id'] ); ?>">&#x21A9;</button>
                            </td>
                        </tr>
                        <?php else : ?>
                        <tr
                            data-player-id="<?php echo esc_attr( $player['player_id'] ); ?>"
                            data-source-type="<?php echo esc_attr( $source_type ); ?>"
                            data-mod-id="<?php echo esc_attr( $mod_id ); ?>"
                            data-overrides="<?php echo esc_attr( wp_json_encode( $override_keys ) ); ?>"
                            data-pos="<?php echo esc_attr( $player['pos'] ?? '' ); ?>"
                            data-name="<?php echo esc_attr( $player['name'] ?? '' ); ?>">
                            <td class="pp-td pp-td-compact"><?php echo esc_html( $player['player_id'] ); ?></td>
                            <td class="pp-td pp-td-compact">
                                <span class="pp-tag <?php echo esc_attr( $source_tag_class ); ?>">
                                    <?php echo esc_html( $player['source'] ?? ( $is_manual ? 'manual' : 'sourced' ) ); ?>
                                </span>
                            </td>
                            <td class="pp-td pp-td-compact<?php echo in_array( 'number', $override_keys ) ? ' pp-cell-overridden' : ''; ?>" data-field="number"><?php echo esc_html( $player['number'] ?? '' ); ?></td>
                            <td class="pp-td pp-td-compact<?php echo in_array( 'name', $override_keys ) ? ' pp-cell-overridden' : ''; ?>" data-field="name"><?php echo esc_html( $player['name'] ?? '' ); ?></td>
                            <td class="pp-td pp-td-compact<?php echo in_array( 'pos', $override_keys ) ? ' pp-cell-overridden' : ''; ?>" data-field="pos"><?php echo esc_html( $player['pos'] ?? '' ); ?></td>
                            <td class="pp-td pp-td-compact<?php echo in_array( 'ht', $override_keys ) ? ' pp-cell-overridden' : ''; ?>" data-field="ht"><?php echo esc_html( $player['ht'] ?? '' ); ?></td>
                            <td class="pp-td pp-td-compact<?php echo in_array( 'wt', $override_keys ) ? ' pp-cell-overridden' : ''; ?>" data-field="wt"><?php echo esc_html( $player['wt'] ?? '' ); ?></td>
                            <td class="pp-td pp-td-compact<?php echo in_array( 'shoots', $override_keys ) ? ' pp-cell-overridden' : ''; ?>" data-field="shoots"><?php echo esc_html( $player['shoots'] ?? '' ); ?></td>
                            <td class="pp-td pp-td-compact<?php echo in_array( 'hometown', $override_keys ) ? ' pp-cell-overridden' : ''; ?>" data-field="hometown"><?php echo esc_html( $player['hometown'] ?? '' ); ?></td>
                            <td class="pp-td pp-td-compact<?php echo in_array( 'last_team', $override_keys ) ? ' pp-cell-overridden' : ''; ?>" data-field="last_team"><?php echo esc_html( $player['last_team'] ?? '' ); ?></td>
                            <td class="pp-td pp-td-compact<?php echo in_array( 'year_in_school', $override_keys ) ? ' pp-cell-overridden' : ''; ?>" data-field="year_in_school"><?php echo esc_html( $player['year_in_school'] ?? '' ); ?></td>
                            <td class="pp-td pp-td-compact<?php echo in_array( 'major', $override_keys ) ? ' pp-cell-overridden' : ''; ?>" data-field="major"><?php echo esc_html( $player['major'] ?? '' ); ?></td>
                            <td class="pp-td pp-td-compact<?php echo in_array( 'headshot_link', $override_keys ) ? ' pp-cell-overridden' : ''; ?>" data-field="headshot_link">
                                <?php $hs = ! empty( $player['headshot_link'] ) ? esc_url( $player['headshot_link'] ) : self::HEADSHOT_FALLBACK; ?>
                                <img src="<?php echo $hs; ?>" onerror="this.onerror=null;this.src='<?php echo self::HEADSHOT_FALLBACK; ?>';" style="width:40px;height:40px;object-fit:cover;border-radius:4px;" alt="">
                            </td>
                            <td class="pp-td pp-td-compact<?php echo in_array( 'hero_image_url', $override_keys ) ? ' pp-cell-overridden' : ''; ?>" data-field="hero_image_url">
                                <?php if ( ! empty( $player['hero_image_url'] ) ) : ?>
                                    <img src="<?php echo esc_url( $player['hero_image_url'] ); ?>" style="width:40px;height:40px;object-fit:cover;border-radius:4px;" alt="">
                                <?php endif; ?>
                            </td>
                            <td class="pp-td pp-td-compact">
                                <div class="pp-flex-small-gap">
                                    <button class="pp-button-icon pp-edit-team-player-btn" data-player-id="<?php echo esc_attr( $player['player_id'] ); ?>">&#x270F;&#xFE0F;</button>
                                    <button class="pp-button-icon pp-delete-team-player-btn" data-player-id="<?php echo esc_attr( $player['player_id'] ); ?>" data-source-type="<?php echo esc_attr( $source_type ); ?>">&#x1F5D1;&#xFE0F;</button>
                                </div>
                            </td>
                        </tr>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
        </div>
        <?php
        return ob_get_clean();
    }

    private function rebuild_and_get_table_html( int $team_id ): string {
        ( new Puck_Press_Team_Roster_Importer( $team_id ) )->rebuild_display_from_mods();
        return ( new self( $team_id ) )->render_players_table();
    }

    public function ajax_get_team_player_data_callback(): void {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( array( 'message' => 'Insufficient permissions.' ) );
        }

        global $wpdb;

        $player_id = sanitize_text_field( $_POST['player_id'] ?? '' );
        $team_id   = (int) ( $_POST['team_id'] ?? 0 );

        if ( ! $player_id || ! $team_id ) {
            wp_send_json_error( array( 'message' => 'Invalid player or team ID.' ) );
        }

        $table  = $wpdb->prefix . 'pp_team_players_display';
        $player = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM $table WHERE player_id = %s AND team_id = %d LIMIT 1",
                $player_id,
                $team_id
            ),
            ARRAY_A
        );

        if ( ! $player ) {
            wp_send_json_error( array( 'message' => 'Player not found.' ) );
        }

        wp_send_json_success( array( 'player' => $player ) );
    }

    public function ajax_update_team_player_edit_callback(): void {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( array( 'message' => 'Insufficient permissions.' ) );
        }

        global $wpdb;

        $team_id   = (int) ( $_POST['team_id'] ?? 0 );
        $edit_json = stripslashes( $_POST['edit_data'] ?? '' );

        if ( ! $team_id || ! $edit_json ) {
            wp_send_json_error( array( 'message' => 'Missing required data.' ) );
        }

        $edit_data = json_decode( $edit_json, true );
        if ( ! is_array( $edit_data ) ) {
            wp_send_json_error( array( 'message' => 'Invalid edit data.' ) );
        }

        $edit_action = sanitize_text_field( $edit_data['edit_action'] ?? 'update' );
        $fields      = $edit_data['fields'] ?? array();
        $external_id = sanitize_text_field( $fields['external_id'] ?? '' );

        if ( ! $external_id ) {
            wp_send_json_error( array( 'message' => 'Missing external_id.' ) );
        }

        $mods_table = $wpdb->prefix . 'pp_team_player_mods';

        $existing_mod = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM $mods_table WHERE external_id = %s AND edit_action = 'update' AND team_id = %d LIMIT 1",
                $external_id,
                $team_id
            ),
            ARRAY_A
        );

        if ( $existing_mod ) {
            $existing_fields = json_decode( $existing_mod['edit_data'], true ) ?: array();
            $merged          = array_merge( $existing_fields, $fields );
            $wpdb->update(
                $mods_table,
                array(
                    'edit_data'  => wp_json_encode( $merged ),
                    'updated_at' => current_time( 'mysql' ),
                ),
                array( 'id' => $existing_mod['id'] )
            );
        } else {
            $wpdb->insert(
                $mods_table,
                array(
                    'team_id'     => $team_id,
                    'external_id' => $external_id,
                    'edit_action' => $edit_action,
                    'edit_data'   => wp_json_encode( $fields ),
                    'created_at'  => current_time( 'mysql' ),
                    'updated_at'  => current_time( 'mysql' ),
                )
            );
        }

        $table_html = $this->rebuild_and_get_table_html( $team_id );
        wp_send_json_success( array( 'roster_table_html' => $table_html ) );
    }

    public function ajax_revert_team_player_field_callback(): void {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( array( 'message' => 'Insufficient permissions.' ) );
        }

        global $wpdb;

        $team_id = (int) ( $_POST['team_id'] ?? 0 );
        $mod_id  = (int) ( $_POST['mod_id'] ?? 0 );
        $fields  = array_map( 'sanitize_text_field', (array) ( $_POST['fields'] ?? array() ) );

        if ( ! $team_id || ! $mod_id || empty( $fields ) ) {
            wp_send_json_error( array( 'message' => 'Missing required data.' ) );
        }

        $mods_table = $wpdb->prefix . 'pp_team_player_mods';
        $mod        = $wpdb->get_row(
            $wpdb->prepare( "SELECT * FROM $mods_table WHERE id = %d AND team_id = %d LIMIT 1", $mod_id, $team_id ),
            ARRAY_A
        );

        if ( ! $mod ) {
            wp_send_json_error( array( 'message' => 'Mod not found.' ) );
        }

        $existing = json_decode( $mod['edit_data'], true ) ?: array();
        foreach ( $fields as $field ) {
            unset( $existing[ $field ] );
        }

        $meaningful_keys = array_diff( array_keys( $existing ), array( 'external_id', 'edit_action' ) );
        if ( empty( $meaningful_keys ) ) {
            $wpdb->delete( $mods_table, array( 'id' => $mod_id ) );
        } else {
            $wpdb->update(
                $mods_table,
                array(
                    'edit_data'  => wp_json_encode( $existing ),
                    'updated_at' => current_time( 'mysql' ),
                ),
                array( 'id' => $mod_id )
            );
        }

        $table_html = $this->rebuild_and_get_table_html( $team_id );
        wp_send_json_success( array( 'roster_table_html' => $table_html ) );
    }

    public function ajax_restore_team_player_callback(): void {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( array( 'message' => 'Insufficient permissions.' ) );
        }

        global $wpdb;

        $team_id      = (int) ( $_POST['team_id'] ?? 0 );
        $delete_mod_id = (int) ( $_POST['delete_mod_id'] ?? 0 );

        if ( ! $team_id || ! $delete_mod_id ) {
            wp_send_json_error( array( 'message' => 'Missing required data.' ) );
        }

        $wpdb->delete(
            $wpdb->prefix . 'pp_team_player_mods',
            array( 'id' => $delete_mod_id )
        );

        $table_html = $this->rebuild_and_get_table_html( $team_id );
        wp_send_json_success( array( 'roster_table_html' => $table_html ) );
    }

    public function ajax_delete_team_manual_player_callback(): void {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( array( 'message' => 'Insufficient permissions.' ) );
        }

        global $wpdb;

        $team_id   = (int) ( $_POST['team_id'] ?? 0 );
        $player_id = sanitize_text_field( $_POST['player_id'] ?? '' );

        if ( ! $team_id || ! $player_id ) {
            wp_send_json_error( array( 'message' => 'Missing required data.' ) );
        }

        $wpdb->delete(
            $wpdb->prefix . 'pp_team_player_mods',
            array(
                'external_id' => $player_id,
                'edit_action' => 'insert',
                'team_id'     => $team_id,
            )
        );

        $table_html = $this->rebuild_and_get_table_html( $team_id );
        wp_send_json_success( array( 'roster_table_html' => $table_html ) );
    }

    public function ajax_reset_all_team_player_edits_callback(): void {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( array( 'message' => 'Insufficient permissions.' ) );
        }

        global $wpdb;

        $team_id = (int) ( $_POST['team_id'] ?? 0 );

        if ( ! $team_id ) {
            wp_send_json_error( array( 'message' => 'Missing team ID.' ) );
        }

        $wpdb->delete(
            $wpdb->prefix . 'pp_team_player_mods',
            array( 'team_id' => $team_id )
        );

        $table_html = $this->rebuild_and_get_table_html( $team_id );
        wp_send_json_success( array( 'roster_table_html' => $table_html ) );
    }

    public function ajax_bulk_update_team_player_field_callback(): void {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( array( 'message' => 'Insufficient permissions.' ) );
        }

        check_ajax_referer( 'pp_team_players_nonce', 'nonce' );

        global $wpdb;

        $team_id    = (int) ( $_POST['team_id'] ?? 0 );
        $field      = sanitize_key( $_POST['field'] ?? '' );
        $value      = sanitize_text_field( $_POST['value'] ?? '' );
        $player_ids = json_decode( stripslashes( $_POST['player_ids'] ?? '[]' ), true );

        $allowed_fields = array( 'hero_image_url', 'headshot_link' );

        if ( ! $team_id || ! in_array( $field, $allowed_fields, true ) || ! is_array( $player_ids ) ) {
            wp_send_json_error( array( 'message' => 'Invalid request.' ) );
        }

        $mods_table    = $wpdb->prefix . 'pp_team_player_mods';
        $display_table = $wpdb->prefix . 'pp_team_players_display';

        foreach ( $player_ids as $player_id ) {
            $player_id = sanitize_text_field( $player_id );
            if ( ! $player_id ) {
                continue;
            }

            $is_deleted = $wpdb->get_var(
                $wpdb->prepare(
                    "SELECT COUNT(*) FROM $mods_table WHERE external_id = %s AND edit_action = 'delete' AND team_id = %d",
                    $player_id,
                    $team_id
                )
            );

            if ( $is_deleted ) {
                continue;
            }

            $existing_mod = $wpdb->get_row(
                $wpdb->prepare(
                    "SELECT * FROM $mods_table WHERE external_id = %s AND edit_action = 'update' AND team_id = %d LIMIT 1",
                    $player_id,
                    $team_id
                ),
                ARRAY_A
            );

            if ( $existing_mod ) {
                $existing_fields          = json_decode( $existing_mod['edit_data'], true ) ?: array();
                $existing_fields[ $field ] = $value;
                $wpdb->update(
                    $mods_table,
                    array(
                        'edit_data'  => wp_json_encode( $existing_fields ),
                        'updated_at' => current_time( 'mysql' ),
                    ),
                    array( 'id' => $existing_mod['id'] )
                );
            } else {
                $wpdb->insert(
                    $mods_table,
                    array(
                        'team_id'     => $team_id,
                        'external_id' => $player_id,
                        'edit_action' => 'update',
                        'edit_data'   => wp_json_encode( array( 'external_id' => $player_id, $field => $value ) ),
                        'created_at'  => current_time( 'mysql' ),
                        'updated_at'  => current_time( 'mysql' ),
                    )
                );
            }
        }

        $table_html = $this->rebuild_and_get_table_html( $team_id );
        wp_send_json_success( array( 'roster_table_html' => $table_html ) );
    }

    public function ajax_bulk_revert_team_player_edits_callback(): void {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( array( 'message' => 'Insufficient permissions.' ) );
        }

        check_ajax_referer( 'pp_team_players_nonce', 'nonce' );

        global $wpdb;

        $team_id    = (int) ( $_POST['team_id'] ?? 0 );
        $player_ids = json_decode( stripslashes( $_POST['player_ids'] ?? '[]' ), true );

        if ( ! $team_id || ! is_array( $player_ids ) ) {
            wp_send_json_error( array( 'message' => 'Invalid request.' ) );
        }

        $mods_table = $wpdb->prefix . 'pp_team_player_mods';

        foreach ( $player_ids as $player_id ) {
            $player_id = sanitize_text_field( $player_id );
            if ( ! $player_id ) {
                continue;
            }
            $wpdb->delete(
                $mods_table,
                array(
                    'external_id' => $player_id,
                    'edit_action' => 'update',
                    'team_id'     => $team_id,
                )
            );
        }

        $table_html = $this->rebuild_and_get_table_html( $team_id );
        wp_send_json_success( array( 'roster_table_html' => $table_html ) );
    }

    public function ajax_add_team_manual_player_and_table_callback(): void {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( array( 'message' => 'Insufficient permissions.' ) );
        }

        global $wpdb;

        $team_id = (int) ( $_POST['team_id'] ?? 0 );
        if ( ! $team_id ) {
            wp_send_json_error( array( 'message' => 'Invalid team ID.' ) );
        }

        $edit_data = array(
            'player_id'      => 'manual_' . uniqid(),
            'number'         => sanitize_text_field( $_POST['number'] ?? '' ),
            'name'           => sanitize_text_field( $_POST['name'] ?? '' ),
            'pos'            => sanitize_text_field( $_POST['pos'] ?? '' ),
            'ht'             => sanitize_text_field( $_POST['ht'] ?? '' ),
            'wt'             => sanitize_text_field( $_POST['wt'] ?? '' ),
            'shoots'         => sanitize_text_field( $_POST['shoots'] ?? '' ),
            'hometown'       => sanitize_text_field( $_POST['hometown'] ?? '' ),
            'last_team'      => sanitize_text_field( $_POST['last_team'] ?? '' ),
            'year_in_school' => sanitize_text_field( $_POST['year_in_school'] ?? '' ),
            'major'          => sanitize_text_field( $_POST['major'] ?? '' ),
            'headshot_link'  => esc_url_raw( $_POST['headshot_link'] ?? '' ),
            'hero_image_url' => esc_url_raw( $_POST['hero_image_url'] ?? '' ),
        );

        $wpdb->insert(
            $wpdb->prefix . 'pp_team_player_mods',
            array(
                'team_id'     => $team_id,
                'edit_action' => 'insert',
                'edit_data'   => wp_json_encode( $edit_data ),
                'created_at'  => current_time( 'mysql' ),
                'updated_at'  => current_time( 'mysql' ),
            )
        );

        $table_html = $this->rebuild_and_get_table_html( $team_id );
        wp_send_json_success( array( 'roster_table_html' => $table_html ) );
    }

    private function render_add_player_modal() {
        ob_start();
        ?>
        <div class="pp-modal-overlay" id="pp-add-player-modal" style="display:none;">
            <div class="pp-modal">
                <button class="pp-modal-close" id="pp-add-player-modal-close">&#x2715;</button>
                <div class="pp-modal-header">
                    <h3 class="pp-modal-title"><?php esc_html_e( 'Add Player', 'puck-press' ); ?></h3>
                    <p class="pp-modal-subtitle"><?php esc_html_e( 'Manually add a player to the roster', 'puck-press' ); ?></p>
                </div>
                <div class="pp-modal-content">
                    <form id="pp-add-player-form">
                        <div class="pp-form-row">
                            <div class="pp-form-group">
                                <label for="pp-new-player-name" class="pp-form-label"><?php esc_html_e( 'Name', 'puck-press' ); ?> <span style="color:red">*</span></label>
                                <input type="text" id="pp-new-player-name" class="pp-form-input" required>
                            </div>
                        </div>
                        <div class="pp-form-row">
                            <div class="pp-form-group">
                                <label for="pp-new-player-number" class="pp-form-label"><?php esc_html_e( 'Number', 'puck-press' ); ?></label>
                                <input type="text" id="pp-new-player-number" class="pp-form-input">
                            </div>
                            <div class="pp-form-group">
                                <label for="pp-new-player-position" class="pp-form-label"><?php esc_html_e( 'Position', 'puck-press' ); ?></label>
                                <select id="pp-new-player-position" class="pp-form-select">
                                    <option value="">-- <?php esc_html_e( 'Select', 'puck-press' ); ?> --</option>
                                    <option value="forward"><?php esc_html_e( 'Forward', 'puck-press' ); ?></option>
                                    <option value="defense"><?php esc_html_e( 'Defense', 'puck-press' ); ?></option>
                                    <option value="goalie"><?php esc_html_e( 'Goalie', 'puck-press' ); ?></option>
                                </select>
                            </div>
                            <div class="pp-form-group">
                                <label for="pp-new-player-height" class="pp-form-label"><?php esc_html_e( 'Height', 'puck-press' ); ?></label>
                                <input type="text" id="pp-new-player-height" class="pp-form-input">
                            </div>
                            <div class="pp-form-group">
                                <label for="pp-new-player-weight" class="pp-form-label"><?php esc_html_e( 'Weight', 'puck-press' ); ?></label>
                                <input type="text" id="pp-new-player-weight" class="pp-form-input">
                            </div>
                            <div class="pp-form-group">
                                <label for="pp-new-player-shoots" class="pp-form-label"><?php esc_html_e( 'Shoots', 'puck-press' ); ?></label>
                                <select id="pp-new-player-shoots" class="pp-form-select">
                                    <option value="">-- <?php esc_html_e( 'Select', 'puck-press' ); ?> --</option>
                                    <option value="right"><?php esc_html_e( 'Right', 'puck-press' ); ?></option>
                                    <option value="left"><?php esc_html_e( 'Left', 'puck-press' ); ?></option>
                                </select>
                            </div>
                        </div>
                        <div class="pp-form-row">
                            <div class="pp-form-group">
                                <label for="pp-new-player-hometown" class="pp-form-label"><?php esc_html_e( 'Hometown', 'puck-press' ); ?></label>
                                <input type="text" id="pp-new-player-hometown" class="pp-form-input">
                            </div>
                            <div class="pp-form-group">
                                <label for="pp-new-player-last-team" class="pp-form-label"><?php esc_html_e( 'Last Team', 'puck-press' ); ?></label>
                                <input type="text" id="pp-new-player-last-team" class="pp-form-input">
                            </div>
                        </div>
                        <div class="pp-form-row">
                            <div class="pp-form-group">
                                <label for="pp-new-player-year" class="pp-form-label"><?php esc_html_e( 'Year', 'puck-press' ); ?></label>
                                <select id="pp-new-player-year" class="pp-form-select">
                                    <option value="">-- <?php esc_html_e( 'Select', 'puck-press' ); ?> --</option>
                                    <option value="freshman"><?php esc_html_e( 'Freshman', 'puck-press' ); ?></option>
                                    <option value="sophomore"><?php esc_html_e( 'Sophomore', 'puck-press' ); ?></option>
                                    <option value="junior"><?php esc_html_e( 'Junior', 'puck-press' ); ?></option>
                                    <option value="senior"><?php esc_html_e( 'Senior', 'puck-press' ); ?></option>
                                </select>
                            </div>
                            <div class="pp-form-group">
                                <label for="pp-new-player-major" class="pp-form-label"><?php esc_html_e( 'Major', 'puck-press' ); ?></label>
                                <input type="text" id="pp-new-player-major" class="pp-form-input">
                            </div>
                        </div>
                        <div class="pp-form-row">
                            <div class="pp-form-group">
                                <label for="pp-new-player-headshot-url" class="pp-form-label"><?php esc_html_e( 'Headshot URL', 'puck-press' ); ?></label>
                                <input type="text" id="pp-new-player-headshot-url" class="pp-form-input">
                            </div>
                        </div>
                        <div class="pp-form-row">
                            <div class="pp-form-group">
                                <label for="pp-new-player-hero-image-url" class="pp-form-label"><?php esc_html_e( 'Hero Image', 'puck-press' ); ?></label>
                                <div style="display:flex;gap:8px;align-items:center;">
                                    <input type="text" id="pp-new-player-hero-image-url" class="pp-form-input" placeholder="<?php esc_attr_e( 'Paste URL or use Browse&hellip;', 'puck-press' ); ?>">
                                    <button type="button" class="pp-button pp-button-secondary pp-hero-image-browse-btn" data-target="#pp-new-player-hero-image-url">Browse&hellip;</button>
                                </div>
                            </div>
                        </div>
                    </form>
                </div>
                <div class="pp-modal-footer">
                    <button class="pp-button pp-button-secondary" id="pp-cancel-add-player"><?php esc_html_e( 'Cancel', 'puck-press' ); ?></button>
                    <button class="pp-button pp-button-primary" id="pp-confirm-add-player"><?php esc_html_e( 'Add Player', 'puck-press' ); ?></button>
                </div>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }

    private function render_edit_player_modal() {
        ob_start();
        ?>
        <div id="pp-edit-player-modal" class="pp-modal-overlay" style="display:none;">
            <div class="pp-modal">
                <div class="pp-modal-header">
                    <h2><?php esc_html_e( 'Edit Player', 'puck-press' ); ?></h2>
                    <div id="pp-edit-player-loading"></div>
                    <button class="pp-modal-close pp-cancel-edit-player">&times;</button>
                </div>
                <div class="pp-modal-body">
                    <form id="pp-edit-player-form">
                        <input type="hidden" id="pp-edit-player-id">
                        <div class="pp-form-group">
                            <label class="pp-form-label"><?php esc_html_e( 'Number', 'puck-press' ); ?></label>
                            <input type="text" id="pp-edit-player-number" class="pp-form-input">
                        </div>
                        <div class="pp-form-group">
                            <label class="pp-form-label"><?php esc_html_e( 'Name', 'puck-press' ); ?></label>
                            <input type="text" id="pp-edit-player-name" class="pp-form-input">
                        </div>
                        <div class="pp-form-group">
                            <label class="pp-form-label"><?php esc_html_e( 'Position', 'puck-press' ); ?></label>
                            <select id="pp-edit-player-pos" class="pp-form-input">
                                <option value="">-- <?php esc_html_e( 'Select', 'puck-press' ); ?> --</option>
                                <option value="forward"><?php esc_html_e( 'Forward', 'puck-press' ); ?></option>
                                <option value="defense"><?php esc_html_e( 'Defense', 'puck-press' ); ?></option>
                                <option value="goalie"><?php esc_html_e( 'Goalie', 'puck-press' ); ?></option>
                            </select>
                        </div>
                        <div class="pp-form-group">
                            <label class="pp-form-label"><?php esc_html_e( 'Height', 'puck-press' ); ?></label>
                            <input type="text" id="pp-edit-player-ht" class="pp-form-input">
                        </div>
                        <div class="pp-form-group">
                            <label class="pp-form-label"><?php esc_html_e( 'Weight', 'puck-press' ); ?></label>
                            <input type="text" id="pp-edit-player-wt" class="pp-form-input">
                        </div>
                        <div class="pp-form-group">
                            <label class="pp-form-label"><?php esc_html_e( 'Shoots', 'puck-press' ); ?></label>
                            <select id="pp-edit-player-shoots" class="pp-form-input">
                                <option value="">-- <?php esc_html_e( 'Select', 'puck-press' ); ?> --</option>
                                <option value="right"><?php esc_html_e( 'Right', 'puck-press' ); ?></option>
                                <option value="left"><?php esc_html_e( 'Left', 'puck-press' ); ?></option>
                            </select>
                        </div>
                        <div class="pp-form-group">
                            <label class="pp-form-label"><?php esc_html_e( 'Hometown', 'puck-press' ); ?></label>
                            <input type="text" id="pp-edit-player-hometown" class="pp-form-input">
                        </div>
                        <div class="pp-form-group">
                            <label class="pp-form-label"><?php esc_html_e( 'Last Team', 'puck-press' ); ?></label>
                            <input type="text" id="pp-edit-player-last-team" class="pp-form-input">
                        </div>
                        <div class="pp-form-group">
                            <label class="pp-form-label"><?php esc_html_e( 'Year in School', 'puck-press' ); ?></label>
                            <select id="pp-edit-player-year" class="pp-form-input">
                                <option value="">-- <?php esc_html_e( 'Select', 'puck-press' ); ?> --</option>
                                <option value="freshman"><?php esc_html_e( 'Freshman', 'puck-press' ); ?></option>
                                <option value="sophomore"><?php esc_html_e( 'Sophomore', 'puck-press' ); ?></option>
                                <option value="junior"><?php esc_html_e( 'Junior', 'puck-press' ); ?></option>
                                <option value="senior"><?php esc_html_e( 'Senior', 'puck-press' ); ?></option>
                            </select>
                        </div>
                        <div class="pp-form-group">
                            <label class="pp-form-label"><?php esc_html_e( 'Major', 'puck-press' ); ?></label>
                            <input type="text" id="pp-edit-player-major" class="pp-form-input">
                        </div>
                        <div class="pp-form-group">
                            <label class="pp-form-label"><?php esc_html_e( 'Headshot URL', 'puck-press' ); ?></label>
                            <input type="text" id="pp-edit-player-headshot" class="pp-form-input">
                        </div>
                        <div class="pp-form-group">
                            <label class="pp-form-label"><?php esc_html_e( 'Hero Image URL', 'puck-press' ); ?></label>
                            <div style="display:flex;gap:8px;align-items:center;">
                                <input type="text" id="pp-edit-player-hero-image-url" class="pp-form-input">
                                <button type="button" class="pp-button pp-button-secondary pp-hero-image-browse-btn" data-target="#pp-edit-player-hero-image-url">Browse&hellip;</button>
                            </div>
                        </div>
                    </form>
                </div>
                <div class="pp-modal-footer">
                    <button class="pp-button pp-cancel-edit-player"><?php esc_html_e( 'Cancel', 'puck-press' ); ?></button>
                    <button class="pp-button pp-button-primary" id="pp-confirm-edit-player"><?php esc_html_e( 'Save Changes', 'puck-press' ); ?></button>
                </div>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }
}
