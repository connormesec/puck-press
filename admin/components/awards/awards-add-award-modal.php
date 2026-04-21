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

            <!-- Mode toggle -->
            <div style="display:flex;gap:0;margin-bottom:1.25rem;border:1px solid #ddd;border-radius:4px;overflow:hidden;">
                <button type="button" id="pp-award-mode-new" class="button" style="flex:1;border:0;border-radius:0;background:#0073aa;color:#fff;">New Award</button>
                <button type="button" id="pp-award-mode-copy" class="button" style="flex:1;border:0;border-radius:0;background:#f6f7f7;color:#444;">Add Year to Existing</button>
            </div>

            <!-- ── New Award fields ─────────────────────────────────────── -->
            <div id="pp-new-award-form-fields">
                <div class="pp-form-group">
                    <label class="pp-form-label">Year <span style="color:red;">*</span></label>
                    <input type="text" id="pp-new-award-year" class="pp-form-input" placeholder="e.g. 2025">
                </div>
                <div class="pp-form-group">
                    <label class="pp-form-label">Name <span style="color:red;">*</span></label>
                    <input type="text" id="pp-new-award-name" class="pp-form-input" placeholder="e.g. All Rookie Team">
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
            </div>

            <!-- ── Add Year to Existing fields ────────────────────────── -->
            <div id="pp-new-award-copy-mode" style="display:none;">
                <div class="pp-form-group">
                    <label class="pp-form-label">Existing Award <span style="color:red;">*</span></label>
                    <select id="pp-new-award-copy-select" style="width:100%;"></select>
                </div>

                <!-- Read-only preview of selected award -->
                <div id="pp-copy-award-preview" style="display:none;padding:0.625rem 0.75rem;background:#f9f9f9;border-radius:4px;margin-bottom:0.75rem;display:none;align-items:center;gap:0.75rem;">
                    <span id="pp-copy-preview-icon" style="font-size:1.5rem;line-height:1;"></span>
                    <div>
                        <strong id="pp-copy-preview-name" style="font-size:0.9rem;"></strong>
                        <span id="pp-copy-preview-parent" style="font-size:0.8rem;color:#888;margin-left:6px;"></span>
                    </div>
                </div>

                <!-- Hidden fields populated on select -->
                <input type="hidden" id="pp-copy-award-name" value="">
                <input type="hidden" id="pp-copy-award-parent" value="">
                <input type="hidden" id="pp-copy-icon-type" value="">
                <input type="hidden" id="pp-copy-icon-value" value="">
                <input type="hidden" id="pp-copy-sort-order" value="">

                <div class="pp-form-group">
                    <label class="pp-form-label">New Year <span style="color:red;">*</span></label>
                    <input type="text" id="pp-copy-award-year" class="pp-form-input" placeholder="e.g. 2026" style="max-width:120px;">
                </div>
            </div>

            <div id="pp-add-award-error" style="color:#a00;margin-top:8px;display:none;"></div>
        </div>
        <div class="pp-modal-footer">
            <button class="pp-button" id="pp-add-award-modal-cancel">Cancel</button>
            <button class="pp-button pp-button-primary" id="pp-add-award-modal-confirm">Create Award</button>
        </div>
    </div>
</div>
