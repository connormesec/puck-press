(function ($) {
	jQuery(document).ready(function ($) {

		//############################################################//
		//                                                            //
		//                Other button event listeners                //
		//                                                            //
		//############################################################//

		$(".pp-collapse-button").click(function () {
			let target = $(this).attr("data-target");
			let content = $("#" + target);
			let icon = $(this).find(".pp-collapse-icon");

			// Toggle content visibility
			content.slideToggle(200)

			if (icon.hasClass('expanded')) {
				icon.css("transform", "rotate(180deg)"); // Arrow points up
				icon.removeClass('expanded').addClass('hidden');
			} else if (icon.hasClass('hidden')) {
				icon.css("transform", "rotate(0deg)"); // Arrow points down
				icon.removeClass('hidden').addClass('expanded');
			} else {
				icon.css("transform", "rotate(180deg)"); // Arrow points up
				icon.addClass('hidden');
			}


		});

		//############################################################//
		//                                                            //
		//                advanced button functionality               //
		//                                                            //
		//############################################################//

		// Toggle dropdown when clicking the button
		$("#pp-advancedBtn").click((e) => {
			e.stopPropagation();
			$("#pp-advancedDropdown").css('display', 'block');
		});

		// Close dropdown when clicking elsewhere
		$(document).click(() => {
			$("#pp-advancedDropdown").css('display', 'none');
		});

		// Prevent dropdown from closing when clicking inside it
		$("#pp-advancedDropdown").click((e) => {
			e.stopPropagation();
		});

		// Close dropdown after clicking any item
		$(".pp-dropdown-item").click(function () {
			$("#pp-advancedDropdown").css('display', 'none');
		});

		// Wipe & Recreate Database
		$("#pp-wipe-and-recreate-db-btn").click(function () {
			if (!confirm("This will drop ALL Puck Press tables and recreate empty new-architecture tables. All data will be lost. Continue?")) {
				return;
			}
			$.post(ajaxurl, { action: 'pp_wipe_and_recreate_db' }, function (response) {
				if (response.success) {
					alert("Done!\n\n" + (response.data.log || []).join("\n"));
					location.reload();
				} else {
					alert("Error: " + (response.data && response.data.message ? response.data.message : "Unknown error"));
				}
			});
		});
	});

	//############################################################//
	//                                                            //
	//               Helper Functions				              //
	//                                                            //
	//############################################################//

	$(window).on('load', () => {
		//remove spinner
		$('#pp-schedule-preview-wrapper').removeClass('loading');
		$('#pp-roster-preview-wrapper').removeClass('loading');
		$('#pp-game-slider-preview-wrapper').removeClass('loading');
		$('#pp-record-preview-wrapper').removeClass('loading');
		$('#pp-stats-preview-wrapper').removeClass('loading');
		$('#pp-stat-leaders-preview-wrapper').removeClass('loading');
	});
})(jQuery);

// shortcode js
document.addEventListener('DOMContentLoaded', () => {
	const groups = document.querySelectorAll('.pp-shortcode-input-group');

	groups.forEach(group => {
		const copyBtn = group.querySelector('.pp-shortcode-copy-btn');
		const urlInput = group.querySelector('.pp-shortcode-input');
		const tooltip = group.querySelector('.pp-shortcode-tooltip');

		copyBtn.addEventListener('click', () => {
			// Select the text field
			urlInput.select();
			urlInput.setSelectionRange(0, 99999); // For mobile devices

			// Copy the text inside the text field
			navigator.clipboard.writeText(urlInput.value).then(() => {
				// Show tooltip feedback
				tooltip.classList.add('show');

				// Hide tooltip after 2 seconds
				setTimeout(() => {
					tooltip.classList.remove('show');
				}, 2000);
			});
		});
	});
});

function formatDate(dateString) {
    const date = new Date(dateString + 'T00:00:00');
    return date.toLocaleDateString('en-US', {
        year: 'numeric',
        month: 'short',
        day: '2-digit'
    });
}

