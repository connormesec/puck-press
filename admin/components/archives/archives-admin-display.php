<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Puck_Press_Archives_Admin_Display {

    private array $archives = array();
    private array $teams    = array();

    public function __construct() {
        require_once plugin_dir_path( __DIR__ ) . '../../includes/archive/class-puck-press-archive-manager.php';
        require_once plugin_dir_path( __DIR__ ) . '../../includes/teams/class-puck-press-teams-wpdb-utils.php';

        $this->archives = ( new Puck_Press_Archive_Manager() )->get_all_archives();
        $this->teams    = ( new Puck_Press_Teams_Wpdb_Utils() )->get_all_teams();
    }

    public function render(): string {
        ob_start();
        ?>
        <div class="pp-container">
            <main class="pp-main">
                <div class="pp-section-header">
                    <div>
                        <h1 class="pp-section-title">Archives</h1>
                        <p class="pp-section-description">Manage archived seasons. Import historical data from league APIs or view existing archives.</p>
                    </div>
                    <div class="pp-flex-row" style="gap:8px;">
                        <button class="pp-button" id="pp-import-acha-btn">Import from ACHA</button>
                        <button class="pp-button" id="pp-import-usphl-btn">Import from USPHL</button>
                        <button class="pp-button" id="pp-refresh-all-archives-btn" title="Delete and re-import all API-imported archives from their original sources">Refresh All</button>
                    </div>
                </div>

                <?php echo $this->render_archives_card(); ?>
            </main>

            <?php echo $this->render_acha_modal(); ?>
            <?php echo $this->render_usphl_modal(); ?>
        </div>
        <?php
        return ob_get_clean();
    }

    private function render_archives_card(): string {
        ob_start();
        ?>
        <div class="pp-card" style="margin-bottom:16px;">
            <div class="pp-card-header">
                <div>
                    <h2 class="pp-card-title">Archived Seasons</h2>
                    <p class="pp-card-subtitle">Archived seasons for all teams</p>
                </div>
            </div>
            <div class="pp-card-content" style="padding:0 24px 16px;">
                <div id="pp-team-archives-list">
                <?php if ( empty( $this->archives ) ) : ?>
                    <p style="color:#5f6368;font-size:0.875rem;margin:12px 0 0;">No archives yet.</p>
                <?php else : ?>
                    <table style="width:100%;border-collapse:collapse;font-size:0.875rem;margin-top:12px;">
                        <thead>
                            <tr style="background:#f5f5f5;">
                                <th style="text-align:left;padding:8px 12px;border:1px solid #e0e0e0;font-weight:600;">Season</th>
                                <th style="text-align:left;padding:8px 12px;border:1px solid #e0e0e0;font-weight:600;">Archived</th>
                                <th style="text-align:center;padding:8px 12px;border:1px solid #e0e0e0;font-weight:600;">Games</th>
                                <th style="text-align:center;padding:8px 12px;border:1px solid #e0e0e0;font-weight:600;">Roster</th>
                                <th style="text-align:center;padding:8px 12px;border:1px solid #e0e0e0;font-weight:600;">Skaters</th>
                                <th style="text-align:center;padding:8px 12px;border:1px solid #e0e0e0;font-weight:600;">Goalies</th>
                                <th style="padding:8px 12px;border:1px solid #e0e0e0;"></th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php foreach ( $this->archives as $archive ) : ?>
                            <tr>
                                <td style="padding:8px 12px;border:1px solid #e0e0e0;">
                                    <span class="pp-archive-label" data-season-key="<?php echo esc_attr( $archive['season_key'] ); ?>">
                                        <?php echo esc_html( $archive['label'] ); ?>
                                    </span>
                                    <button class="pp-archive-rename-btn" data-season-key="<?php echo esc_attr( $archive['season_key'] ); ?>" title="Rename" style="background:none;border:none;cursor:pointer;padding:2px 4px;font-size:0.8rem;">&#9998;</button>
                                    <?php if ( ! empty( $archive['api_label'] ) ) : ?>
                                        <br><small style="color:#888;">Original: <?php echo esc_html( $archive['api_label'] ); ?>
                                        <button class="pp-archive-reset-label-btn" data-season-key="<?php echo esc_attr( $archive['season_key'] ); ?>" data-api-label="<?php echo esc_attr( $archive['api_label'] ); ?>" title="Reset to original" style="background:none;border:none;cursor:pointer;padding:0 2px;font-size:0.75rem;color:#1a73e8;">reset</button>
                                        </small>
                                    <?php endif; ?>
                                </td>
                                <td style="padding:8px 12px;border:1px solid #e0e0e0;"><?php echo esc_html( date_i18n( 'M j, Y', strtotime( $archive['archived_at'] ) ) ); ?></td>
                                <td style="padding:8px 12px;border:1px solid #e0e0e0;text-align:center;"><?php echo (int) $archive['game_count']; ?></td>
                                <td style="padding:8px 12px;border:1px solid #e0e0e0;text-align:center;"><?php echo (int) $archive['roster_count']; ?></td>
                                <td style="padding:8px 12px;border:1px solid #e0e0e0;text-align:center;"><?php echo (int) $archive['skater_count']; ?></td>
                                <td style="padding:8px 12px;border:1px solid #e0e0e0;text-align:center;"><?php echo (int) $archive['goalie_count']; ?></td>
                                <td style="padding:8px 12px;border:1px solid #e0e0e0;text-align:right;">
                                    <button class="pp-button pp-button-danger pp-delete-archive-btn"
                                        data-season-key="<?php echo esc_attr( $archive['season_key'] ); ?>">
                                        Delete
                                    </button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
                </div>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }

    private function render_team_dropdown( string $id ): string {
        ob_start();
        ?>
        <select id="<?php echo esc_attr( $id ); ?>" required>
            <option value="">-- Select a team --</option>
            <?php foreach ( $this->teams as $team ) : ?>
                <option value="<?php echo esc_attr( $team['id'] ); ?>"><?php echo esc_html( $team['name'] ); ?></option>
            <?php endforeach; ?>
        </select>
        <?php
        return ob_get_clean();
    }

    private function render_acha_modal(): string {
        ob_start();
        ?>
        <div class="pp-modal-overlay" id="pp-import-acha-modal" style="display:none;">
            <div class="pp-modal">
                <div class="pp-modal-header">
                    <h2>Import from ACHA</h2>
                    <button class="pp-modal-close">&times;</button>
                </div>
                <div class="pp-modal-content">
                    <div class="pp-form-group">
                        <label for="pp-acha-team">Local Team</label>
                        <?php echo $this->render_team_dropdown( 'pp-acha-team' ); ?>
                    </div>
                    <div class="pp-form-group">
                        <label for="pp-acha-season">ACHA Season</label>
                        <select id="pp-acha-season" disabled>
                            <option value="">Loading seasons...</option>
                        </select>
                    </div>
                    <div class="pp-form-group">
                        <label for="pp-acha-api-team">API Team</label>
                        <select id="pp-acha-api-team" disabled>
                            <option value="">Select a season first</option>
                        </select>
                    </div>
                    <div class="pp-form-group">
                        <label for="pp-acha-season-key">Season Key</label>
                        <input type="text" id="pp-acha-season-key" placeholder="e.g. 2024-2025">
                    </div>
                    <div class="pp-form-group" id="pp-acha-team-mapping" style="display:none;padding:8px 12px;background:#f0f7ff;border-radius:4px;font-size:0.875rem;"></div>
                    <div class="pp-form-group">
                        <label style="font-weight:600;">Data to Import</label>
                        <label style="display:block;margin-top:4px;">
                            <input type="checkbox" id="pp-acha-import-schedule" checked> Schedule / Games
                        </label>
                        <label style="display:block;margin-top:4px;">
                            <input type="checkbox" id="pp-acha-import-roster" checked> Roster &amp; Stats
                        </label>
                        <label style="display:block;margin-top:8px;">
                            <input type="checkbox" id="pp-acha-import-append"> Append to existing archive
                        </label>
                        <div id="pp-acha-append-hint" style="display:none;margin-top:4px;padding:6px 10px;background:#fff8e1;border-radius:4px;font-size:0.8rem;color:#795548;"></div>
                    </div>
                </div>
                <div class="pp-modal-footer">
                    <div id="pp-acha-import-status" style="flex:1;font-size:0.875rem;"></div>
                    <button class="pp-button" id="pp-acha-import-confirm">Import</button>
                </div>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }

    private function render_usphl_modal(): string {
        ob_start();
        ?>
        <div class="pp-modal-overlay" id="pp-import-usphl-modal" style="display:none;">
            <div class="pp-modal">
                <div class="pp-modal-header">
                    <h2>Import from USPHL</h2>
                    <button class="pp-modal-close">&times;</button>
                </div>
                <div class="pp-modal-content">
                    <div class="pp-form-group">
                        <label for="pp-usphl-team">Local Team</label>
                        <?php echo $this->render_team_dropdown( 'pp-usphl-team' ); ?>
                    </div>
                    <div class="pp-form-group">
                        <label for="pp-usphl-api-team-id">API Team ID</label>
                        <input type="text" id="pp-usphl-api-team-id" placeholder="e.g. 2301" required>
                    </div>
                    <div class="pp-form-group">
                        <label for="pp-usphl-season-id">Season ID</label>
                        <input type="text" id="pp-usphl-season-id" placeholder="e.g. 65" required>
                    </div>
                    <div class="pp-form-group">
                        <label for="pp-usphl-season-key">Season Key</label>
                        <input type="text" id="pp-usphl-season-key" placeholder="e.g. 2023-2024" required>
                    </div>
                    <div class="pp-form-group">
                        <label style="font-weight:600;">Data to Import</label>
                        <label style="display:block;margin-top:4px;">
                            <input type="checkbox" id="pp-usphl-import-schedule" checked> Schedule / Games
                        </label>
                        <label style="display:block;margin-top:4px;">
                            <input type="checkbox" id="pp-usphl-import-roster" checked> Roster &amp; Stats
                        </label>
                        <label style="display:block;margin-top:8px;">
                            <input type="checkbox" id="pp-usphl-import-append"> Append to existing archive
                        </label>
                    </div>
                </div>
                <div class="pp-modal-footer">
                    <div id="pp-usphl-import-status" style="flex:1;font-size:0.875rem;"></div>
                    <button class="pp-button" id="pp-usphl-import-confirm">Import</button>
                </div>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }
}
