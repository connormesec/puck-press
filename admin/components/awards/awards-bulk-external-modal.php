<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}
?>
<div id="pp-bulk-external-modal" class="pp-modal-overlay" style="display:none;">
    <div class="pp-modal" style="max-width:640px;">
        <div class="pp-modal-header">
            <h2>Bulk Add External Players</h2>
            <button class="pp-modal-close" id="pp-bulk-external-modal-close">&times;</button>
        </div>
        <div class="pp-modal-body">
            <input type="hidden" id="pp-bulk-ext-award-id" value="">

            <!-- CSV Format Documentation -->
            <div style="background:#f0f6fc;border:1px solid #c3d9f0;border-radius:4px;padding:0.875rem 1rem;margin-bottom:1.25rem;">
                <p style="margin:0 0 0.5rem;font-weight:600;font-size:0.9rem;">CSV Format Requirements</p>
                <table style="width:100%;border-collapse:collapse;font-size:0.82rem;margin-bottom:0.625rem;">
                    <thead>
                        <tr style="background:#daeaf7;">
                            <th style="padding:4px 8px;text-align:left;border:1px solid #c3d9f0;">Column</th>
                            <th style="padding:4px 8px;text-align:left;border:1px solid #c3d9f0;">Required?</th>
                            <th style="padding:4px 8px;text-align:left;border:1px solid #c3d9f0;">Notes</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td style="padding:4px 8px;border:1px solid #c3d9f0;"><strong>Name</strong></td>
                            <td style="padding:4px 8px;border:1px solid #c3d9f0;">Yes</td>
                            <td style="padding:4px 8px;border:1px solid #c3d9f0;">Player's full name</td>
                        </tr>
                        <tr>
                            <td style="padding:4px 8px;border:1px solid #c3d9f0;"><strong>Team</strong></td>
                            <td style="padding:4px 8px;border:1px solid #c3d9f0;">Yes</td>
                            <td style="padding:4px 8px;border:1px solid #c3d9f0;">Team name</td>
                        </tr>
                        <tr>
                            <td style="padding:4px 8px;border:1px solid #c3d9f0;"><strong>Position</strong></td>
                            <td style="padding:4px 8px;border:1px solid #c3d9f0;">No</td>
                            <td style="padding:4px 8px;border:1px solid #c3d9f0;">F, C, LW, RW, D, LD, RD, G, Coach &mdash; or leave blank</td>
                        </tr>
                        <tr>
                            <td style="padding:4px 8px;border:1px solid #c3d9f0;"><strong>Headshot URL</strong></td>
                            <td style="padding:4px 8px;border:1px solid #c3d9f0;">No</td>
                            <td style="padding:4px 8px;border:1px solid #c3d9f0;">Direct image URL, or leave blank</td>
                        </tr>
                        <tr>
                            <td style="padding:4px 8px;border:1px solid #c3d9f0;"><strong>Logo URL</strong></td>
                            <td style="padding:4px 8px;border:1px solid #c3d9f0;">No</td>
                            <td style="padding:4px 8px;border:1px solid #c3d9f0;">Team logo image URL, or leave blank</td>
                        </tr>
                    </tbody>
                </table>
                <ul style="margin:0;padding-left:1.25rem;font-size:0.82rem;line-height:1.6;">
                    <li>The <strong>first row must be a header row</strong> &mdash; it is always skipped.</li>
                    <li>Players already on this award (same name + team) are <strong>skipped</strong>, not flagged as errors.</li>
                    <li>Fields with commas must be wrapped in double quotes.</li>
                </ul>
                <div style="margin-top:0.625rem;">
                    <a id="pp-bulk-ext-download-template" href="#" style="font-size:0.82rem;">&#8595; Download example template</a>
                </div>
            </div>

            <!-- File Picker -->
            <div class="pp-form-group">
                <label class="pp-form-label">Choose CSV File</label>
                <input type="file" id="pp-bulk-ext-file" accept=".csv" style="display:block;margin-top:4px;">
            </div>

            <!-- Preview Table -->
            <div id="pp-bulk-ext-preview" style="display:none;margin-top:0.75rem;">
                <p style="margin:0 0 0.4rem;font-size:0.85rem;font-weight:600;">Preview <span style="color:#888;font-weight:400;">(first 5 rows)</span></p>
                <div style="overflow-x:auto;">
                    <table id="pp-bulk-ext-preview-table" class="widefat striped" style="border:0;font-size:0.82rem;">
                        <thead>
                            <tr>
                                <th>Name</th>
                                <th>Team</th>
                                <th>Position</th>
                                <th>Headshot URL</th>
                                <th>Logo URL</th>
                            </tr>
                        </thead>
                        <tbody></tbody>
                    </table>
                </div>
                <p id="pp-bulk-ext-preview-more" style="font-size:0.8rem;color:#888;margin:4px 0 0;display:none;"></p>
            </div>

            <div id="pp-bulk-ext-error" style="color:#a00;margin-top:8px;display:none;"></div>
            <div id="pp-bulk-ext-result" style="color:#080;margin-top:8px;display:none;"></div>
        </div>
        <div class="pp-modal-footer">
            <button class="pp-button" id="pp-bulk-external-modal-cancel">Cancel</button>
            <button class="pp-button pp-button-primary" id="pp-bulk-external-modal-confirm" disabled>Add Players</button>
        </div>
    </div>
</div>
