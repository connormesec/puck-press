<?php

/**
 * Provide a admin area view for the plugin
 *
 * This file is used to markup the admin-facing aspects of the plugin.
 *
 * @link       https://https://github.com/connormesec/
 * @since      1.0.0
 *
 * @package    Puck_Press
 * @subpackage Puck_Press/admin/partials/roster
 */

if (!defined('ABSPATH')) {
    exit;
}

class Puck_Press_Roster_Admin_Display
{
    private $roster_data_sources;
    private $roster_raw_table;
    private $roster_edits_table;
    private $roster_preview_card;
    private $last_run;

    public function __construct()
    {
        $this->roster_data_sources = new Puck_Press_Roster_Admin_Data_Sources_Card([
            'title' => 'Data Sources',
            'subtitle' => 'Manage external data sources for the roster',
            'id' => 'data-sources-table'
        ]);
        $this->roster_raw_table = new Puck_Press_Raw_Roster_Table_Card([
            'title' => 'Raw Roster',
            'subtitle' => 'Manage your roster',
            'id' => 'raw-roster-table'
        ]);
        $this->roster_edits_table = new Puck_Press_Roster_Admin_Edits_Table_Card([
            'title' => 'Roster Edits',
            'subtitle' => 'Manage your roster edits',
            'id' => 'roster-edits-table'
        ]);
        $this->roster_preview_card = new Puck_Press_Roster_Admin_Preview_Card([
            'title' => 'Roster Preview',
            'subtitle' => 'Preview your roster before publishing',
            'id' => 'roster-preview'
        ]);
        $this->roster_preview_card->init();
        $this->last_run = get_option('puck_press_cron_last_run', 'Never');
    }

    public function render()
    {
        ob_start();
?>
        <div class="pp-container">
            <main class="pp-main">
                <div class="pp-section-header">
                    <div>
                        <h1 class="pp-section-title">Roster</h1>
                        <p class="pp-section-description">Manage your team's roster.</p>
                    </div>

                    <div class="pp-shortcode-container">
                        <div class="pp-shortcode-label">Roster Shortcode</div>
                        <div class="pp-shortcode-input-group">
                            <input
                                type="text"
                                class="pp-shortcode-input"
                                value="[pp-roster]"
                                spellcheck="false"
                                aria-label="shortcode"
                                onfocus="this.select();"
                                readonly>
                            <button class="pp-shortcode-copy-btn" aria-label="Copy URL">
                                <svg class="pp-shortcode-copy-icon" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 5H6a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2v-1M8 5a2 2 0 002 2h2a2 2 0 002-2M8 5a2 2 0 012-2h2a2 2 0 012 2m0 0h2a2 2 0 012 2v3m2 4H10m0 0l3-3m-3 3l3 3" />
                                </svg>
                            </button>
                            <div class="pp-shortcode-tooltip">Copied!</div>
                        </div>
                    </div>

                    <div class="pp-flex-row">

                        <button class="pp-button pp-button-secondary" id="pp-refresh-button">
                            <i>🔄</i>
                            Refresh All Sources
                        </button>

                        <div class="pp-adv-button-container">
                            <button class="pp-button" id="pp-advancedBtn">
                                <svg class="pp-gear-icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    <circle cx="12" cy="12" r="3"></circle>
                                    <path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1 0 2.83 2 2 0 0 1-2.83 0l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-2 2 2 2 0 0 1-2-2v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83 0 2 2 0 0 1 0-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1-2-2 2 2 0 0 1 2-2h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 0-2.83 2 2 0 0 1 2.83 0l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 2-2 2 2 0 0 1 2 2v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 0 2 2 0 0 1 0 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 2 2 2 2 0 0 1-2 2h-.09a1.65 1.65 0 0 0-1.51 1z"></path>
                                </svg>
                                Advanced
                            </button>
                            <div class="pp-dropdown-menu" id="pp-advancedDropdown">
                                <div class="pp-dropdown-header">Sources</div>
                                <div class="pp-dropdown-item">Reset Game Data</div>
                                <div class="pp-dropdown-item">Reset Data Sources</div>
                                <div class="pp-dropdown-item danger">Reset Everything</div>
                            </div>
                        </div>
                    </div>
                </div>

                <p class="pp-refresh-info">Last refreshed: <?php echo esc_html($this->last_run); ?></p>

                <?php echo $this->roster_data_sources->render() ?>

                <?php echo $this->roster_raw_table->render() ?>

                <?php echo $this->roster_edits_table->render() ?>

                <?php echo $this->roster_preview_card->render() ?>

            </main>
            <?php
            include plugin_dir_path(dirname(__FILE__)) . 'roster/roster-add-source-modal.php';
            $source_modal = new Puck_Press_Roster_Add_Source_Modal('pp-add-source-modal');
            echo $source_modal->render();
            include plugin_dir_path(dirname(__FILE__)) . 'roster/roster-edit-player-modal.php';
            include plugin_dir_path(dirname(__FILE__)) . 'roster/roster-color-palette-modal.php';
            ?>

        </div>
<?php
        return ob_get_clean();
    }
}
