(function ($) {
	let currentPlayerData = {
		name: null,
		number: null,
		pos: null,
		ht: null,
		wt: null,
		shoots: null,
		hometown: null,
		headshot_link: null,
		last_team: null,
		year: null,
		major: null
	}

	jQuery(document).ready(function ($) {
		const dimEditListStyles = () => {
			const $el = $('#pp-card-roster-preview, #pp-card-raw-roster-table , #pp-card-roster-edits-table, .pp-modal');
			$el.css({
				'opacity': '0.5',
				'pointer-events': 'none'
			});
		};
		const restoreEditListStyles = () => {
			$('#pp-card-roster-preview, #pp-card-raw-roster-table , #pp-card-roster-edits-table, .pp-modal').css({
				'opacity': '1',
				'pointer-events': 'auto' // re-enables interaction
			});
		}


		//############################################################//
		//                                                            //
		//               Edit Player Modal functionality              //
		//                                                            //
		//############################################################//

		const $editPlayerModal = $('#pp-edit-player-modal');
		const $closeEditPlayerModalBtn = $('#pp-edit-player-modal-close');
		const $cancelEditPlayerBtn = $('#pp-cancel-edit-player');
		const $confirmBtn_editPlayerModal = $('#pp-confirm-edit-player');
		const $editPlayerForm = $('#pp-edit-player-form');
		let currentEditingPlayerId = null;


		// Open modal
		$(document).on('click', '#pp-edit-player-button', function () {
			const playerId = $(this).data('player-id');
			currentEditingPlayerId = playerId;
			const $button = $(this);
			populateEditPlayerModal($button); //set the current player data
			console.log('Edit player button clicked for player id:', currentEditingPlayerId);
			$editPlayerModal.css('display', 'flex');
		});

		// Close modal function
		function closeEditPlayerModal() {
			$editPlayerModal.css('display', 'none');
			$editPlayerForm[0].reset();
		}

		$closeEditPlayerModalBtn.on('click', closeEditPlayerModal);
		$cancelEditPlayerBtn.on('click', closeEditPlayerModal);

		// Close modal when clicking outside
		enableClickOutsideToClose($editPlayerModal, closeEditPlayerModal);

		// Form submission
		$confirmBtn_editPlayerModal.on('click', function () {
			// Check form validity
			if (!$editPlayerForm[0].checkValidity()) {
				$editPlayerForm[0].reportValidity();
				return;
			}

			dimEditListStyles();

			const newValues = {
				name: $('#pp-edit-player-name').val(),
				number: $('#pp-edit-player-number').val(),
				pos: $('#pp-edit-player-position').val(),
				ht: $('#pp-edit-player-height').val(),
				wt: $('#pp-edit-player-weight').val(),
				shoots: $('#pp-edit-player-shoots').val(),
				hometown: $('#pp-edit-player-hometown').val(),
				last_team: $('#pp-edit-player-last-team').val(),
				year: $('#pp-edit-player-year').val(),
				major: $('#pp-edit-player-major').val(),
				headshot_link: $('#pp-edit-player-headshot-url').val(),
			};

			// Compare and only keep changed fields
			const changedFields = {};
			Object.keys(newValues).forEach(key => {
				if (newValues[key] !== currentPlayerData[key]) {
					changedFields[key] = newValues[key];
				}
			});

			// If nothing changed, skip submission
			if (Object.keys(changedFields).length === 0) {
				alert('No changes detected.');
				restoreEditListStyles();
				return;
			}

			const edit_data = {
				edit_action: 'update',
				fields: {
					...changedFields,
					external_id: currentEditingPlayerId
				}
			};

			console.log('Edit data:', edit_data);

			const formData = new FormData();
			formData.append('action', 'pp_update_player_edits');
			formData.append('edit_data', JSON.stringify(edit_data));

			$.ajax({
				url: ajaxurl,
				method: 'POST',
				data: formData,
				processData: false,
				contentType: false,
				success: function (response) {
					console.log(response)
					if (response.success) {
						console.log('Success:', response);
						// Optionally refresh the games table or perform other actions
						closeEditPlayerModal()
						$.ajax({
							url: ajaxurl,
							method: 'POST',
							data: {
								action: 'ajax_refresh_roster_edits_table_card'
							},
							success: function (response) {
								if (response.success) {
									$('#pp-edits-table').html(response.data); // Replace with updated HTML
									refreshGamesTable().then(() => {
										restoreEditListStyles();
									});
								} else {
									console.error(response.data?.message || 'Unknown error');;
								}
							},
							error: function (err) {
								console.error('AJAX error:', err);
								restoreEditListStyles();
							}
						});
					} else {
						console.error('Error:', response);
						alert('Failed to add edit.');
					}
				},
				error: function (err) {
					console.error('Error:', err);
					alert('Failed to add edit.');
				}
			});

			closeEditPlayerModal();

		});

		//############################################################//
		//                                                            //
		//               Delete Player Button Funcitonality		      //
		//                                                            //
		//############################################################//

		$(document).on('click', '#pp-delete-player-button', function () {
			dimEditListStyles();

			const playerId = $(this).data('player-id');

			const edit_data = {
				edit_action: 'delete',
				fields: {
					external_id: playerId,
				}
			}
			const formData = new FormData();
			formData.append('action', 'pp_update_player_edits');
			formData.append('edit_data', JSON.stringify(edit_data));

			$.ajax({
				url: ajaxurl,
				type: 'POST',
				data: formData,
				processData: false,
				contentType: false,
				success: function (response) {
					if (response.success) {
						console.log('Player deleted successfully.');
						refreshGamesTable().then(() => {
							restoreEditListStyles();
						});
					} else {
						console.error('Error deleting edit:', response.data);
						alert('There was an error deleting the edit.');
					}
				},
				error: function () {
					alert('There was an error with the AJAX request to delete the edit.');
				}
			});

		});


		//############################################################//
		//                                                            //
		//               Delete Edit From Edit Table Button           //
		//                                                            //
		//############################################################//

		$(document).on('click', '#pp-delete-edit-button', function () {
			const confirmed = confirm('Are you sure you want to delete this item?');
			if (!confirmed) return;

			const id = $(this).data('edit-id');
			const $nearestRow = $(this).closest('tr');

			dimEditListStyles();

			$.ajax({
				url: ajaxurl,
				type: 'POST',
				data: {
					action: 'ajax_delete_player_edit', // The action hook
					id: id, // The ID of the edit to delete
				},
				success: function (response) {
					if (response.success) {
						// Remove the row from the HTML table
						$nearestRow.remove();
						refreshGamesTable(
							function (refreshResponse) {
								if (refreshResponse.success) {
									restoreEditListStyles();
									console.log('All sources refreshed successfully.');
								} else {
									alert('Failed to refresh all sources.');
								}
							},
							function () {
								alert('An error occurred while refreshing all sources.');
							}).then(() => {
								restoreEditListStyles();
							});
						console.log('Edit deleted successfully.');
					} else {
						console.error('Error deleting edit:', response.data);
						alert('There was an error deleting the edit.');
					}
				},
				error: function () {
					alert('There was an error with the AJAX request to delete the edit.');
				}
			});
		});
	});

	//############################################################//
	//                                                            //
	//           PrePopulate the modal with values		          //
	//                                                            //
	//############################################################//
	function populateEditPlayerModal($button) {
		const $row = $button.closest('tr');
		const tds = $row.find('td');

		currentPlayerData.name = $(tds[3]).text().trim();
		$('#pp-edit-player-name').val(currentPlayerData.name);
		currentPlayerData.number = $(tds[2]).text().trim();
		$('#pp-edit-player-number').val(currentPlayerData.number);

		// Normalize position abbreviations (F, D, G) to full values if needed
		const posMap = { 'F': 'forward', 'D': 'defense', 'G': 'goalie' };
		const rawPos = $(tds[4]).text().trim().toUpperCase();
		currentPlayerData.pos = posMap[rawPos] || rawPos.toLowerCase();
		$('#pp-edit-player-position').val(currentPlayerData.pos);

		currentPlayerData.ht = $(tds[5]).text().trim();
		$('#pp-edit-player-height').val(currentPlayerData.ht);
		currentPlayerData.wt = $(tds[6]).text().trim();
		$('#pp-edit-player-weight').val(currentPlayerData.wt);

		// Normalize shoots (R/L)
		const shootsMap = { 'R': 'right', 'L': 'left' };
		const rawShoots = $(tds[7]).text().trim().toUpperCase();
		currentPlayerData.shoots = shootsMap[rawShoots] || rawShoots.toLowerCase();
		$('#pp-edit-player-shoots').val(currentPlayerData.shoots);

		currentPlayerData.hometown = $(tds[8]).text().trim();
		$('#pp-edit-player-hometown').val(currentPlayerData.hometown);
		currentPlayerData.headshot_link = $(tds[9]).text().trim();
		$('#pp-edit-player-headshot-url').val(currentPlayerData.headshot_link);
		currentPlayerData.last_team = $(tds[10]).text().trim();
		$('#pp-edit-player-last-team').val(currentPlayerData.last_team);

		// Normalize year if possible
		const yearMap = {
			'FR': 'freshman', 'FRESHMAN': 'freshman',
			'SO': 'sophomore', 'SOPHOMORE': 'sophomore',
			'JR': 'junior', 'JUNIOR': 'junior',
			'SR': 'senior', 'SENIOR': 'senior',
			'GR': 'graduate', 'GRADUATE': 'graduate'
		};
		const rawYear = $(tds[11]).text().trim().toUpperCase();
		currentPlayerData.year = yearMap[rawYear] || null;
		$('#pp-edit-player-year').val(currentPlayerData.year);

		currentPlayerData.major = $(tds[12]).text().trim();
		$('#pp-edit-player-major').val(currentPlayerData.major);
	}

	//############################################################//
	//                                                            //
	//               Helper Functions				              //
	//                                                            //
	//############################################################//
	function enableClickOutsideToClose($modal, closeCallback) {
		let mouseDownOutside = false;

		function onMouseDown(e) {
			if (e.target === $modal[0]) {
				mouseDownOutside = true;
			} else {
				mouseDownOutside = false;
			}
		}

		function onMouseUp(e) {
			if (e.target === $modal[0] && mouseDownOutside) {
				closeCallback();
			}
		}

		$modal.on('mousedown.modalClose', onMouseDown);
		$modal.on('mouseup.modalClose', onMouseUp);

		// Optional: return a cleanup function
		return function disableClickOutsideToClose() {
			$modal.off('mousedown.modalClose', onMouseDown);
			$modal.off('mouseup.modalClose', onMouseUp);
		};
	}
})(jQuery);

