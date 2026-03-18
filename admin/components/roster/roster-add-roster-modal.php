<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<div id="pp-add-roster-modal" class="pp-modal-overlay" style="display:none;">
	<div class="pp-modal">
		<div class="pp-modal-header">
			<h2>New Roster</h2>
			<button class="pp-modal-close" id="pp-add-roster-modal-close">&times;</button>
		</div>
		<div class="pp-modal-body">
			<div class="pp-form-group">
				<label class="pp-form-label">Name</label>
				<input type="text" id="pp-new-roster-name" class="pp-form-input" placeholder="e.g. Eagles Roster">
			</div>
			<div class="pp-form-group">
				<label class="pp-form-label">Slug</label>
				<input type="text" id="pp-new-roster-slug" class="pp-form-input" placeholder="e.g. eagles">
			</div>
		</div>
		<div class="pp-modal-footer">
			<button class="pp-button" id="pp-add-roster-modal-cancel">Cancel</button>
			<button class="pp-button pp-button-primary" id="pp-add-roster-modal-confirm">Create Roster</button>
		</div>
	</div>
</div>
