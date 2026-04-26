(function ($) {
  jQuery(document).ready(function ($) {
  'use strict';

  var ajaxUrl = ppArchives.ajaxUrl;
  var nonce   = ppArchives.nonce;

  // ── ACHA Modal ──────────────────────────────────────────────────────────────

  var $achaModal = $('#pp-import-acha-modal');
  var achaSeasons = [];

  $('#pp-import-acha-btn').on('click', function () {
    $achaModal.css('display', 'flex');
    loadAchaSeasons();
  });

  $achaModal.on('click', '.pp-modal-close', function () {
    $achaModal.css('display', 'none');
    resetAchaModal();
  });

  function resetAchaModal() {
    $('#pp-acha-season').val('').prop('disabled', true);
    $('#pp-acha-api-team').html('<option value="">Select a season first</option>').prop('disabled', true);
    $('#pp-acha-season-key').val('');
    $('#pp-acha-team-mapping').hide().empty();
    $('#pp-acha-import-status').empty();
    $('#pp-acha-import-confirm').prop('disabled', false).text('Import');
  }

  function loadAchaSeasons() {
    var $sel = $('#pp-acha-season');
    $sel.html('<option value="">Loading...</option>');

    $.post(ajaxUrl, { action: 'pp_get_acha_seasons', nonce: nonce }, function (res) {
      if (!res.success) {
        $sel.html('<option value="">Failed to load</option>');
        return;
      }
      achaSeasons = res.data.seasons || [];
      var archivedKeys = res.data.archived_keys || [];
      var html = '<option value="">-- Select a season --</option>';

      var regular = achaSeasons.filter(function (s) { return s.type === 'regular'; });
      var playoff = achaSeasons.filter(function (s) { return s.type === 'playoff'; });

      if (regular.length) {
        html += '<optgroup label="Regular Seasons">';
        regular.forEach(function (s) {
          var disabled = archivedKeys.indexOf(s.season_key) >= 0 ? ' disabled' : '';
          var badge = disabled ? ' (archived)' : '';
          html += '<option value="' + s.id + '" data-key="' + s.season_key + '" data-name="' + escAttr(s.name) + '"' + disabled + '>' + escHtml(s.name) + badge + '</option>';
        });
        html += '</optgroup>';
      }
      if (playoff.length) {
        html += '<optgroup label="Playoff Seasons">';
        playoff.forEach(function (s) {
          var disabled = archivedKeys.indexOf(s.season_key) >= 0 ? ' disabled' : '';
          var badge = disabled ? ' (archived)' : '';
          html += '<option value="' + s.id + '" data-key="' + s.season_key + '" data-name="' + escAttr(s.name) + '"' + disabled + '>' + escHtml(s.name) + badge + '</option>';
        });
        html += '</optgroup>';
      }

      $sel.html(html).prop('disabled', false);
    });
  }

  $('#pp-acha-season').on('change', function () {
    var seasonId = $(this).val();
    var $opt = $(this).find(':selected');
    var seasonKey = $opt.data('key') || '';
    $('#pp-acha-season-key').val(seasonKey);
    checkAchaAppendHint(seasonKey);

    var $apiTeam = $('#pp-acha-api-team');
    if (!seasonId) {
      $apiTeam.html('<option value="">Select a season first</option>').prop('disabled', true);
      return;
    }

    $apiTeam.html('<option value="">Loading teams...</option>').prop('disabled', true);
    $.post(ajaxUrl, { action: 'pp_get_acha_teams_for_season', nonce: nonce, season_id: seasonId }, function (res) {
      if (!res.success) {
        $apiTeam.html('<option value="">Failed to load teams</option>');
        return;
      }
      var teams = res.data.teams || [];
      var lastApiTeam = localStorage.getItem('pp_last_acha_api_team') || '';
      var html = '<option value="">-- Select API team --</option>';
      teams.forEach(function (t) {
        var sel = (String(t.id) === lastApiTeam) ? ' selected' : '';
        html += '<option value="' + t.id + '" data-name="' + escAttr(t.name) + '" data-division="' + t.division_id + '"' + sel + '>' + escHtml(t.name) + (t.nickname ? ' (' + escHtml(t.nickname) + ')' : '') + '</option>';
      });
      $apiTeam.html(html).prop('disabled', false);
      if (lastApiTeam && $apiTeam.val()) {
        $apiTeam.trigger('change');
      }
    });
  });

  $('#pp-acha-api-team').on('change', function () {
    var val = $(this).val();
    if (val) {
      localStorage.setItem('pp_last_acha_api_team', val);
    }
    var $opt = $(this).find(':selected');
    var apiName = $opt.data('name') || '';
    var localName = $('#pp-acha-team option:selected').text();
    var $mapping = $('#pp-acha-team-mapping');
    if (apiName && localName && localName !== '-- Select a team --') {
      $mapping.html('Importing as: <strong>' + escHtml(localName) + '</strong> &rarr; API: <strong>' + escHtml(apiName) + '</strong>').show();
    } else {
      $mapping.hide().empty();
    }
  });

  $('#pp-acha-import-confirm').on('click', function () {
    var teamId = $('#pp-acha-team').val();
    var seasonId = $('#pp-acha-season').val();
    var apiTeamId = $('#pp-acha-api-team').val();
    var seasonKey = $('#pp-acha-season-key').val();
    var $apiOpt = $('#pp-acha-api-team option:selected');
    var divisionId = $apiOpt.data('division') || '-1';
    var $seasonOpt = $('#pp-acha-season option:selected');
    var apiLabel = $seasonOpt.data('name') || '';

    if (!teamId || !seasonId || !apiTeamId || !seasonKey) {
      alert('Please fill in all fields.');
      return;
    }

    var $btn = $(this).prop('disabled', true).text('Importing…');
    var $status = $('#pp-acha-import-status').html('Fetching data from ACHA API...');

    $.post(ajaxUrl, {
      action: 'pp_import_acha_archive',
      nonce: nonce,
      team_id: teamId,
      api_team_id: apiTeamId,
      season_id: seasonId,
      division_id: divisionId,
      season_key: seasonKey,
      label: seasonKey,
      api_label: apiLabel,
      schedule: $('#pp-acha-import-schedule').is(':checked') ? 1 : 0,
      roster_and_stats: $('#pp-acha-import-roster').is(':checked') ? 1 : 0,
      append: $('#pp-acha-import-append').is(':checked') ? 1 : 0
    }, function (res) {
      $btn.prop('disabled', false).text('Import');
      if (res.success) {
        var d = res.data || {};
        var msg = d.message || 'Import complete.';
        if (d.errors && d.errors.length) {
          msg += '\n\nWarnings:\n' + d.errors.join('\n');
        }
        $status.html('<span style="color:green;">' + escHtml(msg) + '</span>');
        if (d.archives_html) {
          $('#pp-team-archives-list').replaceWith($(d.archives_html).find('#pp-team-archives-list').addBack('#pp-team-archives-list'));
        }
      } else {
        var errMsg = res.data && res.data.message ? res.data.message : 'Import failed.';
        $status.html('<span style="color:red;">' + escHtml(errMsg) + '</span>');
      }
    }).fail(function () {
      $btn.prop('disabled', false).text('Import');
      $status.html('<span style="color:red;">Request failed. Check server logs.</span>');
    });
  });

  // ── USPHL Modal ─────────────────────────────────────────────────────────────

  var $usphlModal = $('#pp-import-usphl-modal');

  $('#pp-import-usphl-btn').on('click', function () {
    $usphlModal.css('display', 'flex');
  });

  $usphlModal.on('click', '.pp-modal-close', function () {
    $usphlModal.css('display', 'none');
    $('#pp-usphl-import-status').empty();
    $('#pp-usphl-import-confirm').prop('disabled', false).text('Import');
  });

  $('#pp-usphl-import-confirm').on('click', function () {
    var teamId = $('#pp-usphl-team').val();
    var apiTeamId = $('#pp-usphl-api-team-id').val();
    var seasonId = $('#pp-usphl-season-id').val();
    var seasonKey = $('#pp-usphl-season-key').val();

    if (!teamId || !apiTeamId || !seasonId || !seasonKey) {
      alert('Please fill in all fields.');
      return;
    }

    var $btn = $(this).prop('disabled', true).text('Importing…');
    var $status = $('#pp-usphl-import-status').html('Fetching data from USPHL API...');

    $.post(ajaxUrl, {
      action: 'pp_import_usphl_archive',
      nonce: nonce,
      team_id: teamId,
      api_team_id: apiTeamId,
      season_id: seasonId,
      season_key: seasonKey,
      label: seasonKey,
      schedule: $('#pp-usphl-import-schedule').is(':checked') ? 1 : 0,
      roster_and_stats: $('#pp-usphl-import-roster').is(':checked') ? 1 : 0,
      append: $('#pp-usphl-import-append').is(':checked') ? 1 : 0
    }, function (res) {
      $btn.prop('disabled', false).text('Import');
      if (res.success) {
        var d = res.data || {};
        var msg = d.message || 'Import complete.';
        if (d.errors && d.errors.length) {
          msg += '\n\nWarnings:\n' + d.errors.join('\n');
        }
        $status.html('<span style="color:green;">' + escHtml(msg) + '</span>');
        if (d.archives_html) {
          $('#pp-team-archives-list').replaceWith($(d.archives_html).find('#pp-team-archives-list').addBack('#pp-team-archives-list'));
        }
      } else {
        var errMsg = res.data && res.data.message ? res.data.message : 'Import failed.';
        $status.html('<span style="color:red;">' + escHtml(errMsg) + '</span>');
      }
    }).fail(function () {
      $btn.prop('disabled', false).text('Import');
      $status.html('<span style="color:red;">Request failed. Check server logs.</span>');
    });
  });

  // ── Refresh All Archives ─────────────────────────────────────────────────────

  $('#pp-refresh-all-archives-btn').on('click', function () {
    if (!confirm('This will delete and re-import ALL API-imported archives from their original sources. This may take a while. Continue?')) return;

    var $btn = $(this).prop('disabled', true).text('Refreshing…');
    console.group('[PP Archives] Refresh All');
    console.time('[PP Archives] Total refresh time');
    console.log('Starting full archive refresh...');

    $.post(ajaxUrl, { action: 'pp_refresh_all_archives', nonce: nonce }, function (res) {
      $btn.prop('disabled', false).text('Refresh All');
      if (res.success) {
        var d = res.data || {};
        console.log('Refresh complete:', d.message);
        console.log('Refreshed:', d.refreshed, 'imports');
        if (d.log && d.log.length) {
          d.log.forEach(function (entry) { console.log('  ' + entry); });
        }
        if (d.errors && d.errors.length) {
          console.warn('Errors:', d.errors);
        }
        console.timeEnd('[PP Archives] Total refresh time');
        console.groupEnd();
        alert(d.message + (d.errors && d.errors.length ? '\n\nErrors:\n' + d.errors.join('\n') : ''));
        if (d.archives_html) {
          $('#pp-team-archives-list').replaceWith($(d.archives_html).find('#pp-team-archives-list').addBack('#pp-team-archives-list'));
        }
      } else {
        console.error('Refresh failed:', res.data);
        console.timeEnd('[PP Archives] Total refresh time');
        console.groupEnd();
        alert(res.data && res.data.message ? res.data.message : 'Refresh failed.');
      }
    }).fail(function (xhr) {
      $btn.prop('disabled', false).text('Refresh All');
      console.error('Request failed:', xhr.status, xhr.statusText);
      console.log('Response body:', xhr.responseText ? xhr.responseText.substring(0, 500) : '(empty)');
      console.timeEnd('[PP Archives] Total refresh time');
      console.groupEnd();
      alert('Request failed (' + xhr.status + '). The operation may have timed out — check console and server logs.');
    });
  });

  // ── Delete (full season) ────────────────────────────────────────────────────

  $(document).on('click', '.pp-delete-archive-btn', function () {
    if (!confirm('Delete this entire archive? This cannot be undone.')) return;
    var $btn = $(this).prop('disabled', true);
    var seasonKey = $btn.data('season-key');
    $.post(ajaxUrl, { action: 'pp_delete_team_archive', season_key: seasonKey }, function (res) {
      if (res.success && res.data.archives_html) {
        $('#pp-team-archives-list').replaceWith($(res.data.archives_html).find('#pp-team-archives-list').addBack('#pp-team-archives-list'));
      } else {
        $btn.prop('disabled', false);
      }
    }).fail(function () {
      $btn.prop('disabled', false);
    });
  });

  // ── Delete per-team ─────────────────────────────────────────────────────────

  $(document).on('click', '.pp-delete-archive-team-btn', function () {
    var seasonKey = $(this).data('season-key');
    var teamName = $(this).data('team-name');
    if (!confirm('Delete archived data for "' + teamName + '" in season ' + seasonKey + '?')) return;
    var $btn = $(this).prop('disabled', true);
    $.post(ajaxUrl, { action: 'pp_delete_archive_for_team', nonce: nonce, season_key: seasonKey, team_name: teamName }, function (res) {
      if (res.success && res.data.archives_html) {
        $('#pp-team-archives-list').replaceWith($(res.data.archives_html).find('#pp-team-archives-list').addBack('#pp-team-archives-list'));
      } else {
        $btn.prop('disabled', false);
      }
    }).fail(function () {
      $btn.prop('disabled', false);
    });
  });

  // ── Rename ──────────────────────────────────────────────────────────────────

  $(document).on('click', '.pp-archive-rename-btn', function () {
    var seasonKey = $(this).data('season-key');
    var $label = $('.pp-archive-label[data-season-key="' + seasonKey + '"]');
    var current = $label.text().trim();
    var newLabel = prompt('Rename season:', current);
    if (newLabel === null || newLabel.trim() === '' || newLabel.trim() === current) return;

    $.post(ajaxUrl, { action: 'pp_rename_archive', nonce: nonce, season_key: seasonKey, label: newLabel.trim() }, function (res) {
      if (res.success && res.data.archives_html) {
        $('#pp-team-archives-list').replaceWith($(res.data.archives_html).find('#pp-team-archives-list').addBack('#pp-team-archives-list'));
      } else {
        alert(res.data && res.data.message ? res.data.message : 'Rename failed.');
      }
    }).fail(function (xhr) {
      alert('Rename request failed: ' + xhr.status + ' ' + xhr.statusText);
    });
  });

  $(document).on('click', '.pp-archive-reset-label-btn', function () {
    var seasonKey = $(this).data('season-key');
    var apiLabel = $(this).data('api-label');
    if (!apiLabel) return;

    $.post(ajaxUrl, { action: 'pp_rename_archive', nonce: nonce, season_key: seasonKey, label: apiLabel }, function (res) {
      if (res.success && res.data.archives_html) {
        $('#pp-team-archives-list').replaceWith($(res.data.archives_html).find('#pp-team-archives-list').addBack('#pp-team-archives-list'));
      }
    });
  });

  // ── Append hint ─────────────────────────────────────────────────────────────

  var cachedArchivedKeys = [];

  function checkAchaAppendHint(seasonKey) {
    var $hint = $('#pp-acha-append-hint');
    if (!seasonKey || cachedArchivedKeys.indexOf(seasonKey) < 0) {
      $hint.hide().empty();
      return;
    }
    $hint.html('An archive for <strong>' + escHtml(seasonKey) + '</strong> already exists. Check "Append" to add data to it.').show();
  }

  // Cache archived keys when seasons load.
  $(document).ajaxComplete(function (e, xhr, settings) {
    if (settings && settings.data && typeof settings.data === 'string' && settings.data.indexOf('pp_get_acha_seasons') >= 0) {
      try {
        var res = JSON.parse(xhr.responseText);
        if (res.success && res.data) {
          cachedArchivedKeys = res.data.archived_keys || [];
        }
      } catch (ex) {}
    }
  });

  $('#pp-acha-season-key').on('input change', function () {
    checkAchaAppendHint($(this).val());
  });

  // ── Helpers ─────────────────────────────────────────────────────────────────

  function escHtml(str) {
    var div = document.createElement('div');
    div.appendChild(document.createTextNode(str));
    return div.innerHTML;
  }

  function escAttr(str) {
    return String(str).replace(/&/g, '&amp;').replace(/"/g, '&quot;').replace(/'/g, '&#39;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
  }

  });
})(jQuery);
