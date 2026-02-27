<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }
?>
<!-- Player Detail Color Palette Modal -->
<div id="pp-player-detail-paletteModal" class="pp-modal-overlay-palette">
    <div class="pp-modal-palette">
        <button class="pp-modal-close" id="pp-player-detail-palette-modal-close">&#10005;</button>

        <div class="pp-form-row">
            <div class="pp-modal-header">
                <h3 class="pp-modal-title">Player Page Colors</h3>
                <p class="pp-modal-subtitle">Customize the appearance of the /player/ detail page.</p>
            </div>
            <!--
                Hidden selector required by createColorPickerController().
                Single fixed value 'pd' — no template switching needed.
            -->
            <select id="pp-player-detail-template-selector" style="display:none;">
                <option value="pd" selected>Player Detail</option>
            </select>
        </div>

        <div class="pp-modal-content">
            <form id="pp-player-detail-color-palette-form">
                <p class="pp-section-label">Colors</p>
                <div class="pp-form-row" id="pp-player-detail-dynamic-color-fields">
                    <!-- Populated by puck-press-player-detail-color-picker.js -->
                </div>
                <p class="pp-section-label pp-section-label--typography">Typography</p>
                <div class="pp-form-row" id="pp-player-detail-dynamic-font-fields">
                    <!-- Populated by puck-press-player-detail-color-picker.js -->
                </div>
            </form>
        </div>

        <div class="pp-modal-footer">
            <button class="pp-button pp-button-secondary" id="pp-cancel-save-player-detail-colors">Cancel</button>
            <button class="pp-button pp-button-primary" id="pp-player-detail-palette-save-colors">Save Colors</button>
        </div>
    </div>
</div>
