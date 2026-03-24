function ppLnCardInitContainer(container) {
  var track    = container.querySelector('.pp-ln-card-track');
  var cards    = Array.from(container.querySelectorAll('.pp-ln-card'));
  var dotsWrap = container.querySelector('.pp-ln-card-dots');
  var prevBtn  = container.querySelector('.pp-ln-card-arrow--prev');
  var nextBtn  = container.querySelector('.pp-ln-card-arrow--next');

  if (!track || !cards.length) return;

  var perPage = getPerPage();
  var pages   = Math.ceil(cards.length / perPage);
  var current = 0;

  function getPerPage() {
    var w = container.offsetWidth;
    if (w <= 500) return 1;
    if (w <= 768) return 2;
    return 3;
  }

  function buildDots() {
    dotsWrap.innerHTML = '';
    for (var i = 0; i < pages; i++) {
      var dot = document.createElement('button');
      dot.className = 'pp-ln-card-dot' + (i === current ? ' pp-ln-card-dot--active' : '');
      dot.setAttribute('aria-label', 'Page ' + (i + 1));
      dot.dataset.page = i;
      dot.addEventListener('click', function () { goTo(parseInt(this.dataset.page)); });
      dotsWrap.appendChild(dot);
    }
  }

  function updateDots() {
    Array.from(dotsWrap.querySelectorAll('.pp-ln-card-dot')).forEach(function (dot, i) {
      dot.classList.toggle('pp-ln-card-dot--active', i === current);
    });
  }

  function getCardWidth() {
    if (!cards[0]) return 0;
    var style = window.getComputedStyle(track);
    var gap   = parseFloat(style.gap) || 20;
    return cards[0].offsetWidth + gap;
  }

  function goTo(page) {
    current = Math.max(0, Math.min(page, pages - 1));
    track.style.transform = 'translateX(-' + (current * perPage * getCardWidth()) + 'px)';
    updateDots();
    if (prevBtn) prevBtn.disabled = current === 0;
    if (nextBtn) nextBtn.disabled = current === pages - 1;
  }

  function init() {
    perPage = getPerPage();
    pages   = Math.ceil(cards.length / perPage);
    current = 0;
    buildDots();
    goTo(0);
  }

  if (prevBtn) prevBtn.addEventListener('click', function () { goTo(current - 1); });
  if (nextBtn) nextBtn.addEventListener('click', function () { goTo(current + 1); });

  init();

  var resizeTimer;
  window.addEventListener('resize', function () {
    clearTimeout(resizeTimer);
    resizeTimer = setTimeout(init, 150);
  });
}

document.addEventListener('DOMContentLoaded', function () {
  document.querySelectorAll('.pp-ln-card-container').forEach(ppLnCardInitContainer);
});
