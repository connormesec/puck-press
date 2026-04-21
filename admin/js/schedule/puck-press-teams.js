(function ($) {
  jQuery(document).ready(function ($) {
    // Override countGameRows to target the team game list subtitle
    window.countGameRows = () => {
      const rowCount = $('#pp-games-table tbody tr:not(.pp-row-deleted)').length;
      $('#pp-card-subtitle-team-game-list').text(`${rowCount} games scheduled`);
    };
    window.countGameRows();

    window.applyGameEditHighlights = function() {
      $('#pp-games-table tbody tr:not(.pp-row-deleted)').each(function() {
        const $row  = $(this);
        const modId = $row.attr('data-mod-id');
        let overrides = [];
        try { overrides = JSON.parse($row.attr('data-overrides') || '[]'); } catch(e) {}

        $row.find('td[data-field]').each(function() {
          const $td   = $(this);
          const field = $td.attr('data-field');
          if (overrides.indexOf(field) !== -1) {
            $td.addClass('pp-cell-overridden');
            if (!$td.find('.pp-revert-game-field-btn').length) {
              $td.append(`<button class="pp-revert-btn pp-revert-game-field-btn" title="Revert to original" data-mod-id="${modId}" data-fields="${field}">&#x2715;</button>`);
            }
          } else {
            $td.removeClass('pp-cell-overridden');
            $td.find('.pp-revert-game-field-btn').remove();
          }
        });
      });
    };
    window.applyGameEditHighlights();

    //############################################################//
    //               Sub-section Tab Switching                    //
    //############################################################//

    $('.pp-subsection-tab').on('click', function () {
      const tab = $(this).data('tab');

      $('.pp-subsection-tab').css({
        'font-weight': 'normal',
        'color': '#555',
        'border-bottom': '2px solid transparent',
      });
      $(this).css({
        'font-weight': '600',
        'color': '#1a6fa8',
        'border-bottom': '2px solid #1a6fa8',
      });

      $('#pp-subsection-teams, #pp-subsection-schedules').hide();
      $('#pp-subsection-' + tab).show();
    });


    //############################################################//
    //               Add Team Modal                               //
    //############################################################//

    const $addTeamModal  = $('#pp-add-team-modal');
    const $addTeamName   = $('#pp-new-team-name');
    const $addTeamSlug   = $('#pp-new-team-slug');

    $('#pp-add-team-btn').on('click', function () {
      $addTeamName.val('');
      $addTeamSlug.val('');
      $addTeamModal.css('display', 'flex');
    });

    $('#pp-add-team-modal-close, #pp-add-team-modal-cancel').on('click', function () {
      $addTeamModal.css('display', 'none');
    });

    $addTeamName.on('input', function () {
      $addTeamSlug.val($(this).val().toLowerCase().replace(/[^a-z0-9]+/g, '-').replace(/^-+|-+$/g, ''));
    });

    $('#pp-add-team-modal-confirm').on('click', function () {
      const name = $addTeamName.val().trim();
      const slug = $addTeamSlug.val().trim();
      if (!name || !slug) {
        console.error('Name and slug are required.');
        return;
      }
      $.ajax({
        url: ajaxurl,
        type: 'POST',
        data: { action: 'pp_create_team', name: name, slug: slug },
        success: function (response) {
          if (response.success) {
            $addTeamModal.css('display', 'none');
            const team = response.data;
            const $tbody = $('#pp-teams-list-table tbody');
            if (!$tbody.length) {
              location.reload();
              return;
            }
            $tbody.append(
              '<tr data-team-id="' + team.id + '" data-team-name="' + $('<span>').text(team.name).html() + '" data-team-slug="' + $('<span>').text(team.slug).html() + '">' +
                '<td class="pp-td"><code>' + team.id + '</code></td>' +
                '<td class="pp-td">' + $('<span>').text(team.name).html() + '</td>' +
                '<td class="pp-td"><code>' + $('<span>').text(team.slug).html() + '</code></td>' +
                '<td class="pp-td"><button class="pp-button-icon pp-edit-team-btn" data-team-id="' + team.id + '" title="Edit team">✏️</button> <button class="pp-button-icon pp-delete-team-btn" data-team-id="' + team.id + '" title="Delete team">🗑️</button></td>' +
              '</tr>'
            );
            $('#pp-team-selector').append(
              '<option value="' + team.id + '">' + $('<span>').text(team.name + ' (' + team.slug + ')').html() + '</option>'
            );
            $('#pp-team-selector').val(team.id);
            $('#pp-card-data-sources-table').replaceWith(team.data_sources_html);
            $('#pp-card-team-game-list').replaceWith(team.games_html);
            $('#pp-card-roster-sources').replaceWith(team.roster_sources_html);
            $('#pp-card-roster-players').replaceWith(team.players_html);
            if (team.standings_html) { $('#pp-card-standings').replaceWith(team.standings_html); }
            if (team.pages_card_html) { $('#pp-card-team-pages').replaceWith(team.pages_card_html); }
            $('#pp-active-team-id').val(team.id);
            $('#pp-refresh-team-btn').data('team-id', team.id).attr('data-team-id', team.id);
            window.countGameRows && window.countGameRows();
            window.applyGameEditHighlights && window.applyGameEditHighlights();
          } else {
            console.error('Failed to create team:', response.data && response.data.message);
          }
        },
        error: function () { console.error('An error occurred while creating the team.'); }
      });
    });

    //############################################################//
    //               Delete Team                                  //
    //############################################################//

    $(document).on('click', '.pp-delete-team-btn', function () {
      const teamId = $(this).data('team-id');
      const $row = $(this).closest('tr');
      if (!confirm('Delete this team and all its data? This cannot be undone.')) {
        return;
      }
      $.ajax({
        url: ajaxurl,
        type: 'POST',
        data: { action: 'pp_delete_team', team_id: teamId },
        success: function (response) {
          if (response.success) {
            $row.remove();
          } else {
            console.error('Failed to delete team:', response.data && response.data.message);
          }
        },
        error: function () { console.error('An error occurred while deleting the team.'); }
      });
    });

    //############################################################//
    //               Edit Team                                   //
    //############################################################//

    const $editTeamModal = $('#pp-edit-team-modal');
    const $editTeamId    = $('#pp-edit-team-id');
    const $editTeamName  = $('#pp-edit-team-name');
    const $editTeamSlug  = $('#pp-edit-team-slug');

    $(document).on('click', '.pp-edit-team-btn', function () {
      const $row = $(this).closest('tr');
      $editTeamId.val($row.data('team-id'));
      $editTeamName.val($row.data('team-name'));
      $editTeamSlug.val($row.data('team-slug'));
      $editTeamModal.css('display', 'flex');
    });

    $('#pp-edit-team-modal-close, #pp-edit-team-modal-cancel').on('click', function () {
      $editTeamModal.css('display', 'none');
    });

    $editTeamName.on('input', function () {
      $editTeamSlug.val($(this).val().toLowerCase().replace(/[^a-z0-9]+/g, '-').replace(/^-+|-+$/g, ''));
    });

    $('#pp-edit-team-modal-confirm').on('click', function () {
      const teamId = $editTeamId.val();
      const name   = $editTeamName.val().trim();
      const slug   = $editTeamSlug.val().trim();
      if (!name || !slug) return;
      $.ajax({
        url: ajaxurl,
        type: 'POST',
        data: { action: 'pp_update_team', team_id: teamId, name: name, slug: slug },
        success: function (response) {
          if (response.success) {
            $editTeamModal.css('display', 'none');
            const $row = $('#pp-teams-list-table tbody tr[data-team-id="' + teamId + '"]');
            $row.data('team-name', response.data.name).attr('data-team-name', response.data.name);
            $row.data('team-slug', response.data.slug).attr('data-team-slug', response.data.slug);
            $row.find('td').eq(1).text(response.data.name);
            $row.find('td').eq(2).html('<code>' + $('<span>').text(response.data.slug).html() + '</code>');
            $('#pp-team-selector option[value="' + teamId + '"]').text(response.data.name + ' (' + response.data.slug + ')');
          } else {
            alert('Failed to update team: ' + (response.data && response.data.message ? response.data.message : 'Unknown error'));
          }
        },
        error: function () { console.error('An error occurred while updating the team.'); }
      });
    });

    //############################################################//
    //               Team Selector                                //
    //############################################################//

    $('#pp-team-selector').on('change', function () {
      const teamId = parseInt($(this).val(), 10);
      $.ajax({
        url: ajaxurl,
        type: 'POST',
        data: { action: 'pp_switch_active_team', team_id: teamId },
        success: function (response) {
          if (!response.success) {
            console.error('Failed to switch active team.', response);
            return;
          }
          const d = response.data;
          $('#pp-card-data-sources-table').replaceWith(d.data_sources_html);
          $('#pp-card-team-game-list').replaceWith(d.games_html);
          $('#pp-card-roster-sources').replaceWith(d.roster_sources_html);
          $('#pp-card-roster-players').replaceWith(d.players_html);
          if (d.standings_html) { $('#pp-card-standings').replaceWith(d.standings_html); }
          if (d.pages_card_html) { $('#pp-card-team-pages').replaceWith(d.pages_card_html); }
          $('#pp-active-team-id').val(d.team_id);
          $('#pp-refresh-team-btn').data('team-id', d.team_id).attr('data-team-id', d.team_id);
          window.countGameRows && window.countGameRows();
          window.applyGameEditHighlights && window.applyGameEditHighlights();
        },
        error: function () { console.error('Failed to switch active team.'); }
      });
    });

    //############################################################//
    //               Refresh All Sources                         //
    //############################################################//

    $('#pp-refresh-button').on('click', function () {
      const $btn        = $(this);
      const scheduleId  = parseInt($('#pp-active-new-schedule-id').val(), 10) || 0;
      const activeTeamId = parseInt($('#pp-active-team-id').val(), 10) || 0;
      $btn.text('Refreshing...').prop('disabled', true);
      $.ajax({
        url: ajaxurl,
        type: 'POST',
        data: { action: 'pp_refresh_schedule_sources', scope: 'all', schedule_id: scheduleId, active_team_id: activeTeamId },
        success: function (response) {
          if (response.success) {
            const games = response.data.games_debug || [];
            console.group('Puck Press — Games before template render (schedule_id=' + (response.data.debug_schedule_id || scheduleId) + ')');
            console.log('Total games:', games.length);
            console.table(games.map(function (g) {
              return {
                id: g.id,
                team_id: g.team_id,
                game_timestamp: g.game_timestamp,
                game_date_day: g.game_date_day,
                target_team_name: g.target_team_name,
                opponent_team_name: g.opponent_team_name,
                home_or_away: g.home_or_away,
                game_status: g.game_status,
              };
            }));
            console.groupEnd();

            if (response.data.refreshed_game_table_ui) {
              $('#pp-games-table').replaceWith(response.data.refreshed_game_table_ui);
              window.countGameRows && window.countGameRows();
              window.applyGameEditHighlights && window.applyGameEditHighlights();
            }
            if (typeof ppScheduleTemplates !== 'undefined') {
              // Default tab: color picker JS is loaded — restore all templates and
              // run the same visibility logic createColorPickerController relies on.
              if (response.data.refreshed_game_preview_html) {
                $('#pp-game-schedule-preview').html(response.data.refreshed_game_preview_html);
                for (const k of Object.keys(ppScheduleTemplates.scheduleTemplates)) {
                  $('.' + k + '_schedule_container').hide();
                }
                $('.' + ppScheduleTemplates.selected_template + '_schedule_container').show();
              }
              if (response.data.refreshed_slider_preview_html && typeof ppSliderTemplates !== 'undefined') {
                $('#pp-game-slider-preview').html(response.data.refreshed_slider_preview_html);
                for (const k of Object.keys(ppSliderTemplates.sliderTemplates)) {
                  $('.' + k + '_slider_container').hide();
                }
                $('.' + ppSliderTemplates.selected_template + '_slider_container').show();
              }
            } else {
              // Teams tab: color picker JS not loaded — just show the active template.
              if (response.data.refreshed_active_preview_html) {
                $('#pp-game-schedule-preview').html(response.data.refreshed_active_preview_html);
              }
              if (response.data.refreshed_active_slider_html) {
                $('#pp-game-slider-preview').html(response.data.refreshed_active_slider_html);
              }
            }
            gameScheduleInitializers.forEach(function (initFn) {
              if (typeof initFn === 'function') initFn();
            });
            $btn.html('<i>🔄</i> Refresh All Sources').prop('disabled', false);
          } else {
            console.error('Failed to refresh all sources:', response.data && response.data.message);
            $btn.html('<i>🔄</i> Refresh All Sources').prop('disabled', false);
          }
        },
        error: function () {
          console.error('An error occurred while refreshing all sources.');
          $btn.html('<i>🔄</i> Refresh All Sources').prop('disabled', false);
        }
      });
    });

    //############################################################//
    //               Refresh Team                                 //
    //############################################################//

    $('#pp-refresh-team-btn').on('click', function () {
      const $btn = $(this);
      const teamId = parseInt($('#pp-active-team-id').val(), 10) || 0;
      $btn.text('Refreshing...').prop('disabled', true);
      const gamesRefresh = new Promise(function(resolve, reject) {
        refreshGamesTable(
          (r) => { resolve(r); },
          (e) => { reject(e); }
        );
      });
      const rosterRefresh = $.post(ajaxurl, { action: 'pp_refresh_team_roster', team_id: teamId });
      Promise.allSettled([gamesRefresh, rosterRefresh]).then(function(results) {
        $btn.text('Refresh Team').prop('disabled', false);
        const rosterResult = results[1];
        if (rosterResult.status === 'fulfilled') {
          const r = rosterResult.value;
          console.group('[PP Roster Refresh] Roster import log (team_id=' + teamId + ')');
          if (r && r.success && r.data && r.data.results) {
            const importData = r.data.results.import || {};
            const messages = (importData.messages || []).filter(m => typeof m === 'string');
            const errors   = importData.errors || [];
            if (messages.length === 0 && errors.length === 0) {
              console.log('No sources imported (check that a roster source is added and active for this team).');
            }
            messages.forEach(function(m) { console.log(m); });
            errors.forEach(function(e) { console.warn('ERROR [' + (e.source || '') + ']: ' + (e.message || JSON.stringify(e))); });

            const displayData = r.data.results.display;
            console.group('Display rebuild');
            if (Array.isArray(displayData) && displayData.length) {
              displayData.forEach(function(m) { console.log(m); });
            } else if (Array.isArray(displayData) && displayData.length === 0) {
              console.log('Display rebuild: no changes (0 mods applied).');
            } else {
              console.log('Display rebuild data:', displayData);
            }
            console.groupEnd();
          } else {
            console.warn('Roster refresh response:', r);
          }
          console.groupEnd();
        } else {
          console.error('[PP Roster Refresh] Roster AJAX failed:', rosterResult.reason);
        }
      });
    });

    //############################################################//
    //               Archive All Teams Season Modal              //
    //############################################################//

    const $archiveModal = $('#pp-team-archive-modal');

    function resetArchiveModal() {
      $('#pp-team-archive-season option:not([disabled]):first').prop('selected', true);
      $('#pp-team-archive-wipe-stats').prop('checked', false);
      $('#pp-team-archive-modal-confirm').prop('disabled', false).text('Archive Season');
    }

    $('#pp-archive-all-teams-season-btn').on('click', function () {
      resetArchiveModal();
      $archiveModal.css('display', 'flex');
    });

    $('#pp-team-archive-modal-close, #pp-team-archive-modal-cancel').on('click', function () {
      $archiveModal.css('display', 'none');
    });

    $('#pp-team-archive-modal-confirm').on('click', function () {
      const seasonKey = $('#pp-team-archive-season').val();

      if (!seasonKey) {
        alert('Please select a season.');
        return;
      }

      const wipe  = $('#pp-team-archive-wipe-stats').is(':checked');
      const $btn  = $(this);
      $btn.prop('disabled', true).text('Archiving…');

      $.ajax({
        url: ajaxurl,
        type: 'POST',
        data: { action: 'pp_archive_all_teams_season', season_key: seasonKey, label: seasonKey, wipe: wipe ? 1 : 0 },
        success: function (response) {
          if (response.success) {
            const d = response.data || {};
            if (d.reload) {
              window.location.reload();
              return;
            }
            if (d.archives_html) {
              $('#pp-team-archives-list').replaceWith(d.archives_html);
            }
            $('#pp-team-archive-season option[value="' + seasonKey + '"]')
              .prop('disabled', true).text(seasonKey + ' (archived)');
            $archiveModal.css('display', 'none');
          } else {
            const msg = response.data && response.data.message ? response.data.message : 'Archive failed.';
            alert(msg);
            $btn.prop('disabled', false).text('Archive Season');
          }
        },
        error: function () {
          alert('An error occurred while archiving the season.');
          $btn.prop('disabled', false).text('Archive Season');
        }
      });
    });

    $(document).on('click', '.pp-delete-archive-btn', function () {
      if (!confirm('Delete this archive? This cannot be undone.')) return;
      const $btn      = $(this).prop('disabled', true);
      const seasonKey = $btn.data('season-key');
      $.ajax({
        url: ajaxurl,
        type: 'POST',
        data: { action: 'pp_delete_team_archive', season_key: seasonKey },
        success: function (response) {
          if (response.success && response.data.archives_html) {
            $('#pp-team-archives-list').replaceWith(response.data.archives_html);
            $('#pp-team-archive-season option[value="' + seasonKey + '"]')
              .prop('disabled', false).text(seasonKey);
          } else {
            $btn.prop('disabled', false);
          }
        },
        error: function () {
          $btn.prop('disabled', false);
        }
      });
    });

    //############################################################//
    //               Team Data Sources                           //
    //############################################################//

    // Show/hide source-type-specific fields in the add-source modal
    const sourceTypeConfig = {
      achaGameScheduleUrl:   { required: ['#pp-acha-team-id', '#pp-acha-season-id'] },
      usphlGameScheduleUrl:  { required: ['#pp-usphl-team-id', '#pp-usphl-season-id'] },
      csv:                   { required: ['#pp-schedule-fileInput'] },
    };

    const toggleSourceInputs = () => {
      const selectedType = $('#pp-source-type').val();
      const isAcha = selectedType === 'achaGameScheduleUrl';

      // Hide/show the name field — ACHA auto-populates it from the API
      $('#pp-source-name').closest('.pp-form-group').toggle(!isAcha);
      $('#pp-source-name').prop('required', !isAcha).prop('disabled', isAcha);

      // Clear required from all type-specific fields
      Object.values(sourceTypeConfig).forEach(({ required }) => {
        required.forEach(sel => $(sel).prop('required', false));
      });

      // Apply required + show/hide + enable/disable per type
      Object.entries(sourceTypeConfig).forEach(([type, { required }]) => {
        const $group  = $(`.pp-dynamic-source-group-${type}`);
        const visible = type === selectedType;
        $group.toggle(visible);
        $group.find('input, select, textarea').prop('disabled', !visible);
        if (visible) required.forEach(sel => $(sel).prop('required', true));
      });
    };

    $(document).on('change', '#pp-source-type', toggleSourceInputs);

    // Open / close add-source modal
    // Use delegated binding — #pp-add-source-button lives inside #pp-card-data-sources-table
    // which gets replaced via replaceWith() on team switch, destroying direct bindings.
    $(document).on('click', '#pp-add-source-button', function () {
      $('#pp-add-source-form')[0].reset();
      toggleSourceInputs();
      $('#pp-add-source-modal').css('display', 'flex');
    });

    const closeAddSourceModal = () => {
      $('#pp-add-source-modal').css('display', 'none');
      $('#pp-add-source-form')[0].reset();
    };

    $('#pp-modal-close, #pp-cancel-add-source').on('click', closeAddSourceModal);

    $('#pp-add-source-modal').on('click', function (e) {
      if (e.target === this) closeAddSourceModal();
    });

    // Override schedule sources JS — use team-specific AJAX actions instead
    $('#pp-confirm-add-source').off('click').on('click', function () {
      const $form = $('#pp-add-source-form');
      if (!$form[0].checkValidity()) {
        $form[0].reportValidity();
        return;
      }
      const $btn   = $(this);
      const teamId = parseInt($('#pp-active-team-id').val(), 10);

      $btn.prop('disabled', true).text('Adding…');

      PPDataSourceUtils.handleFormSubmit({
        $form: $form,
        action: 'pp_add_team_source',
        fieldExtractors: () => {
          const type = $('#pp-source-type').val();
          const data = {
            name: $('#pp-source-name').val(),
            type,
            active: $('#pp-new-source-active').is(':checked') ? 1 : 0,
            team_id: teamId,
          };
          if (type === 'achaGameScheduleUrl') {
            data.team_id_url     = $('#pp-acha-team-id').val();
            data.season_id       = $('#pp-acha-season-id').val();
            data.auto_discover   = $('#pp-acha-auto-discover').is(':checked') ? 1 : 0;
          } else if (type === 'usphlGameScheduleUrl') {
            data.team_id_url = $('#pp-usphl-team-id').val();
            const seasonId = $('#pp-usphl-season-id').val();
            if (seasonId) data.season_id = seasonId;
          } else if (type === 'csv') {
            const file = $('#pp-schedule-fileInput')[0].files[0];
            if (!file) throw new Error('Please select a CSV file.');
            data.csv = file;
          }
          return data;
        },
        onSuccess: (response) => {
          const type = $('#pp-source-type').val();

          if (type === 'achaGameScheduleUrl' || type === 'usphlGameScheduleUrl') {
            refreshGamesTable().then(() => { location.reload(); });
            return;
          }

          const name   = $('#pp-source-name').val();
          const active = $('#pp-new-source-active').is(':checked');
          const id     = response.data.id;

          let displayValue = '';
          if (type === 'csv') {
            const file = $('#pp-schedule-fileInput')[0].files[0];
            displayValue = file ? file.name : '';
          } else if (type === 'usphlGameScheduleUrl') {
            const sid = $('#pp-usphl-season-id').val();
            displayValue = `Team: ${$('#pp-usphl-team-id').val()}${sid ? ' / Season: ' + sid : ''}`;
          } else {
            displayValue = $('#pp-source-url').val();
          }

          const now = new Date().toLocaleString('en-US', { year: 'numeric', month: 'short', day: '2-digit', hour: '2-digit', minute: '2-digit', hour12: true });
          $('#pp-sources-table tbody #kill-me-please').remove();
          $('#pp-sources-table tbody').append(`
            <tr data-id="${id}">
              <td class="pp-td">${name}</td>
              <td class="pp-td"><span class="pp-tag pp-tag-${type}">${type}</span></td>
              <td class="pp-td">${displayValue}</td>
              <td class="pp-td">${now}</td>
              <td class="pp-td">
                <label class="pp-data-source-toggle-switch">
                  <input type="checkbox" ${active ? 'checked' : ''} data-id="${id}">
                  <span class="pp-slider"></span>
                </label>
                <span style="margin-left:10px;">${active ? 'Active' : 'Inactive'}</span>
              </td>
              <td class="pp-td">
                <button class="pp-button-icon pp-delete-team-source-btn" data-id="${id}">🗑️</button>
              </td>
            </tr>
          `);

          $btn.prop('disabled', false).text('Add Source');
          closeAddSourceModal();

          refreshGamesTable().then(() => { window.countGameRows(); });
        },
        onError: (error) => {
          console.error('Team source add error:', error);
          $btn.prop('disabled', false).text('Add Source');
        },
      });
    });

    // Override toggle to use pp_update_team_source_status
    $(document).off('change.ppScheduleSource', '.pp-data-source-toggle-switch input').on('change.ppTeamSource', '.pp-data-source-toggle-switch input', function () {
      const status   = $(this).prop('checked') ? 'active' : 'inactive';
      const sourceId = $(this).data('id');
      const teamId   = parseInt($('#pp-active-team-id').val(), 10) || 0;
      const $text    = $(this).closest('td').find('span').last();
      const requestData = { action: 'pp_update_team_source_status', source_id: sourceId, team_id: teamId, status };
      console.log('[toggle] → pp_update_team_source_status request:', requestData);
      $.ajax({
        url: ajaxurl,
        method: 'POST',
        data: requestData,
        success: (response) => {
          console.log('[toggle] ← pp_update_team_source_status response:', response);
          if (response.success) {
            if ($text.length) $text.text(status === 'active' ? 'Active' : 'Inactive');
            if (response.data && response.data.games_html) {
              $('#pp-games-table').replaceWith(response.data.games_html);
              if (typeof window.applyGameEditHighlights === 'function') window.applyGameEditHighlights();
            }
            if (typeof window.countGameRows === 'function') window.countGameRows();
          }
        },
        error: (xhr, s, err) => { console.error('[toggle] AJAX error:', s, err, xhr.responseText); },
      });
    });

    // Delete team source
    $(document).on('click', '.pp-delete-team-source-btn', function () {
      if (!confirm('Delete this data source?')) return;
      const sourceId = $(this).data('id');
      const $row = $(this).closest('tr');
      $.ajax({
        url: ajaxurl,
        type: 'POST',
        data: { action: 'pp_delete_team_source', source_id: sourceId },
        success: (response) => {
          if (response.success) {
            $row.remove();
            refreshGamesTable().then(() => { window.countGameRows(); });
          } else {
            console.error('Failed to delete source:', response.data && response.data.message);
          }
        },
        error: () => { console.error('An error occurred while deleting the source.'); },
      });
    });

    //############################################################//
    //               Schedules Section                            //
    //############################################################//

    function applyScheduleView(scheduleId, d) {
      // Update preview cards
      if (d.active_preview_html) $('#pp-game-schedule-preview').html(d.active_preview_html);
      if (d.slider_preview_html && typeof ppSliderTemplates !== 'undefined') {
        $('#pp-game-slider-preview').html(d.slider_preview_html);
        for (const k of Object.keys(ppSliderTemplates.sliderTemplates)) {
          $('.' + k + '_slider_container').hide();
        }
        const activeSlider = d.selected_slider_template || ppSliderTemplates.selected_template;
        if (activeSlider) {
          $('.' + activeSlider + '_slider_container').show();
          ppSliderTemplates.selected_template = activeSlider;
        }
      } else if (d.active_slider_html) {
        $('#pp-game-slider-preview').html(d.active_slider_html);
      }

      // Update schedule color globals and reapply CSS vars for the new schedule.
      if (d.schedule_template_colors && typeof ppScheduleTemplates !== 'undefined') {
        ppScheduleTemplates.scheduleTemplates = d.schedule_template_colors;
        if (d.schedule_color_labels)  ppScheduleTemplates.colorLabels  = d.schedule_color_labels;
        if (d.schedule_font_settings) ppScheduleTemplates.fontSettings = d.schedule_font_settings;
        if (d.schedule_font_labels)   ppScheduleTemplates.fontLabels   = d.schedule_font_labels;
        if (d.selected_template)      ppScheduleTemplates.selected_template = d.selected_template;
        Object.entries(ppScheduleTemplates.scheduleTemplates).forEach(function(entry) {
          Object.entries(entry[1]).forEach(function(c) {
            document.documentElement.style.setProperty('--pp-' + entry[0] + '-' + c[0], c[1]);
          });
        });
        Object.entries(ppScheduleTemplates.fontSettings || {}).forEach(function(entry) {
          Object.entries(entry[1]).forEach(function(f) {
            if (!f[1] || !f[0].endsWith('_font')) return;
            document.documentElement.style.setProperty('--pp-' + entry[0] + '-' + f[0], "'" + f[1] + "', sans-serif");
          });
        });
      }

      // Update slider color globals and reapply CSS vars for the new schedule.
      if (d.slider_template_colors && typeof ppSliderTemplates !== 'undefined') {
        ppSliderTemplates.sliderTemplates = d.slider_template_colors;
        if (d.slider_color_labels)  ppSliderTemplates.colorLabels  = d.slider_color_labels;
        if (d.slider_font_settings) ppSliderTemplates.fontSettings = d.slider_font_settings;
        if (d.slider_font_labels)   ppSliderTemplates.fontLabels   = d.slider_font_labels;
        Object.entries(ppSliderTemplates.sliderTemplates).forEach(function(entry) {
          Object.entries(entry[1]).forEach(function(c) {
            document.documentElement.style.setProperty('--pp-' + entry[0] + '-' + c[0], c[1]);
          });
        });
        Object.entries(ppSliderTemplates.fontSettings || {}).forEach(function(entry) {
          Object.entries(entry[1]).forEach(function(f) {
            if (!f[1] || !f[0].endsWith('_font')) return;
            document.documentElement.style.setProperty('--pp-' + entry[0] + '-' + f[0], "'" + f[1] + "', sans-serif");
          });
        });
      }

      // Update shortcode display
      if (d.shortcode) {
        $('#pp-new-schedule-shortcode').val(d.shortcode).attr('size', d.shortcode.length);
      }

      // Update card subtitle
      $('#pp-schedule-membership-subtitle').text(
        d.is_main ? 'Main schedule — auto-includes all teams.' : 'Teams assigned to this schedule'
      );

      // Update teams table
      const $content = $('#pp-schedule-teams-content');
      if (d.schedule_teams && d.schedule_teams.length > 0) {
        let rows = '';
        d.schedule_teams.forEach(function (team) {
          rows += '<tr data-team-id="' + team.id + '">' +
            '<td class="pp-td">' + $('<span>').text(team.name).html() + '</td>' +
            '<td class="pp-td"><code>' + $('<span>').text(team.slug).html() + '</code></td>';
          if (!d.is_main) {
            rows += '<td class="pp-td"><button class="pp-button-icon pp-remove-team-from-schedule-btn" ' +
              'data-schedule-id="' + scheduleId + '" data-team-id="' + team.id + '">✕</button></td>';
          }
          rows += '</tr>';
        });
        const actionHeader = d.is_main ? '' : '<th class="pp-th">Actions</th>';
        $content.html(
          '<table class="pp-table" id="pp-schedule-teams-table">' +
          '<thead class="pp-thead"><tr><th class="pp-th">Name</th><th class="pp-th">Slug</th>' + actionHeader + '</tr></thead>' +
          '<tbody>' + rows + '</tbody></table>'
        );
      } else {
        const emptyMsg = d.is_main
          ? 'All teams are auto-included in the main schedule.'
          : 'No teams in this schedule yet.';
        $content.html('<p style="color:#888;">' + emptyMsg + '</p>');
      }

      // Update the add-team select and button
      const $toolbar = $('#pp-schedule-membership-toolbar');
      let $addSelect = $('#pp-add-team-to-schedule-select');
      let $addBtn    = $('#pp-add-team-to-schedule-btn');

      if (!d.is_main && d.available_teams && d.available_teams.length > 0) {
        let opts = '<option value="">— Add team —</option>';
        d.available_teams.forEach(function (team) {
          opts += '<option value="' + team.id + '">' + $('<span>').text(team.name).html() + '</option>';
        });
        if (!$addSelect.length) {
          $toolbar.append('<select id="pp-add-team-to-schedule-select" class="pp-select" data-schedule-id="' + scheduleId + '"></select>');
          $addSelect = $('#pp-add-team-to-schedule-select');
        }
        if (!$addBtn.length) {
          $toolbar.append('<button class="pp-button pp-button-primary" id="pp-add-team-to-schedule-btn">+ Add Team</button>');
        }
        $addSelect.attr('data-schedule-id', scheduleId).data('schedule-id', scheduleId).html(opts).show();
        $('#pp-add-team-to-schedule-btn').show();
      } else {
        $addSelect.hide();
        $addBtn.hide();
      }

      // Update slider cal URL field in the modal
      if (typeof d.cal_url !== 'undefined') {
        $('#pp-slider-cal-url').val(d.cal_url);
        if (typeof ppSliderTemplates !== 'undefined') ppSliderTemplates.cal_url = d.cal_url;
      }

      // Update delete footer
      const $deleteFooter = $('#pp-schedule-delete-footer');
      if (d.is_main) {
        $deleteFooter.hide();
      } else {
        $deleteFooter.show().find('.pp-delete-new-schedule-btn').data('schedule-id', scheduleId).attr('data-schedule-id', scheduleId);
      }

      gameScheduleInitializers.forEach(function (fn) { if (typeof fn === 'function') fn(); });
    }

    function refreshScheduleView(scheduleId) {
      $.ajax({
        url: ajaxurl,
        type: 'POST',
        data: { action: 'pp_get_schedule_preview', schedule_id: scheduleId },
        success: function (response) {
          if (!response.success) return;
          applyScheduleView(scheduleId, response.data);
        },
        error: function () { console.error('Failed to load schedule preview.'); },
      });
    }

    $('#pp-schedule-group-selector').on('change', function () {
      const scheduleId = parseInt($(this).val(), 10);
      if (!scheduleId) return;
      $('#pp-active-new-schedule-id').val(scheduleId);
      $.post(ajaxurl, { action: 'pp_set_active_new_schedule_id', schedule_id: scheduleId });
      refreshScheduleView(scheduleId);
    });

    $('#pp-new-schedule-selector').on('change', function () {
      const scheduleId = parseInt($(this).val(), 10);
      $.ajax({
        url: ajaxurl,
        type: 'POST',
        data: { action: 'pp_set_active_new_schedule_id', schedule_id: scheduleId },
        success: function () { refreshScheduleView(scheduleId); },
        error: function () { console.error('Failed to switch active schedule.'); }
      });
    });

    const $addScheduleModal  = $('#pp-add-schedule-modal');
    const $addScheduleName   = $('#pp-new-schedule-name');
    const $addScheduleSlug   = $('#pp-new-schedule-slug');

    $('#pp-add-schedule-btn').on('click', function () {
      $addScheduleName.val('');
      $addScheduleSlug.val('');
      $addScheduleModal.css('display', 'flex');
    });

    $('#pp-add-schedule-modal-close, #pp-add-schedule-modal-cancel').on('click', function () {
      $addScheduleModal.css('display', 'none');
    });

    $addScheduleName.on('input', function () {
      $addScheduleSlug.val($(this).val().toLowerCase().replace(/[^a-z0-9]+/g, '-').replace(/^-+|-+$/g, ''));
    });

    $('#pp-add-schedule-modal-confirm').on('click', function () {
      const name = $addScheduleName.val().trim();
      const slug = $addScheduleSlug.val().trim();
      if (!name || !slug) {
        console.error('Name and slug are required.');
        return;
      }
      $.ajax({
        url: ajaxurl,
        type: 'POST',
        data: { action: 'pp_create_new_schedule', name: name, slug: slug },
        success: function (response) {
          if (response.success) {
            $addScheduleModal.css('display', 'none');
            const sched = response.data;
            $('#pp-schedule-group-selector').append(
              $('<option>', { value: sched.id, text: sched.name })
            );
            $('#pp-schedule-group-selector').val(sched.id).trigger('change');
          } else {
            console.error('Failed to create schedule:', response.data && response.data.message);
          }
        },
        error: function () { console.error('An error occurred while creating the schedule.'); }
      });
    });

    $(document).on('click', '.pp-delete-new-schedule-btn', function () {
      if (!confirm('Delete this schedule? This cannot be undone.')) return;
      const scheduleId = $(this).data('schedule-id');
      $.ajax({
        url: ajaxurl,
        type: 'POST',
        data: { action: 'pp_delete_new_schedule', schedule_id: scheduleId },
        success: function (response) {
          if (response.success) {
            $('#pp-schedule-group-selector option[value="' + scheduleId + '"]').remove();
            const firstId = parseInt($('#pp-schedule-group-selector option:first').val(), 10);
            $('#pp-schedule-group-selector').val(firstId).trigger('change');
          } else {
            alert(response.data && response.data.message ? response.data.message : 'Failed to delete schedule.');
          }
        },
        error: function () { console.error('An error occurred while deleting the schedule.'); }
      });
    });


    $(document).on('click', '#pp-add-team-to-schedule-btn', function () {
      const $select    = $('#pp-add-team-to-schedule-select');
      const teamId     = parseInt($select.val(), 10);
      const scheduleId = parseInt($select.data('schedule-id'), 10);
      if (!teamId) return;
      $.ajax({
        url: ajaxurl,
        type: 'POST',
        data: { action: 'pp_add_team_to_schedule', schedule_id: scheduleId, team_id: teamId },
        success: function (response) {
          if (response.success) {
            refreshScheduleView(scheduleId);
          } else {
            console.error('Failed to add team to schedule:', response.data && response.data.message);
          }
        },
        error: function () { console.error('Error adding team to schedule.'); }
      });
    });

    $(document).on('click', '.pp-remove-team-from-schedule-btn', function () {
      if (!confirm('Remove this team from the schedule?')) return;
      const scheduleId = $(this).data('schedule-id');
      const teamId     = $(this).data('team-id');
      $.ajax({
        url: ajaxurl,
        type: 'POST',
        data: { action: 'pp_remove_team_from_schedule', schedule_id: scheduleId, team_id: teamId },
        success: function (response) {
          if (response.success) {
            refreshScheduleView(scheduleId);
          } else {
            console.error('Failed to remove team from schedule:', response.data && response.data.message);
          }
        },
        error: function () { console.error('Error removing team from schedule.'); }
      });
    });

    //############################################################//
    //               Roster Sources                              //
    //############################################################//

    function resetRosterSourceModal() {
      $('#pp-roster-source-name').val('');
      $('#pp-roster-source-type').val('achaRosterUrl');
      $('#pp-roster-source-url').val('');
      $('#pp-roster-source-include-stats').prop('checked', true);
      $('#pp-roster-usphl-team-id').val('');
      $('#pp-roster-usphl-season-id').val('');
      $('#pp-roster-source-stat-period').val('');
      $('#pp-roster-source-stat-period-other').hide().val('');
      $('#pp-roster-source-active').prop('checked', true);
      toggleRosterSourceInputs();
    }

    function toggleRosterSourceInputs() {
      const selectedType = $('#pp-roster-source-type').val();
      const isAcha = selectedType === 'achaRosterUrl';

      // Hide shared fields that are irrelevant for ACHA
      $('#pp-roster-source-name').closest('.pp-form-group').toggle(!isAcha);
      $('#pp-roster-source-name').prop('disabled', isAcha);
      $('#pp-roster-source-stat-period').closest('.pp-form-group').toggle(!isAcha);
      $('#pp-roster-source-stat-period').prop('disabled', isAcha);
      $('#pp-roster-source-stat-period-other').prop('disabled', isAcha);
      $('#pp-roster-source-season-year').closest('.pp-form-group').toggle(!isAcha);
      $('#pp-roster-source-season-year').prop('disabled', isAcha);

      const types = ['achaRosterUrl', 'usphlRosterUrl', 'csv'];
      types.forEach(function(type) {
        const $group = $('.pp-dynamic-roster-source-group-' + type);
        const isVisible = type === selectedType;
        $group.toggle(isVisible);
        $group.find('input, select, textarea').prop('disabled', !isVisible);
        $group.find('input, select, textarea').prop('required', isVisible);
      });
    }

    $(document).on('click', '#pp-add-roster-source-button', function() {
      $('#pp-add-roster-source-modal').css('display', 'flex');
      toggleRosterSourceInputs();
      $('#pp-roster-source-stat-period').val('');
      $('#pp-roster-source-stat-period-other').hide().val('');
      $('#pp-roster-source-include-stats').prop('checked', true);
    });

    $(document).on('change', '#pp-roster-source-type', toggleRosterSourceInputs);

    $(document).on('change', '#pp-roster-source-stat-period', function() {
      if ($(this).val() === '__other__') {
        $('#pp-roster-source-stat-period-other').show();
      } else {
        $('#pp-roster-source-stat-period-other').hide().val('');
      }
    });

    $(document).on('click', '.pp-cancel-roster-source-modal', function() {
      $('#pp-add-roster-source-modal').css('display', 'none');
      resetRosterSourceModal();
    });

    $(document).on('click', '#pp-confirm-roster-add-source', function() {
      const $btn   = $(this);
      const teamId = parseInt($('#pp-active-team-id').val(), 10) || 0;
      const type   = $('#pp-roster-source-type').val();
      const name   = $('#pp-roster-source-name').val();
      const active = $('#pp-roster-source-active').is(':checked') ? 1 : 0;

      const rawPeriod = $('#pp-roster-source-stat-period').val();
      const statPeriod = rawPeriod === '__other__' ? $('#pp-roster-source-stat-period-other').val().trim() : rawPeriod;

      const seasonYear = $('#pp-roster-source-season-year').val();
      const data = { action: 'pp_add_team_roster_source', team_id: teamId, name: name, type: type, active: active };
      if (statPeriod) data.stat_period = statPeriod;
      if (seasonYear) data.season_year = seasonYear;

      if (type === 'achaRosterUrl') {
        data.team_id_url   = $('#pp-acha-roster-team-id').val();
        data.season_id     = $('#pp-acha-roster-season-id').val();
        data.include_stats = $('#pp-roster-source-include-stats').is(':checked') ? 1 : 0;
        data.auto_discover = $('#pp-acha-roster-auto-discover').is(':checked') ? 1 : 0;
      } else if (type === 'usphlRosterUrl') {
        data.team_id_usphl = $('#pp-roster-usphl-team-id').val();
        data.season_id     = $('#pp-roster-usphl-season-id').val();
      }

      $btn.prop('disabled', true).text('Adding…');

      $.post(ajaxurl, data, function(response) {
        if (response.success) {
          if (type === 'achaRosterUrl') {
            location.reload();
            return;
          }

          const id         = response.data.id;
          const now        = new Date().toLocaleString('en-US', { year: 'numeric', month: 'short', day: '2-digit', hour: '2-digit', minute: '2-digit', hour12: true });
          let displayValue = '';
          if (type === 'usphlRosterUrl') {
            const sid = $('#pp-roster-usphl-season-id').val();
            displayValue = 'Team: ' + $('#pp-roster-usphl-team-id').val() + (sid ? ' / Season: ' + sid : '');
          }
          $('#pp-roster-sources-table tbody').append(
            '<tr data-roster-source-id="' + id + '">' +
            '<td class="pp-td">' + $('<span>').text(name).html() + '</td>' +
            '<td class="pp-td"><span class="pp-tag pp-tag-' + type + '">' + type + '</span></td>' +
            '<td class="pp-td">' + $('<span>').text(displayValue).html() + '</td>' +
            '<td class="pp-td">' + now + '</td>' +
            '<td class="pp-td"><label class="pp-roster-source-toggle-switch"><input type="checkbox"' + (active ? ' checked' : '') + ' data-id="' + id + '" data-team-id="' + teamId + '"><span class="pp-slider"></span></label>' +
            '<span style="margin-left:10px;">' + (active ? 'Active' : 'Inactive') + '</span></td>' +
            '<td class="pp-td"><div class="pp-flex-small-gap"><button class="pp-button-icon pp-delete-roster-source" data-id="' + id + '" data-team-id="' + teamId + '">🗑️</button></div></td>' +
            '</tr>'
          );
          if (response.data.roster_table_html) {
            $(TABLE_SEL).replaceWith(response.data.roster_table_html);
            applyTeamEditHighlights();
          }
          $('#pp-add-roster-source-modal').css('display', 'none');
          resetRosterSourceModal();
        } else {
          alert('Error adding roster source.');
        }
      }).always(function() {
        $btn.prop('disabled', false).text('Add Source');
      });
    });

    $(document).on('click', '#pp-discover-acha-seasons', function() {
      const $btn   = $(this);
      const teamId = parseInt($('#pp-active-team-id').val(), 10) || 0;
      $btn.prop('disabled', true).text('Checking...');
      $.post(ajaxurl, { action: 'pp_run_acha_discovery', team_id: teamId })
        .done(function(res) {
          if (res.success && res.data.log && res.data.log.some(function(e) { return e.discovered.length > 0; })) {
            location.reload();
          } else {
            $('#pp-discover-result').text(res.success ? 'No new seasons found.' : 'Discovery failed. Check the error log.');
            $btn.prop('disabled', false).text('Discover New Seasons');
          }
        })
        .fail(function() {
          $('#pp-discover-result').text('Discovery failed. Check the error log.');
          $btn.prop('disabled', false).text('Discover New Seasons');
        });
    });

    $(document).on('click', '.pp-delete-roster-source', function() {
      if (!confirm('Delete this roster source?')) return;
      const sourceId = $(this).data('id');
      const teamId   = $(this).data('team-id');
      $.post(ajaxurl, { action: 'pp_delete_team_roster_source', source_id: sourceId, team_id: teamId }, function(response) {
        if (response.success) {
          $('tr[data-roster-source-id="' + sourceId + '"]').remove();
          if (response.data && response.data.roster_table_html) {
            $(TABLE_SEL).replaceWith(response.data.roster_table_html);
            applyTeamEditHighlights();
          }
        } else {
          alert('Error deleting roster source.');
        }
      });
    });

    $(document).on('change', '.pp-roster-source-toggle-switch input', function() {
      const status   = $(this).prop('checked') ? 'active' : 'inactive';
      const sourceId = $(this).data('id');
      const teamId   = $(this).data('team-id');
      const $text    = $(this).closest('td').find('span').last();
      $.post(ajaxurl, { action: 'pp_update_team_roster_source_status', source_id: sourceId, team_id: teamId, status: status }, function(response) {
        if (response.success) {
          $text.text(status === 'active' ? 'Active' : 'Inactive');
          if (response.data && response.data.log) {
            console.log('[pp roster source toggle] DB counts after rebuild:', response.data.log);
          }
          if (response.data && response.data.roster_table_html) {
            $(TABLE_SEL).replaceWith(response.data.roster_table_html);
            applyTeamEditHighlights();
          }
        } else {
          alert('Error updating status.');
        }
      });
    });

    //############################################################//
    //               Players                                     //
    //############################################################//

    const getTeamId = () => parseInt($('#pp-active-team-id').val(), 10) || 0;
    const TABLE_SEL = '#pp-team-players-table-wrapper';
    const CARD_SEL  = '#pp-card-roster-players';

    // ── Edit highlights ──────────────────────────────────────────────────────────
    window.applyTeamEditHighlights = function() {
      $(TABLE_SEL + ' tbody tr:not(.pp-row-deleted)').each(function() {
        const $row    = $(this);
        const modId   = $row.attr('data-mod-id');
        let overrides = [];
        try { overrides = JSON.parse($row.attr('data-overrides') || '[]'); } catch(e) {}

        $row.find('td[data-field]').each(function() {
          const $td   = $(this);
          const field = $td.attr('data-field');
          if (overrides.indexOf(field) !== -1) {
            $td.addClass('pp-cell-overridden');
            if (!$td.find('.pp-revert-btn').length) {
              $td.append(`<button class="pp-revert-btn" title="Revert" data-mod-id="${modId}" data-fields="${field}">&#x2715;</button>`);
            }
          } else {
            $td.removeClass('pp-cell-overridden');
            $td.find('.pp-revert-btn').remove();
          }
        });
      });
    };
    applyTeamEditHighlights();

    const dimTeamPlayers = () => $(CARD_SEL + ', .pp-modal').css({ opacity: '0.5', 'pointer-events': 'none' });
    const restoreTeamPlayers = () => $(CARD_SEL + ', .pp-modal').css({ opacity: '1', 'pointer-events': 'auto' });

    function doWithTeamPlayerUpdate(ajaxOptions) {
      dimTeamPlayers();
      const defaults = { url: ajaxurl, method: 'POST' };
      $.ajax(Object.assign({}, defaults, ajaxOptions, {
        success: function(response) {
          if (response.success && response.data && response.data.roster_table_html) {
            $(TABLE_SEL).replaceWith(response.data.roster_table_html);
            applyTeamEditHighlights();
          }
          restoreTeamPlayers();
        },
        error: function() { restoreTeamPlayers(); }
      }));
    }

    // ── Add Player modal ──────────────────────────────────────────────────────────
    $(document).on('click', '#pp-add-player-btn', function() {
      $('#pp-add-player-modal').css('display', 'flex');
    });

    $(document).on('click', '#pp-add-player-modal-close, #pp-cancel-add-player', function() {
      $('#pp-add-player-modal').css('display', 'none');
      const form = document.getElementById('pp-add-player-form');
      if (form) form.reset();
    });

    $(document).on('click', '#pp-confirm-add-player', function() {
      const form = document.getElementById('pp-add-player-form');
      if (form && !form.checkValidity()) { form.reportValidity(); return; }
      const teamId = getTeamId();
      const formData = new FormData();
      formData.append('action',         'pp_add_team_manual_player_and_table');
      formData.append('team_id',        teamId);
      formData.append('name',           $('#pp-new-player-name').val());
      formData.append('number',         $('#pp-new-player-number').val());
      formData.append('pos',            $('#pp-new-player-position').val());
      formData.append('ht',             $('#pp-new-player-height').val());
      formData.append('wt',             $('#pp-new-player-weight').val());
      formData.append('shoots',         $('#pp-new-player-shoots').val());
      formData.append('hometown',       $('#pp-new-player-hometown').val());
      formData.append('last_team',      $('#pp-new-player-last-team').val());
      formData.append('year_in_school', $('#pp-new-player-year').val());
      formData.append('major',          $('#pp-new-player-major').val());
      formData.append('headshot_link',  $('#pp-new-player-headshot-url').val());
      formData.append('hero_image_url', $('#pp-new-player-hero-image-url').val());
      $('#pp-add-player-modal').css('display', 'none');
      const f = document.getElementById('pp-add-player-form');
      if (f) f.reset();
      doWithTeamPlayerUpdate({ data: formData, processData: false, contentType: false });
    });

    // ── Edit Player modal ─────────────────────────────────────────────────────────
    let currentEditPlayerId = null;
    let originalEditValues  = {};

    const closeEditModal = () => {
      $('#pp-edit-player-modal').css('display', 'none');
      currentEditPlayerId = null;
      originalEditValues  = {};
    };

    $(document).on('click', '#pp-edit-player-modal-close, .pp-cancel-edit-player', closeEditModal);

    $(document).on('click', '.pp-edit-team-player-btn', function() {
      const playerId = $(this).data('player-id');
      const teamId   = getTeamId();
      currentEditPlayerId = playerId;
      originalEditValues  = {};
      $('#pp-edit-player-loading').addClass('is-loading');
      $('#pp-edit-player-modal').css('display', 'flex');
      $.ajax({
        url: ajaxurl, method: 'POST',
        data: { action: 'pp_get_team_player_data', player_id: playerId, team_id: teamId },
        success: function(response) {
          $('#pp-edit-player-loading').removeClass('is-loading');
          if (response.success && response.data && response.data.player) {
            const p = response.data.player;
            const posMap    = { 'F': 'forward', 'D': 'defense', 'G': 'goalie' };
            const shootsMap = { 'R': 'right', 'L': 'left' };
            const yearMap   = { 'FR': 'freshman', 'SO': 'sophomore', 'JR': 'junior', 'SR': 'senior', 'GR': 'graduate', 'FRESHMAN': 'freshman', 'SOPHOMORE': 'sophomore', 'JUNIOR': 'junior', 'SENIOR': 'senior' };
            const vals = {
              name:           p.name           || '',
              number:         p.number         || '',
              pos:            posMap[(p.pos||'').toUpperCase()]    || (p.pos||'').toLowerCase(),
              ht:             p.ht             || '',
              wt:             p.wt             || '',
              shoots:         shootsMap[(p.shoots||'').toUpperCase()] || (p.shoots||'').toLowerCase(),
              hometown:       p.hometown       || '',
              last_team:      p.last_team      || '',
              year_in_school: yearMap[(p.year_in_school||'').toUpperCase()] || (p.year_in_school||'').toLowerCase(),
              major:          p.major          || '',
              headshot_link:  p.headshot_link  || '',
              hero_image_url: p.hero_image_url || '',
            };
            $('#pp-edit-player-name').val(vals.name);
            $('#pp-edit-player-number').val(vals.number);
            $('#pp-edit-player-pos').val(vals.pos);
            $('#pp-edit-player-ht').val(vals.ht);
            $('#pp-edit-player-wt').val(vals.wt);
            $('#pp-edit-player-shoots').val(vals.shoots);
            $('#pp-edit-player-hometown').val(vals.hometown);
            $('#pp-edit-player-last-team').val(vals.last_team);
            $('#pp-edit-player-year').val(vals.year_in_school);
            $('#pp-edit-player-major').val(vals.major);
            $('#pp-edit-player-headshot').val(vals.headshot_link);
            $('#pp-edit-player-hero-image-url').val(vals.hero_image_url);
            originalEditValues = vals;
          }
        },
        error: function() { $('#pp-edit-player-loading').removeClass('is-loading'); }
      });
    });

    $(document).on('click', '#pp-confirm-edit-player', function() {
      const newVals = {
        name:           $('#pp-edit-player-name').val(),
        number:         $('#pp-edit-player-number').val(),
        pos:            $('#pp-edit-player-pos').val(),
        ht:             $('#pp-edit-player-ht').val(),
        wt:             $('#pp-edit-player-wt').val(),
        shoots:         $('#pp-edit-player-shoots').val(),
        hometown:       $('#pp-edit-player-hometown').val(),
        last_team:      $('#pp-edit-player-last-team').val(),
        year_in_school: $('#pp-edit-player-year').val(),
        major:          $('#pp-edit-player-major').val(),
        headshot_link:  $('#pp-edit-player-headshot').val(),
        hero_image_url: $('#pp-edit-player-hero-image-url').val(),
      };
      const changed = {};
      Object.keys(newVals).forEach(function(k) {
        if (newVals[k] !== (originalEditValues[k] || '')) { changed[k] = newVals[k]; }
      });
      if (!Object.keys(changed).length) { alert('No changes detected.'); return; }
      const editData = { edit_action: 'update', fields: Object.assign({ external_id: currentEditPlayerId }, changed) };
      closeEditModal();
      const teamId   = getTeamId();
      const formData = new FormData();
      formData.append('action', 'pp_update_team_player_edit');
      formData.append('team_id', teamId);
      formData.append('edit_data', JSON.stringify(editData));
      doWithTeamPlayerUpdate({ data: formData, processData: false, contentType: false });
    });

    // ── Revert field ──────────────────────────────────────────────────────────────
    $(document).on('click', '.pp-revert-btn', function(e) {
      e.stopPropagation();
      const modId  = $(this).data('mod-id');
      const fields = String($(this).data('fields')).split(',');
      doWithTeamPlayerUpdate({ data: { action: 'pp_revert_team_player_field', mod_id: modId, fields: fields, team_id: getTeamId() } });
    });

    // ── Restore deleted player ────────────────────────────────────────────────────
    $(document).on('click', '.pp-restore-team-player-btn', function() {
      const deleteModId = $(this).data('delete-mod-id');
      doWithTeamPlayerUpdate({ data: { action: 'pp_restore_team_player', delete_mod_id: deleteModId, team_id: getTeamId() } });
    });

    // ── Delete player ─────────────────────────────────────────────────────────────
    $(document).on('click', '.pp-delete-team-player-btn', function() {
      if (!confirm('Delete this player?')) return;
      const playerId   = $(this).data('player-id');
      const sourceType = $(this).data('source-type');
      const teamId     = getTeamId();
      if (sourceType === 'manual') {
        doWithTeamPlayerUpdate({ data: { action: 'pp_delete_team_manual_player', player_id: playerId, team_id: teamId } });
      } else {
        doWithTeamPlayerUpdate({ data: { action: 'pp_delete_team_player', player_id: playerId, team_id: teamId } });
      }
    });

    // ── Reset all edits ───────────────────────────────────────────────────────────
    $(document).on('click', '#pp-reset-all-team-player-edits', function() {
      if (!confirm('Reset all player edits? This removes every override, deletion, and manual player.')) return;
      doWithTeamPlayerUpdate({ data: { action: 'pp_reset_all_team_player_edits', team_id: getTeamId() } });
    });

    // ── Bulk Edit modal ───────────────────────────────────────────────────────────
    const BULK_FIELDS = {
      hero_image_url: { label: 'Hero Image URL', type: 'url', placeholder: 'https://...' },
      headshot_link:  { label: 'Headshot URL',   type: 'url', placeholder: 'https://...' },
    };
    const POS_GROUPS_TEAM = {
      forwards: ['f', 'c', 'lw', 'rw'],
      defense:  ['d', 'ld', 'rd'],
      goalies:  ['g'],
    };

    function getTeamPosGroup(pos) {
      const lower = (pos || '').toLowerCase();
      for (const [group, positions] of Object.entries(POS_GROUPS_TEAM)) {
        if (positions.includes(lower)) return group;
      }
      return 'other';
    }

    let bulkFieldsPopulated = false;
    $(document).on('click', '#pp-bulk-edit-team-players-btn', function() {
      if (!bulkFieldsPopulated) {
        const $sel = $('#pp-bulk-roster-field');
        $sel.empty();
        Object.entries(BULK_FIELDS).forEach(([key, cfg]) => {
          $sel.append(`<option value="${key}">${cfg.label}</option>`);
        });
        $sel.append('<option value="__remove_edits__">Remove All Edits</option>');
        $sel.trigger('change');
        bulkFieldsPopulated = true;
      }
      $('#pp-bulk-roster-error').hide();
      $('#pp-bulk-roster-value').val('');
      buildTeamBulkList();
      applyTeamBulkFilters();
      $('#pp-bulk-roster-modal').css('display', 'flex');
    });

    $('#pp-bulk-roster-field').on('change', function() {
      const isRemove = this.value === '__remove_edits__';
      $('#pp-bulk-roster-value').toggle(!isRemove);
      $('.pp-bulk-edit-clear-hint').toggle(!isRemove);
      if (!isRemove) {
        const cfg = BULK_FIELDS[this.value];
        if (cfg) $('#pp-bulk-roster-value').attr('type', cfg.type).attr('placeholder', cfg.placeholder);
      }
    });

    function buildTeamBulkList() {
      const $list = $('#pp-bulk-roster-list');
      $list.empty();
      $(TABLE_SEL + ' tbody tr:not(.pp-row-deleted)').each(function() {
        const $tr  = $(this);
        const id   = $tr.attr('data-player-id') || '';
        const pos  = $tr.attr('data-pos') || '';
        const name = $tr.attr('data-name') || '';
        $list.append(
          `<li class="pp-bulk-edit-row" data-player-id="${id}" data-pos="${pos.toLowerCase()}" data-pos-group="${getTeamPosGroup(pos)}" data-name="${name.toLowerCase()}">
            <label class="pp-bulk-edit-row__label">
              <input type="checkbox" class="pp-bulk-edit-cb" checked>
              <span class="pp-bulk-pos-badge">${pos || '—'}</span>
              <span class="pp-bulk-edit-row__name">${name}</span>
            </label>
          </li>`
        );
      });
      updateTeamBulkCount();
    }

    function applyTeamBulkFilters() {
      const posFilter  = $('#pp-bulk-roster-pos').val();
      const nameFilter = ($('#pp-bulk-roster-name-filter').val() || '').toLowerCase().trim();
      $('#pp-bulk-roster-list .pp-bulk-edit-row').each(function() {
        const $row = $(this);
        const vis  = (posFilter === 'all' || $row.data('pos-group') === posFilter) &&
                     (!nameFilter || String($row.data('name') || '').includes(nameFilter));
        $row.toggleClass('is-hidden', !vis);
      });
      updateTeamBulkCount();
    }

    $(document).on('input change', '#pp-bulk-roster-pos, #pp-bulk-roster-name-filter', applyTeamBulkFilters);
    $(document).on('click', '#pp-bulk-roster-select-all', function() {
      $('#pp-bulk-roster-list .pp-bulk-edit-row:not(.is-hidden) .pp-bulk-edit-cb').prop('checked', true);
      updateTeamBulkCount();
    });
    $(document).on('click', '#pp-bulk-roster-deselect-all', function() {
      $('#pp-bulk-roster-list .pp-bulk-edit-row:not(.is-hidden) .pp-bulk-edit-cb').prop('checked', false);
      updateTeamBulkCount();
    });
    $(document).on('change', '#pp-bulk-roster-list .pp-bulk-edit-cb', updateTeamBulkCount);

    function updateTeamBulkCount() {
      const n = $('#pp-bulk-roster-list .pp-bulk-edit-cb:checked').length;
      $('#pp-bulk-roster-count').text(n + ' player' + (n !== 1 ? 's' : '') + ' selected');
      $('#pp-bulk-roster-apply').text('Apply to ' + n + ' Player' + (n !== 1 ? 's' : ''));
    }

    $(document).on('click', '#pp-bulk-roster-close, #pp-bulk-roster-cancel', function() {
      $('#pp-bulk-roster-modal').css('display', 'none');
    });
    $(document).on('click', '#pp-bulk-roster-modal', function(e) {
      if (e.target === this) $('#pp-bulk-roster-modal').css('display', 'none');
    });

    $(document).on('click', '#pp-bulk-roster-apply', function() {
      $('#pp-bulk-roster-error').hide();
      const ids = [];
      $('#pp-bulk-roster-list .pp-bulk-edit-cb:checked').each(function() {
        ids.push($(this).closest('.pp-bulk-edit-row').data('player-id'));
      });
      if (!ids.length) { $('#pp-bulk-roster-error').text('Select at least one player.').show(); return; }
      const field = $('#pp-bulk-roster-field').val();
      const nonce = (typeof ppTeamPlayers !== 'undefined') ? ppTeamPlayers.nonce : '';
      if (field === '__remove_edits__') {
        if (!confirm('Remove all edits from ' + ids.length + ' player(s)?')) return;
        $('#pp-bulk-roster-modal').css('display', 'none');
        doWithTeamPlayerUpdate({ data: { action: 'pp_bulk_revert_team_player_edits', nonce: nonce, player_ids: JSON.stringify(ids), team_id: getTeamId() } });
        return;
      }
      const value = $('#pp-bulk-roster-value').val().trim();
      if (value && !value.startsWith('http')) { $('#pp-bulk-roster-error').text('Please enter a valid URL starting with http.').show(); return; }
      if (!value && !confirm('Clear "' + (BULK_FIELDS[field] ? BULK_FIELDS[field].label : field) + '" on ' + ids.length + ' player(s)?')) return;
      $('#pp-bulk-roster-modal').css('display', 'none');
      doWithTeamPlayerUpdate({ data: { action: 'pp_bulk_update_team_player_field', nonce: nonce, field: field, value: value, player_ids: JSON.stringify(ids), team_id: getTeamId() } });
    });

    // ── Hero image browse ─────────────────────────────────────────────────────────
    $(document).on('click', '.pp-hero-image-browse-btn', function(e) {
      e.preventDefault();
      const targetSelector = $(this).data('target');
      const frame = wp.media({ title: 'Select Image', button: { text: 'Use this image' }, multiple: false, library: { type: 'image' } });
      frame.on('select', function() {
        const att = frame.state().get('selection').first().toJSON();
        $(targetSelector).val(att.url).trigger('input');
      });
      frame.open();
    });

    //############################################################//
    //                   Advanced Dropdown                        //
    //############################################################//

    $(document).on('click', '#pp-fix-team-databases-btn', function () {
      $('#pp-advancedDropdown').css('display', 'none');

      if ( !confirm('This will repair all team database tables without deleting any data. Proceed?') ) {
        return;
      }

      const $btn = $(this);
      $btn.text('Fixing...').prop('disabled', true);

      $.post(ajaxurl, { action: 'pp_fix_roster_databases' }, function(response) {
        if (response.success) {
          alert('Fix complete:\n\n' + response.data.log.join('\n'));
          location.reload();
        } else {
          alert('Error: ' + (response.data && response.data.message ? response.data.message : 'Unknown error'));
          $btn.text('Fix Database Tables').prop('disabled', false);
        }
      }).fail(function() {
        alert('Server error. Check the debug log.');
        $btn.text('Fix Database Tables').prop('disabled', false);
      });
    });

    //############################################################//
    //               Edit / Delete / Restore Game                 //
    //############################################################//

    const $editGameModal   = $('#pp-edit-game-modal');
    const $editGameLoading = $('#pp-edit-game-loading');
    let   editGameId       = null;

    function openEditGameModal() {
      $editGameModal.css('display', 'flex');
    }

    function closeEditGameModal() {
      $editGameModal.css('display', 'none');
      editGameId = null;
    }

    function replaceGamesTable(html) {
      $('#pp-games-table').replaceWith(html);
      if (typeof window.countGameRows === 'function') window.countGameRows();
      if (typeof window.applyGameEditHighlights === 'function') window.applyGameEditHighlights();
    }

    // Open edit modal and load game data
    $(document).on('click', '.pp-edit-game-btn', function () {
      const gameId  = $(this).data('game-id');
      const teamId  = parseInt($('#pp-active-team-id').val(), 10) || 0;
      editGameId    = gameId;

      openEditGameModal();
      $editGameLoading.show();

      $.post(ajaxurl, { action: 'pp_get_game_data', game_id: gameId, team_id: teamId }, function (response) {
        $editGameLoading.hide();
        if (!response.success) {
          alert('Failed to load game: ' + (response.data?.message || 'Unknown error'));
          closeEditGameModal();
          return;
        }
        const g = response.data.game;
        if (g.game_timestamp) {
          const d = new Date(g.game_timestamp * 1000);
          $('#pp-edit-game-date').val(d.toISOString().slice(0, 10));
        }
        $('#pp-edit-game-time').val(g.game_time || '');
        $('#pp-edit-home-or-away').val(g.home_or_away || '');
        $('#pp-edit-game-status').val(g.game_status || '');
        $('#pp-edit-target-score').val(g.target_score !== null ? g.target_score : '');
        $('#pp-edit-opponent-score').val(g.opponent_score !== null ? g.opponent_score : '');
        $('#pp-edit-venue').val(g.venue || '');
        $('#pp-promo-header').val(g.promo_header || '');
        $('#pp-promo-text').val(g.promo_text || '');
        $('#pp-promo-img-url').val(g.promo_img_url || '');
        $('#pp-promo-ticket-link').val(g.promo_ticket_link || '');
        $('#pp-post-link').val(g.post_link || '');
      }).fail(function () {
        $editGameLoading.hide();
        alert('Server error loading game data.');
        closeEditGameModal();
      });
    });

    // Close edit modal
    $('#pp-edit-game-modal-close, #pp-cancel-edit-game').on('click', closeEditGameModal);
    $editGameModal.on('mousedown', function (e) {
      if (e.target === $editGameModal[0]) closeEditGameModal();
    });

    // Save game edit
    $('#pp-confirm-edit-game').on('click', function () {
      const teamId = parseInt($('#pp-active-team-id').val(), 10) || 0;
      if (!editGameId || !teamId) return;

      const $btn = $(this);
      $btn.prop('disabled', true).text('Saving…');

      $.post(ajaxurl, {
        action:          'pp_save_game_edit',
        game_id:         editGameId,
        team_id:         teamId,
        game_date:       $('#pp-edit-game-date').val(),
        game_time:       $('#pp-edit-game-time').val(),
        home_or_away:    $('#pp-edit-home-or-away').val(),
        game_status:     $('#pp-edit-game-status').val(),
        target_score:    $('#pp-edit-target-score').val(),
        opponent_score:  $('#pp-edit-opponent-score').val(),
        venue:           $('#pp-edit-venue').val(),
        promo_header:    $('#pp-promo-header').val(),
        promo_text:      $('#pp-promo-text').val(),
        promo_img_url:   $('#pp-promo-img-url').val(),
        promo_ticket_link: $('#pp-promo-ticket-link').val(),
        post_link:       $('#pp-post-link').val(),
      }, function (response) {
        $btn.prop('disabled', false).text('Save Edit');
        if (response.success) {
          replaceGamesTable(response.data.games_table_html);
          closeEditGameModal();
        } else {
          alert('Save failed: ' + (response.data?.message || 'Unknown error'));
        }
      }).fail(function () {
        $btn.prop('disabled', false).text('Save Edit');
        alert('Server error saving game.');
      });
    });

    // Delete game
    $(document).on('click', '.pp-delete-game-btn', function () {
      const gameId   = $(this).data('game-id');
      const teamId   = parseInt($('#pp-active-team-id').val(), 10) || 0;
      if (!confirm('Delete this game?')) return;

      $.post(ajaxurl, { action: 'pp_delete_game', game_id: gameId, team_id: teamId }, function (response) {
        if (response.success) {
          replaceGamesTable(response.data.games_table_html);
        } else {
          alert('Delete failed: ' + (response.data?.message || 'Unknown error'));
        }
      }).fail(function () {
        alert('Server error deleting game.');
      });
    });

    // Restore deleted game
    $(document).on('click', '.pp-restore-game-button', function () {
      const deleteModId = $(this).data('delete-mod-id');
      const teamId      = parseInt($('#pp-active-team-id').val(), 10) || 0;

      $.post(ajaxurl, { action: 'pp_restore_game', delete_mod_id: deleteModId, team_id: teamId }, function (response) {
        if (response.success) {
          replaceGamesTable(response.data.games_table_html);
        } else {
          alert('Restore failed: ' + (response.data?.message || 'Unknown error'));
        }
      }).fail(function () {
        alert('Server error restoring game.');
      });
    });

    // Revert individual game field
    $(document).on('click', '.pp-revert-game-field-btn', function(e) {
      e.stopPropagation();
      const modId  = $(this).data('mod-id');
      const fields = String($(this).data('fields')).split(',');
      const teamId = parseInt($('#pp-active-team-id').val(), 10) || 0;

      $.post(ajaxurl, { action: 'pp_revert_game_field', mod_id: modId, fields: fields, team_id: teamId }, function(response) {
        if (response.success) {
          replaceGamesTable(response.data.games_table_html);
        } else {
          alert('Revert failed: ' + (response.data?.message || 'Unknown error'));
        }
      }).fail(function() {
        alert('Server error reverting field.');
      });
    });

    //############################################################//
    //               Generate / Delete Team Pages                //
    //############################################################//

    $(document).on('click', '#pp-generate-team-pages-btn', function () {
      const $btn           = $(this);
      const teamId         = parseInt($('#pp-active-team-id').val(), 10) || 0;
      const maxWidth       = $('#pp-divi-max-width').val() || '1080px';
      const padding        = $('#pp-divi-padding').val() || '30px 0px';
      const headerColor     = $('#pp-divi-header-color').val() || '';
      const headerFontSize  = $('#pp-divi-header-font-size').val() || '1.4rem';
      const headerFont      = $('#pp-divi-header-font').val() || '';
      const headerTextColor = $('#pp-divi-header-text-color').val() || '#ffffff';
      const schoolUrl       = $('#pp-divi-school-url').val() || '';
      $btn.prop('disabled', true).text('Generating…');
      $.ajax({
        url: ajaxurl,
        type: 'POST',
        data: { action: 'pp_generate_team_pages', team_id: teamId, max_width: maxWidth, padding: padding, header_color: headerColor, header_font_size: headerFontSize, header_font: headerFont, header_text_color: headerTextColor, school_url: schoolUrl },
        success: function (response) {
          if (response.success) {
            $('#pp-card-team-pages').replaceWith(response.data.pages_card_html);
          } else {
            alert('Failed to generate pages: ' + (response.data && response.data.message ? response.data.message : 'Unknown error'));
            $btn.prop('disabled', false).text('Generate Pages');
          }
        },
        error: function () {
          console.error('Server error generating pages.');
          $btn.prop('disabled', false).text('Generate Pages');
        }
      });
    });

    $(document).on('click', '#pp-delete-team-pages-btn', function () {
      if (!confirm('Permanently delete all generated pages for this team? This cannot be undone.')) return;
      const $btn   = $(this);
      const teamId = parseInt($('#pp-active-team-id').val(), 10) || 0;
      $btn.prop('disabled', true).text('Deleting…');
      $.ajax({
        url: ajaxurl,
        type: 'POST',
        data: { action: 'pp_delete_team_pages', team_id: teamId },
        success: function (response) {
          if (response.success) {
            $('#pp-card-team-pages').replaceWith(response.data.pages_card_html);
          } else {
            console.error('Failed to delete pages:', response.data && response.data.message);
            $btn.prop('disabled', false).text('Delete Pages');
          }
        },
        error: function () {
          console.error('Server error deleting pages.');
          $btn.prop('disabled', false).text('Delete Pages');
        }
      });
    });

  });
})(jQuery);
