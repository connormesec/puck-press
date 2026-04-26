(function ($) {
  $(document).ready(() => {
    initScoreStripSlider();

    if (typeof gameScheduleInitializers !== 'undefined') {
      gameScheduleInitializers.push(initScoreStripSlider);
    }
  });
})(jQuery);

function initScoreStripSlider() {
  const el = document.querySelector('.pp-ss-glider');
  if (!el) return;

  const container = el.closest('.pp-ss-container');

  container.classList.add('pp-ss-ready');

  // Defer one frame so the flex layout settles before Glider reads clientWidth.
  requestAnimationFrame(() => {
    const glider = new Glider(el, {
      slidesToShow: 1,
      slidesToScroll: 1,
      draggable: true,
      scrollLock: true,
      arrows: {
        prev: '.pp-ss-prev',
        next: '.pp-ss-next',
      },
      easing: (x, t, b, c, d) => c * (t /= d) * t + b,
      responsive: [
        {
          breakpoint: 480,
          settings: { slidesToShow: 2, slidesToScroll: 1, scrollLock: true, duration: 0.25 },
        },
        {
          breakpoint: 720,
          settings: { slidesToShow: 3, slidesToScroll: 1, scrollLock: true, duration: 0.25 },
        },
        {
          breakpoint: 960,
          settings: { slidesToShow: 4, slidesToScroll: 1, scrollLock: true, duration: 0.25 },
        },
      ],
    });

    // Scroll so the next upcoming game is positioned near the right of the visible window.
    const targetIndex = window.ppScoreStripIndex || 0;
    glider.setOption({ duration: 0 });
    glider.scrollItem(targetIndex);
    glider.setOption({ duration: 0.25 });

    // Re-measure if the container resizes after init (scrollbar shift, etc.).
    let initWidth = el.clientWidth;
    new ResizeObserver(() => {
      if (el.clientWidth !== initWidth) {
        initWidth = el.clientWidth;
        glider.refresh(true);
        glider.setOption({ duration: 0 });
        glider.scrollItem(targetIndex);
        glider.setOption({ duration: 0.25 });
      }
    }).observe(el);
  });

  // Tap-to-open recap overlay (mobile). Guard prevents duplicate listeners on re-init.
  if (!container.dataset.tapInit) {
    container.dataset.tapInit = '1';

    const closeAll = () =>
      container.querySelectorAll('.pp-ss-item--recap-open')
        .forEach(c => c.classList.remove('pp-ss-item--recap-open'));

    container.addEventListener('click', (e) => {
      const hint    = e.target.closest('.pp-ss-recap-hint');
      const overlay = e.target.closest('.pp-ss-recap-overlay');
      const link    = e.target.closest('.pp-ss-recap-link');

      if (hint) {
        const item   = hint.closest('.pp-ss-item');
        const isOpen = item.classList.contains('pp-ss-item--recap-open');
        closeAll();
        if (!isOpen) item.classList.add('pp-ss-item--recap-open');
        e.stopPropagation();
      } else if (overlay && !link) {
        closeAll();
        e.stopPropagation();
      }
    });

    document.addEventListener('click', closeAll);
  }
}
