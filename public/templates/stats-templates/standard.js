(function ($) {
    $(document).ready(() => {
        initializeStandardStats();
        if (typeof statsInitializers !== 'undefined') {
            statsInitializers.push(initializeStandardStats);
        }
    });
})(jQuery);

function initializeStandardStats() {
    if (typeof PpPlayerDetail !== 'undefined') {
        PpPlayerDetail.init('.standard_stats_container', '.pp-stats-player-link');
    }
}
