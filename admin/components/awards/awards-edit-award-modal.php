<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}
?>
<div id="pp-edit-award-modal" class="pp-modal-overlay" style="display:none;">
    <div class="pp-modal">
        <div class="pp-modal-header">
            <h2>Edit Award</h2>
            <button class="pp-modal-close" id="pp-edit-award-modal-close">&times;</button>
        </div>
        <div class="pp-modal-body">
            <input type="hidden" id="pp-edit-award-id" value="">
            <div class="pp-form-group">
                <label class="pp-form-label">Slug <span style="color:#888;font-size:0.8em;">(read-only)</span></label>
                <input type="text" id="pp-edit-award-slug" class="pp-form-input" readonly style="background:#f5f5f5;color:#888;">
            </div>
            <div class="pp-form-group">
                <label class="pp-form-label">Year <span style="color:red;">*</span></label>
                <input type="text" id="pp-edit-award-year" class="pp-form-input">
            </div>
            <div class="pp-form-group">
                <label class="pp-form-label">Name <span style="color:red;">*</span></label>
                <input type="text" id="pp-edit-award-name" class="pp-form-input">
            </div>
            <div class="pp-form-group">
                <label class="pp-form-label">Parent Group</label>
                <select id="pp-edit-award-parent" class="pp-form-input pp-award-parent-select" style="width:100%;"></select>
            </div>
            <div class="pp-form-group">
                <label class="pp-form-label">Icon Type</label>
                <div style="display:flex;gap:1rem;margin-top:4px;">
                    <label><input type="radio" name="pp-edit-award-icon-type" value="emoji" checked> Emoji</label>
                    <label><input type="radio" name="pp-edit-award-icon-type" value="image"> Image</label>
                </div>
            </div>
            <div class="pp-form-group pp-edit-icon-emoji-group">
                <label class="pp-form-label">Emoji</label>
                <input type="text" id="pp-edit-award-icon-emoji" class="pp-form-input" style="max-width:80px;">
            </div>
            <div class="pp-form-group pp-edit-icon-image-group" style="display:none;">
                <label class="pp-form-label">Image</label>
                <div style="display:flex;align-items:center;gap:0.5rem;">
                    <button type="button" id="pp-edit-award-icon-image-btn" class="button">Choose Image</button>
                    <img id="pp-edit-award-icon-image-preview" src="" alt="" style="width:32px;height:32px;object-fit:contain;display:none;">
                    <input type="hidden" id="pp-edit-award-icon-image-url" value="">
                </div>
            </div>
            <div class="pp-form-group">
                <label class="pp-form-label">Sort Order</label>
                <input type="number" id="pp-edit-award-sort-order" class="pp-form-input" value="0" style="max-width:80px;">
            </div>
            <div id="pp-edit-award-error" style="color:#a00;margin-top:8px;display:none;"></div>
        </div>
        <div class="pp-modal-footer">
            <button class="pp-button" id="pp-edit-award-modal-cancel">Cancel</button>
            <button class="pp-button pp-button-primary" id="pp-edit-award-modal-confirm">Save Changes</button>
        </div>
    </div>
</div>
