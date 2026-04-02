(function ($) {
  $(document).ready(function () {
  var cfg = window.ppAwardsAdmin || {};

  // ── Select2 AJAX autocomplete for player search ────────────────────────
  $('#pp-awp-player-search').select2({
    ajax: {
      url: cfg.ajaxUrl,
      dataType: 'json',
      delay: 300,
      data: function (params) {
        return {
          action: 'pp_search_award_players',
          q: params.term,
          nonce: cfg.nonce
        };
      },
      processResults: function (data) {
        return { results: (data.data && data.data.results) || [] };
      },
      cache: true
    },
    minimumInputLength: 2,
    placeholder: 'Type player name...',
    allowClear: true,
    width: '100%',
    dropdownParent: $('#pp-add-award-player-modal')
  });

  // ── On player select ───────────────────────────────────────────────────
  $('#pp-awp-player-search').on('select2:select', function (e) {
    var d = e.params.data;
    $('#pp-awp-db-player-id').val(d.player_id);
    $('#pp-awp-db-team-id').val(d.team_id);
    $('#pp-awp-db-name-val').val(d.name);
    $('#pp-awp-db-team-name-val').val(d.team_name);
    $('#pp-awp-db-pos').val(d.pos);
    $('#pp-awp-db-headshot-url').val(d.headshot_url);
    $('#pp-awp-db-logo-url').val(d.team_logo_url);

    $('#pp-awp-db-player-name').text(d.name);
    $('#pp-awp-db-team-name').text(d.team_name + ' (' + d.pos + ')');

    if (d.headshot_url) {
      $('#pp-awp-db-headshot').attr('src', d.headshot_url).show();
    } else {
      $('#pp-awp-db-headshot').hide();
    }

    if (d.team_logo_url) {
      $('#pp-awp-db-logo').attr('src', d.team_logo_url).show();
    } else {
      $('#pp-awp-db-logo').hide();
    }

    $('#pp-awp-db-position').val('');
    $('#pp-awp-db-details').show();
  });

  $('#pp-awp-player-search').on('select2:clear', function () {
    $('#pp-awp-db-details').hide();
    $('#pp-awp-db-player-id, #pp-awp-db-team-id, #pp-awp-db-headshot-url, #pp-awp-db-logo-url, #pp-awp-db-pos, #pp-awp-db-name-val, #pp-awp-db-team-name-val').val('');
  });

  // ── Toggle external/DB modes ───────────────────────────────────────────
  $('#pp-awp-toggle-external').on('click', function (e) {
    e.preventDefault();
    $('#pp-awp-db-mode').hide();
    $('#pp-awp-external-mode').show();
  });

  $('#pp-awp-toggle-db').on('click', function (e) {
    e.preventDefault();
    $('#pp-awp-external-mode').hide();
    $('#pp-awp-db-mode').show();
  });

  // ── WP Media picker for logo override (DB mode) ───────────────────────
  $('#pp-awp-db-logo-override-btn').on('click', function (e) {
    e.preventDefault();
    var frame = wp.media({ multiple: false });
    frame.on('select', function () {
      var url = frame.state().get('selection').first().toJSON().url;
      $('#pp-awp-db-logo-override-url').val(url);
      $('#pp-awp-db-logo-override-preview').attr('src', url).show();
      $('#pp-awp-db-logo-override-clear').show();
    });
    frame.open();
  });

  $('#pp-awp-db-logo-override-clear').on('click', function () {
    $('#pp-awp-db-logo-override-url').val('');
    $('#pp-awp-db-logo-override-preview').hide();
    $(this).hide();
  });

  // ── WP Media picker for team logo (external mode) ─────────────────────
  $('#pp-awp-ext-logo-btn').on('click', function (e) {
    e.preventDefault();
    var frame = wp.media({ multiple: false });
    frame.on('select', function () {
      var url = frame.state().get('selection').first().toJSON().url;
      $('#pp-awp-ext-logo-url').val(url);
      $('#pp-awp-ext-logo-preview').attr('src', url).show();
    });
    frame.open();
  });
  });
})(jQuery);
