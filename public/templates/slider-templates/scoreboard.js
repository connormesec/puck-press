(function ($) {
    $(document).ready(() => {
        initScoreboardSlider();

        if (typeof gameScheduleInitializers !== 'undefined') {
            gameScheduleInitializers.push(initScoreboardSlider);
        }
    });
})(jQuery);

function initScoreboardSlider() {
    const el = document.querySelector('.pp-sb-glider');
    if (!el) return;

    // Reveal before init so Glider.js can read real dimensions.
    // Browser does not paint between synchronous JS statements, so no FOUC.
    el.closest('.pp-sb-container').classList.add('pp-sb-ready');

    const glider = new Glider(el, {
        slidesToShow: 1,
        slidesToScroll: 1,
        draggable: false,
        scrollLock: true,
        arrows: {
            prev: '.pp-sb-prev',
            next: '.pp-sb-next',
        },
        easing: (x, t, b, c, d) => c * (t /= d) * t + b,
        responsive: [
            {
                breakpoint: 480,
                settings: { slidesToShow: 2, slidesToScroll: 1, scrollLock: true, duration: 0.25 }
            },
            {
                breakpoint: 720,
                settings: { slidesToShow: 3, slidesToScroll: 1, scrollLock: true, duration: 0.25 }
            },
            {
                breakpoint: 960,
                settings: { slidesToShow: 4, slidesToScroll: 1, scrollLock: true, duration: 0.25 }
            },
            {
                breakpoint: 1200,
                settings: { slidesToShow: 5, slidesToScroll: 1, scrollLock: true, duration: 0.25 }
            },
        ],
    });

    // Scroll so the next upcoming game is positioned as the 3rd visible card.
    const targetIndex = window.ppScoreboardIndex || 0;
    glider.setOption({ duration: 0 });
    glider.scrollItem(targetIndex);
    glider.setOption({ duration: 0.25 });

    // Tap-to-open overlay (mobile). Guard prevents duplicate listeners on re-init.
    const container = el.closest('.pp-sb-container');
    if (container && !container.dataset.tapInit) {
        container.dataset.tapInit = '1';

        const closeAll = () => container.querySelectorAll('.pp-sb-card--overlay-open')
            .forEach(c => c.classList.remove('pp-sb-card--overlay-open'));

        container.addEventListener('click', (e) => {
            const hint = e.target.closest('.pp-sb-hint');
            const overlay = e.target.closest('.pp-sb-overlay');
            const link = e.target.closest('.pp-sb-ovl-link');

            if (hint) {
                const card = hint.closest('.pp-sb-card');
                const isOpen = card.classList.contains('pp-sb-card--overlay-open');
                closeAll();
                if (!isOpen) card.classList.add('pp-sb-card--overlay-open');
                e.stopPropagation();
            } else if (overlay && !link) {
                closeAll();
                e.stopPropagation();
            }
        });

        document.addEventListener('click', closeAll);
    }
}
