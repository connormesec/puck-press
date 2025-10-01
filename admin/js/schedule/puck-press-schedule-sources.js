(function ($) {
	jQuery(document).ready(function ($) {
		const dimGameListStyles = () => $('#pp-card-game-schedule-preview, #pp-card-schedule-game-list , #pp-card-game-schedule-edits, .pp-modal, #pp-card-game-slider-preview').css({
			'opacity': '0.5',
			'pointer-events': 'none' // disables interaction
		});
		const restoreGameListStyles = () => {
			$('#pp-card-game-schedule-preview, #pp-card-schedule-game-list, #pp-card-game-schedule-edits, .pp-modal, #pp-card-game-slider-preview').css({
				'opacity': '1',
				'pointer-events': 'auto' // re-enables interaction
			});
		}
		const countGameRows = () => {
			const rowCount = $('#pp-games-table tbody tr').length;
			$('#pp-card-subtitle-schedule-game-list').text(`${rowCount} games scheduled`);
		}

		countGameRows();
		//############################################################//
		//                                                            //
		//               Add Source Modal functionality               //
		//                                                            //
		//############################################################//

		const $modal = $('#pp-add-source-modal');
		const $addSourceBtn = $('#pp-add-source-button');
		const $closeBtn = $('#pp-modal-close');
		const $cancelBtn = $('#pp-cancel-add-source');
		const $confirmBtn = $('#pp-confirm-add-source');
		const $addSourceForm = $('#pp-add-source-form');

		// Open modal
		$addSourceBtn.on('click', function () {
			$modal.css('display', 'flex');
			//Reset source URL or file upload input
			toggleInputs();
			// Initialize the dropdown for selecting the opponent
			$('.pp-select2-opponent').select2();
			$('.pp-select2-target').select2();

		});

		// Close modal function
		function closeModal() {
			$modal.css('display', 'none');
			$addSourceForm[0].reset();
		}

		$closeBtn.on('click', closeModal);
		$cancelBtn.on('click', closeModal);

		// Close modal when clicking outside
		enableClickOutsideToClose($modal, closeModal);

		$confirmBtn.on('click', function () {
			const $form = $('#pp-add-source-form');
			if (!$form[0].checkValidity()) {
				$form[0].reportValidity();
				return;
			}
			dimGameListStyles();
			PPDataSourceUtils.handleFormSubmit({
				$form: $('#pp-add-source-form'),
				action: 'pp_add_data_source',

				fieldExtractors: () => {
					const type = $('#pp-source-type').val();
					const data = {
						name: $('#pp-source-name').val(),
						type,
						active: $('#pp-new-source-active').is(':checked') ? 1 : 0
					};

					if (type === 'achaGameScheduleUrl') {
						data.url = $('#pp-source-url').val();
						data.season = $('#pp-source-season-year').val();
					} else if (type === 'usphlGameScheduleUrl') {
						data.url = $('#pp-source-url-usphl').val();
					} else if (type === 'csv') {
						const file = $('#pp-schedule-fileInput')[0].files[0];
						if (!file) throw new Error('Please select a CSV file.');
						data.csv = file;
					} else if (type === 'customGame') {
						const extract = sel => {
							const $opt = $(sel).find('option:selected');
							return {
								id: $opt.data('id'),
								name: $opt.data('name'),
								nickname: $opt.data('nickname'),
								logo: $opt.data('logo')
							};
						};

						data.other_data = JSON.stringify({
							target: extract('#pp-game-target'),
							opponent: extract('#pp-game-opponent'),
							gameDate: formatDate($('#pp-game-date').val()),
							gameTime: $('#pp-game-time').val(),
							home_or_away: $('#pp-home-or-away').val(),
							target_score: $('#pp-game-target-score').val(),
							opponent_score: $('#pp-game-opponent-score').val(),
							game_status: $('#pp-game-status').val(),
							venue: $('#pp-game-venue').val()
						});
					}

					return data;
				},

				onSuccess: function (response) {
					const type = $('#pp-source-type').val();
					const name = $('#pp-source-name').val();
					const url = $('#pp-source-url').val();
					const csvFile = $('#pp-schedule-fileInput')[0].files[0];
					const active = $('#pp-new-source-active').is(':checked');

					let displayValue = url;
					if (type === 'csv'){
						displayValue = csvFile.name;
					} else if (type === 'usphlGameScheduleUrl') {
						displayValue = $('#pp-source-url-usphl').val();
					}
					
					createNewSourceRow(name, type, displayValue, active, response.data.id);

					refreshGamesTable().then(() => {
						restoreGameListStyles();
						countGameRows();
					});
					console.log('Success:', response);
					closeModal();
				},

				onError: function (error) {
					console.error('Error:', error);
					restoreGameListStyles();
				}
			});

			function createNewSourceRow(name, type, urlDisplay = '', active, id) {
				const $tbody = $('#pp-sources-table tbody');
				const currentDate = new Date().toLocaleString('en-US', {
					year: 'numeric',
					month: 'short',
					day: '2-digit',
					hour: '2-digit',
					minute: '2-digit',
					hour12: true
				});

				const newRow = `
					<tr data-id="${id}">
						<td class="pp-td" id="pp-sched-source-name">${name}</td>
						<td class="pp-td"><span class="pp-tag pp-tag-${type}">${type}</span></td>
						<td class="pp-td">${urlDisplay}</td>
						<td class="pp-td">${currentDate}</td>
						<td class="pp-td">
							<label class="pp-data-source-toggle-switch">
								<input type="checkbox" ${active ? 'checked' : ''} data-id="${id}">
								<span class="pp-slider"></span>
							</label>
							<span style="margin-left: 10px;">${active ? 'Active' : 'Inactive'}</span>
						</td>
						<td class="pp-td">
							<div class="pp-flex-small-gap">
								<button class="pp-button-icon" id="pp-delete-source" data-id="${id}">🗑️</button>
							</div>
						</td>
					</tr>
				`;
				$('#kill-me-please').remove();
				$tbody.append(newRow);
			}
		});


		//############################################################//
		//                                                            //
		//                Active/Inactive Toggle               	      //
		//                                                            //
		//############################################################//

		$(document).on('change', '.pp-data-source-toggle-switch input', function () {
			const status = $(this).prop('checked') ? 'active' : 'inactive';
			const sourceId = $(this).data('id');
			dimGameListStyles();
			$('.pp-data-source-toggle-switch').css({
				'opacity': '0.5',
				'pointer-events': 'none' // re-enables interaction
			});
			const restoreToggleStyles = () => {
				$('.pp-data-source-toggle-switch').css({
					'opacity': '1',
					'pointer-events': 'auto' // re-enables interaction
				});
			}
			$.ajax({
				url: ajaxurl,
				method: 'POST',
				data: {
					action: 'pp_update_schedule_source_status',
					source_id: sourceId,
					status: status,
				},
				success: function (response) {
					refreshGamesTable().then(() => {
						restoreGameListStyles();
						restoreToggleStyles();
						countGameRows();
					});
					console.log('Success:', response);
				},
				error: function (error) {
					console.error('Error:', err);
					alert('Failed to update status.');
					restoreGameListStyles();
					restoreToggleStyles();
				}
			});

			//update text
			const $statusText = $(this).parent().parent().children('span').last();
			if ($statusText.length) {
				$statusText.text($(this).is(':checked') ? 'Active' : 'Inactive');
			}

		});

		//############################################################//
		//                                                            //
		//                Sources Delete Toggle               	      //
		//                                                            //
		//############################################################//

		$(document).on('click', '#pp-delete-source', function () {
			const confirmed = confirm('Are you sure you want to delete this item?');
			if (!confirmed) return;

			// Get the data-id from the clicked delete button
			const dataSourceId = $(this).data('id');
			$(this).closest('tr').css({
				'opacity': '0.5',
				'pointer-events': 'none' // disables interaction
			});
			dimGameListStyles();

			// Send an AJAX request to delete the data source
			$.ajax({
				url: ajaxurl,
				type: 'POST',
				data: {
					action: 'pp_delete_schedule_data_source', // The action hook
					source_id: dataSourceId, // The ID of the data source to delete
				},
				success: function (response) {
					if (response.success) {
						// Remove the row from the HTML table
						$(`tr[data-id="${dataSourceId}"]`).remove();
						console.log('Data source deleted successfully.');
						refreshGamesTable(
							function (refreshResponse) {
								if (refreshResponse.success) {
									// Replace the table with the refreshed game table from the response
									$('#pp-games-table').html(refreshResponse.data.refreshed_game_table_ui);
									console.log('All sources refreshed successfully.');
								} else {
									alert('Failed to refresh all sources.');
								}
							},
							function () {
								alert('An error occurred while refreshing all sources.');
							}).then(() => {
								restoreGameListStyles();
								countGameRows();
							});
					} else {
						alert('There was an error deleting the data source.');
						restoreGameListStyles();
					}
				},
				error: function () {
					alert('There was an error with the AJAX request to delete the source.');
					restoreGameListStyles();
				}
			});
		});

		//############################################################//
		//                                                            //
		//               Modal - Add Source dropdown               	  //
		//                                                            //
		//############################################################//

		// Initially hide the file input and show the URL input based on the default selection
		toggleInputs();

		// When the selection changes, toggle the visibility of the inputs
		$(document).on('change', '#pp-source-type', function () {
			toggleInputs();
		});

		function toggleInputs() {
			const selectedType = $('#pp-source-type').val();

			// Config object mapping type to visibility and required states
			const config = {
				achaGameScheduleUrl: {
					required: ['#pp-source-url', '#pp-source-season-year']
				},
				usphlGameScheduleUrl: {
					required: ['#pp-source-url-usphl']
				},
				csv: {
					required: ['#pp-schedule-fileInput']
				},
				customGame: {
					required: ['#pp-game-date']
				}
			};

			// Show and add required to specified fields for the selected type
			const selectedConfig = config[selectedType] || { required: [] };

			// 1. Loop through all configs to clear required from everything
			for (const type in config) {
				const { required } = config[type];
				required.forEach(selector => {
					$(selector).prop('required', false);
				});
			}

			// 2. Re-apply `required` to the selected type's required fields
			selectedConfig.required.forEach(selector => {
				$(selector).prop('required', true);
			});

			// 3. Show/hide dynamic field groups
			for (const type in config) {
				if (type !== selectedType) {
					$(`.pp-dynamic-source-group-${type}`).hide();
				} else {
					$(`.pp-dynamic-source-group-${type}`).show();
				}
			}

			// disable inputs in hidden groups (so they don’t submit)
			for (const type in config) {
				const isVisible = type === selectedType;
				const group = $(`.pp-dynamic-source-group-${type}`);

				group.toggle(isVisible); // show/hide

				// enable/disable all inputs inside the group
				group.find('input, select, textarea').prop('disabled', !isVisible);
			}
		}

		//############################################################//
		//                                                            //
		//               Refresh All Sources functionality            //
		//                                                            //
		//############################################################//

		$('#pp-refresh-button').on('click', function () {
			// Store a reference to the button
			const $button = $(this);

			// Save the original button text
			const originalHtml = $button.html();

			// Change the button content to show WordPress native spinner
			$button.html('<span class="spinner is-active" style="float:left; margin-right:5px;"></span> Loading...');
			// Disable the button to prevent multiple clicks
			$button.prop('disabled', true);
			dimGameListStyles();
			// Perform Ajax request
			refreshGamesTable(
				function (response) {
					$button.html(originalHtml);
					$button.prop('disabled', false);
					if (response.success) {
						// Optionally update UI based on success (e.g., refresh table)
						// Replace the table with the refreshed game table from the response

					} else {
						if(response.data.errors) {
							console.log(response.data.errors); // Log errors if any
						}
					}
				},
				function (error) {
					$button.html(originalHtml);
					$button.prop('disabled', false);

					console.error('An unexpected error occurred. Please try again.');
				}
			).then(() => {
				restoreGameListStyles();
				countGameRows();
			});
		});


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

		//############################################################//
		//                                                            //
		//					Copy to clipboard functionality 		  //
		//                                                            //
		//############################################################//
		const csvData = `target_team_name, target_team_nickname, target_team_logo, target_score, opponent_team_name, opponent_team_nickname, opponent_team_logo, opponent_score, game_time, game_timestamp, game_status, home_or_away, venue
Montana State University, Bobcats, https://assets.leaguestat.com/acha/logos/51.png, , Boise State University, Broncos, https://assets.leaguestat.com/acha/logos/217.png, , 7:30 pm MST, "February 28, 2025 7:30 PM", , away, Idaho Ice World
Montana Tech, Orediggers, https://assets.leaguestat.com/acha/logos/883.png, 1, Williston State College, Tetons, https://assets.leaguestat.com/acha/logos/60.png,4, "Thu, Oct 10", "October 10, 2024 12:00 am", Final, home, Butte Community Ice Center`;

		$('#copyCsvToClipboard').on('click', function (e) {
			e.preventDefault(); // Prevent form submission

			const $btn = $(this);

			const tabDelimited = csvData
				.split('\n')
				.map(line => line.split(',').map(cell => cell.trim()).join('\t'))
				.join('\n');

			navigator.clipboard.writeText(tabDelimited).then(() => {
				const originalText = $btn.text();
				$btn.text('✅ Copied!');
				setTimeout(() => $btn.text(originalText), 2000);
			}).catch(err => {
				console.error('Clipboard copy failed:', err);
				alert('❌ Failed to copy data.');
			});
		});

	});
})(jQuery);