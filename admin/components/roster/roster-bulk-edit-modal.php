<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<!-- Bulk Edit Players Modal -->
<div class="pp-modal-overlay pp-bulk-edit-modal" id="pp-bulk-roster-modal">
	<div class="pp-modal pp-bulk-edit-modal__inner">
		<button class="pp-modal-close" id="pp-bulk-roster-close">✕</button>

		<div class="pp-modal-header">
			<h3 class="pp-modal-title">Bulk Edit Players</h3>
		</div>

		<div class="pp-modal-body">

			<!-- Field selector -->
			<div class="pp-bulk-edit-field-row">
				<label class="pp-label" for="pp-bulk-roster-field">Field to Edit</label>
				<div class="pp-bulk-edit-field-inputs">
					<select class="pp-input" id="pp-bulk-roster-field"></select>
					<input class="pp-input pp-bulk-edit-value-input" type="url" id="pp-bulk-roster-value" placeholder="">
				</div>
				<p class="pp-bulk-edit-clear-hint">Leave blank to clear the field on selected players.</p>
			</div>

			<!-- Filters -->
			<div class="pp-bulk-edit-filters">
				<div class="pp-bulk-edit-filter-group">
					<label class="pp-label" for="pp-bulk-roster-pos">Position Group</label>
					<select class="pp-input" id="pp-bulk-roster-pos">
						<option value="all" selected>All Players</option>
						<option value="forwards">Forwards (F/C/LW/RW)</option>
						<option value="defense">Defense (D/LD/RD)</option>
						<option value="goalies">Goalies (G)</option>
					</select>
				</div>
				<div class="pp-bulk-edit-filter-group">
					<label class="pp-label" for="pp-bulk-roster-name-filter">Name contains</label>
					<input class="pp-input" type="text" id="pp-bulk-roster-name-filter" placeholder="Filter by name">
				</div>
			</div>

			<!-- Select All / Deselect All + count -->
			<div class="pp-bulk-edit-select-bar">
				<button type="button" id="pp-bulk-roster-select-all">Select All Visible</button>
				<span class="pp-bulk-edit-sep">·</span>
				<button type="button" id="pp-bulk-roster-deselect-all">Deselect All</button>
				<span class="pp-bulk-edit-count" id="pp-bulk-roster-count">0 players selected</span>
			</div>

			<!-- Player list -->
			<ul class="pp-bulk-edit-item-list" id="pp-bulk-roster-list"></ul>

			<!-- Error message -->
			<p class="pp-bulk-edit-error" id="pp-bulk-roster-error" style="display:none;"></p>

		</div>

		<div class="pp-modal-footer">
			<button class="pp-button pp-button-secondary" id="pp-bulk-roster-cancel">Cancel</button>
			<button class="pp-button pp-button-primary" id="pp-bulk-roster-apply">Apply</button>
		</div>
	</div>
</div>
