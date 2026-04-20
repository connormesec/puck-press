(function () {
  'use strict';

  /**
   * @param {HTMLElement} container
   */
  function initTabbedLeaders(container) {
    var tabs   = container.querySelectorAll('.pp-tabbed-tab');
    var panels = container.querySelectorAll('.pp-tabbed-panel');

    if (!tabs.length || !panels.length) {
      return;
    }

    tabs.forEach(function (tab) {
      tab.addEventListener('click', function () {
        var idx = tab.getAttribute('data-tab');

        tabs.forEach(function (t) {
          var isActive = t === tab;
          t.classList.toggle('pp-tab-active', isActive);
          t.setAttribute('aria-selected', isActive ? 'true' : 'false');
        });

        panels.forEach(function (panel) {
          var isActive = panel.getAttribute('data-panel') === idx;
          panel.classList.toggle('pp-panel-active', isActive);
          if (isActive) {
            panel.removeAttribute('hidden');
          } else {
            panel.setAttribute('hidden', '');
          }
        });
      });
    });
  }

  document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('.pp-stat-leaders-tabbed-container').forEach(initTabbedLeaders);
  });
})();
