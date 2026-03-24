<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

require_once plugin_dir_path( __FILE__ ) . '../../../public/templates/class-puck-press-template-manager-abstract.php';
require_once plugin_dir_path( __FILE__ ) . '../../../public/templates/class-puck-press-template-abstract.php';
require_once plugin_dir_path( __FILE__ ) . '../../../public/templates/class-puck-press-league-news-template-manager.php';

$ln_templates     = new Puck_Press_League_News_Template_Manager();
$ln_template_meta = $ln_templates->get_registered_templates_metadata();
$ln_selected      = $ln_templates->get_current_template_key();
?>

<div id="pp-league-news-paletteModal" class="pp-modal-overlay-palette">
    <div class="pp-modal-palette">
        <button class="pp-modal-close" id="pp-league-news-palette-modal-close">&#x2715;</button>
        <div class="pp-form-row">
            <div class="pp-modal-header">
                <h3 class="pp-modal-title">Customize Colors</h3>
                <p class="pp-modal-subtitle">Customize your league news feed.</p>
            </div>

            <div class="pp-modal-header">
                <label for="pp-league-news-template-selector">Choose a Template:</label>
                <select id="pp-league-news-template-selector">
                    <?php foreach ( $ln_template_meta as $key => $template ) : ?>
                        <option value="<?php echo esc_attr( $key ); ?>" <?php selected( $key, $ln_selected ); ?>>
                            <?php echo esc_html( $template['name'] ); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>
        <div class="pp-modal-content">
            <form id="pp-league-news-color-palette-form">
                <div class="pp-form-row" id="pp-league-news-dynamic-color-fields">
                    <!-- Color inputs will be dynamically generated here -->
                </div>
            </form>
        </div>
        <div class="pp-modal-footer">
            <button class="pp-button pp-button-secondary" id="pp-league-news-cancel-save-colors">Cancel</button>
            <button class="pp-button pp-button-primary" id="pp-league-news-palette-save-colors">Save Colors</button>
        </div>
    </div>
</div>
