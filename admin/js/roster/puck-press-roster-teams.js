(function($) {
  jQuery(document).ready(function($) {

    const getRosterId = () => parseInt($('#pp-active-new-roster-id').val(), 10) || 0;

    // ----------------------------------------------------------------
    // Core view helpers
    // ----------------------------------------------------------------

    function applyRosterView(rosterId, d) {
      // Sync color picker globals so saves and modal reflect the correct roster
      if (d.template_colors && typeof window.ppRosterAdmin !== 'undefined') {
        window.ppRosterAdmin.activeRosterId = rosterId;
        Object.assign(window.ppRosterTemplates, d.template_colors);
      }

      // Preview
      if (d.all_templates_html !== undefined) {
        $('#pp-roster-preview').html(d.all_templates_html);
        $('#pp-roster-preview-wrapper').removeClass('loading');
        if (typeof ppRosterTemplates !== 'undefined') {
          const keys = Object.keys(ppRosterTemplates.rosterTemplates);
          keys.forEach(function(k) { $('.' + k + '_roster_container').hide(); });
          const selected = ppRosterTemplates.selected_template || keys[0] || '';
          if (selected) $('.' + selected + '_roster_container').show();
        }
      }

      // Subtitle
      $('#pp-roster-membership-subtitle').text(
        d.is_main ? 'Main roster — auto-includes all teams.' : 'Teams assigned to this roster'
      );

      // Shortcode
      if (d.shortcode) {
        $('#pp-new-roster-shortcode').val(d.shortcode).attr('size', d.shortcode.length);
      }

      // Active roster ID
      $('#pp-active-new-roster-id').val(rosterId);

      // Teams table
      const $content = $('#pp-roster-teams-content');
      if (d.roster_teams && d.roster_teams.length > 0) {
        const actionHeader = d.is_main ? '' : '<th class="pp-th">Actions</th>';
        let rows = '';
        d.roster_teams.forEach(function(team) {
          rows += '<tr data-team-id="' + team.id + '">' +
            '<td class="pp-td">' + $('<span>').text(team.name).html() + '</td>' +
            '<td class="pp-td"><code>' + $('<span>').text(team.slug).html() + '</code></td>';
          if (!d.is_main) {
            rows += '<td class="pp-td"><button class="pp-button-icon pp-remove-team-from-roster-btn" ' +
              'data-team-id="' + team.id + '">✕</button></td>';
          }
          rows += '</tr>';
        });
        $content.html(
          '<table class="pp-table" id="pp-roster-teams-table">' +
          '<thead class="pp-thead"><tr>' +
          '<th class="pp-th">Name</th><th class="pp-th">Slug</th>' + actionHeader +
          '</tr></thead><tbody>' + rows + '</tbody></table>'
        );
      } else {
        $content.html('<p style="color:#888;">' + (
          d.is_main ? 'All teams are auto-included in the main roster.' : 'No teams in this roster yet.'
        ) + '</p>');
      }

      // Add-team controls
      const $toolbar    = $('#pp-roster-membership-toolbar');
      let $addSelect    = $('#pp-add-team-select');
      let $addBtn       = $('#pp-add-team-to-roster-btn');
      if (!d.is_main && d.available_teams && d.available_teams.length > 0) {
        let opts = '<option value="">— Add team —</option>';
        d.available_teams.forEach(function(team) {
          opts += '<option value="' + team.id + '">' + $('<span>').text(team.name).html() + '</option>';
        });
        if (!$addSelect.length) {
          $toolbar.append('<select id="pp-add-team-select" class="pp-select"></select>');
          $addSelect = $('#pp-add-team-select');
        }
        if (!$addBtn.length) {
          $toolbar.append('<button class="pp-button pp-button-primary" id="pp-add-team-to-roster-btn">+ Add Team</button>');
        }
        $addSelect.html(opts).show();
        $addBtn.show();
      } else {
        $addSelect.hide();
        $addBtn.hide();
      }

      // Delete footer
      const $footer = $('#pp-roster-delete-footer');
      if (d.is_main) {
        $footer.hide();
      } else {
        $footer.show();
        $footer.find('.pp-delete-new-roster-btn').attr('data-roster-id', rosterId).data('roster-id', rosterId);
      }
    }

    function refreshRosterView(rosterId) {
      console.log('[PP Roster] refreshRosterView called, roster_id=', rosterId);
      $.ajax({
        url: ajaxurl,
        type: 'POST',
        data: { action: 'pp_get_roster_preview', roster_id: rosterId },
        success: function(response) {
          console.log('[PP Roster] AJAX raw response:', response);
          if (response.success) {
            const d = response.data;
            console.log('[PP Roster] response.data keys:', Object.keys(d));
            console.log('[PP Roster] all_templates_html length:', d.all_templates_html ? d.all_templates_html.length : 'undefined/null');
            console.log('[PP Roster] all_templates_html snippet:', d.all_templates_html ? d.all_templates_html.substring(0, 300) : '(empty)');
            if (d.debug) console.log('[PP Roster] debug:', d.debug);
            applyRosterView(rosterId, d);
          } else {
            console.error('[PP Roster] AJAX returned success=false:', response);
          }
        },
        error: function(xhr) {
          console.error('[PP Roster] Failed to refresh roster view.', xhr.status, xhr.responseText);
          $('#pp-roster-preview-wrapper').removeClass('loading');
        },
      });
    }

    // ----------------------------------------------------------------
    // Roster selector
    // ----------------------------------------------------------------

    $('#pp-roster-selector').on('change', function() {
      const rosterId = parseInt($(this).val(), 10);
      $.post(ajaxurl, { action: 'pp_set_active_new_roster_id', roster_id: rosterId });
      refreshRosterView(rosterId);
    });

    // ----------------------------------------------------------------
    // Add roster modal
    // ----------------------------------------------------------------

    $('#pp-add-roster-btn').on('click', function() {
      $('#pp-new-roster-name').val('');
      $('#pp-new-roster-slug').val('');
      $('#pp-add-roster-modal').css('display', 'flex');
    });

    $('#pp-add-roster-modal-close, #pp-add-roster-modal-cancel').on('click', function() {
      $('#pp-add-roster-modal').css('display', 'none');
    });

    $('#pp-new-roster-name').on('input', function() {
      $('#pp-new-roster-slug').val($(this).val().toLowerCase().replace(/[^a-z0-9]+/g, '-').replace(/^-+|-+$/g, ''));
    });

    $('#pp-add-roster-modal-confirm').on('click', function() {
      const name = $('#pp-new-roster-name').val().trim();
      const slug = $('#pp-new-roster-slug').val().trim();
      if (!name || !slug) {
        console.error('Name and slug are required.');
        return;
      }
      $.ajax({
        url: ajaxurl,
        type: 'POST',
        data: { action: 'pp_create_new_roster', name: name, slug: slug },
        success: function(response) {
          if (response.success) {
            const id = response.data.id;
            $('#pp-roster-selector').append('<option value="' + id + '">' + $('<span>').text(name).html() + '</option>');
            $('#pp-roster-selector').val(id);
            $.post(ajaxurl, { action: 'pp_set_active_new_roster_id', roster_id: id });
            $('#pp-add-roster-modal').css('display', 'none');
            refreshRosterView(id);
          } else {
            alert(response.data && response.data.message ? response.data.message : 'Failed to create roster.');
          }
        },
        error: function(xhr) { alert('Server error: ' + (xhr.responseText ? xhr.responseText.substring(0, 200) : 'Unknown error')); }
      });
    });

    // ----------------------------------------------------------------
    // Delete roster
    // ----------------------------------------------------------------

    $(document).on('click', '.pp-delete-new-roster-btn', function() {
      if (!confirm('Delete this roster? This cannot be undone.')) return;
      const rosterId = $(this).data('roster-id');
      $.ajax({
        url: ajaxurl,
        type: 'POST',
        data: { action: 'pp_delete_new_roster', roster_id: rosterId },
        success: function(response) {
          if (response.success) {
            $('#pp-roster-selector option[value="' + rosterId + '"]').remove();
            const firstId = parseInt($('#pp-roster-selector option:first').val(), 10);
            if (firstId) {
              $('#pp-roster-selector').val(firstId);
              $.post(ajaxurl, { action: 'pp_set_active_new_roster_id', roster_id: firstId });
              refreshRosterView(firstId);
            }
          } else {
            console.error('Failed to delete roster:', response.data && response.data.message);
          }
        },
        error: function() { console.error('An error occurred while deleting the roster.'); }
      });
    });

    // ----------------------------------------------------------------
    // Add / remove team from roster
    // ----------------------------------------------------------------

    $(document).on('click', '#pp-add-team-to-roster-btn', function() {
      const rosterId = getRosterId();
      const teamId   = parseInt($('#pp-add-team-select').val(), 10);
      if (!teamId) return;
      $.ajax({
        url: ajaxurl,
        type: 'POST',
        data: { action: 'pp_add_team_to_roster', roster_id: rosterId, team_id: teamId },
        success: function(response) {
          if (response.success) {
            refreshRosterView(rosterId);
          } else {
            console.error('Failed to add team to roster:', response.data && response.data.message);
          }
        },
        error: function() { console.error('Error adding team to roster.'); }
      });
    });

    $(document).on('click', '.pp-remove-team-from-roster-btn', function() {
      if (!confirm('Remove this team from the roster?')) return;
      const rosterId = getRosterId();
      const teamId   = $(this).data('team-id');
      $.ajax({
        url: ajaxurl,
        type: 'POST',
        data: { action: 'pp_remove_team_from_roster', roster_id: rosterId, team_id: teamId },
        success: function(response) {
          if (response.success) {
            refreshRosterView(rosterId);
          } else {
            console.error('Failed to remove team from roster:', response.data && response.data.message);
          }
        },
        error: function() { console.error('Error removing team from roster.'); }
      });
    });

    // ----------------------------------------------------------------
    // Refresh all sources
    // ----------------------------------------------------------------

    $('#pp-roster-refresh-all-btn').on('click', function() {
      const $btn       = $(this);
      const originalHtml = $btn.html();
      $btn.html('<span class="spinner is-active" style="float:left;margin-right:5px;"></span> Refreshing...').prop('disabled', true);
      $.ajax({
        url: ajaxurl,
        type: 'POST',
        data: { action: 'pp_ajax_refresh_roster_sources' },
        success: function(response) {
          $btn.html(originalHtml).prop('disabled', false);
          if (response.success) {
            console.log('[PP Roster Refresh] Success. Log:');
            if (response.data && response.data.log) {
              response.data.log.forEach(function(line) { console.log(' ', line); });
            }
            const rosterId = getRosterId();
            if (rosterId) refreshRosterView(rosterId);
          } else {
            console.error('[PP Roster Refresh] AJAX returned failure:', response);
          }
        },
        error: function(xhr) {
          $btn.html(originalHtml).prop('disabled', false);
          console.error('[PP Roster Refresh] AJAX error:', xhr.status, xhr.responseText);
        }
      });
    });

    const initialRosterId = getRosterId();
    if (initialRosterId) refreshRosterView(initialRosterId);

  });
})(jQuery);
