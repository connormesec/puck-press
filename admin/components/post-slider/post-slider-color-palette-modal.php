<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

require_once plugin_dir_path( __FILE__ ) . '../../../public/templates/class-puck-press-template-manager-abstract.php';
require_once plugin_dir_path( __FILE__ ) . '../../../public/templates/class-puck-press-template-abstract.php';
require_once plugin_dir_path( __FILE__ ) . '../../../public/templates/class-puck-press-post-slider-template-manager.php';

$ps_templates      = new Puck_Press_Post_Slider_Template_Manager();
$ps_template_meta  = $ps_templates->get_registered_templates_metadata();
$ps_selected       = $ps_templates->get_current_template_key();
?>

<div id="pp-post-slider-paletteModal" class="pp-modal-overlay-palette">
    <div class="pp-modal-palette">
        <button class="pp-modal-close" id="pp-post-slider-palette-modal-close">&#x2715;</button>
        <div class="pp-form-row">
            <div class="pp-modal-header">
                <h3 class="pp-modal-title">Customize Colors</h3>
                <p class="pp-modal-subtitle">Customize your post slider.</p>
            </div>

            <div class="pp-modal-header">
                <label for="pp-post-slider-template-selector">Choose a Template:</label>
                <select id="pp-post-slider-template-selector">
                    <?php foreach ( $ps_template_meta as $key => $template ) : ?>
                        <option value="<?php echo esc_attr( $key ); ?>" <?php selected( $key, $ps_selected ); ?>>
                            <?php echo esc_html( $template['name'] ); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>
        <div class="pp-modal-content">
            <form id="pp-post-slider-color-palette-form">
                <div class="pp-form-row" id="pp-post-slider-dynamic-color-fields">
                    <!-- Color inputs will be dynamically generated here -->
                </div>
            </form>
        </div>
        <div class="pp-modal-footer">
            <button class="pp-button pp-button-secondary" id="pp-post-slider-cancel-save-colors">Cancel</button>
            <button class="pp-button pp-button-primary" id="pp-post-slider-palette-save-colors">Save Colors</button>
        </div>
    </div>
</div>
