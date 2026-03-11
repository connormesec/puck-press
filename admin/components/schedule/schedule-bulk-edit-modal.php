<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<!-- Bulk Edit Games Modal -->
<div class="pp-modal-overlay pp-bulk-edit-modal" id="pp-bulk-schedule-modal">
	<div class="pp-modal pp-bulk-edit-modal__inner">
		<button class="pp-modal-close" id="pp-bulk-schedule-close">✕</button>

		<div class="pp-modal-header">
			<h3 class="pp-modal-title">Bulk Edit Games</h3>
		</div>

		<div class="pp-modal-body">

			<!-- Field selector -->
			<div class="pp-bulk-edit-field-row">
				<label class="pp-label" for="pp-bulk-schedule-field">Field to Edit</label>
				<div class="pp-bulk-edit-field-inputs">
					<select class="pp-input" id="pp-bulk-schedule-field"></select>
					<input class="pp-input pp-bulk-edit-value-input" type="text" id="pp-bulk-schedule-value" placeholder="">
				</div>
				<p class="pp-bulk-edit-clear-hint">Leave blank to clear the field on selected games.</p>
			</div>

			<!-- Filters -->
			<div class="pp-bulk-edit-filters">
				<div class="pp-bulk-edit-filter-group">
					<label class="pp-label" for="pp-bulk-schedule-ha">Home / Away</label>
					<select class="pp-input" id="pp-bulk-schedule-ha">
						<option value="all" selected>All</option>
						<option value="home">Home</option>
						<option value="away">Away</option>
					</select>
				</div>
				<div class="pp-bulk-edit-filter-group">
					<label class="pp-label" for="pp-bulk-schedule-contains">Venue contains</label>
					<input class="pp-input" type="text" id="pp-bulk-schedule-contains" placeholder="e.g. Arena">
				</div>
				<div class="pp-bulk-edit-filter-group">
					<label class="pp-label" for="pp-bulk-schedule-excludes">Venue excludes</label>
					<input class="pp-input" type="text" id="pp-bulk-schedule-excludes" placeholder="e.g. Tournament">
				</div>
				<div class="pp-bulk-edit-filter-group">
					<label class="pp-label">Date range</label>
					<div class="pp-bulk-edit-radios">
						<label><input type="radio" name="pp-bulk-schedule-date" value="upcoming"> Upcoming</label>
						<label><input type="radio" name="pp-bulk-schedule-date" value="all" checked> All</label>
					</div>
				</div>
			</div>

			<!-- Select All / Deselect All + count -->
			<div class="pp-bulk-edit-select-bar">
				<button type="button" id="pp-bulk-schedule-select-all">Select All Visible</button>
				<span class="pp-bulk-edit-sep">·</span>
				<button type="button" id="pp-bulk-schedule-deselect-all">Deselect All</button>
				<span class="pp-bulk-edit-count" id="pp-bulk-schedule-count">0 games selected</span>
			</div>

			<!-- Game list -->
			<ul class="pp-bulk-edit-item-list" id="pp-bulk-schedule-list"></ul>

			<!-- Error message -->
			<p class="pp-bulk-edit-error" id="pp-bulk-schedule-error" style="display:none;"></p>

		</div>

		<div class="pp-modal-footer">
			<button class="pp-button pp-button-secondary" id="pp-bulk-schedule-cancel">Cancel</button>
			<button class="pp-button pp-button-primary" id="pp-bulk-schedule-apply">Apply</button>
		</div>
	</div>
</div>
