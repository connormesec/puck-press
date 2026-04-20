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

  // Reveal before Glider.js init so it can read real dimensions.
  el.closest('.pp-ss-container').classList.add('pp-ss-ready');

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
}
