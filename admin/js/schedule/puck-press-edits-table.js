(function ($) {
	jQuery(document).ready(function ($) {
		const dimEditListStyles = () => $('#pp-card-game-schedule-preview, #pp-card-schedule-game-list , #pp-card-game-schedule-edits, .pp-modal, .pp-card').css({
			'opacity': '0.5',
			'pointer-events': 'none'
		});
		const restoreEditListStyles = () => {
			$('#pp-card-game-schedule-preview, #pp-card-schedule-game-list , #pp-card-game-schedule-edits, .pp-modal, .pp-card').css({
				'opacity': '1',
				'pointer-events': 'auto'
			});
		}

		// Restore styles helper for use with refreshGamesTable callbacks
		function afterRefresh() {
			restoreEditListStyles();
			countGameRows();
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

		// Convert a formatted time string like "7:30 PM" to "HH:MM" for <input type="time">
		function formatTimeForInput(timeStr) {
			if (!timeStr) return '';
			const m = String(timeStr).match(/^(\d{1,2}):(\d{2})(?::(\d{2}))?\s*(AM|PM)?$/i);
			if (!m) return '';
			let h = parseInt(m[1], 10);
			const min = m[2];
			const period = (m[4] || '').toUpperCase();
			if (period === 'PM' && h !== 12) h += 12;
			if (period === 'AM' && h === 12) h = 0;
			return String(h).padStart(2, '0') + ':' + min;
		}

		// Map a formatted status string ("Final", "Final OT", etc.) to a select option value
		function mapStatusToInput(status) {
			if (status === null || status === undefined || status === '') return '';
			var map = {
				'Final':    'final',
				'Final OT': 'final-ot',
				'Final SO': 'final-so',
			};
			return map[String(status)] || '';
		}

		/**
		 * Pre-fill all edit form fields from a pp_game_schedule_for_display row.
		 * This is the current effective state of the game (raw + all edits applied).
		 */
		function prefillEditForm(game) {
			if (!game) return;

			// game_timestamp is a MySQL DATETIME string: "2024-01-15 19:30:00"
			// Extract just the date part for the date input.
			if (game.game_timestamp) {
				var datePart = String(game.game_timestamp).split(' ')[0]; // "YYYY-MM-DD"
				$('#pp-edit-game-date').val(datePart);
			}

			// game_time is stored as "7:30 PM" — convert to HH:MM for <input type="time">
			$('#pp-edit-game-time').val(formatTimeForInput(game.game_time || ''));

			// home_or_away: "home" / "away" — direct match with select options
			$('#pp-edit-home-or-away').val(game.home_or_away || '');

			// game_status: "Final" / "Final OT" / null — map to select option values
			$('#pp-edit-game-status').val(mapStatusToInput(game.game_status));

			// Scores
			var ts = game.target_score;
			$('#pp-edit-target-score').val(ts !== null && ts !== undefined ? ts : '');
			var os = game.opponent_score;
			$('#pp-edit-opponent-score').val(os !== null && os !== undefined ? os : '');

			// Venue
			$('#pp-edit-venue').val(game.venue || '');

			// Promo fields
			$('#pp-promo-header').val(game.promo_header || '');
			$('#pp-promo-text').val(game.promo_text || '');
			$('#pp-promo-img-url').val(game.promo_img_url || '');
			$('#pp-promo-ticket-link').val(game.promo_ticket_link || '');
		}

		/**
		 * Open the edit modal for a game and asynchronously pre-fill it with current values.
		 * The modal is shown FIRST — nothing gates this operation.
		 */
		function openEditModalForGame(gameId) {
			currentEditingGameId = gameId;

			// Show the modal immediately — this must always run regardless of what follows.
			$editGameModal.css('display', 'flex');
			$('.pp-modal-subtitle').text('Loading...');

			// Reset form after the modal is visible, with null safety.
			if ($editGameForm.length) {
				$editGameForm[0].reset();
			}

			// Pre-fill asynchronously from the server's authoritative for_display row.
			$.ajax({
				url: ajaxurl,
				method: 'POST',
				data: {
					action: 'pp_get_game_data',
					game_id: gameId,
				},
				success: function (response) {
					if (response.success && response.data && response.data.game) {
						prefillEditForm(response.data.game);
					}
					$('.pp-modal-subtitle').text('Game: ' + gameId);
				},
				error: function () {
					$('.pp-modal-subtitle').text('Game: ' + gameId);
				}
			});
		}

		// Open modal from the Games Table
		$(document).on('click', '#pp-edit-game-button', function () {
			openEditModalForGame($(this).data('game-id'));
		});

		// Open modal from the Edits Table
		$(document).on('click', '#pp-edit-edit-button', function () {
			openEditModalForGame($(this).data('game-id'));
		});

		// Close modal
		function closeEditGameModal() {
			$editGameModal.css('display', 'none');
			if ($editGameForm.length) {
				$editGameForm[0].reset();
			}
		}

		$closeEditGameModalBtn.on('click', closeEditGameModal);
		$cancelEditGameBtn.on('click', closeEditGameModal);

		$editGameModal.on('click', function (e) {
			if (e.target === this) {
				closeEditGameModal();
			}
		});

		// Form submission
		$confirmBtn_editGameModal.on('click', function () {
			if ($editGameForm.length && !$editGameForm[0].checkValidity()) {
				$editGameForm[0].reportValidity();
				return;
			}

			dimEditListStyles();

			// Collect only non-empty fields so we don't overwrite values with blanks
			var fields = { external_id: currentEditingGameId };

			var gameDate = $('#pp-edit-game-date').val();
			if (gameDate) fields.game_date = gameDate;

			var gameTime = $('#pp-edit-game-time').val();
			if (gameTime) fields.game_time = gameTime;

			var homeOrAway = $('#pp-edit-home-or-away').val();
			if (homeOrAway) fields.home_or_away = homeOrAway;

			var gameStatus = $('#pp-edit-game-status').val();
			if (gameStatus) fields.game_status = gameStatus;

			var targetScore = $('#pp-edit-target-score').val();
			if (targetScore !== '') fields.target_score = targetScore;

			var opponentScore = $('#pp-edit-opponent-score').val();
			if (opponentScore !== '') fields.opponent_score = opponentScore;

			var venue = $('#pp-edit-venue').val();
			if (venue) fields.venue = venue;

			var promoHeader = $('#pp-promo-header').val();
			if (promoHeader) fields.promo_header = promoHeader;

			var promoText = $('#pp-promo-text').val();
			if (promoText) fields.promo_text = promoText;

			var promoImgUrl = $('#pp-promo-img-url').val();
			if (promoImgUrl) fields.promo_img_url = promoImgUrl;

			var promoTicketLink = $('#pp-promo-ticket-link').val();
			if (promoTicketLink) fields.promo_ticket_link = promoTicketLink;

			var edit_data = {
				edit_action: 'update',
				fields: fields,
			};

			var formData = new FormData();
			formData.append('action', 'pp_update_game_promos');
			formData.append('edit_data', JSON.stringify(edit_data));

			closeEditGameModal();

			$.ajax({
				url: ajaxurl,
				method: 'POST',
				data: formData,
				processData: false,
				contentType: false,
				success: function (response) {
					if (response.success) {
						$.ajax({
							url: ajaxurl,
							method: 'POST',
							data: { action: 'ajax_refresh_edits_table_card' },
							success: function (response) {
								if (response.success) {
									$('#pp-schedule-edits-table').html(response.data);
									refreshGamesTable(afterRefresh, afterRefresh);
								} else {
									console.error(response.data ? response.data.message : 'Unknown error');
									restoreEditListStyles();
								}
							},
							error: function (err) {
								console.error('AJAX error:', err);
								restoreEditListStyles();
							}
						});
					} else {
						console.error('Error:', response);
						alert('Failed to save edit.');
						restoreEditListStyles();
					}
				},
				error: function (err) {
					console.error('Error:', err);
					alert('Failed to save edit.');
					restoreEditListStyles();
				}
			});
		});


		//############################################################//
		//                                                            //
		//               Delete Game Button Functionality             //
		//                                                            //
		//############################################################//

		$(document).on('click', '#pp-delete-game-button', function () {
			dimEditListStyles();

			var $row = $(this).closest('tr');
			var gameId = $(this).data('game-id');
			var sourceType = $row.data('source-type');

			if (sourceType === 'manual') {
				$.ajax({
					url: ajaxurl,
					type: 'POST',
					data: {
						action: 'pp_delete_manual_game',
						game_id: gameId,
					},
					success: function (response) {
						if (response.success) {
							$('#pp-games-table').replaceWith(response.data.games_table_html);
							$('#pp-schedule-edits-table').replaceWith(response.data.edits_table_html);
							refreshGamesTable(afterRefresh, afterRefresh);
						} else {
							console.error('Error deleting manual game:', response.data);
							alert('There was an error deleting the game.');
							restoreEditListStyles();
						}
					},
					error: function () {
						alert('There was an error with the AJAX request to delete the game.');
						restoreEditListStyles();
					}
				});
			} else {
				var edit_data = {
					edit_action: 'delete',
					fields: { external_id: gameId }
				};
				var formData = new FormData();
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
							$.ajax({
								url: ajaxurl,
								method: 'POST',
								data: { action: 'ajax_refresh_edits_table_card' },
								success: function (response) {
									if (response.success) {
										$('#pp-schedule-edits-table').html(response.data);
										refreshGamesTable(afterRefresh, afterRefresh);
									} else {
										console.error(response.data ? response.data.message : 'Unknown error');
										restoreEditListStyles();
									}
								},
								error: function (err) {
									console.error('AJAX error:', err);
									restoreEditListStyles();
								}
							});
						} else {
							console.error('Error deleting edit:', response.data);
							alert('There was an error deleting the edit.');
							restoreEditListStyles();
						}
					},
					error: function () {
						alert('There was an error with the AJAX request to delete the edit.');
						restoreEditListStyles();
					}
				});
			}
		});


		//############################################################//
		//                                                            //
		//               Delete Edit From Table Button                //
		//                                                            //
		//############################################################//

		$(document).on('click', '#pp-delete-edit-button', function () {
			var confirmed = confirm('Are you sure you want to delete this item?');
			if (!confirmed) return;

			dimEditListStyles();

			var id = $(this).data('edit-id');
			var $nearestRow = $(this).closest('tr');

			$.ajax({
				url: ajaxurl,
				type: 'POST',
				data: {
					action: 'ajax_delete_game_edit',
					id: id,
				},
				success: function (response) {
					if (response.success) {
						$nearestRow.remove();
						refreshGamesTable(afterRefresh, afterRefresh);
					} else {
						console.error('Error deleting edit:', response.data);
						alert('There was an error deleting the edit.');
						restoreEditListStyles();
					}
				},
				error: function () {
					alert('There was an error with the AJAX request to delete the edit.');
					restoreEditListStyles();
				}
			});
		});
	});

})(jQuery);
