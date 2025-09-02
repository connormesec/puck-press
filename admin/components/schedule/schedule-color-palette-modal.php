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

$templates = new Puck_Press_Schedule_Template_Manager;
$scheduleTemplates = $templates->get_registered_templates_metadata();
$selected_template = $templates->get_current_template_key();
?>

<!-- Color Palette Modal -->
<div id="pp-schedule-paletteModal" class="pp-modal-overlay-palette">
    <div class="pp-modal-palette">
        <button class="pp-modal-close" id="pp-schedule-palette-modal-close">✕</button>
        <div class="pp-form-row">
        <div class="pp-modal-header">
            <h3 class="pp-modal-title">Customize Colors</h3>
            <p class="pp-modal-subtitle">Customize your schedule.</p>
        </div>

        <div class="pp-modal-header">
            <!-- Template Selector -->
            <label for="pp-template-selector">Choose a Template:</label>
            <select id="pp-template-selector">
                <?php foreach ($scheduleTemplates as $key => $template) : ?>
                    <option value="<?php echo esc_attr($key); ?>" <?php selected($key, $selected_template); ?>>
                        <?php echo esc_html($template['name']); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        </div>
        <div class="pp-modal-content">
            <form id="pp-color-palette-form">
                <div class="pp-form-row" id="pp-dynamic-color-fields">
                    <!-- Color inputs will be dynamically generated here -->
                </div>
            </form>
        </div>
        <div class="pp-modal-footer">
            <button class="pp-button pp-button-secondary" id="pp-cancel-save-colors">Cancel</button>
            <button class="pp-button pp-button-primary" id="pp-sched-palette-save-colors">Save Colors</button>
        </div>
    </div>
</div>