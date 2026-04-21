<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}
?>
<div id="pp-add-award-player-modal" class="pp-modal-overlay" style="display:none;">
    <div class="pp-modal" style="max-width:560px;">
        <div class="pp-modal-header">
            <h2>Add Player to Award</h2>
            <button class="pp-modal-close" id="pp-add-award-player-modal-close">&times;</button>
        </div>
        <div class="pp-modal-body">
            <input type="hidden" id="pp-awp-award-id" value="">

            <!-- DB Player Mode -->
            <div id="pp-awp-db-mode">
                <div class="pp-form-group">
                    <label class="pp-form-label">Search Player</label>
                    <select id="pp-awp-player-search" style="width:100%;"></select>
                </div>

                <div id="pp-awp-db-details" style="display:none;">
                    <div style="display:flex;gap:1rem;align-items:center;margin:1rem 0;padding:0.75rem;background:#f9f9f9;border-radius:4px;">
                        <img id="pp-awp-db-headshot" src="" alt="" style="width:48px;height:48px;border-radius:50%;object-fit:cover;display:none;">
                        <div>
                            <strong id="pp-awp-db-player-name"></strong><br>
                            <span id="pp-awp-db-team-name" style="color:#666;"></span>
                        </div>
                        <div id="pp-awp-db-logo-wrap" style="margin-left:auto;">
                            <img id="pp-awp-db-logo" src="" alt="" style="width:32px;height:32px;object-fit:contain;display:none;">
                        </div>
                    </div>

                    <div class="pp-form-group">
                        <label class="pp-form-label">Position Override</label>
                        <select id="pp-awp-db-position" class="pp-form-input" style="max-width:200px;">
                            <option value="">-- Use player's position --</option>
                            <option value="F">F</option>
                            <option value="C">C</option>
                            <option value="LW">LW</option>
                            <option value="RW">RW</option>
                            <option value="D">D</option>
                            <option value="LD">LD</option>
                            <option value="RD">RD</option>
                            <option value="G">G</option>
                            <option value="Coach">Coach</option>
                        </select>
                    </div>

                    <div class="pp-form-group">
                        <label class="pp-form-label">Logo Override <span style="color:#888;font-size:0.8em;">(optional)</span></label>
                        <div style="display:flex;align-items:center;gap:0.5rem;">
                            <button type="button" id="pp-awp-db-logo-override-btn" class="button">Choose Logo</button>
                            <img id="pp-awp-db-logo-override-preview" src="" alt="" style="width:24px;height:24px;object-fit:contain;display:none;">
                            <input type="hidden" id="pp-awp-db-logo-override-url" value="">
                            <button type="button" id="pp-awp-db-logo-override-clear" class="button" style="display:none;">Clear</button>
                        </div>
                    </div>
                </div>

                <input type="hidden" id="pp-awp-db-player-id" value="">
                <input type="hidden" id="pp-awp-db-team-id" value="">
                <input type="hidden" id="pp-awp-db-headshot-url" value="">
                <input type="hidden" id="pp-awp-db-logo-url" value="">
                <input type="hidden" id="pp-awp-db-pos" value="">
                <input type="hidden" id="pp-awp-db-name-val" value="">
                <input type="hidden" id="pp-awp-db-team-name-val" value="">

                <p style="margin-top:1rem;"><a href="#" id="pp-awp-toggle-external">Add external player instead &rarr;</a></p>
            </div>

            <!-- External Player Mode -->
            <div id="pp-awp-external-mode" style="display:none;">
                <div class="pp-form-group">
                    <label class="pp-form-label">Player Name <span style="color:red;">*</span></label>
                    <input type="text" id="pp-awp-ext-name" class="pp-form-input" placeholder="e.g. John Doe">
                </div>
                <div class="pp-form-group">
                    <label class="pp-form-label">Team Name <span style="color:red;">*</span></label>
                    <input type="text" id="pp-awp-ext-team" class="pp-form-input" placeholder="e.g. DePaul">
                </div>
                <div class="pp-form-group">
                    <label class="pp-form-label">Position</label>
                    <select id="pp-awp-ext-position" class="pp-form-input" style="max-width:200px;">
                        <option value="F">F</option>
                        <option value="C">C</option>
                        <option value="LW">LW</option>
                        <option value="RW">RW</option>
                        <option value="D">D</option>
                        <option value="LD">LD</option>
                        <option value="RD">RD</option>
                        <option value="G">G</option>
                        <option value="Coach">Coach</option>
                    </select>
                </div>
                <div class="pp-form-group">
                    <label class="pp-form-label">Headshot <span style="color:#888;font-size:0.8em;">(optional)</span></label>
                    <div style="display:flex;align-items:center;gap:0.5rem;">
                        <button type="button" id="pp-awp-ext-headshot-btn" class="button">Choose Headshot</button>
                        <img id="pp-awp-ext-headshot-preview" src="" alt="" style="width:36px;height:48px;object-fit:cover;border-radius:3px;display:none;">
                        <input type="hidden" id="pp-awp-ext-headshot-url" value="">
                        <button type="button" id="pp-awp-ext-headshot-clear" class="button" style="display:none;">Clear</button>
                    </div>
                </div>
                <div class="pp-form-group">
                    <label class="pp-form-label">Team Logo <span style="color:#888;font-size:0.8em;">(optional)</span></label>
                    <div style="display:flex;align-items:center;gap:0.5rem;">
                        <button type="button" id="pp-awp-ext-logo-btn" class="button">Choose Logo</button>
                        <img id="pp-awp-ext-logo-preview" src="" alt="" style="width:24px;height:24px;object-fit:contain;display:none;">
                        <input type="hidden" id="pp-awp-ext-logo-url" value="">
                    </div>
                </div>

                <p style="margin-top:1rem;"><a href="#" id="pp-awp-toggle-db">&larr; Search DB players instead</a></p>
            </div>

            <div id="pp-add-player-error" style="color:#a00;margin-top:8px;display:none;"></div>
        </div>
        <div class="pp-modal-footer">
            <button class="pp-button" id="pp-add-award-player-modal-cancel">Cancel</button>
            <button class="pp-button pp-button-primary" id="pp-add-award-player-modal-confirm">Add Player</button>
        </div>
    </div>
</div>
