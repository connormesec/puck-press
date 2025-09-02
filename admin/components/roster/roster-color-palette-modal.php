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
 */

if (!defined('ABSPATH')) {
    exit;
}

$templates = new Puck_Press_Roster_Template_Manager;
$rosterTemplates = $templates->get_registered_templates_metadata();
$selected_template = $templates->get_current_template_key();
?>

<!-- Color Palette Modal -->
<div id="pp-roster-paletteModal" class="pp-modal-overlay-palette">
    <div class="pp-modal-palette">
        <button class="pp-modal-close" id="pp-roster-palette-modal-close">✕</button>
        <div class="pp-form-row">
        <div class="pp-modal-header">
            <h3 class="pp-modal-title">Customize Colors</h3>
            <p class="pp-modal-subtitle">Customize your schedule.</p>
        </div>

        <div class="pp-modal-header">
            <!-- Template Selector -->
            <label for="pp-roster-template-selector">Choose a Template:</label>
            <select id="pp-roster-template-selector">
                <?php foreach ($rosterTemplates as $key => $template) : ?>
                    <option value="<?php echo esc_attr($key); ?>" <?php selected($key, $selected_template); ?>>
                        <?php echo esc_html($template['name']); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        </div>
        <div class="pp-modal-content">
            <form id="pp-roster-color-palette-form">
                <div class="pp-form-row" id="pp-roster-dynamic-color-fields">
                    <!-- Color inputs will be dynamically generated here -->
                </div>
            </form>
        </div>
        <div class="pp-modal-footer">
            <button class="pp-button pp-button-secondary" id="pp-cancel-save-roster-colors">Cancel</button>
            <button class="pp-button pp-button-primary" id="pp-roster-palette-save-colors">Save Colors</button>
        </div>
    </div>
</div>