(function ($) {
    $(document).ready(() => {
        initializeStandardStats();
        if (typeof statsInitializers !== 'undefined') {
            statsInitializers.push(initializeStandardStats);
        }
    });
})(jQuery);

function initializeStandardStats() {
}
