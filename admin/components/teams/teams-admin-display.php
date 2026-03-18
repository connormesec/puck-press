<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

require_once plugin_dir_path( __DIR__ ) . '../../includes/teams/class-puck-press-teams-wpdb-utils.php';
require_once plugin_dir_path( __DIR__ ) . '../../includes/archive/class-puck-press-archive-manager.php';

class Puck_Press_Teams_Admin_Display {

    private array $teams;
    private int $active_team_id;
    private $pp_sched_data_sources;
    private $pp_sched_admin;
    private $roster_sources_card;
    private $players_table_card;

    public function __construct() {
        $teams_utils          = new Puck_Press_Teams_Wpdb_Utils();
        $this->teams          = $teams_utils->get_all_teams();
        $this->active_team_id = (int) get_option( 'pp_admin_active_team_id', 0 );
        $valid_team_ids       = array_column( $this->teams, 'id' );
        if ( ! empty( $valid_team_ids ) && ! in_array( (string) $this->active_team_id, $valid_team_ids, false ) ) {
            $this->active_team_id = (int) $valid_team_ids[0];
            update_option( 'pp_admin_active_team_id', $this->active_team_id );
        }

        $this->pp_sched_data_sources = new Puck_Press_Teams_Admin_Data_Sources_Card(
            array(
                'title'    => 'Data Sources',
                'subtitle' => 'Manage external data sources for games',
                'id'       => 'data-sources-table',
            ),
            $this->active_team_id
        );
        $this->pp_sched_admin        = new Puck_Press_Teams_Admin_Games_Table_Card(
            array(
                'title'    => 'Games',
                'subtitle' => '0 games scheduled',
                'id'       => 'team-game-list',
            ),
            $this->active_team_id
        );
        $this->roster_sources_card = new Puck_Press_Teams_Admin_Roster_Sources_Card( $this->active_team_id );
        $this->players_table_card  = new Puck_Press_Teams_Admin_Players_Table_Card( $this->active_team_id );
    }

    public function render(): string {
        ob_start();
        ?>
        <div class="pp-container">
            <main class="pp-main">
                <div class="pp-section-header">
                    <div>
                        <h1 class="pp-section-title">Teams</h1>
                        <p class="pp-section-description">Manage your teams, their data sources, and schedule assignments.</p>
                    </div>

                    <div class="pp-flex-row">
                        <div class="pp-adv-button-container">
                            <button class="pp-button" id="pp-advancedBtn">
                                <svg class="pp-gear-icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    <circle cx="12" cy="12" r="3"></circle>
                                    <path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1 0 2.83 2 2 0 0 1-2.83 0l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-2 2 2 2 0 0 1-2-2v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83 0 2 2 0 0 1 0-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1-2-2 2 2 0 0 1 2-2h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 0-2.83 2 2 0 0 1 2.83 0l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 2-2 2 2 0 0 1 2 2v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 0 2 2 0 0 1 0 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 2 2 2 2 0 0 1-2 2h-.09a1.65 1.65 0 0 0-1.51 1z"></path>
                                </svg>
                                Advanced
                            </button>
                            <div class="pp-dropdown-menu" id="pp-advancedDropdown">
                                <div class="pp-dropdown-header">Database</div>
                                <div class="pp-dropdown-item danger" id="pp-wipe-and-recreate-db-btn">Wipe &amp; Recreate Database</div>
                            </div>
                        </div>
                    </div>
                </div>

                <?php echo $this->render_teams_section(); ?>

            </main>

            <?php echo $this->render_add_team_modal(); ?>
            <?php echo $this->render_team_season_archive_modal(); ?>
            <?php
            include plugin_dir_path( __DIR__ ) . 'schedule/schedule-add-source-modal.php';
            $source_modal = new Puck_Press_Schedule_Add_Source_Modal( 'pp-add-source-modal' );
            echo $source_modal->render();
            include plugin_dir_path( __DIR__ ) . 'schedule/schedule-edit-game-modal.php';
            include plugin_dir_path( __DIR__ ) . 'schedule/schedule-bulk-edit-modal.php';
            require_once plugin_dir_path( __DIR__ ) . 'schedule/schedule-add-game-modal.php';
            $add_game_modal = new Puck_Press_Schedule_Add_Game_Modal( 'pp-add-game-modal' );
            echo $add_game_modal->render();
            include plugin_dir_path( __DIR__ ) . 'roster/roster-bulk-edit-modal.php';
            ?>
        </div>
        <?php
        return ob_get_clean();
    }

    private function render_teams_section(): string {
        ob_start();
        $teams          = $this->teams;
        $active_team_id = $this->active_team_id;

        ?>
        <!-- Teams list card -->
        <div class="pp-card" style="margin-bottom:16px;">
            <div class="pp-card-header">
                <div>
                    <h2 class="pp-card-title">Teams</h2>
                    <p class="pp-card-subtitle">Manage your teams</p>
                </div>
                <button class="pp-button pp-button-primary" id="pp-add-team-btn">+ Add Team</button>
            </div>
            <div class="pp-card-content">
                <?php if ( empty( $teams ) ) : ?>
                    <p style="color:#888;">No teams yet. Add your first team above.</p>
                <?php else : ?>
                    <table class="pp-table" id="pp-teams-list-table">
                        <thead class="pp-thead">
                            <tr>
                                <th class="pp-th">Name</th>
                                <th class="pp-th">Slug</th>
                                <th class="pp-th">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ( $teams as $team ) : ?>
                                <tr data-team-id="<?php echo esc_attr( $team['id'] ); ?>">
                                    <td class="pp-td"><?php echo esc_html( $team['name'] ); ?></td>
                                    <td class="pp-td"><code><?php echo esc_html( $team['slug'] ); ?></code></td>
                                    <td class="pp-td">
                                        <button class="pp-button-icon pp-delete-team-btn" data-team-id="<?php echo esc_attr( $team['id'] ); ?>" title="Delete team">🗑️</button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
        </div>

        <?php if ( ! empty( $teams ) ) : ?>
        <!-- Active team selector -->
        <div class="pp-card" style="margin-bottom:16px;">
            <div class="pp-card-content" style="padding:16px 24px; display:flex; gap:12px; align-items:center; flex-wrap:wrap;">
                <label for="pp-team-selector"><strong>Editing team:</strong></label>
                <select id="pp-team-selector" class="pp-select">
                    <?php foreach ( $teams as $team ) : ?>
                        <option value="<?php echo esc_attr( $team['id'] ); ?>"
                            <?php selected( (int) $team['id'], $active_team_id ); ?>>
                            <?php echo esc_html( $team['name'] ); ?> (<?php echo esc_html( $team['slug'] ); ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
                <button class="pp-button pp-button-secondary" id="pp-refresh-team-btn" data-team-id="<?php echo esc_attr( $active_team_id ); ?>">
                    🔄 Refresh Team
                </button>
            </div>
        </div>
        <input type="hidden" id="pp-active-team-id" value="<?php echo esc_attr( $active_team_id ); ?>">

        <?php echo $this->pp_sched_data_sources->render(); ?>
        <?php echo $this->pp_sched_admin->render(); ?>

        <h3 class="pp-section-header" style="margin: 24px 0 12px;">Roster</h3>
        <?php echo $this->roster_sources_card->render(); ?>
        <?php echo $this->players_table_card->render(); ?>

        <!-- Archive team season card -->
        <div class="pp-card" style="margin-bottom:16px;">
            <div class="pp-card-header">
                <div>
                    <h2 class="pp-card-title">Archive Team Season</h2>
                    <p class="pp-card-subtitle">Snapshot the current team display games into an archive</p>
                </div>
                <button class="pp-button pp-button-secondary" id="pp-archive-team-season-btn" data-team-id="<?php echo esc_attr( $active_team_id ); ?>">
                    📦 Archive Season
                </button>
            </div>
            <div class="pp-card-content" style="padding:0 24px 16px;">
                <?php
                $archive_manager  = new Puck_Press_Archive_Manager();
                $existing_archives = $archive_manager->get_team_archives( $active_team_id );
                if ( empty( $existing_archives ) ) :
                ?>
                <div id="pp-team-archives-list">
                    <p style="color:#5f6368;font-size:0.875rem;margin:12px 0 0;">No archives yet.</p>
                </div>
                <?php else : ?>
                <div id="pp-team-archives-list">
                    <table style="width:100%;border-collapse:collapse;font-size:0.875rem;margin-top:12px;">
                        <thead>
                            <tr style="background:#f5f5f5;">
                                <th style="text-align:left;padding:8px 12px;border:1px solid #e0e0e0;font-weight:600;">Season</th>
                                <th style="text-align:left;padding:8px 12px;border:1px solid #e0e0e0;font-weight:600;">Archived</th>
                                <th style="text-align:center;padding:8px 12px;border:1px solid #e0e0e0;font-weight:600;">Games</th>
                                <th style="text-align:center;padding:8px 12px;border:1px solid #e0e0e0;font-weight:600;">Skaters</th>
                                <th style="text-align:center;padding:8px 12px;border:1px solid #e0e0e0;font-weight:600;">Goalies</th>
                                <th style="padding:8px 12px;border:1px solid #e0e0e0;"></th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php foreach ( $existing_archives as $archive ) : ?>
                            <tr>
                                <td style="padding:8px 12px;border:1px solid #e0e0e0;"><?php echo esc_html( $archive['label'] ); ?></td>
                                <td style="padding:8px 12px;border:1px solid #e0e0e0;"><?php echo esc_html( date_i18n( 'M j, Y', strtotime( $archive['archived_at'] ) ) ); ?></td>
                                <td style="padding:8px 12px;border:1px solid #e0e0e0;text-align:center;"><?php echo (int) $archive['game_count']; ?></td>
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
                </div>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>
        <?php
        return ob_get_clean();
    }

    private function render_add_team_modal(): string {
        ob_start();
        ?>
        <div id="pp-add-team-modal" class="pp-modal-overlay" style="display:none;">
            <div class="pp-modal">
                <div class="pp-modal-header">
                    <h2>Add Team</h2>
                    <button class="pp-modal-close" id="pp-add-team-modal-close">&times;</button>
                </div>
                <div class="pp-modal-body">
                    <div class="pp-form-group">
                        <label class="pp-form-label">Team Name</label>
                        <input type="text" id="pp-new-team-name" class="pp-form-input" placeholder="e.g. Eagles">
                    </div>
                    <div class="pp-form-group">
                        <label class="pp-form-label">Slug</label>
                        <input type="text" id="pp-new-team-slug" class="pp-form-input" placeholder="e.g. eagles">
                    </div>
                </div>
                <div class="pp-modal-footer">
                    <button class="pp-button" id="pp-add-team-modal-cancel">Cancel</button>
                    <button class="pp-button pp-button-primary" id="pp-add-team-modal-confirm">Create Team</button>
                </div>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }

    private function render_team_season_archive_modal(): string {
        ob_start();
        ?>
        <div id="pp-team-archive-modal" class="pp-modal-overlay" style="display:none;">
            <div class="pp-modal">
                <div class="pp-modal-header">
                    <h2>Archive Team Season</h2>
                    <button class="pp-modal-close" id="pp-team-archive-modal-close">&times;</button>
                </div>
                <div class="pp-modal-body">
                    <div id="pp-team-archive-form-step">
                        <div class="pp-form-group">
                            <label class="pp-form-label" for="pp-team-archive-season">Season</label>
                            <select id="pp-team-archive-season" class="pp-form-input">
                                <?php
                                $current_year     = (int) gmdate( 'Y' );
                                $default_key      = ( $current_year - 1 ) . '-' . $current_year;
                                $archived_manager = new Puck_Press_Archive_Manager();
                                $archived_keys    = array_column( $archived_manager->get_team_archives( $this->active_team_id ), 'season_key' );
                                for ( $y = $current_year - 1; $y >= $current_year - 11; $y-- ) {
                                    $key      = $y . '-' . ( $y + 1 );
                                    $disabled = in_array( $key, $archived_keys, true ) ? ' disabled' : '';
                                    $suffix   = $disabled ? ' (archived)' : '';
                                    $selected = $key === $default_key ? ' selected' : '';
                                    echo '<option value="' . esc_attr( $key ) . '"' . $disabled . $selected . '>' . esc_html( $key . $suffix ) . '</option>';
                                }
                                ?>
                            </select>
                        </div>
                    </div>
                    <div id="pp-team-archive-result-step" style="display:none;">
                        <p id="pp-team-archive-result-message" style="margin-bottom:12px;"></p>
                        <label style="display:flex;align-items:center;gap:8px;cursor:pointer;">
                            <input type="checkbox" id="pp-team-archive-wipe-stats">
                            Also clear current season stats for this team
                        </label>
                    </div>
                </div>
                <div class="pp-modal-footer">
                    <button class="pp-button" id="pp-team-archive-modal-cancel">Cancel</button>
                    <button class="pp-button pp-button-primary" id="pp-team-archive-modal-confirm">Archive Season</button>
                    <button class="pp-button pp-button-primary" id="pp-team-archive-modal-done" style="display:none;">Done</button>
                </div>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }
}
