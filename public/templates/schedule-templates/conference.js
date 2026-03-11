(function ($) {
    $(document).ready(() => {
        initializeConference();
        if (typeof gameScheduleInitializers !== 'undefined') {
            gameScheduleInitializers.push(initializeConference);
        }
    });
})(jQuery);

function initializeConference() {
    const tabBtns = document.querySelectorAll('.csb-tab-btn');
    if (!tabBtns.length) return;

    tabBtns.forEach((btn) => {
        btn.addEventListener('click', function () {
            const targetId = this.getAttribute('data-csb-tab');

            tabBtns.forEach((b) => b.classList.remove('csb-tab-active'));
            this.classList.add('csb-tab-active');

            document.querySelectorAll('.csb-panel').forEach((panel) => {
                panel.style.display = (panel.id === targetId) ? 'block' : 'none';
            });
        });
    });
}
