(function ($) {
    $(document).ready(() => {
        const $modal      = $('#pp-roster-archive-modal');
        const $statsCount = $('#pp-roster-archive-stats-count');
        const $error      = $('#pp-roster-archive-error');
        const $confirmBtn = $('#pp-confirm-roster-archive');
        const $seasonSel  = $('#pp-roster-archive-season');

        const closeModal = () => {
            $modal.css('display', 'none');
            $error.hide().text('');
            $confirmBtn.prop('disabled', false).text('Archive Roster');
        };

        const openModal = () => {
            $modal.css('display', 'flex');
            $error.hide().text('');
            $statsCount.text('Loading...');

            $.post(ajaxurl, { action: 'pp_get_roster_stats_count' }, (response) => {
                if (response.success) {
                    const { skater_count, goalie_count } = response.data;
                    $statsCount.text(`${skater_count} skater${skater_count !== 1 ? 's' : ''} and ${goalie_count} goalie${goalie_count !== 1 ? 's' : ''} will be archived.`);
                } else {
                    $statsCount.text('');
                }
            });
        };

        $('#pp-archive-roster-btn').on('click', openModal);

        $modal.find('.pp-modal-close').on('click', closeModal);
        $('#pp-cancel-roster-archive').on('click', closeModal);

        if (typeof enableClickOutsideToClose === 'function') {
            enableClickOutsideToClose($modal, closeModal);
        }

        $confirmBtn.on('click', () => {
            const season = $seasonSel.val();
            $error.hide().text('');

            if (!season) {
                $error.text('Please select a season.').show();
                return;
            }

            $confirmBtn.prop('disabled', true).text('Archiving...');

            $.post(ajaxurl, {
                action: 'pp_create_roster_archive',
                season,
            }, (response) => {
                $confirmBtn.prop('disabled', false).text('Archive Roster');

                if (response.success) {
                    closeModal();
                    $('#pp-card-content-roster-archives').html(response.data.html);
                } else {
                    $error.text(response.data || 'Something went wrong.').show();
                }
            });
        });

        $(document).on('click', '.pp-delete-roster-archive', function () {
            if (!confirm('Delete this roster archive? This cannot be undone.')) return;

            const key  = $(this).data('key');
            const $row = $(this).closest('tr');
            $row.css({ opacity: '0.5', 'pointer-events': 'none' });

            $.post(ajaxurl, {
                action: 'pp_delete_roster_archive',
                archive_key: key,
            }, (response) => {
                if (response.success) {
                    $row.remove();

                    if ($('.pp-archives-table tbody tr', '#pp-card-content-roster-archives').length === 0) {
                        $('#pp-card-content-roster-archives').html(
                            '<p class="pp-empty-state">No roster archives yet. Use <strong>Advanced &rarr; Archive Roster</strong> to create one.</p>'
                        );
                    }
                } else {
                    $row.css({ opacity: '1', 'pointer-events': 'auto' });
                    alert('Delete failed. Please try again.');
                }
            });
        });
    });
})(jQuery);
