<?php

/**
 * Provide a admin area view for the plugin
 *
 * This file is used to markup the admin-facing aspects of the plugin.
 *
 * @link       https://https://github.com/connormesec/
 * @since      1.0.0
 *
 * @package    Puck_Press
 * @subpackage Puck_Press/admin/partials/schedule
 */

if (!defined('ABSPATH')) {
    exit;
}
?>
<!-- Edit Game Modal -->
<div class="pp-modal-overlay" id="pp-edit-game-modal">
    <div class="pp-modal">
        <button class="pp-modal-close" id="pp-edit-game-modal-close">✕</button>

        <div class="pp-modal-header">
            <h3 class="pp-modal-title">Edit Game</h3>
            <p class="pp-modal-subtitle"></p>
        </div>

        <div class="pp-modal-content">
            <form id="pp-edit-game-form">

                <p class="pp-form-section-label">Game Details</p>

                <div class="pp-form-row">
                    <div class="pp-form-group">
                        <label for="pp-edit-game-date" class="pp-form-label">Date</label>
                        <input type="date" id="pp-edit-game-date" class="pp-form-input">
                    </div>
                    <div class="pp-form-group">
                        <label for="pp-edit-game-time" class="pp-form-label">Time</label>
                        <input type="time" id="pp-edit-game-time" class="pp-form-input">
                    </div>
                </div>

                <div class="pp-form-row">
                    <div class="pp-form-group">
                        <label for="pp-edit-home-or-away" class="pp-form-label">Location</label>
                        <select id="pp-edit-home-or-away" class="pp-form-select">
                            <option value="">— No change —</option>
                            <option value="home">Home</option>
                            <option value="away">Away</option>
                        </select>
                    </div>
                    <div class="pp-form-group">
                        <label for="pp-edit-game-status" class="pp-form-label">Status</label>
                        <select id="pp-edit-game-status" class="pp-form-select">
                            <option value="">— No change —</option>
                            <option value="none">None</option>
                            <option value="final">Final</option>
                            <option value="final-ot">Final OT</option>
                            <option value="final-so">Final SO</option>
                        </select>
                    </div>
                </div>

                <div class="pp-form-row">
                    <div class="pp-form-group">
                        <label for="pp-edit-target-score" class="pp-form-label">Target Score</label>
                        <input type="text" id="pp-edit-target-score" class="pp-form-input" style="width: 25%;">
                    </div>
                    <div class="pp-form-group">
                        <label for="pp-edit-opponent-score" class="pp-form-label">Opponent Score</label>
                        <input type="text" id="pp-edit-opponent-score" class="pp-form-input" style="width: 25%;">
                    </div>
                </div>

                <div class="pp-form-row">
                    <div class="pp-form-group">
                        <label for="pp-edit-venue" class="pp-form-label">Venue</label>
                        <input type="text" id="pp-edit-venue" class="pp-form-input">
                    </div>
                </div>

                <p class="pp-form-section-label">Promo</p>

                <div class="pp-form-row">
                    <div class="pp-form-group">
                        <label for="pp-promo-header" class="pp-form-label">Promo Header</label>
                        <input type="text" id="pp-promo-header" class="pp-form-input">
                    </div>
                </div>
                <div class="pp-form-row">
                    <div class="pp-form-group">
                        <label for="pp-promo-text" class="pp-form-label">Promo Text</label>
                        <textarea id="pp-promo-text" class="pp-form-input" rows="4" style="resize:vertical;"></textarea>
                    </div>
                </div>
                <div class="pp-form-row">
                    <div class="pp-form-group">
                        <label for="pp-promo-img-url" class="pp-form-label">Promo Image URL</label>
                        <input type="text" id="pp-promo-img-url" class="pp-form-input">
                    </div>
                </div>
                <div class="pp-form-row">
                    <div class="pp-form-group">
                        <label for="pp-promo-ticket-link" class="pp-form-label">Ticket Link</label>
                        <input type="text" id="pp-promo-ticket-link" class="pp-form-input">
                    </div>
                </div>


                <p class="pp-form-section-label">Post Summary</p>

                <div class="pp-form-row">
                    <div class="pp-form-group">
                        <label for="pp-post-link" class="pp-form-label">Post Game Summary URL</label>
                        <input type="url" id="pp-post-link" class="pp-form-input" placeholder="https://...">
                        <small class="pp-form-hint">Auto-populated from post game summary. Enter a custom URL to override.</small>
                    </div>
                </div>

            </form>
        </div>

        <div class="pp-modal-footer">
            <button class="pp-button pp-button-secondary" id="pp-cancel-edit-game">Cancel</button>
            <button class="pp-button pp-button-primary" id="pp-confirm-edit-game">Save Edit</button>
        </div>
    </div>
</div>
