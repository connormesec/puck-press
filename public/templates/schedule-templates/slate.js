(function ($) {
    $(document).ready(() => {
        initializeSlate();
        if (typeof gameScheduleInitializers !== 'undefined') {
            gameScheduleInitializers.push(initializeSlate);
        }
    });
})(jQuery);

function initializeSlate() {
    const tabBtns = document.querySelectorAll('.slate-tab-btn');
    if (!tabBtns.length) return;

    tabBtns.forEach((btn) => {
        btn.addEventListener('click', function () {
            const targetId = this.getAttribute('data-slate-tab');

            // Update button active states
            tabBtns.forEach((b) => {
                b.classList.remove('slate-tab-active');
            });
            this.classList.add('slate-tab-active');

            // Show the target panel, hide all others
            document.querySelectorAll('.slate-panel').forEach((panel) => {
                panel.style.display = (panel.id === targetId) ? 'block' : 'none';
            });
        });
    });
}
