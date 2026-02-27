/**
 * Player detail page interactions.
 *
 * Handles tab switching and the "Back" button on the /player/{slug} page.
 * Routing and AJAX are no longer needed — player pages are server-rendered
 * via native WordPress rewrite rules.
 */
(function ($) {
    $(document).ready(() => {

        // ── Tab switching ────────────────────────────────────────────────────
        $(document).on('click', '.pp-player-tab', function () {
            const tabId = $(this).data('tab');
            $('.pp-player-tab').removeClass('pp-tab-active');
            $(this).addClass('pp-tab-active');
            $('.pp-player-tab-panel').removeClass('pp-panel-active');
            $(`#pp-panel-${tabId}`).addClass('pp-panel-active');
        });

        // ── Back button ──────────────────────────────────────────────────────
        $(document).on('click', '.pp-player-back-btn', (e) => {
            e.preventDefault();
            history.back();
        });

    });
})(jQuery);
