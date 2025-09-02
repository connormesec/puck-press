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
		$("#pp-advancedBtn").click(function (e) {
			e.stopPropagation();
			$("#pp-advancedDropdown").css('display', 'block');
		});

		// Close dropdown when clicking elsewhere
		$(document).click(function () {
			$("#pp-advancedDropdown").css('display', 'none');
		});

		// Prevent dropdown from closing when clicking inside it
		$("#pp-advancedDropdown").click(function (e) {
			e.stopPropagation();
		});

		// Add functionality for dropdown items
		$(".pp-dropdown-item").click(function () {
			const action = $(this).text();
			console.log("Selected action:", action);

			// Close dropdown after selection
			$("#pp-advancedDropdown").css('display', 'none');

			// Here you can add specific functionality for each action
			if (action === "Reset Everything") {
				if (confirm("Are you sure you want to reset everything? This action cannot be undone.")) {
					console.log("Resetting everything...");
					// Add reset functionality here
				}
			}
		});
	});

	//############################################################//
	//                                                            //
	//               Helper Functions				              //
	//                                                            //
	//############################################################//

	$(window).on('load', function () {
		//remove spinner
		$('#pp-schedule-preview-wrapper').removeClass('loading');
		$('#pp-roster-preview-wrapper').removeClass('loading');
		$('#pp-game-slider-preview-wrapper').removeClass('loading');
	});
})(jQuery);

// shortcode js
document.addEventListener('DOMContentLoaded', function () {
	const groups = document.querySelectorAll('.pp-shortcode-input-group');

	groups.forEach(group => {
		const copyBtn = group.querySelector('.pp-shortcode-copy-btn');
		const urlInput = group.querySelector('.pp-shortcode-input');
		const tooltip = group.querySelector('.pp-shortcode-tooltip');

		copyBtn.addEventListener('click', function () {
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
	const date = new Date(dateString);
	return date.toLocaleDateString('en-US', {
		year: 'numeric',
		month: 'short',
		day: '2-digit'
	});
}

