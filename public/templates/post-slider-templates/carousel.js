document.addEventListener('DOMContentLoaded', function () {
  document.querySelectorAll('.carousel_post_slider_container').forEach(function (container) {
    var slides = Array.from(container.querySelectorAll('.pp-cr-slide'));
    if (!slides.length) return;

    var titleEl = container.querySelector('.pp-cr-title');
    var btnEl = container.querySelector('.pp-cr-btn');
    var prevBtn = container.querySelector('.pp-cr-nav--prev');
    var nextBtn = container.querySelector('.pp-cr-nav--next');
    var current = 0;

    function goTo(index) {
      slides[current].classList.remove('pp-cr-slide--active');
      current = (index + slides.length) % slides.length;
      slides[current].classList.add('pp-cr-slide--active');
      updateFooter();
    }

    function updateFooter() {
      var slide = slides[current];
      if (titleEl) titleEl.textContent = slide.dataset.title || '';
      if (btnEl) btnEl.href = slide.dataset.url || '#';
    }

    if (prevBtn) prevBtn.addEventListener('click', function () { goTo(current - 1); });
    if (nextBtn) nextBtn.addEventListener('click', function () { goTo(current + 1); });

    updateFooter();
  });
});
