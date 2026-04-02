<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}
?>
<div id="pp-bulk-add-team-modal" class="pp-modal-overlay" style="display:none;">
    <div class="pp-modal">
        <div class="pp-modal-header">
            <h2>Add Entire Team to Award</h2>
            <button class="pp-modal-close" id="pp-bulk-add-team-modal-close">&times;</button>
        </div>
        <div class="pp-modal-body">
            <input type="hidden" id="pp-bulk-team-award-id" value="">
            <div class="pp-form-group">
                <label class="pp-form-label">Select Team</label>
                <select id="pp-bulk-team-select" style="width:100%;"></select>
            </div>
            <p style="color:#666;font-size:0.85rem;margin-top:0.5rem;">All players from the selected team will be added to this award. Players already on the award will be skipped.</p>
            <div id="pp-bulk-add-team-error" style="color:#a00;margin-top:8px;display:none;"></div>
            <div id="pp-bulk-add-team-result" style="color:#080;margin-top:8px;display:none;"></div>
        </div>
        <div class="pp-modal-footer">
            <button class="pp-button" id="pp-bulk-add-team-modal-cancel">Cancel</button>
            <button class="pp-button pp-button-primary" id="pp-bulk-add-team-modal-confirm">Add All Players</button>
        </div>
    </div>
</div>
