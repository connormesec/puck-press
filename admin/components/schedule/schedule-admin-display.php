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
 * @subpackage Puck_Press/admin/partials/schedule
 */

if (!defined('ABSPATH')) {
    exit;
}

class Puck_Press_Schedule_Admin_Display
{
    private $pp_sched_admin;
    private $pp_sched_data_sources;
    private $pp_sched_edits;
    private $game_template_preview;
    private $game_slider_preview;
    private $last_run;

    public function __construct()
    {
        $this->pp_sched_admin = new Puck_Press_Schedule_Admin_Games_Table_Card([
            'title' => 'Games',
            'subtitle' => '0 games scheduled',
            'id' => 'schedule-game-list'
        ]);
        $this->pp_sched_data_sources = new Puck_Press_Schedule_Admin_Data_Sources_Card([
            'title' => 'Data Sources',
            'subtitle' => 'Manage external data sources for games',
            'id' => 'data-sources-table'
        ]);
        $this->pp_sched_edits = new Puck_Press_Schedule_Admin_Edits_Table_Card([
            'title' => 'Edits',
            'subtitle' => 'Review and apply changes to game data before sending to frontend',
            'id' => 'game-schedule-edits'
        ]);
        $this->game_template_preview = new Puck_Press_Schedule_Admin_Preview_Card([
            'title' => 'Preview',
            'subtitle' => 'Preview how the schedule will appear on the public website',
            'id' => 'game-schedule-preview'
        ]);
        $this->game_slider_preview = new Puck_Press_Schedule_Admin_Slider_Preview_Card([
            'title' => 'Game Slider Preview',
            'subtitle' => 'Preview how the game slider will appear on the public website',
            'id' => 'game-slider-preview'
        ]);
        $this->game_template_preview->init();
        $this->game_slider_preview->init();
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
                        <h1 class="pp-section-title">Game Schedule</h1>
                        <p class="pp-section-description">Manage your team's game schedule and results.</p>
                    </div>

                    <div class="pp-shortcode-container">
                        <div class="pp-shortcode-label">Schedule Shortcode</div>
                        <div class="pp-shortcode-input-group">
                            <input
                                type="text"
                                class="pp-shortcode-input"
                                value="[pp-schedule]"
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

                <?php echo $this->pp_sched_data_sources->render() ?>

                <?php echo $this->game_template_preview->render() ?>

                <?php echo $this->pp_sched_admin->render(); ?>

                <?php echo $this->pp_sched_edits->render() ?>

                <?php echo $this->game_slider_preview->render() ?>

            </main>
            <?php
            include plugin_dir_path(dirname(__FILE__)) . 'schedule/schedule-add-source-modal.php';
            $source_modal = new Puck_Press_Schedule_Add_Source_Modal('pp-add-source-modal');
            echo $source_modal->render();
            include plugin_dir_path(dirname(__FILE__)) . 'schedule/schedule-color-palette-modal.php';
            include plugin_dir_path(dirname(__FILE__)) . 'schedule/slider-color-palette-modal.php';
            include plugin_dir_path(dirname(__FILE__)) . 'schedule/schedule-edit-game-modal.php';
            ?>

        </div>
<?php
        return ob_get_clean();
    }
}



?>