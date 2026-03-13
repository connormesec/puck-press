(function ($) {
  'use strict';

  function initializeStandardStats(container) {
    var $container = $(container);
    var state = {
      activeTeam: 'all',
      sortCol: null,
      sortDir: 'desc',
    };

    // Cache original sections HTML for restoring current season
    var originalSectionsHtml = $container.find('.pp-stats-sections').html();

    // ── Season selector ────────────────────────────────────────────

    $container.on('change', '.pp-stats-season-select', function () {
      var archiveKey = $(this).val();

      if (archiveKey === 'current') {
        $container.find('.pp-stats-sections').html(originalSectionsHtml);
        applyFilters();
        return;
      }

      $.ajax({
        url: $container.data('ajaxurl'),
        type: 'POST',
        data: {
          action: 'pp_get_archive_stats',
          nonce: $container.data('nonce'),
          archive_key: archiveKey,
        },
        success: function (response) {
          if (response.success && response.data && response.data.sections_html !== undefined) {
            $container.find('.pp-stats-sections').html(response.data.sections_html);
            applyFilters();
          }
        },
      });
    });

    // ── Team filter ────────────────────────────────────────────────

    $container.on('change', '.pp-stats-team-select', function () {
      state.activeTeam = $(this).val();
      applyFilters();
    });

    function applyFilters() {
      $container.find('tbody').each(function () {
        var $tbody = $(this);
        var visible = 0;
        $tbody.find('tr:not(.pp-stats-no-results)').each(function () {
          var $row = $(this);
          var show = state.activeTeam === 'all' || ($row.data('team-name') || '') === state.activeTeam;
          $row.toggle(show);
          if (show) visible++;
        });
        var $empty = $tbody.find('.pp-stats-no-results');
        if ($empty.length === 0) {
          $empty = $('<tr class="pp-stats-no-results"><td colspan="99" class="pp-stats-filter-empty">No players match the selected filter.</td></tr>');
          $tbody.append($empty);
        }
        $empty.toggle(visible === 0);
      });
      rerank();
    }

    // ── Re-rank visible rows ───────────────────────────────────────

    function rerank() {
      $container.find('tbody').each(function () {
        var rank = 1;
        $(this).find('tr:not(.pp-stats-no-results):visible').each(function () {
          $(this).find('.pp-stats-rank-cell').text(rank);
          rank++;
        });
      });
    }

    // ── Initial rank ───────────────────────────────────────────────

    rerank();

    // ── Column sort ────────────────────────────────────────────────

    $container.on('click', 'th[data-col]', function () {
      var $th = $(this);
      var col = $th.data('col');
      var type = $th.data('type');
      var $tbody = $th.closest('table').find('tbody');

      if (state.sortCol === col) {
        state.sortDir = state.sortDir === 'desc' ? 'asc' : 'desc';
      } else {
        state.sortCol = col;
        state.sortDir = 'desc';
      }

      $th.closest('thead').find('th').removeClass('pp-stats-sort-asc pp-stats-sort-desc');
      $th.addClass('pp-stats-sort-' + state.sortDir);

      var colIndex = $th.index();
      var rows = $tbody.find('tr').get();

      rows.sort(function (a, b) {
        var aVal = $(a).find('td').eq(colIndex).text().trim();
        var bVal = $(b).find('td').eq(colIndex).text().trim();

        if (type === 'num') {
          aVal = parseFloat(aVal) || 0;
          bVal = parseFloat(bVal) || 0;
        } else {
          aVal = aVal.toLowerCase();
          bVal = bVal.toLowerCase();
        }

        if (aVal < bVal) return state.sortDir === 'desc' ? 1 : -1;
        if (aVal > bVal) return state.sortDir === 'desc' ? -1 : 1;
        return 0;
      });

      $tbody.append(rows);
      rerank();
    });
  }

  // ── Boot ───────────────────────────────────────────────────────

  $(document).ready(function () {
    $('.standard_stats_container').each(function () {
      initializeStandardStats(this);
    });
  });

})(jQuery);
