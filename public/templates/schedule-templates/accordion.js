(function ($) {
    function initializeAccordion() {
		$(".accordion_schedule_container").removeClass("css-transitions-only-after-page-load");
		
    // Remove existing event listeners to prevent duplicates
		$(".accordion").off("click").on("click", function () {
			$(this).toggleClass("active");
			const panel = this.nextElementSibling;
			$(panel).toggleClass("active");

			if (panel.style.maxHeight) {
				panel.style.maxHeight = null;
			} else {
				panel.style.maxHeight = panel.scrollHeight + "px";
			}
		});

		// Set max height for active panels
		$(".accordion_panel.active").each(function () {
			this.style.maxHeight = this.scrollHeight + "px";
		});
	}

	// Initial run
	$(document).ready(function () {
		initializeAccordion();
	});

	// Expose the init function globally so you can call it after AJAX
	if (typeof gameScheduleInitializers !== 'undefined') {
            gameScheduleInitializers.push(initializeAccordion);
        }
})(jQuery);