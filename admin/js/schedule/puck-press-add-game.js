(function ($) {
	jQuery(document).ready(function ($) {
		const $modal = $('#pp-add-game-modal');
		const $addGameBtn = $('#pp-add-game-button');
		const $closeBtn = $modal.find('.pp-modal-close');
		const $cancelBtn = $('#pp-cancel-add-game');
		const $confirmBtn = $('#pp-confirm-add-game');
		const $addGameForm = $('#pp-add-game-form');

		const dimStyles = () => {
			$('#pp-card-game-schedule-preview, #pp-card-schedule-game-list, #pp-card-game-schedule-edits, .pp-modal, #pp-card-game-slider-preview').css({
				'opacity': '0.5',
				'pointer-events': 'none'
			});
		};

		const restoreStyles = () => {
			$('#pp-card-game-schedule-preview, #pp-card-schedule-game-list, #pp-card-game-schedule-edits, .pp-modal, #pp-card-game-slider-preview').css({
				'opacity': '1',
				'pointer-events': 'auto'
			});
		};

		// Open modal
		$(document).on('click', '#pp-add-game-button', function () {
			$modal.css('display', 'flex');
			$('.pp-select2-opponent', $modal).select2({ dropdownParent: $modal });
			$('.pp-select2-target', $modal).select2({ dropdownParent: $modal });
		});

		// Close modal
		function closeModal() {
			$modal.css('display', 'none');
			$addGameForm[0].reset();
		}

		$closeBtn.on('click', closeModal);
		$cancelBtn.on('click', closeModal);

		// Close when clicking outside
		$modal.on('mousedown', function (e) {
			if (e.target === $modal[0]) {
				closeModal();
			}
		});

		// Submit
		$confirmBtn.on('click', function () {
			if (!$addGameForm[0].checkValidity()) {
				$addGameForm[0].reportValidity();
				return;
			}

			const extractTeam = (selector) => {
				const $opt = $(selector, $modal).find('option:selected');
				return {
					id: $opt.data('id'),
					name: $opt.data('name'),
					nickname: $opt.data('nickname'),
					logo: $opt.data('logo')
				};
			};

			const target = extractTeam('.pp-select2-target');
			const opponent = extractTeam('.pp-select2-opponent');

			const formData = new FormData();
			formData.append('action', 'pp_add_manual_game');
			formData.append('schedule_id', (window.ppScheduleAdmin && window.ppScheduleAdmin.activeScheduleId) ? window.ppScheduleAdmin.activeScheduleId : 1);
			formData.append('game_date', $('#pp-game-date', $modal).val());
			formData.append('game_time', $('#pp-game-time', $modal).val());
			formData.append('target_team_id', target.id || '0');
			formData.append('target_team_name', target.name || '');
			formData.append('target_team_nickname', target.nickname || '');
			formData.append('target_team_logo', target.logo || '');
			formData.append('target_score', $('#pp-game-target-score', $modal).val());
			formData.append('opponent_team_id', opponent.id || '0');
			formData.append('opponent_team_name', opponent.name || '');
			formData.append('opponent_team_nickname', opponent.nickname || '');
			formData.append('opponent_team_logo', opponent.logo || '');
			formData.append('opponent_score', $('#pp-game-opponent-score', $modal).val());
			formData.append('home_or_away', $('#pp-home-or-away', $modal).val());
			formData.append('game_status', $('#pp-game-status', $modal).val());
			formData.append('venue', $('#pp-game-venue', $modal).val());

			dimStyles();

			$.ajax({
				url: ajaxurl,
				method: 'POST',
				data: formData,
				processData: false,
				contentType: false,
				success: function (response) {
					if (response.success) {
						$('#pp-games-table').replaceWith(response.data.games_table_html);
						$('#pp-schedule-edits-table').replaceWith(response.data.edits_table_html);
						refreshGamesTable().then(() => {
							restoreStyles();
							countGameRows();
						});
						closeModal();
					} else {
						alert('Failed to add game: ' + (response.data?.message || 'Unknown error'));
						restoreStyles();
					}
				},
				error: function () {
					alert('An error occurred while adding the game.');
					restoreStyles();
				}
			});
		});
	});
})(jQuery);
