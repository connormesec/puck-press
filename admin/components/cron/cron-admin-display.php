<?php

if (!defined('ABSPATH')) {
    exit;
}

class Puck_Press_Cron_Admin_Display
{
    private $enabled;
    private $last_run;
    private $cron;
    private $schedule;
    private $logs;

    public function __construct()
    {
        $this->enabled = get_option('puck_press_cron_enabled', true);
        $this->last_run = get_option('puck_press_cron_last_run', 'Never');
        $this->schedule = get_option('puck_press_cron_schedule', 'twicedaily');
        $this->logs = get_option('puck_press_cron_last_log', []);
        require_once plugin_dir_path(__FILE__) . '../../../includes/class-puck-press-cron.php';
        $this->cron = new Puck_Press_Cron();
    }

    public function render()
    {
        ob_start();

        // Handle "Enable/Disable Cron" form
        if (isset($_POST['puck_press_toggle_cron']) && check_admin_referer('puck_press_cron_settings_action', 'puck_press_cron_settings_nonce')) {
            $was_enabled = $this->enabled;
            $old_schedule = $this->schedule;
            
            $this->enabled = isset($_POST['cron_enabled']);
            $this->schedule = sanitize_text_field($_POST['cron_schedule'] ?? 'twicedaily');
            
            update_option('puck_press_cron_enabled', $this->enabled);
            update_option('puck_press_cron_schedule', $this->schedule);
            
            // Handle cron scheduling based on the changes
            if ($this->enabled) {
                if (!$was_enabled || $old_schedule !== $this->schedule) {
                    // Cron was just enabled or schedule changed - reschedule it
                    $this->cron->unschedule_cron();
                    $this->cron->schedule_cron($this->schedule);
                    $message = $was_enabled 
                        ? 'Cron schedule updated and rescheduled.' 
                        : 'Cron enabled and scheduled.';
                    echo '<div class="notice notice-success"><p>' . esc_html($message) . '</p></div>';
                }
            } else {
                if ($was_enabled) {
                    // Cron was just disabled - unschedule it
                    $this->cron->unschedule_cron();
                    echo '<div class="notice notice-success"><p>Cron disabled and unscheduled.</p></div>';
                }
            }
            
            if ($this->enabled && $was_enabled && $old_schedule === $this->schedule) {
                echo '<div class="notice notice-success"><p>Cron settings saved.</p></div>';
            }
        }

        // Handle "Run Cron Task Now" form
        if (isset($_POST['puck_press_run_cron_now']) && check_admin_referer('puck_press_cron_manual_action', 'puck_press_cron_manual_nonce')) {
            $this->cron->run_cron_task();
            // Refresh the last run time after manual execution
            $this->last_run = get_option('puck_press_cron_last_run', 'Never');
            echo '<div class="notice notice-success"><p>Cron task ran manually. Last run time updated.</p></div>';
        }

        // Handle Debug form
        if (isset($_POST['puck_press_debug_cron']) && check_admin_referer('puck_press_cron_debug', 'puck_press_cron_debug_nonce')) {
            $debug_info = $this->cron->debug_cron_status();
            echo '<div class="notice notice-info"><p>Debug information generated below.</p></div>';
            echo '<h3>🔍 Debug Information</h3>';
            echo '<pre style="background: #f1f1f1; padding: 10px; border-radius: 4px; overflow-x: auto;">';
            echo esc_html(print_r($debug_info, true));
            echo '</pre>';
        }
?>

        <div class="wrap">
            <h1>Puck Press Cron Management</h1>
            
            <!-- Enable/Disable Cron Form -->
            <div class="card" style="max-width: 800px;">
                <h2>Cron Settings</h2>
                <form method="post" action="">
                    <?php wp_nonce_field('puck_press_cron_settings_action', 'puck_press_cron_settings_nonce'); ?>
                    <table class="form-table">
                        <tr>
                            <th scope="row">Enable Cron</th>
                            <td>
                                <label>
                                    <input type="checkbox" name="cron_enabled" <?php checked($this->enabled); ?> />
                                    Enable Automated Schedule Import
                                </label>
                                <p class="description">
                                    When enabled, the system will automatically import and process hockey schedule data.
                                </p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">Schedule Frequency</th>
                            <td>
                                <select name="cron_schedule" <?php disabled(!$this->enabled); ?>>
                                    <?php
                                    $schedules = wp_get_schedules();
                                    $current_schedule = $this->schedule;
                                    
                                    foreach ($schedules as $key => $schedule) {
                                        $selected = selected($current_schedule, $key, false);
                                        $interval_hours = $schedule['interval'] / HOUR_IN_SECONDS;
                                        $display_text = $schedule['display'];
                                        
                                        // Add helpful interval info for common schedules
                                        if ($interval_hours >= 24) {
                                            $days = $interval_hours / 24;
                                            $display_text .= ' (every ' . ($days == 1 ? 'day' : $days . ' days') . ')';
                                        } elseif ($interval_hours >= 1) {
                                            $display_text .= ' (every ' . ($interval_hours == 1 ? 'hour' : $interval_hours . ' hours') . ')';
                                        } else {
                                            $minutes = $schedule['interval'] / MINUTE_IN_SECONDS;
                                            $display_text .= ' (every ' . $minutes . ' minutes)';
                                        }
                                        
                                        echo '<option value="' . esc_attr($key) . '"' . $selected . '>' . esc_html($display_text) . '</option>';
                                    }
                                    ?>
                                </select>
                                <p class="description">
                                    Choose how frequently the cron task should run. More frequent schedules may impact performance.
                                </p>
                                <script>
                                document.querySelector('input[name="cron_enabled"]').addEventListener('change', function() {
                                    document.querySelector('select[name="cron_schedule"]').disabled = !this.checked;
                                });
                                </script>
                            </td>
                        </tr>
                    </table>
                    <?php submit_button('Save Settings', 'primary', 'puck_press_toggle_cron'); ?>
                </form>
            </div>

            <!-- Manual Execution -->
            <div class="card" style="max-width: 800px;">
                <h2>Manual Execution</h2>
                <p>Run the cron task immediately for testing or to update data now.</p>
                <form method="post" action="">
                    <?php wp_nonce_field('puck_press_cron_manual_action', 'puck_press_cron_manual_nonce'); ?>
                    <?php submit_button('Run Cron Task Now', 'secondary', 'puck_press_run_cron_now'); ?>
                </form>
            </div>

            <div class="card" style="max-width: 800px;">
                <h2>Last Run Logs</h2>
                <p>View the logs from the last run of the cron task.</p>
                <pre style="white-space: pre-wrap;word-wrap: break-word;"><?php echo esc_html(implode("\n", $this->logs)); ?></pre>
            </div>

            <!-- Status Information -->
            <div class="card" style="max-width: 800px;">
                <h2>🔍 Cron Status</h2>
                <?php
                $next_run = wp_next_scheduled('puck_press_cron_hook');
                $status = $next_run ? 'Scheduled for ' . wp_date('Y-m-d H:i:s', $next_run) : 'NOT SCHEDULED';
                $status_class = $next_run ? 'notice-success' : 'notice-warning';
                
                // Get current schedule info
                $schedules = wp_get_schedules();
                $current_schedule_info = isset($schedules[$this->schedule]) ? $schedules[$this->schedule] : null;
                ?>
                
                <div class="<?php echo esc_attr($status_class); ?> notice inline">
                    <p><strong>Current Status:</strong> <?php echo esc_html($status); ?></p>
                </div>
                
                <table class="widefat">
                    <tbody>
                        <tr>
                            <td><strong>Cron Enabled:</strong></td>
                            <td><?php echo $this->enabled ? '✅ Yes' : '❌ No'; ?></td>
                        </tr>
                        <tr>
                            <td><strong>Current Schedule:</strong></td>
                            <td>
                                <?php 
                                if ($current_schedule_info) {
                                    echo esc_html($current_schedule_info['display']) . ' (' . esc_html($this->schedule) . ')';
                                } else {
                                    echo esc_html($this->schedule) . ' (schedule not found)';
                                }
                                ?>
                            </td>
                        </tr>
                        <tr>
                            <td><strong>Last Run:</strong></td>
                            <td><?php echo esc_html($this->last_run); ?></td>
                        </tr>
                        <tr>
                            <td><strong>Next Scheduled Run:</strong></td>
                            <td><?php echo esc_html($status); ?></td>
                        </tr>
                        <?php if ($next_run): ?>
                        <tr>
                            <td><strong>Time Until Next Run:</strong></td>
                            <td><?php echo esc_html(human_time_diff(time(), $next_run)); ?></td>
                        </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <!-- Debug Tools -->
            <div class="card" style="max-width: 800px;">
                <h2>🛠️ Debug Tools</h2>
                <p>Use this to troubleshoot cron scheduling issues.</p>
                <form method="post" action="">
                    <?php wp_nonce_field('puck_press_cron_debug', 'puck_press_cron_debug_nonce'); ?>
                    <?php submit_button('Debug Cron Status', 'secondary', 'puck_press_debug_cron'); ?>
                </form>
                
                <!-- Quick Actions -->
                <h3>Quick Actions</h3>
                <p>
                    <button type="button" class="button" onclick="if(confirm('This will force reschedule the cron. Continue?')) { document.getElementById('reschedule-form').submit(); }">
                        🔄 Force Reschedule Cron
                    </button>
                </p>
                
                <form id="reschedule-form" method="post" action="" style="display: none;">
                    <?php wp_nonce_field('puck_press_cron_reschedule', 'puck_press_cron_reschedule_nonce'); ?>
                    <input type="hidden" name="puck_press_reschedule_cron" value="1">
                </form>
            </div>
        </div>

        <?php
        // Handle reschedule form
        if (isset($_POST['puck_press_reschedule_cron']) && check_admin_referer('puck_press_cron_reschedule', 'puck_press_cron_reschedule_nonce')) {
            $this->cron->unschedule_cron();
            $this->cron->schedule_cron($this->schedule);
            echo '<div class="notice notice-success"><p>Cron has been rescheduled successfully with the current schedule (' . esc_html($this->schedule) . ').</p></div>';
        }
        
        return ob_get_clean();
    }
}