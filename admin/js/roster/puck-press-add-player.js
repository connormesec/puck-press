(function ($) {
    jQuery(document).ready(function ($) {

        var $modal      = $('#pp-add-player-modal');
        var $addBtn     = $('#pp-add-player-button');
        var $closeBtn   = $('#pp-add-player-modal-close');
        var $cancelBtn  = $('#pp-cancel-add-player');
        var $confirmBtn = $('#pp-confirm-add-player');
        var $form       = $('#pp-add-player-form');

        // Open modal
        $(document).on('click', '#pp-add-player-button', function () {
            $modal.css('display', 'flex');
        });

        // Close modal
        function closeModal() {
            $modal.css('display', 'none');
            if ($form.length) { $form[0].reset(); }
        }

        $closeBtn.on('click', closeModal);
        $cancelBtn.on('click', closeModal);

        // Close on click outside
        $modal.on('mousedown', function (e) {
            if (e.target === $modal[0]) {
                closeModal();
            }
        });

        // Submit
        $confirmBtn.on('click', function () {
            if ($form.length && !$form[0].checkValidity()) {
                $form[0].reportValidity();
                return;
            }

            var formData = new FormData();
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
            formData.append('headshot_link', $('#pp-new-player-headshot-url').val());

            $confirmBtn.prop('disabled', true).text('Adding...');

            $.ajax({
                url: ajaxurl, method: 'POST', data: formData,
                processData: false, contentType: false,
                success: function (response) {
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
                error: function () {
                    $confirmBtn.prop('disabled', false).text('Add Player');
                    alert('Error adding player. Please try again.');
                }
            });
        });
    });
})(jQuery);
