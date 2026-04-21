(function ($) {
    $(document).ready(() => {
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
        if (!gliderEl || !gliderEl.offsetWidth) return;

        const glider = new Glider(gliderEl, {
            slidesToShow: 1,
            draggable: false,
            scrollLock: true,
            arrows: {
                prev: '.glider-prev',
                next: '.glider-next',
            },
            easing: (x, t, b, c, d) => {
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

    // Tap-to-open recap overlay (mobile). Guard prevents duplicate listeners on re-init.
    const container = document.querySelector('.gameslider_slider_container');
    if (container && !container.dataset.tapInit) {
        container.dataset.tapInit = '1';

        const closeAll = () =>
            container.querySelectorAll('.entry--recap-open')
                .forEach(c => c.classList.remove('entry--recap-open'));

        container.addEventListener('click', (e) => {
            const hint    = e.target.closest('.gs-recap-hint');
            const overlay = e.target.closest('.gs-recap-overlay');
            const link    = e.target.closest('.gs-recap-link');

            if (hint) {
                const entry  = hint.closest('.entry');
                const isOpen = entry.classList.contains('entry--recap-open');
                closeAll();
                if (!isOpen) entry.classList.add('entry--recap-open');
                e.stopPropagation();
            } else if (overlay && !link) {
                closeAll();
                e.stopPropagation();
            }
        });

        document.addEventListener('click', closeAll);
    }
    //})
};
