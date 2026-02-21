/**
 * Shared player detail module.
 *
 * Usage (from a template's JS file):
 *   PpPlayerDetail.init('.my_roster_container', '.my_card_selector');
 *
 * The container element must have data-ajaxurl and data-nonce attributes
 * set in the PHP template.
 */
window.PpPlayerDetail = (function ($) {

    function init(containerSelector, cardSelector) {
        var container = $(containerSelector);
        if (!container.length) return;

        var ajaxurl = container.data('ajaxurl');
        var nonce   = container.data('nonce');
        if (!ajaxurl || !nonce) return;

        var originalGridHTML = container.html();

        // Check URL for ?player= param on page load
        var urlParams = new URLSearchParams(window.location.search);
        var playerId  = urlParams.get('player');

        if (playerId) {
            history.replaceState({ player_id: playerId }, '', window.location.href);
            loadPlayerDetail(playerId);
        } else {
            history.replaceState({ player_id: null }, '', window.location.href);
        }

        // ── Card click ───────────────────────────────────────────────────────
        $(document).on('click', cardSelector, function () {
            var pid = $(this).attr('id');
            if (!pid) return;
            var newUrl = updateQueryParam(window.location.href, 'player', pid);
            history.pushState({ player_id: pid }, '', newUrl);
            loadPlayerDetail(pid);
        });

        // ── Back button inside detail view ───────────────────────────────────
        $(document).on('click', '.pp-player-back-btn', function (e) {
            e.preventDefault();
            history.back();
        });

        // ── Tab switching ────────────────────────────────────────────────────
        $(document).on('click', '.pp-player-tab', function () {
            var tabId = $(this).data('tab');
            $('.pp-player-tab').removeClass('pp-tab-active');
            $(this).addClass('pp-tab-active');
            $('.pp-player-tab-panel').removeClass('pp-panel-active');
            $('#pp-panel-' + tabId).addClass('pp-panel-active');
        });

        // ── Browser back / forward ───────────────────────────────────────────
        window.addEventListener('popstate', function (e) {
            var state = e.state;
            if (state && state.player_id) {
                loadPlayerDetail(state.player_id);
            } else {
                restoreGrid();
            }
        });

        // ── Internal helpers ─────────────────────────────────────────────────

        function loadPlayerDetail(pid) {
            container = $(containerSelector);
            container.html(
                '<div class="pp-player-loading">' +
                '<span class="pp-loading-spinner"></span> Loading&hellip;' +
                '</div>'
            );

            $.ajax({
                url:  ajaxurl,
                type: 'POST',
                data: {
                    action:    'pp_get_player_detail',
                    player_id: pid,
                    nonce:     nonce,
                },
                success: function (response) {
                    if (response.success && response.data && response.data.html) {
                        container.html(response.data.html);
                        $('html, body').animate({ scrollTop: container.offset().top - 40 }, 200);
                    } else {
                        container.html('<div class="pp-player-error">Player not found.</div>');
                    }
                },
                error: function () {
                    container.html('<div class="pp-player-error">An error occurred. Please try again.</div>');
                },
            });
        }

        function restoreGrid() {
            container = $(containerSelector);
            if (originalGridHTML) {
                container.html(originalGridHTML);
            }
        }

        function updateQueryParam(url, key, value) {
            var urlObj = new URL(url);
            urlObj.searchParams.set(key, value);
            return urlObj.toString();
        }
    }

    return { init: init };

})(jQuery);
