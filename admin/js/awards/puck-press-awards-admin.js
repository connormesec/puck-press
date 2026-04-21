(function ($) {
  $(document).ready(function () {
  var cfg = window.ppAwardsAdmin || {};

  // ── Year filter ──────────────────────────────────────────────────────────
  $('#pp-award-year-filter').on('change', function () {
    var url = new URL(window.location.href);
    url.searchParams.set('award_year', $(this).val());
    window.location.href = url.toString();
  });

  // ── Group filter ─────────────────────────────────────────────────────────
  $('#pp-award-group-filter').on('change', function () {
    var url = new URL(window.location.href);
    url.searchParams.set('award_group', $(this).val());
    window.location.href = url.toString();
  });

  // ── Icon type toggle (add modal) ────────────────────────────────────────
  $('input[name="pp-new-award-icon-type"]').on('change', function () {
    var isImage = $(this).val() === 'image';
    $('.pp-icon-emoji-group').toggle(!isImage);
    $('.pp-icon-image-group').toggle(isImage);
  });

  // ── Icon type toggle (edit modal) ───────────────────────────────────────
  $('input[name="pp-edit-award-icon-type"]').on('change', function () {
    var isImage = $(this).val() === 'image';
    $('.pp-edit-icon-emoji-group').toggle(!isImage);
    $('.pp-edit-icon-image-group').toggle(isImage);
  });

  // ── WP Media picker for icon image (add modal) ─────────────────────────
  $('#pp-new-award-icon-image-btn').on('click', function (e) {
    e.preventDefault();
    var frame = wp.media({ multiple: false });
    frame.on('select', function () {
      var url = frame.state().get('selection').first().toJSON().url;
      $('#pp-new-award-icon-image-url').val(url);
      $('#pp-new-award-icon-image-preview').attr('src', url).show();
    });
    frame.open();
  });

  // ── WP Media picker for icon image (edit modal) ────────────────────────
  $('#pp-edit-award-icon-image-btn').on('click', function (e) {
    e.preventDefault();
    var frame = wp.media({ multiple: false });
    frame.on('select', function () {
      var url = frame.state().get('selection').first().toJSON().url;
      $('#pp-edit-award-icon-image-url').val(url);
      $('#pp-edit-award-icon-image-preview').attr('src', url).show();
    });
    frame.open();
  });

  // ── Parent name Select2 (tags mode) ────────────────────────────────────
  function initParentSelect($el) {
    $.ajax({
      url: cfg.ajaxUrl,
      data: { action: 'pp_get_parent_names', nonce: cfg.nonce },
      success: function (res) {
        var opts = (res.data && res.data.parents) || [];
        $el.empty();
        $el.append('<option value=""></option>');
        opts.forEach(function (p) {
          $el.append('<option value="' + $('<span>').text(p).html() + '">' + $('<span>').text(p).html() + '</option>');
        });
        $el.select2({
          tags: true,
          allowClear: true,
          placeholder: 'e.g. All-Conference',
          width: '100%'
        });
      }
    });
  }

  // ── Add Award modal: mode toggle helpers ──────────────────────────────
  function setAddAwardMode(mode) {
    if (mode === 'copy') {
      $('#pp-new-award-form-fields').hide();
      $('#pp-new-award-copy-mode').show();
      $('#pp-award-mode-new').css({ background: '#f6f7f7', color: '#444' });
      $('#pp-award-mode-copy').css({ background: '#0073aa', color: '#fff' });
    } else {
      $('#pp-new-award-copy-mode').hide();
      $('#pp-new-award-form-fields').show();
      $('#pp-award-mode-new').css({ background: '#0073aa', color: '#fff' });
      $('#pp-award-mode-copy').css({ background: '#f6f7f7', color: '#444' });
    }
    $('#pp-add-award-error').hide();
  }

  $('#pp-award-mode-new').on('click', function () { setAddAwardMode('new'); });
  $('#pp-award-mode-copy').on('click', function () {
    setAddAwardMode('copy');
    loadCopyAwardSelect();
  });

  function loadCopyAwardSelect() {
    $.ajax({
      url: cfg.ajaxUrl,
      data: { action: 'pp_get_award_name_templates', nonce: cfg.nonce },
      success: function (res) {
        var templates = (res.data && res.data.templates) || [];
        var $sel = $('#pp-new-award-copy-select');
        $sel.empty().append('<option value=""></option>');
        templates.forEach(function (t) {
          var label = t.parent_name ? t.name + ' (' + t.parent_name + ')' : t.name;
          $sel.append('<option value="' + $('<span>').text(t.name + '||' + (t.parent_name || '') + '||' + t.icon_type + '||' + t.icon_value + '||' + t.sort_order).html() + '">' + $('<span>').text(label).html() + '</option>');
        });
        if ($sel.data('select2')) { $sel.select2('destroy'); }
        $sel.select2({
          placeholder: 'Select an award...',
          allowClear: true,
          width: '100%',
          dropdownParent: $('#pp-add-award-modal')
        });
      }
    });
  }

  $('#pp-new-award-copy-select').on('select2:select', function (e) {
    var raw = e.params.data.id.split('||');
    var name      = raw[0] || '';
    var parent    = raw[1] || '';
    var iconType  = raw[2] || 'emoji';
    var iconValue = raw[3] || '🏅';
    var sortOrder = raw[4] || '0';

    $('#pp-copy-award-name').val(name);
    $('#pp-copy-award-parent').val(parent);
    $('#pp-copy-icon-type').val(iconType);
    $('#pp-copy-icon-value').val(iconValue);
    $('#pp-copy-sort-order').val(sortOrder);

    // Preview
    var $icon = $('#pp-copy-preview-icon');
    if (iconType === 'image' && iconValue) {
      $icon.html('<img src="' + $('<span>').text(iconValue).html() + '" style="width:1.5rem;height:1.5rem;object-fit:contain;vertical-align:middle;">');
    } else {
      $icon.text(iconValue || '🏅');
    }
    $('#pp-copy-preview-name').text(name);
    $('#pp-copy-preview-parent').text(parent ? '(' + parent + ')' : '');
    $('#pp-copy-award-preview').css('display', 'flex');
  });

  $('#pp-new-award-copy-select').on('select2:clear', function () {
    $('#pp-copy-award-name, #pp-copy-award-parent, #pp-copy-icon-type, #pp-copy-icon-value, #pp-copy-sort-order').val('');
    $('#pp-copy-award-preview').hide();
  });

  // ── Open Add Award Modal ───────────────────────────────────────────────
  $('#pp-add-award-btn').on('click', function () {
    // Reset to "New Award" mode
    setAddAwardMode('new');
    $('#pp-new-award-year').val('');
    $('#pp-new-award-name').val('');
    $('#pp-new-award-shortcode-label').val('');
    $('input[name="pp-new-award-icon-type"][value="emoji"]').prop('checked', true).trigger('change');
    $('#pp-new-award-icon-emoji').val('🏅');
    $('#pp-new-award-icon-image-url').val('');
    $('#pp-new-award-icon-image-preview').hide();
    $('#pp-new-award-sort-order').val('0');
    // Reset copy mode fields
    $('#pp-copy-award-year').val('');
    $('#pp-copy-award-name, #pp-copy-award-parent, #pp-copy-icon-type, #pp-copy-icon-value, #pp-copy-sort-order').val('');
    $('#pp-copy-award-preview').hide();
    if ($('#pp-new-award-copy-select').data('select2')) {
      $('#pp-new-award-copy-select').val(null).trigger('change');
    }
    $('#pp-add-award-error').hide();
    initParentSelect($('#pp-new-award-parent'));
    $('#pp-add-award-modal').css('display', 'flex');
  });

  $('#pp-add-award-modal-close, #pp-add-award-modal-cancel').on('click', function () {
    $('#pp-add-award-modal').css('display', 'none');
  });

  // ── Submit Add Award ───────────────────────────────────────────────────
  $('#pp-add-award-modal-confirm').on('click', function () {
    var isCopyMode = $('#pp-new-award-copy-mode').is(':visible');
    var postData = { action: 'pp_create_award', nonce: cfg.nonce };

    if (isCopyMode) {
      postData.name       = $('#pp-copy-award-name').val();
      postData.year       = $('#pp-copy-award-year').val();
      postData.parent_name = $('#pp-copy-award-parent').val() || '';
      postData.icon_type  = $('#pp-copy-icon-type').val() || 'emoji';
      postData.icon_value = $('#pp-copy-icon-value').val() || '🏅';
      postData.sort_order = $('#pp-copy-sort-order').val() || '0';
    } else {
      var iconType = $('input[name="pp-new-award-icon-type"]:checked').val();
      postData.name       = $('#pp-new-award-name').val();
      postData.year       = $('#pp-new-award-year').val();
      postData.parent_name = $('#pp-new-award-parent').val() || '';
      postData.icon_type  = iconType;
      postData.icon_value = iconType === 'image' ? $('#pp-new-award-icon-image-url').val() : $('#pp-new-award-icon-emoji').val();
      postData.sort_order = $('#pp-new-award-sort-order').val();
    }

    $.post(cfg.ajaxUrl, postData, function (res) {
      if (res.success) {
        window.location.reload();
      } else {
        $('#pp-add-award-error').text(res.data.message).show();
      }
    });
  });

  // ── Open Edit Award Modal ──────────────────────────────────────────────
  $(document).on('click', '.pp-edit-award-btn', function () {
    var $btn = $(this);
    $('#pp-edit-award-id').val($btn.data('award-id'));
    $('#pp-edit-award-slug').val($btn.data('slug'));
    $('#pp-edit-award-year').val($btn.data('year'));
    $('#pp-edit-award-name').val($btn.data('name'));
$('#pp-edit-award-sort-order').val($btn.data('sort-order'));

    var iconType = $btn.data('icon-type') || 'emoji';
    $('input[name="pp-edit-award-icon-type"][value="' + iconType + '"]').prop('checked', true).trigger('change');
    if (iconType === 'image') {
      $('#pp-edit-award-icon-image-url').val($btn.data('icon-value'));
      $('#pp-edit-award-icon-image-preview').attr('src', $btn.data('icon-value')).show();
    } else {
      $('#pp-edit-award-icon-emoji').val($btn.data('icon-value') || '🏅');
    }

    initParentSelect($('#pp-edit-award-parent'));
    setTimeout(function () {
      $('#pp-edit-award-parent').val($btn.data('parent-name') || '').trigger('change');
    }, 300);

    $('#pp-edit-award-error').hide();
    $('#pp-edit-award-modal').css('display', 'flex');
  });

  $('#pp-edit-award-modal-close, #pp-edit-award-modal-cancel').on('click', function () {
    $('#pp-edit-award-modal').css('display', 'none');
  });

  // ── Submit Edit Award ──────────────────────────────────────────────────
  $('#pp-edit-award-modal-confirm').on('click', function () {
    var iconType = $('input[name="pp-edit-award-icon-type"]:checked').val();
    var iconValue = iconType === 'image' ? $('#pp-edit-award-icon-image-url').val() : $('#pp-edit-award-icon-emoji').val();

    $.post(cfg.ajaxUrl, {
      action: 'pp_update_award',
      nonce: cfg.nonce,
      id: $('#pp-edit-award-id').val(),
      name: $('#pp-edit-award-name').val(),
      year: $('#pp-edit-award-year').val(),
      parent_name: $('#pp-edit-award-parent').val() || '',
      icon_type: iconType,
      icon_value: iconValue,
      sort_order: $('#pp-edit-award-sort-order').val()
    }, function (res) {
      if (res.success) {
        window.location.reload();
      } else {
        $('#pp-edit-award-error').text(res.data.message).show();
      }
    });
  });

  // ── Delete Award ───────────────────────────────────────────────────────
  $(document).on('click', '.pp-delete-award-btn', function () {
    if (!confirm('Delete this award and all its player entries?')) return;
    var id = $(this).data('award-id');
    $.post(cfg.ajaxUrl, {
      action: 'pp_delete_award',
      nonce: cfg.nonce,
      id: id
    }, function (res) {
      if (res.success) {
        window.location.reload();
      } else {
        alert(res.data.message || 'Failed to delete.');
      }
    });
  });

  // ── Remove Player from Award ───────────────────────────────────────────
  $(document).on('click', '.pp-remove-award-player-btn', function () {
    var $btn = $(this);
    var id = $btn.data('id');
    $.post(cfg.ajaxUrl, {
      action: 'pp_remove_award_player',
      nonce: cfg.nonce,
      id: id
    }, function (res) {
      if (res.success) {
        $btn.closest('tr').fadeOut(200, function () { $(this).remove(); });
      }
    });
  });

  // ── Open Add Player Modal ──────────────────────────────────────────────
  $(document).on('click', '.pp-add-award-player-btn', function () {
    var awardId = $(this).data('award-id');
    $('#pp-awp-award-id').val(awardId);
    $('#pp-awp-db-mode').show();
    $('#pp-awp-external-mode').hide();
    $('#pp-awp-db-details').hide();
    $('#pp-add-player-error').hide();
    // Reset hidden fields
    $('#pp-awp-db-player-id, #pp-awp-db-team-id, #pp-awp-db-headshot-url, #pp-awp-db-logo-url, #pp-awp-db-pos, #pp-awp-db-name-val, #pp-awp-db-team-name-val').val('');
    $('#pp-awp-db-position').val('');
    $('#pp-awp-db-logo-override-url').val('');
    $('#pp-awp-db-logo-override-preview').hide();
    $('#pp-awp-db-logo-override-clear').hide();
    // Reset external fields
    $('#pp-awp-ext-name, #pp-awp-ext-team').val('');
    $('#pp-awp-ext-position').val('F');
    $('#pp-awp-ext-headshot-url').val('');
    $('#pp-awp-ext-headshot-preview').hide();
    $('#pp-awp-ext-headshot-clear').hide();
    $('#pp-awp-ext-logo-url').val('');
    $('#pp-awp-ext-logo-preview').hide();

    // Reset Select2
    if ($('#pp-awp-player-search').data('select2')) {
      $('#pp-awp-player-search').val(null).trigger('change');
    }

    $('#pp-add-award-player-modal').css('display', 'flex');
  });

  $('#pp-add-award-player-modal-close, #pp-add-award-player-modal-cancel').on('click', function () {
    $('#pp-add-award-player-modal').css('display', 'none');
  });

  // ── Submit Add Player ──────────────────────────────────────────────────
  $('#pp-add-award-player-modal-confirm').on('click', function () {
    var isExternal = $('#pp-awp-external-mode').is(':visible');
    var data = {
      action: 'pp_add_award_player',
      nonce: cfg.nonce,
      award_id: $('#pp-awp-award-id').val()
    };

    if (isExternal) {
      data.is_external = 1;
      data.player_name = $('#pp-awp-ext-name').val();
      data.team_name = $('#pp-awp-ext-team').val();
      data.position = $('#pp-awp-ext-position').val();
      data.headshot_url = $('#pp-awp-ext-headshot-url').val();
      data.team_logo_url = $('#pp-awp-ext-logo-url').val();
    } else {
      data.is_external = 0;
      data.player_id = $('#pp-awp-db-player-id').val();
      data.team_id = $('#pp-awp-db-team-id').val();
      data.player_name = $('#pp-awp-db-name-val').val();
      data.team_name = $('#pp-awp-db-team-name-val').val();
      data.position = $('#pp-awp-db-position').val() || $('#pp-awp-db-pos').val();
      data.headshot_url = $('#pp-awp-db-headshot-url').val();
      var logoOverride = $('#pp-awp-db-logo-override-url').val();
      data.team_logo_url = logoOverride || $('#pp-awp-db-logo-url').val();
    }

    $.post(cfg.ajaxUrl, data, function (res) {
      if (res.success) {
        window.location.reload();
      } else {
        $('#pp-add-player-error').text(res.data.message).show();
      }
    });
  });

  // ── Toggle Award Visibility ────────────────────────────────────────────
  $(document).on('click', '.pp-toggle-award-visibility-btn', function () {
    var $btn = $(this);
    var id = $btn.data('award-id');
    var currentlyVisible = parseInt($btn.data('visible'), 10);
    var newValue = currentlyVisible ? 0 : 1;

    $.post(cfg.ajaxUrl, {
      action: 'pp_toggle_award_visibility',
      nonce: cfg.nonce,
      id: id,
      show_in_shortcode: newValue
    }, function (res) {
      if (res.success) {
        window.location.reload();
      }
    });
  });

  // ── Open Bulk Add Team Modal ───────────────────────────────────────────
  $(document).on('click', '.pp-bulk-add-team-btn', function () {
    var awardId = $(this).data('award-id');
    $('#pp-bulk-team-award-id').val(awardId);
    $('#pp-bulk-add-team-error').hide();
    $('#pp-bulk-add-team-result').hide();

    if ($('#pp-bulk-team-select').data('select2')) {
      $('#pp-bulk-team-select').val(null).trigger('change').select2('destroy');
    }

    $.ajax({
      url: cfg.ajaxUrl,
      data: { action: 'pp_get_teams_for_awards', nonce: cfg.nonce },
      success: function (res) {
        var teams = (res.data && res.data.teams) || [];
        var $sel = $('#pp-bulk-team-select');
        $sel.empty().append('<option value=""></option>');
        teams.forEach(function (t) {
          $sel.append('<option value="' + t.id + '">' + $('<span>').text(t.text).html() + '</option>');
        });
        $sel.select2({
          placeholder: 'Select a team...',
          allowClear: true,
          width: '100%',
          dropdownParent: $('#pp-bulk-add-team-modal')
        });
      }
    });

    $('#pp-bulk-add-team-modal').css('display', 'flex');
  });

  $('#pp-bulk-add-team-modal-close, #pp-bulk-add-team-modal-cancel').on('click', function () {
    $('#pp-bulk-add-team-modal').css('display', 'none');
  });

  // ── Submit Bulk Add Team ───────────────────────────────────────────────
  $('#pp-bulk-add-team-modal-confirm').on('click', function () {
    var teamId = $('#pp-bulk-team-select').val();
    var awardId = $('#pp-bulk-team-award-id').val();

    if (!teamId) {
      $('#pp-bulk-add-team-error').text('Please select a team.').show();
      return;
    }

    var $btn = $(this);
    $btn.prop('disabled', true).text('Adding...');
    $('#pp-bulk-add-team-error').hide();
    $('#pp-bulk-add-team-result').hide();

    $.post(cfg.ajaxUrl, {
      action: 'pp_bulk_add_team_to_award',
      nonce: cfg.nonce,
      award_id: awardId,
      team_id: teamId
    }, function (res) {
      if (res.success) {
        var d = res.data;
        $('#pp-bulk-add-team-result')
          .text('Added ' + d.added + ' players. ' + (d.skipped > 0 ? d.skipped + ' already on award.' : ''))
          .show();
        setTimeout(function () { window.location.reload(); }, 1200);
      } else {
        $('#pp-bulk-add-team-error').text(res.data.message).show();
        $btn.prop('disabled', false).text('Add All Players');
      }
    });
  });

  // ── Bulk Add External Players (CSV) ────────────────────────────────────

  var bulkExtParsed = [];

  function parseCsv(text) {
    var rows = [];
    var lines = text.replace(/\r\n/g, '\n').replace(/\r/g, '\n').split('\n');
    lines.forEach(function (line) {
      if (line.trim() === '') return;
      var fields = [];
      var cur = '';
      var inQuotes = false;
      for (var i = 0; i < line.length; i++) {
        var ch = line[i];
        if (ch === '"') {
          if (inQuotes && line[i + 1] === '"') { cur += '"'; i++; }
          else { inQuotes = !inQuotes; }
        } else if (ch === ',' && !inQuotes) {
          fields.push(cur.trim());
          cur = '';
        } else {
          cur += ch;
        }
      }
      fields.push(cur.trim());
      rows.push(fields);
    });
    return rows;
  }

  $('#pp-bulk-ext-download-template').on('click', function (e) {
    e.preventDefault();
    var csv = 'Name,Team,Position,Headshot URL,Logo URL\nJohn Doe,DePaul,F,,\nJane Smith,Loyola,G,,\n';
    var blob = new Blob([csv], { type: 'text/csv' });
    var url = URL.createObjectURL(blob);
    var a = document.createElement('a');
    a.href = url;
    a.download = 'award-players-template.csv';
    a.click();
    URL.revokeObjectURL(url);
  });

  $(document).on('click', '.pp-bulk-add-external-btn', function () {
    var awardId = $(this).data('award-id');
    $('#pp-bulk-ext-award-id').val(awardId);
    $('#pp-bulk-ext-file').val('');
    $('#pp-bulk-ext-preview').hide();
    $('#pp-bulk-ext-preview-table tbody').empty();
    $('#pp-bulk-ext-preview-more').hide();
    $('#pp-bulk-ext-error').hide();
    $('#pp-bulk-ext-result').hide();
    $('#pp-bulk-external-modal-confirm').prop('disabled', true);
    bulkExtParsed = [];
    $('#pp-bulk-external-modal').css('display', 'flex');
  });

  $('#pp-bulk-external-modal-close, #pp-bulk-external-modal-cancel').on('click', function () {
    $('#pp-bulk-external-modal').css('display', 'none');
  });

  $('#pp-bulk-ext-file').on('change', function () {
    var file = this.files[0];
    $('#pp-bulk-ext-error').hide();
    $('#pp-bulk-ext-preview').hide();
    $('#pp-bulk-external-modal-confirm').prop('disabled', true);
    bulkExtParsed = [];

    if (!file) return;

    var reader = new FileReader();
    reader.onload = function (e) {
      var rows = parseCsv(e.target.result);
      if (rows.length < 2) {
        $('#pp-bulk-ext-error').text('CSV must have a header row and at least one data row.').show();
        return;
      }
      // Skip header row (index 0)
      var dataRows = rows.slice(1);
      var valid = [];
      var hasError = false;
      dataRows.forEach(function (r, idx) {
        var name = r[0] || '';
        var team = r[1] || '';
        if (!name || !team) {
          $('#pp-bulk-ext-error').text('Row ' + (idx + 2) + ' is missing Name or Team and will be skipped.').show();
          hasError = true;
          return;
        }
        valid.push({
          player_name: name,
          team_name: team,
          position: r[2] || '',
          headshot_url: r[3] || '',
          team_logo_url: r[4] || ''
        });
      });

      if (valid.length === 0) {
        if (!hasError) $('#pp-bulk-ext-error').text('No valid rows found in CSV.').show();
        return;
      }

      bulkExtParsed = valid;

      // Populate preview table (first 5 rows)
      var $tbody = $('#pp-bulk-ext-preview-table tbody').empty();
      var previewRows = valid.slice(0, 5);
      previewRows.forEach(function (p) {
        $tbody.append(
          '<tr>' +
          '<td>' + $('<span>').text(p.player_name).html() + '</td>' +
          '<td>' + $('<span>').text(p.team_name).html() + '</td>' +
          '<td>' + $('<span>').text(p.position).html() + '</td>' +
          '<td style="max-width:120px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">' + $('<span>').text(p.headshot_url).html() + '</td>' +
          '<td style="max-width:120px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">' + $('<span>').text(p.team_logo_url).html() + '</td>' +
          '</tr>'
        );
      });

      if (valid.length > 5) {
        $('#pp-bulk-ext-preview-more').text('…and ' + (valid.length - 5) + ' more row(s) not shown.').show();
      }

      $('#pp-bulk-ext-preview').show();
      $('#pp-bulk-external-modal-confirm').prop('disabled', false);
    };
    reader.readAsText(file);
  });

  $('#pp-bulk-external-modal-confirm').on('click', function () {
    if (!bulkExtParsed.length) return;

    var $btn = $(this);
    $btn.prop('disabled', true).text('Adding...');
    $('#pp-bulk-ext-error').hide();
    $('#pp-bulk-ext-result').hide();

    $.post(cfg.ajaxUrl, {
      action: 'pp_bulk_add_external_players',
      nonce: cfg.nonce,
      award_id: $('#pp-bulk-ext-award-id').val(),
      players: JSON.stringify(bulkExtParsed)
    }, function (res) {
      $btn.prop('disabled', false).text('Add Players');
      if (res.success) {
        var d = res.data;
        var msg = 'Added ' + d.added + ' player(s).';
        if (d.skipped > 0) msg += ' ' + d.skipped + ' already on award (skipped).';
        if (d.errors && d.errors.length) msg += ' ' + d.errors.length + ' row(s) had errors.';
        $('#pp-bulk-ext-result').text(msg).show();
        setTimeout(function () { window.location.reload(); }, 1500);
      } else {
        $('#pp-bulk-ext-error').text((res.data && res.data.message) || 'An error occurred.').show();
      }
    });
  });

  });
})(jQuery);
