<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}
?>
<div id="pp-add-award-modal" class="pp-modal-overlay" style="display:none;">
    <div class="pp-modal">
        <div class="pp-modal-header">
            <h2>New Award</h2>
            <button class="pp-modal-close" id="pp-add-award-modal-close">&times;</button>
        </div>
        <div class="pp-modal-body">
            <div class="pp-form-group">
                <label class="pp-form-label">Year <span style="color:red;">*</span></label>
                <input type="text" id="pp-new-award-year" class="pp-form-input" placeholder="e.g. 2025">
            </div>
            <div class="pp-form-group">
                <label class="pp-form-label">Name <span style="color:red;">*</span></label>
                <input type="text" id="pp-new-award-name" class="pp-form-input" placeholder="e.g. All Rookie Team">
            </div>
            <div class="pp-form-group">
                <label class="pp-form-label">Shortcode Label <span style="color:red;">*</span> <span style="color:#888;font-size:0.8em;">(max 4 chars)</span></label>
                <input type="text" id="pp-new-award-shortcode-label" class="pp-form-input" placeholder="e.g. RKY" maxlength="4" style="max-width:120px;">
            </div>
            <div class="pp-form-group">
                <label class="pp-form-label">Parent Group <span style="color:#888;font-size:0.8em;">(optional, for shortcode grouping)</span></label>
                <select id="pp-new-award-parent" class="pp-form-input pp-award-parent-select" style="width:100%;"></select>
            </div>
            <div class="pp-form-group">
                <label class="pp-form-label">Icon Type</label>
                <div style="display:flex;gap:1rem;margin-top:4px;">
                    <label><input type="radio" name="pp-new-award-icon-type" value="emoji" checked> Emoji</label>
                    <label><input type="radio" name="pp-new-award-icon-type" value="image"> Image</label>
                </div>
            </div>
            <div class="pp-form-group pp-icon-emoji-group">
                <label class="pp-form-label">Emoji</label>
                <input type="text" id="pp-new-award-icon-emoji" class="pp-form-input" value="🏅" style="max-width:80px;">
            </div>
            <div class="pp-form-group pp-icon-image-group" style="display:none;">
                <label class="pp-form-label">Image</label>
                <div style="display:flex;align-items:center;gap:0.5rem;">
                    <button type="button" id="pp-new-award-icon-image-btn" class="button">Choose Image</button>
                    <img id="pp-new-award-icon-image-preview" src="" alt="" style="width:32px;height:32px;object-fit:contain;display:none;">
                    <input type="hidden" id="pp-new-award-icon-image-url" value="">
                </div>
            </div>
            <div class="pp-form-group">
                <label class="pp-form-label">Sort Order</label>
                <input type="number" id="pp-new-award-sort-order" class="pp-form-input" value="0" style="max-width:80px;">
            </div>
            <div id="pp-add-award-error" style="color:#a00;margin-top:8px;display:none;"></div>
        </div>
        <div class="pp-modal-footer">
            <button class="pp-button" id="pp-add-award-modal-cancel">Cancel</button>
            <button class="pp-button pp-button-primary" id="pp-add-award-modal-confirm">Create Award</button>
        </div>
    </div>
</div>
