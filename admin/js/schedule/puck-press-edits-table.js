(function ($) {
	jQuery(document).ready(function ($) {
		const dimEditListStyles = () => $('#pp-card-game-schedule-preview, #pp-card-schedule-game-list , #pp-card-game-schedule-edits, .pp-modal, .pp-card').css({
			'opacity': '0.5',
			'pointer-events': 'none' // disables interaction
		});
		const restoreEditListStyles = () => {
			$('#pp-card-game-schedule-preview, #pp-card-schedule-game-list , #pp-card-game-schedule-edits, .pp-modal, .pp-card').css({
				'opacity': '1',
				'pointer-events': 'auto' // re-enables interaction
			});
		}


		//############################################################//
		//                                                            //
		//               Edit Game Modal functionality                //
		//                                                            //
		//############################################################//

		const $editGameModal = $('#pp-edit-game-modal');
		const $closeEditGameModalBtn = $('#pp-edit-game-modal-close');
		const $cancelEditGameBtn = $('#pp-cancel-edit-game');
		const $confirmBtn_editGameModal = $('#pp-confirm-edit-game');
		const $editGameForm = $('#pp-edit-game-form');
		let currentEditingGameId = null;

		// Open modal
		$(document).on('click', '#pp-edit-game-button', function () {
			const gameId = $(this).data('game-id');
			currentEditingGameId = gameId;

			console.log('Edit game button clicked for game ID:', gameId);
			$editGameModal.css('display', 'flex');
			$('.pp-modal-subtitle').text(`Add promotional information for the game: ${currentEditingGameId}`);
			// You can now also pre-fill form fields using gameId here
		});

		// Close modal function
		function closeEditGameModal() {
			$editGameModal.css('display', 'none');
			$editGameForm[0].reset();
		}

		$closeEditGameModalBtn.on('click', closeEditGameModal);
		$cancelEditGameBtn.on('click', closeEditGameModal);

		// Close modal when clicking outside
		$editGameModal.on('click', function (e) {
			if (e.target === this) {
				closeEditGameModal();
			}
		});

		// Form submission
		$confirmBtn_editGameModal.on('click', function () {
			// Check form validity
			if (!$editGameForm[0].checkValidity()) {
				$editGameForm[0].reportValidity();
				return;
			}

			dimEditListStyles();

			// Get form values
			const promo_header = $('#pp-promo-header').val();
			const promo_text = $('#pp-promo-text').val();
			const promo_img_url = $('#pp-promo-img-url').val();
			const promo_ticket_link = $('#pp-promo-ticket-link').val();
			const external_id = currentEditingGameId;
			const edit_action = 'update';
			const edit_data = {
				edit_action: edit_action,
				fields: {
					promo_header: promo_header,
					promo_text: promo_text,
					promo_img_url: promo_img_url,
					promo_ticket_link: promo_ticket_link,
					external_id: external_id,
				}
			}

			const formData = new FormData();
			formData.append('action', 'pp_update_game_promos');
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
						//createNewEditRow(name, type, type === 'csv' ? csvFile.name : url, active, response.data.id);
						// Optionally refresh the games table or perform other actions
						closeEditGameModal();
						$.ajax({
							url: ajaxurl,
							method: 'POST',
							data: {
								action: 'ajax_refresh_edits_table_card'
							},
							success: function (response) {
								if (response.success) {
									console.log(response)
									$('#pp-schedule-edits-table').html(response.data); // Replace with updated HTML
									refreshGamesTable().then(() => {
										restoreEditListStyles();
										countGameRows();
									});
								} else {
									console.error(response.data?.message || 'Unknown error');;
								}
							},
							error: function (err) {
								console.error('AJAX error:', err);
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

			closeEditGameModal();

		});

		//############################################################//
		//                                                            //
		//               Delete Game Button Funcitonality		      //
		//                                                            //
		//############################################################//

		$(document).on('click', '#pp-delete-game-button', function () {
			dimEditListStyles();
			
			const gameId = $(this).data('game-id');
			
			const edit_data = {
				edit_action: 'delete',
				fields: {
					external_id: gameId,
				}
			}
			const formData = new FormData();
			formData.append('action', 'pp_update_game_promos');
			formData.append('edit_data', JSON.stringify(edit_data));

			$.ajax({
				url: ajaxurl,
				type: 'POST',
				data: formData,
				processData: false,
				contentType: false,
				success: function (response) {
					if (response.success) {
						console.log('Game deleted successfully.');
						$.ajax({
							url: ajaxurl,
							method: 'POST',
							data: {
								action: 'ajax_refresh_edits_table_card'
							},
							success: function (response) {
								if (response.success) {
									console.log(response)
									$('#pp-schedule-edits-table').html(response.data); // Replace edit table with updated HTML
									refreshGamesTable().then(() => {
										restoreEditListStyles();
										countGameRows();
									});
								} else {
									console.error(response.data?.message || 'Unknown error');;
								}
							},
							error: function (err) {
								console.error('AJAX error:', err);
							},
							complete: function () {
								//restoreEditListStyles();
							}
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
		//               Delete Edit From Table Button                //
		//                                                            //
		//############################################################//

		$(document).on('click', '#pp-delete-edit-button', function () {
			const confirmed = confirm('Are you sure you want to delete this item?');
			if (!confirmed) return;

			dimEditListStyles();

			const id = $(this).data('edit-id');
			const $nearestRow = $(this).closest('tr');

			$.ajax({
				url: ajaxurl,
				type: 'POST',
				data: {
					action: 'ajax_delete_game_edit', // The action hook
					id: id, // The ID of the edit to delete
				},
				success: function (response) {
					if (response.success) {
						// Remove the row from the HTML table
						$nearestRow.remove();
						refreshGamesTable().then(() => {
							restoreEditListStyles();
							countGameRows();
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

})(jQuery);