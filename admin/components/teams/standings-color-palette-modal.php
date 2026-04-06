<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

$standings_team_id        = (int) get_option( 'pp_admin_active_team_id', 0 );
$standings_tm             = new Puck_Press_Standings_Template_Manager( $standings_team_id );
$standings_templates_meta = $standings_tm->get_registered_templates_metadata();
$selected_standings_tpl   = $standings_tm->get_current_template_key();
if ( empty( $selected_standings_tpl ) && ! empty( $standings_templates_meta ) ) {
    $selected_standings_tpl = array_key_first( $standings_templates_meta );
}
?>

<div id="pp-standings-paletteModal" class="pp-modal-overlay-palette">
    <div class="pp-modal-palette">
        <button class="pp-modal-close" id="pp-standings-palette-modal-close">&#10005;</button>
        <div class="pp-form-row">
            <div class="pp-modal-header">
                <h3 class="pp-modal-title">Customize Colors</h3>
                <p class="pp-modal-subtitle">Style your division standings table.</p>
            </div>

            <div class="pp-modal-header"<?php echo count( $standings_templates_meta ) <= 1 ? ' style="display:none;"' : ''; ?>>
                <label for="pp-standings-template-selector">Choose a Template:</label>
                <select id="pp-standings-template-selector">
                    <?php foreach ( $standings_templates_meta as $key => $template ) : ?>
                        <option value="<?php echo esc_attr( $key ); ?>" <?php selected( $key, $selected_standings_tpl ); ?>>
                            <?php echo esc_html( $template['name'] ); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>

        <div class="pp-modal-content">
            <form id="pp-standings-color-palette-form">
                <p class="pp-section-label">Colors</p>
                <div class="pp-form-row" id="pp-standings-dynamic-color-fields"></div>
                <p class="pp-section-label pp-section-label--typography">Typography</p>
                <div class="pp-form-row" id="pp-standings-dynamic-font-fields"></div>
            </form>
        </div>

        <div class="pp-modal-footer">
            <button class="pp-button pp-button-secondary" id="pp-cancel-save-standings-colors">Cancel</button>
            <button class="pp-button pp-button-primary" id="pp-standings-palette-save-colors">Save Colors</button>
        </div>
    </div>
</div>
