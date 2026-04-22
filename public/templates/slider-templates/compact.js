(function ($) {
  $(document).ready(() => {
    initCompactSlider();

    if (typeof gameScheduleInitializers !== 'undefined') {
      gameScheduleInitializers.push(initCompactSlider);
    }
  });
})(jQuery);

function initCompactSlider() {
  const el = document.querySelector('.pp-cs-glider');
  if (!el) return;

  // Reveal before init so Glider.js can read real dimensions.
  const container = el.closest('.pp-cs-container');
  container.classList.add('pp-cs-ready');

  if (!el.offsetWidth) {
    // Still hidden after reveal — inside a closed tab/accordion. Pull back
    // the reveal so we don't show an uninitialized slider, and bail out.
    container.classList.remove('pp-cs-ready');
    return;
  }

  const glider = new Glider(el, {
    slidesToShow: 1,
    slidesToScroll: 1,
    draggable: false,
    scrollLock: true,
    arrows: {
      prev: '.pp-cs-prev',
      next: '.pp-cs-next',
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
  const targetIndex = window.ppCompactIndex || 0;
  glider.setOption({ duration: 0 });
  glider.scrollItem(targetIndex);
  glider.setOption({ duration: 0.25 });

  // Tap-to-open overlay (mobile). Guard prevents duplicate listeners on re-init.
  if (container && !container.dataset.tapInit) {
    container.dataset.tapInit = '1';

    const closeAll = () => container.querySelectorAll('.pp-cs-card--overlay-open')
      .forEach(c => c.classList.remove('pp-cs-card--overlay-open'));

    container.addEventListener('click', (e) => {
      const hint = e.target.closest('.pp-cs-hint');
      const overlay = e.target.closest('.pp-cs-overlay');
      const link = e.target.closest('.pp-cs-ovl-link--recap, .pp-cs-ovl-link--ticket');

      if (hint) {
        const card = hint.closest('.pp-cs-card');
        const isOpen = card.classList.contains('pp-cs-card--overlay-open');
        closeAll();
        if (!isOpen) card.classList.add('pp-cs-card--overlay-open');
        e.stopPropagation();
      } else if (overlay && !link) {
        closeAll();
        e.stopPropagation();
      }
    });

    document.addEventListener('click', closeAll);
  }
}
