<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

require_once plugin_dir_path( dirname( dirname( __DIR__ ) ) ) . 'public/templates/class-puck-press-template-manager-abstract.php';
require_once plugin_dir_path( dirname( dirname( __DIR__ ) ) ) . 'public/templates/class-puck-press-awards-template-manager.php';

$awards_templates         = new Puck_Press_Awards_Template_Manager();
$awards_templates_meta    = $awards_templates->get_registered_templates_metadata();
$selected_awards_template = $awards_templates->get_current_template_key();
?>

<div id="pp-awards-paletteModal" class="pp-modal-overlay-palette">
    <div class="pp-modal-palette">
        <button class="pp-modal-close" id="pp-awards-palette-modal-close">✕</button>
        <div class="pp-form-row">
            <div class="pp-modal-header">
                <h3 class="pp-modal-title">Customize Colors</h3>
                <p class="pp-modal-subtitle">Style your awards display.</p>
            </div>

            <div class="pp-modal-header">
                <label for="pp-awards-template-selector">Choose a Template:</label>
                <select id="pp-awards-template-selector">
                    <?php foreach ( $awards_templates_meta as $key => $template ) : ?>
                        <option value="<?php echo esc_attr( $key ); ?>" <?php selected( $key, $selected_awards_template ); ?>>
                            <?php echo esc_html( $template['name'] ); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>

        <div class="pp-modal-content">
            <form id="pp-awards-color-palette-form">
                <p class="pp-section-label">Colors</p>
                <div class="pp-form-row" id="pp-awards-dynamic-color-fields">
                </div>
                <p class="pp-section-label pp-section-label--typography">Typography</p>
                <div class="pp-form-row" id="pp-awards-dynamic-font-fields">
                </div>
            </form>
        </div>

        <div class="pp-modal-footer">
            <button class="pp-button pp-button-secondary" id="pp-cancel-save-awards-colors">Cancel</button>
            <button class="pp-button pp-button-primary" id="pp-awards-palette-save-colors">Save Colors</button>
        </div>
    </div>
</div>
