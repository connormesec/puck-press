(function ($) {
    const getRosterId = () => (window.ppRosterAdmin && window.ppRosterAdmin.activeRosterId) ? parseInt(window.ppRosterAdmin.activeRosterId, 10) : 1;

    jQuery(document).ready(function ($) {

        const $modal      = $('#pp-add-player-modal');
        const $addBtn     = $('#pp-add-player-button');
        const $closeBtn   = $('#pp-add-player-modal-close');
        const $cancelBtn  = $('#pp-cancel-add-player');
        const $confirmBtn = $('#pp-confirm-add-player');
        const $form       = $('#pp-add-player-form');

        // Open modal
        $(document).on('click', '#pp-add-player-button', () => {
            $modal.css('display', 'flex');
        });

        // Close modal
        const closeModal = () => {
            $modal.css('display', 'none');
            if ($form.length) { $form[0].reset(); }
        };

        $closeBtn.on('click', closeModal);
        $cancelBtn.on('click', closeModal);

        // Close on click outside
        $modal.on('mousedown', (e) => {
            if (e.target === $modal[0]) {
                closeModal();
            }
        });

        // Submit
        $confirmBtn.on('click', () => {
            if ($form.length && !$form[0].checkValidity()) {
                $form[0].reportValidity();
                return;
            }

            const formData = new FormData();
            formData.append('action',        'pp_add_manual_player');
            formData.append('name',          $('#pp-new-player-name').val());
            formData.append('number',        $('#pp-new-player-number').val());
            formData.append('pos',           $('#pp-new-player-position').val());
            formData.append('ht',            $('#pp-new-player-height').val());
            formData.append('wt',            $('#pp-new-player-weight').val());
            formData.append('shoots',        $('#pp-new-player-shoots').val());
            formData.append('hometown',      $('#pp-new-player-hometown').val());
            formData.append('last_team',     $('#pp-new-player-last-team').val());
            formData.append('year_in_school', $('#pp-new-player-year').val());
            formData.append('major',         $('#pp-new-player-major').val());
            formData.append('headshot_link',  $('#pp-new-player-headshot-url').val());
            formData.append('hero_image_url', $('#pp-new-player-hero-image-url').val());
            formData.append('roster_id',        getRosterId());

            $confirmBtn.prop('disabled', true).text('Adding...');

            $.ajax({
                url: ajaxurl, method: 'POST', data: formData,
                processData: false, contentType: false,
                success: (response) => {
                    $confirmBtn.prop('disabled', false).text('Add Player');
                    if (response.success && response.data && response.data.roster_table_html) {
                        $('#pp-roster-edits-table').replaceWith(response.data.roster_table_html);
                        if (typeof applyEditHighlights === 'function') {
                            applyEditHighlights();
                        }
                        closeModal();
                    } else {
                        alert('Failed to add player: ' + (response.data && response.data.message || 'Unknown error'));
                    }
                },
                error: () => {
                    $confirmBtn.prop('disabled', false).text('Add Player');
                    alert('Error adding player. Please try again.');
                }
            });
        });
    });
})(jQuery);
