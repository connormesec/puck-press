(function ($) {
  jQuery(document).ready(function ($) {
    const admin    = window.ppRosterAdmin || {};
    const nonce    = admin.nonce || '';
    const domainLc = 'roster';

    $('#pp-roster-group-selector').on('change', function () {
      const rosterId = parseInt($(this).val(), 10);
      $.ajax({
        url: ajaxurl,
        type: 'POST',
        data: {
          action: 'pp_set_active_roster_id',
          roster_id: rosterId,
        },
        success: () => {
          window.location.reload();
        },
        error: () => {
          alert('Failed to switch roster group.');
        }
      });
    });

    const $modal      = $(`#pp-${domainLc}-new-group-modal`);
    const $nameInput  = $(`#pp-${domainLc}-group-name`);
    const $slugInput  = $(`#pp-${domainLc}-group-slug`);
    const $createBtn  = $(`#pp-${domainLc}-create-group-btn`);
    const $cancelBtns = $(`.pp-${domainLc}-modal-cancel`);

    $(`#pp-${domainLc}-new-group-btn`).on('click', function () {
      $nameInput.val('');
      $slugInput.val('');
      $modal.css('display', 'flex');
    });

    $cancelBtns.on('click', function () {
      $modal.css('display', 'none');
    });

    $nameInput.on('input', function () {
      const slug = $(this).val()
        .toLowerCase()
        .replace(/[^a-z0-9]+/g, '-')
        .replace(/^-+|-+$/g, '');
      $slugInput.val(slug);
    });

    $createBtn.on('click', function () {
      const name = $nameInput.val().trim();
      const slug = $slugInput.val().trim();

      if (!name || !slug) {
        alert('Name and slug are required.');
        return;
      }

      $.ajax({
        url: ajaxurl,
        type: 'POST',
        data: {
          action: $createBtn.data('action'),
          name: name,
          slug: slug,
          nonce: nonce,
        },
        success: (response) => {
          if (response.success) {
            window.location.reload();
          } else {
            alert(response.data.message || 'Failed to create group.');
          }
        },
        error: () => {
          alert('An error occurred while creating the group.');
        }
      });
    });

    $(document).on('click', '.pp-delete-group-btn', function () {
      const groupId = $(this).data('group-id');
      const action  = $(this).data('action');

      if (!confirm('Delete this roster group and all its data? This cannot be undone.')) {
        return;
      }

      $.ajax({
        url: ajaxurl,
        type: 'POST',
        data: {
          action: action,
          group_id: groupId,
          nonce: nonce,
        },
        success: (response) => {
          if (response.success) {
            $(`tr[data-group-id="${groupId}"]`).remove();
            if (parseInt(groupId, 10) === parseInt(admin.activeRosterId, 10)) {
              window.location.reload();
            }
          } else {
            alert(response.data.message || 'Failed to delete group.');
          }
        },
        error: () => {
          alert('An error occurred while deleting the group.');
        }
      });
    });
  });
})(jQuery);
