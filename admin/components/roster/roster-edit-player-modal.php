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

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<!-- Edit Player Modal -->
<div class="pp-modal-overlay" id="pp-edit-player-modal">
	<div class="pp-modal">
		<button class="pp-modal-close" id="pp-edit-player-modal-close">✕</button>

		<div id="pp-edit-player-loading" class="pp-modal-loading-overlay">
			<div class="pp-modal-loading-spinner"></div>
			<span>Loading player data&hellip;</span>
		</div>

		<div class="pp-modal-header">
			<h3 class="pp-modal-title">Edit Player</h3>
			<p class="pp-modal-subtitle">Change information for the player</p>
		</div>

		<div class="pp-modal-content">
			<form id="pp-edit-player-form">
				<div class="pp-form-row">
					<div class="pp-form-group">
						<label for="pp-edit-player-name" class="pp-form-label">Name</label>
						<input type="text" id="pp-edit-player-name" class="pp-form-input">
					</div>
				</div>
				<div class="pp-form-row">
					<div class="pp-form-group">
						<label for="pp-edit-player-number" class="pp-form-label">Number</label>
						<input type="text" id="pp-edit-player-number" class="pp-form-input">
					</div>
					<div class="pp-form-group">
						<label for="pp-edit-player-position" class="pp-form-label">Position</label>
						<input type="text" id="pp-edit-player-position" class="pp-form-input">
					</div>
					<div class="pp-form-group">
						<label for="pp-edit-player-height" class="pp-form-label">Height</label>
						<input type="text" id="pp-edit-player-height" class="pp-form-input">
					</div>
					<div class="pp-form-group">
						<label for="pp-edit-player-weight" class="pp-form-label">Weight</label>
						<input type="text" id="pp-edit-player-weight" class="pp-form-input">
					</div>
					<div class="pp-form-group">
						<label for="pp-edit-player-shoots" class="pp-form-label">Shoots</label>
						<select id="pp-edit-player-shoots" class="pp-form-select">
							<option selected="selected" value="right">Right</option>
							<option value="left">Left</option>
						</select>
					</div>
				</div>
				<div class="pp-form-row">
					<div class="pp-form-group">
						<label for="pp-edit-player-hometown" class="pp-form-label">Hometown</label>
						<input type="text" id="pp-edit-player-hometown" class="pp-form-input">
					</div>
					<div class="pp-form-group">
						<label for="pp-edit-player-last-team" class="pp-form-label">Last Team</label>
						<input type="text" id="pp-edit-player-last-team" class="pp-form-input">
					</div>
				</div>
				<div class="pp-form-row">
					<div class="pp-form-group">
						<label for="pp-edit-player-year" class="pp-form-label">Year</label>
						<select id="pp-edit-player-year" class="pp-form-select">
							<option selected="selected" value="freshman">Freshman</option>
							<option value="sophomore">Sophomore</option>
							<option value="junior">Junior</option>
							<option value="senior">Senior</option>
						</select>
					</div>
					<div class="pp-form-group">
						<label for="pp-edit-player-major" class="pp-form-label">Major</label>
						<input type="text" id="pp-edit-player-major" class="pp-form-input">
					</div>
				</div>
				<div class="pp-form-row">
					<div class="pp-form-group">
						<label for="pp-edit-player-headshot-url" class="pp-form-label">Headshot URL</label>
						<input type="text" id="pp-edit-player-headshot-url" class="pp-form-input">
					</div>
				</div>
				<div class="pp-form-row">
					<div class="pp-form-group">
						<label for="pp-edit-player-hero-image-url" class="pp-form-label">Hero Image</label>
						<div style="display:flex;gap:8px;align-items:center;">
							<input type="text" id="pp-edit-player-hero-image-url" class="pp-form-input" placeholder="Paste URL or use Browse&hellip;">
							<button type="button" class="pp-button pp-button-secondary pp-hero-image-browse-btn" data-target="#pp-edit-player-hero-image-url">Browse&hellip;</button>
						</div>
					</div>
				</div>
			</form>
		</div>

		<div class="pp-modal-footer">
			<button class="pp-button pp-button-secondary" id="pp-cancel-edit-player">Cancel</button>
			<button class="pp-button pp-button-primary" id="pp-confirm-edit-player">Submit</button>
		</div>
	</div>
</div>