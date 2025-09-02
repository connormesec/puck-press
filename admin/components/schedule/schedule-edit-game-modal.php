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
            <h3 class="pp-modal-title">Edit Game Promotion</h3>
            <p class="pp-modal-subtitle">Add promotional information for the game</p>
        </div>

        <div class="pp-modal-content">
            <form id="pp-edit-game-form">
                <div class="pp-form-row">
                    <div class="pp-form-group">
                        <label for="pp-promo-header" class="pp-form-label">Promo Header</label>
                        <input type="text" id="pp-promo-header" class="pp-form-input">
                    </div>
                </div>
                <div class="pp-form-row">
                    <div class="pp-form-group">
                        <label for="pp-promo-text" class="pp-form-label">Promo Text</label>
                        <input type="text" id="pp-promo-text" class="pp-form-input">
                    </div>
                </div>
                <div class="pp-form-row">
                    <div class="pp-form-group">
                        <label for="pp-promo-img-url" class="pp-form-label">Promo Image UL</label>
                        <input type="text" id="pp-promo-img-url" class="pp-form-input">
                    </div>
                </div>
                <div class="pp-form-row">
                    <div class="pp-form-group">
                        <label for="pp-promo-ticket-link" class="pp-form-label">Ticket Link</label>
                        <input type="text" id="pp-promo-ticket-link" class="pp-form-input">
                    </div>
                </div> 
            </form>
        </div>

        <div class="pp-modal-footer">
            <button class="pp-button pp-button-secondary" id="pp-cancel-edit-game">Cancel</button>
            <button class="pp-button pp-button-primary" id="pp-confirm-edit-game">Add Source</button>
        </div>
    </div>
</div>