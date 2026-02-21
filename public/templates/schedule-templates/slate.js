(function ($) {
    $(document).ready(function () {
        initializeSlate();
        if (typeof gameScheduleInitializers !== 'undefined') {
            gameScheduleInitializers.push(initializeSlate);
        }
    });
})(jQuery);

function initializeSlate() {
    var tabBtns = document.querySelectorAll('.slate-tab-btn');
    if (!tabBtns.length) return;

    tabBtns.forEach(function (btn) {
        btn.addEventListener('click', function () {
            var targetId = this.getAttribute('data-slate-tab');

            // Update button active states
            tabBtns.forEach(function (b) {
                b.classList.remove('slate-tab-active');
            });
            this.classList.add('slate-tab-active');

            // Show the target panel, hide all others
            document.querySelectorAll('.slate-panel').forEach(function (panel) {
                panel.style.display = (panel.id === targetId) ? 'block' : 'none';
            });
        });
    });
}
