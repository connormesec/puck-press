(function ($) {
    $(document).ready(function () {
        var cfg = window.ppAwardsFilter || {};

        $('.pp-awards-year-select').on('change', function () {
            var $select = $(this);
            var $wrap = $select.closest('.pp-awards-wrap');
            var $content = $wrap.find('.pp-awards-content');
            var year = $select.val();

            var attr = function (name, fallback) {
                var v = $wrap.attr('data-' + name);
                return (v === undefined || v === '') ? fallback : v;
            };

            $content.addClass('pp-loading');

            $.post(cfg.ajaxUrl, {
                action: 'pp_get_awards_html',
                year: year,
                parent: attr('parent', ''),
                award: attr('award', ''),
                columns: attr('columns', '6'),
                show_headshots: attr('show-headshots', 'true'),
                link_players: attr('link-players', 'true'),
                show_title: attr('show-title', 'true')
            }, function (res) {
                if (res.success && res.data.html) {
                    $content.html(res.data.html);
                }
                $content.removeClass('pp-loading');
            }).fail(function () {
                $content.removeClass('pp-loading');
            });

            var url = new URL(window.location.href);
            url.searchParams.set('pp_awards_year', year);
            history.replaceState(null, '', url.toString());
        });
    });
})(jQuery);
