<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<!-- Add Player Modal -->
<div class="pp-modal-overlay" id="pp-add-player-modal">
	<div class="pp-modal">
		<button class="pp-modal-close" id="pp-add-player-modal-close">&#x2715;</button>

		<div class="pp-modal-header">
			<h3 class="pp-modal-title">Add Player</h3>
			<p class="pp-modal-subtitle">Manually add a player to the roster</p>
		</div>

		<div class="pp-modal-content">
			<form id="pp-add-player-form">
				<div class="pp-form-row">
					<div class="pp-form-group">
						<label for="pp-new-player-name" class="pp-form-label">Name <span style="color:red">*</span></label>
						<input type="text" id="pp-new-player-name" class="pp-form-input" required>
					</div>
				</div>
				<div class="pp-form-row">
					<div class="pp-form-group">
						<label for="pp-new-player-number" class="pp-form-label">Number</label>
						<input type="text" id="pp-new-player-number" class="pp-form-input">
					</div>
					<div class="pp-form-group">
						<label for="pp-new-player-position" class="pp-form-label">Position</label>
						<select id="pp-new-player-position" class="pp-form-select">
							<option value="">-- Select --</option>
							<option value="forward">Forward</option>
							<option value="defense">Defense</option>
							<option value="goalie">Goalie</option>
						</select>
					</div>
					<div class="pp-form-group">
						<label for="pp-new-player-height" class="pp-form-label">Height</label>
						<input type="text" id="pp-new-player-height" class="pp-form-input">
					</div>
					<div class="pp-form-group">
						<label for="pp-new-player-weight" class="pp-form-label">Weight</label>
						<input type="text" id="pp-new-player-weight" class="pp-form-input">
					</div>
					<div class="pp-form-group">
						<label for="pp-new-player-shoots" class="pp-form-label">Shoots</label>
						<select id="pp-new-player-shoots" class="pp-form-select">
							<option value="">-- Select --</option>
							<option value="right">Right</option>
							<option value="left">Left</option>
						</select>
					</div>
				</div>
				<div class="pp-form-row">
					<div class="pp-form-group">
						<label for="pp-new-player-hometown" class="pp-form-label">Hometown</label>
						<input type="text" id="pp-new-player-hometown" class="pp-form-input">
					</div>
					<div class="pp-form-group">
						<label for="pp-new-player-last-team" class="pp-form-label">Last Team</label>
						<input type="text" id="pp-new-player-last-team" class="pp-form-input">
					</div>
				</div>
				<div class="pp-form-row">
					<div class="pp-form-group">
						<label for="pp-new-player-year" class="pp-form-label">Year</label>
						<select id="pp-new-player-year" class="pp-form-select">
							<option value="">-- Select --</option>
							<option value="freshman">Freshman</option>
							<option value="sophomore">Sophomore</option>
							<option value="junior">Junior</option>
							<option value="senior">Senior</option>
						</select>
					</div>
					<div class="pp-form-group">
						<label for="pp-new-player-major" class="pp-form-label">Major</label>
						<input type="text" id="pp-new-player-major" class="pp-form-input">
					</div>
				</div>
				<div class="pp-form-row">
					<div class="pp-form-group">
						<label for="pp-new-player-headshot-url" class="pp-form-label">Headshot URL</label>
						<input type="text" id="pp-new-player-headshot-url" class="pp-form-input">
					</div>
				</div>
				<div class="pp-form-row">
					<div class="pp-form-group">
						<label for="pp-new-player-hero-image-url" class="pp-form-label">Hero Image</label>
						<div style="display:flex;gap:8px;align-items:center;">
							<input type="text" id="pp-new-player-hero-image-url" class="pp-form-input" placeholder="Paste URL or use Browse&hellip;">
							<button type="button" class="pp-button pp-button-secondary pp-hero-image-browse-btn" data-target="#pp-new-player-hero-image-url">Browse&hellip;</button>
						</div>
					</div>
				</div>
			</form>
		</div>

		<div class="pp-modal-footer">
			<button class="pp-button pp-button-secondary" id="pp-cancel-add-player">Cancel</button>
			<button class="pp-button pp-button-primary" id="pp-confirm-add-player">Add Player</button>
		</div>
	</div>
</div>
