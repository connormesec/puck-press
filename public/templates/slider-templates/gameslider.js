(function ($) {
    $(document).ready(function () {
        initializeSlider();
        // Expose the init function globally so you can call it after AJAX
        //window.initializePillAccordion = initializePillAccordion;
        if (typeof gameScheduleInitializers !== 'undefined') {
            gameScheduleInitializers.push(initializeSlider);
        }
    });
})(jQuery);

function initializeSlider() {
    //document.addEventListener('DOMContentLoaded', function () {
        const gliderEl = document.querySelector('.glider');
        if (!gliderEl) return;

        const glider = new Glider(gliderEl, {
            slidesToShow: 1,
            draggable: false,
            scrollLock: true,
            arrows: {
                prev: '.glider-prev',
                next: '.glider-next',
            },
            easing: function (x, t, b, c, d) {
                return c * (t /= d) * t + b;
            },
            responsive: [
                {
                    breakpoint: 800,
                    settings: {
                        slidesToShow: 3,
                        slidesToScroll: 1,
                        itemWidth: 150,
                        scrollLock: true,
                        duration: 0.25
                    }
                },
                {
                    breakpoint: 650,
                    settings: {
                        slidesToShow: 2,
                        slidesToScroll: 1,
                        itemWidth: 150,
                        scrollLock: true,
                        duration: 0.25
                    }
                }
            ]
        });

        const targetIndex = window.gameSliderScrollIndex || 0;
        glider.setOption({ duration: 0 });
        glider.scrollItem(targetIndex);
        glider.setOption({ duration: 0.25 });
    //})
};