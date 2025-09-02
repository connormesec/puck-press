(function ($) {
    $(document).ready(function () {
        initializePillAccordion();
        // Expose the init function globally so you can call it after AJAX
	    //window.initializePillAccordion = initializePillAccordion;
        if (typeof gameScheduleInitializers !== 'undefined') {
            gameScheduleInitializers.push(initializePillAccordion);
        }
    });
})(jQuery);

function show_hide(show, hide, active_btn_id, unactive_btn_id) {
    var show = document.getElementById(show);
    var hide = document.getElementById(hide);
    var active_btn = document.getElementById(active_btn_id);
    var unactive_btn = document.getElementById(unactive_btn_id);
    if (show.style.display === "none") {
        show.style.display = "block";
        hide.style.display = "none";
        active_btn.style.opacity = "1";
        unactive_btn.style.opacity = "0.5";
    }
}

function initializePillAccordion() {

    const accordionBtns = document.querySelectorAll(".pill_accordion");

    accordionBtns.forEach((accordion) => {
        accordion.onclick = function () {
            this.classList.toggle("is-open");

            let content = this.nextElementSibling;

            if (content.style.maxHeight) {
                //this is if the accordion is open
                content.style.maxHeight = null;
            } else {
                //if the accordion is currently closed
                content.style.maxHeight = content.scrollHeight + "px";
            }
        };
    });
}