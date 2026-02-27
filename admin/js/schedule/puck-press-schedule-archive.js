(function ($) {
    $(document).ready(() => {
        const $modal      = $('#pp-archive-modal');
        const $gameCount  = $('#pp-archive-game-count');
        const $error      = $('#pp-archive-error');
        const $confirmBtn = $('#pp-confirm-archive');
        const $seasonSel  = $('#pp-archive-season');

        const closeModal = () => {
            $modal.css('display', 'none');
            $error.hide().text('');
            $confirmBtn.prop('disabled', false).text('Archive Season');
        };

        const openModal = () => {
            $modal.css('display', 'flex');
            $error.hide().text('');
            $gameCount.text('Loading...');

            $.post(ajaxurl, { action: 'pp_get_archive_game_count' }, (response) => {
                if (response.success) {
                    const count = response.data.count;
                    $gameCount.text(`${count} game${count !== 1 ? 's' : ''} will be archived.`);
                } else {
                    $gameCount.text('');
                }
            });
        };

        $('#pp-archive-season-btn').on('click', openModal);

        $modal.find('.pp-modal-close').on('click', closeModal);
        $('#pp-cancel-archive').on('click', closeModal);

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
                action: 'pp_create_schedule_archive',
                season,
            }, (response) => {
                $confirmBtn.prop('disabled', false).text('Archive Season');

                if (response.success) {
                    closeModal();
                    $('#pp-card-content-schedule-archives').html(response.data.html);
                } else {
                    $error.text(response.data || 'Something went wrong.').show();
                }
            });
        });

        $(document).on('click', '.pp-delete-archive', function () {
            if (!confirm('Delete this archive? This cannot be undone.')) return;

            const key  = $(this).data('key');
            const $row = $(this).closest('tr');
            $row.css({ opacity: '0.5', 'pointer-events': 'none' });

            $.post(ajaxurl, {
                action: 'pp_delete_schedule_archive',
                archive_key: key,
            }, (response) => {
                if (response.success) {
                    $row.remove();

                    if ($('.pp-archives-table tbody tr').length === 0) {
                        $('#pp-card-content-schedule-archives').html(
                            '<p class="pp-empty-state">No archives yet. Use <strong>Advanced &rarr; Archive Season</strong> to create one.</p>'
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
