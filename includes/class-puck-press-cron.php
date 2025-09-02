<?php
// File: includes/class-puck-press-cron.php

class Puck_Press_Cron
{
    const HOOK = 'puck_press_cron_hook';
    const OPTION_ENABLED = 'puck_press_cron_enabled';
    const OPTION_LAST_RUN = 'puck_press_cron_last_run';
    const OPTION_LAST_RUN_TIMESTAMP = 'puck_press_cron_last_run_timestamp';
    const OPTION_SCHEDULE = 'puck_press_cron_schedule';

    private $cron_messages = [];

    public function __construct()
    {
        // Load dependencies
        $this->load_dependencies();

        // Register the cron action
        add_action(self::HOOK, [$this, 'run_cron_task']);

        // Only schedule if cron is enabled
        add_action('init', [$this, 'maybe_schedule_cron']);
    }

    private function load_dependencies()
    {
        require_once plugin_dir_path(dirname(__FILE__)) . 'includes/class-puck-press-wpdb-utils-base-abstract.php';
        require_once plugin_dir_path(dirname(__FILE__)) . 'includes/schedule/class-puck-press-schedule-source-importer.php';
        require_once plugin_dir_path(dirname(__FILE__)) . 'includes/schedule/class-puck-press-schedule-wpdb-utils.php';
        require_once plugin_dir_path(dirname(__FILE__)) . 'includes/roster/class-puck-press-roster-source-importer.php';
        require_once plugin_dir_path(dirname(__FILE__)) . 'includes/roster/class-puck-press-roster-wpdb-utils.php';
    }

    public function maybe_schedule_cron()
    {
        $enabled = get_option(self::OPTION_ENABLED, true);

        if (!$enabled) {
            // If cron is disabled, make sure it's unscheduled
            if (wp_next_scheduled(self::HOOK)) {
                $this->unschedule_cron();
                error_log('Puck Press Cron: Unscheduled cron because it\'s disabled');
            }
            return;
        }

        $current_schedule = get_option(self::OPTION_SCHEDULE, 'twicedaily');

        // Check if the event is already scheduled
        if (!wp_next_scheduled(self::HOOK)) {
            error_log('Puck Press Cron: Scheduling new cron event with schedule: ' . $current_schedule);
            $this->schedule_cron($current_schedule);
        } else {
            error_log('Puck Press Cron: Event already scheduled for ' . date('Y-m-d H:i:s', wp_next_scheduled(self::HOOK)));
        }
    }

    public function schedule_cron($schedule = null)
    {
        if ($schedule === null) {
            $schedule = get_option(self::OPTION_SCHEDULE, 'twicedaily');
        }

        // Clear any existing schedules first to avoid duplicates
        wp_clear_scheduled_hook(self::HOOK);

        // Validate that the schedule exists
        $available_schedules = wp_get_schedules();
        if (!isset($available_schedules[$schedule])) {
            error_log('Puck Press Cron: Invalid schedule "' . $schedule . '", falling back to twicedaily');
            $schedule = 'twicedaily';
        }

        // Schedule the event
        $scheduled = wp_schedule_event(time(), $schedule, self::HOOK);

        if ($scheduled === false) {
            error_log('Puck Press Cron: Failed to schedule cron event with schedule: ' . $schedule);

            // Try with twicedaily as fallback if we weren't already using it
            if ($schedule !== 'twicedaily') {
                $fallback_scheduled = wp_schedule_event(time(), 'twicedaily', self::HOOK);
                if ($fallback_scheduled === false) {
                    error_log('Puck Press Cron: Fallback scheduling also failed');
                    return false;
                } else {
                    error_log('Puck Press Cron: Fallback to twicedaily schedule successful');
                    // Update the stored schedule option to reflect what actually got scheduled
                    update_option(self::OPTION_SCHEDULE, 'twicedaily');
                    return true;
                }
            } else {
                return false;
            }
        } else {
            error_log('Puck Press Cron: Successfully scheduled with ' . $schedule . ' interval');

            // Update the stored schedule option
            update_option(self::OPTION_SCHEDULE, $schedule);

            // Log the next scheduled time
            $next_run = wp_next_scheduled(self::HOOK);
            error_log('Puck Press Cron: Next run scheduled for ' . date('Y-m-d H:i:s', $next_run));
            return true;
        }
    }

    public function unschedule_cron()
    {
        $timestamp = wp_next_scheduled(self::HOOK);
        if ($timestamp) {
            wp_unschedule_event($timestamp, self::HOOK);
            error_log('Puck Press Cron: Unscheduled event at timestamp ' . $timestamp);
        }

        // Also clear any remaining hooks
        wp_clear_scheduled_hook(self::HOOK);
    }

    public function run_cron_task()
    {
        $this->log_message('Puck Press Cron: Starting cron task execution');

        // Double-check if cron is enabled
        $enabled = get_option(self::OPTION_ENABLED, true);
        if (!$enabled) {
            $this->log_message('Puck Press Cron: Task is disabled via option');
            return;
        }

        // Set start time for performance monitoring
        $start_time = microtime(true);

        try {
            // Check if required classes exist
            if (!class_exists('Puck_Press_Schedule_Source_Importer')) {
                $this->log_message('Puck Press Cron: Puck_Press_Schedule_Source_Importer class not found');
                return;
            }

            if (!class_exists('Puck_Press_Schedule_Wpdb_Utils')) {
                $this->log_message('Puck Press Cron: Puck_Press_Schedule_Wpdb_Utils class not found');
                return;
            }

            if (!class_exists('Puck_Press_Roster_Source_Importer')) {
                $this->log_message('Puck Press Cron: Puck_Press_Roster_Source_Importer class not found');
                return;
            }

            if (!class_exists('Puck_Press_Roster_Wpdb_Utils')) {
                $this->log_message('Puck Press Cron: Puck_Press_Roster_Wpdb_Utils class not found');
                return;
            }

            global $wpdb;
            $utils = new Puck_Press_Schedule_Wpdb_Utils();

            $utils->reset_table('pp_game_schedule_raw');
            $utils->reset_table('pp_game_schedule_for_display');

            $importer = new Puck_Press_Schedule_Source_Importer();
            $raw_table_results = $importer->populate_raw_schedule_table_from_sources();

            $importer->sanitize_raw_games_table();
            $display_game_table_results = $importer->apply_edits_and_save_to_display_table();

            $r_utils = new Puck_Press_Roster_Wpdb_Utils;
            $r_utils->reset_table('pp_roster_raw');
            $r_utils->reset_table('pp_roster_for_display');

            $r_importer = new Puck_Press_Roster_Source_Importer();
            $r_importer->populate_raw_roster_table_from_sources();
            $r_importer->apply_edits_and_save_to_display_table();
            $r_importer->sanitize_roster_display_table();

            $execution_time = round(microtime(true) - $start_time, 2);
            $this->log_message('Puck Press Cron: schedule/roster refresh executed successfully in ' . $execution_time . ' seconds');
        } catch (Exception $e) {
            $execution_time = round(microtime(true) - $start_time, 2);
            $this->log_message('Puck Press Cron: Error during schedule/roster refresh after ' . $execution_time . ' seconds - ' . $e->getMessage());
            $this->log_message('Puck Press Cron: Stack trace - ' . $e->getTraceAsString());
        }

        //Create game posts
        try {
            include_once plugin_dir_path(__FILE__) . 'game-summary-post/class-puck-press-game-post-creator.php';
            $game_post_creator = new Puck_Press_Game_Post_Creator();
            $messages = $game_post_creator->run_daily();
            foreach ($messages as $msg) {
                $this->log_message("Puck Press Cron: Game Post Creator - " . $msg);
            }
        } catch (Exception $e) {
            $this->log_message('Puck Press Cron: Error during game post creation - ' . $e->getMessage());
            $this->log_message('Puck Press Cron: Stack trace - ' . $e->getTraceAsString());
        }

        //Import from Instagram
        try {
            include_once plugin_dir_path(__FILE__) . 'instagram-post-importer/class-puck-press-instagram-post-importer.php';
            $instagram_importer = new Puck_Press_Instagram_Post_Importer();
            $messages = $instagram_importer->run_daily();
            foreach ($messages as $msg) {
                $this->log_message("Puck Press Cron: Instagram Importer - " . $msg);
            }
        } catch (Exception $e) {
            $this->log_message('Puck Press Cron: Error during Instagram import - ' . $e->getMessage());
            $this->log_message('Puck Press Cron: Stack trace - ' . $e->getTraceAsString());
        }

        // Update last run information
        $current_time = current_time('mysql');
        update_option(self::OPTION_LAST_RUN, $current_time);
        update_option(self::OPTION_LAST_RUN_TIMESTAMP, time());
        update_option('puck_press_cron_last_log', $this->cron_messages);

        // Log completion
        $this->log_message('Puck Press Cron: Updated last run time to ' . $current_time);
    }

    private function log_message($message)
    {
        $timestamp = current_time('mysql');
        $msg = "[$timestamp] $message";
        $this->cron_messages[] = $msg;
    }

    public static function run_manually()
    {
        error_log('Puck Press Cron: Manual execution triggered');
        do_action(self::HOOK);
    }

    // Get comprehensive status information
    public function get_status()
    {
        $next_run = wp_next_scheduled(self::HOOK);
        $enabled = get_option(self::OPTION_ENABLED, true);
        $last_run = get_option(self::OPTION_LAST_RUN, 'Never');
        $last_run_timestamp = get_option(self::OPTION_LAST_RUN_TIMESTAMP, 0);
        $schedule = get_option(self::OPTION_SCHEDULE, 'twicedaily');

        return [
            'enabled' => $enabled,
            'schedule' => $schedule,
            'next_run' => $next_run,
            'next_run_formatted' => $next_run ? date('Y-m-d H:i:s', $next_run) : 'Not scheduled',
            'last_run' => $last_run,
            'last_run_timestamp' => $last_run_timestamp,
            'last_run_human' => $last_run_timestamp ? human_time_diff($last_run_timestamp) . ' ago' : 'Never',
            'is_scheduled' => (bool)$next_run,
            'hook_name' => self::HOOK,
        ];
    }

    // Debug method to check cron status
    public function debug_cron_status()
    {
        $next_run = wp_next_scheduled(self::HOOK);
        $schedules = wp_get_schedules();
        $status = $this->get_status();

        // Check WordPress cron configuration
        $cron_disabled = defined('DISABLE_WP_CRON');
        $cron_status = $cron_disabled ? 'disabled via DISABLE_WP_CRON' : 'enabled';

        $debug_info = [
            'hook' => self::HOOK,
            'enabled' => $status['enabled'],
            'selected_schedule' => $status['schedule'],
            'next_run' => $next_run,
            'next_run_formatted' => $status['next_run_formatted'],
            'last_run' => $status['last_run'],
            'last_run_timestamp' => $status['last_run_timestamp'],
            'schedules_available' => array_keys($schedules),
            'selected_schedule_exists' => isset($schedules[$status['schedule']]) ? 'Yes' : 'No',
            'wp_cron_status' => $cron_status,
            'disable_wp_cron_defined' => defined('DISABLE_WP_CRON') ? 'Yes' : 'No',
            'current_time' => date('Y-m-d H:i:s'),
            'current_timestamp' => time(),
        ];

        // Add selected schedule details if it exists
        if (isset($schedules[$status['schedule']])) {
            $schedule_info = $schedules[$status['schedule']];
            $debug_info['selected_schedule_interval'] = $schedule_info['interval'];
            $debug_info['selected_schedule_display'] = $schedule_info['display'];
            $debug_info['selected_schedule_interval_hours'] = round($schedule_info['interval'] / HOUR_IN_SECONDS, 2);
        }

        // Show all available schedules with their intervals
        $debug_info['all_schedules'] = [];
        foreach ($schedules as $key => $schedule) {
            $debug_info['all_schedules'][$key] = [
                'display' => $schedule['display'],
                'interval' => $schedule['interval'],
                'interval_hours' => round($schedule['interval'] / HOUR_IN_SECONDS, 2)
            ];
        }

        error_log('Puck Press Cron Debug: ' . print_r($debug_info, true));

        return $debug_info;
    }

    // Health check method
    public function health_check()
    {
        $issues = [];
        $status = $this->get_status();

        // Check if cron is enabled but not scheduled
        if ($status['enabled'] && !$status['is_scheduled']) {
            $issues[] = 'Cron is enabled but not scheduled';
        }

        // Check if cron is disabled but still scheduled
        if (!$status['enabled'] && $status['is_scheduled']) {
            $issues[] = 'Cron is disabled but still scheduled';
        }

        // Check if selected schedule exists
        $schedules = wp_get_schedules();
        if (!isset($schedules[$status['schedule']])) {
            $issues[] = 'Selected schedule "' . $status['schedule'] . '" does not exist';
        }

        // Check if required classes exist
        if (!class_exists('Puck_Press_Schedule_Source_Importer')) {
            $issues[] = 'Required class Puck_Press_Schedule_Source_Importer not found';
        }

        if (!class_exists('Puck_Press_Schedule_Wpdb_Utils')) {
            $issues[] = 'Required class Puck_Press_Schedule_Wpdb_Utils not found';
        }

        return [
            'healthy' => empty($issues),
            'issues' => $issues,
            'status' => $status
        ];
    }

    // Get available schedules for admin display
    public function get_available_schedules()
    {
        return wp_get_schedules();
    }
}
