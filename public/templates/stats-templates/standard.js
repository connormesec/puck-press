(($) => {
  const initializeStandardStats = (container) => {
    const $container = $(container);
    const state = {
      activeTeam: 'all',
      activeSource: '__all__',
      sortCol: null,
      sortDir: 'desc',
    };

    // Cache original sections HTML and sources for restoring current season
    const originalSectionsHtml = $container.find('.pp-stats-sections').html();
    const rawSources = $container.data('originalSources');
    const originalSources = Array.isArray(rawSources) ? rawSources : [];

    // ── Source dropdown rebuild ────────────────────────────────────

    const rebuildSourceDropdown = (sources) => {
      const $select = $container.find('.pp-stats-source-select');
      $select.empty().append('<option value="__all__">All</option>');
      sources.forEach((src) => $select.append($('<option>').val(src).text(src)));
      $select.val('__all__').toggle(sources.length > 1);
    };

    // ── Season selector ────────────────────────────────────────────

    $container.on('change', '.pp-stats-season-select', function () {
      const archiveKey = $(this).val();

      if (archiveKey === 'current') {
        $container.find('.pp-stats-sections').html(originalSectionsHtml);
        state.activeSource = '__all__';
        $container.find('.pp-stats-source-select').val('__all__');
        rebuildSourceDropdown(originalSources);
        defaultSort();
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
          show_team: $container.data('show-team'),
          teams: $container.data('teams'),
        },
        success: ({ success, data } = {}) => {
          if (success && data?.sections_html !== undefined) {
            $container.find('.pp-stats-sections').html(data.sections_html);
            state.activeSource = '__all__';
            rebuildSourceDropdown(data.sources ?? []);
            defaultSort();
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

    // ── Source filter ──────────────────────────────────────────────

    $container.on('change', '.pp-stats-source-select', function () {
      state.activeSource = $(this).val();
      state.activeTeam = 'all';
      $container.find('.pp-stats-team-select').val('all');
      applyFilters();
    });

    // ── Apply team + source filters ────────────────────────────────

    const applyFilters = () => {
      $container.find('.pp-stats-section').each(function () {
        const $section = $(this);
        const $tbody = $section.find('tbody');
        if (!$tbody.length) return;

        let visible = 0;
        $tbody.find('tr').each(function () {
          const $row = $(this);
          const matchesSource = String($row.data('source') ?? '') === state.activeSource;
          const matchesTeam = state.activeTeam === 'all' || ($row.data('team-name') ?? '') === state.activeTeam;
          const show = matchesSource && matchesTeam;
          $row.toggle(show);
          if (show) visible++;
        });

        const $wrapper = $section.find('.pp-stats-table-wrapper');
        let $noResults = $section.find('.pp-stats-no-results-msg');
        if (!$noResults.length) {
          $noResults = $('<p class="pp-stats-filter-empty pp-stats-no-results-msg">No players match the selected filter.</p>');
          $wrapper.after($noResults);
        }
        $wrapper.toggle(visible > 0);
        $noResults.toggle(visible === 0);
      });
      rerank();
      restripe();
    };

    // ── Re-stripe visible rows ─────────────────────────────────────

    const restripe = () => {
      $container.find('tbody').each(function () {
        let i = 0;
        $(this).find('tr:visible').each(function () {
          $(this).removeClass('pp-row-odd pp-row-even').addClass(i++ % 2 === 0 ? 'pp-row-odd' : 'pp-row-even');
        });
      });
    };

    // ── Re-rank visible rows ───────────────────────────────────────

    const rerank = () => {
      $container.find('tbody').each(function () {
        let rank = 1;
        $(this).find('tr:visible').each(function () {
          $(this).find('.pp-stats-rank-cell').text(rank++);
        });
      });
    };

    // ── Default sort (pts desc for skaters, wins desc for goalies) ─

    const sortByCol = ($table, $tbody, colAttr, dir = 'desc') => {
      const $th = $table.find(`th[data-col="${colAttr}"]`);
      if (!$th.length) return;

      const idx = $th.index();
      const rows = $tbody.find('tr').get();
      rows.sort((a, b) => {
        const aVal = parseFloat($(a).find('td').eq(idx).text().trim()) || 0;
        const bVal = parseFloat($(b).find('td').eq(idx).text().trim()) || 0;
        return dir === 'desc' ? bVal - aVal : aVal - bVal;
      });
      $tbody.append(rows);
      $table.find('thead th').removeClass('pp-stats-sort-asc pp-stats-sort-desc');
      $th.addClass(`pp-stats-sort-${dir}`);
    };

    const defaultSort = () => {
      $container.find('.pp-stats-section').each(function () {
        const $table = $(this).find('table');
        const $tbody = $table.find('tbody');
        if (!$tbody.length) return;

        if ($table.find('th[data-col="pts"]').length) {
          sortByCol($table, $tbody, 'pts', 'desc');
        } else if ($table.find('th[data-col="w"]').length) {
          sortByCol($table, $tbody, 'w', 'desc');
        }
      });
    };

    // ── Initial render ─────────────────────────────────────────────

    defaultSort();
    applyFilters();

    // ── Column sort ────────────────────────────────────────────────

    $container.on('click', 'th[data-col]', function () {
      const $th = $(this);
      const col = $th.data('col');
      const type = $th.data('type');
      const $tbody = $th.closest('table').find('tbody');

      if (state.sortCol === col) {
        state.sortDir = state.sortDir === 'desc' ? 'asc' : 'desc';
      } else {
        state.sortCol = col;
        state.sortDir = 'desc';
      }

      $th.closest('thead').find('th').removeClass('pp-stats-sort-asc pp-stats-sort-desc');
      $th.addClass(`pp-stats-sort-${state.sortDir}`);

      const colIndex = $th.index();
      const rows = $tbody.find('tr').get();

      rows.sort((a, b) => {
        let aVal = $(a).find('td').eq(colIndex).text().trim();
        let bVal = $(b).find('td').eq(colIndex).text().trim();

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
      restripe();
    });
  };

  // ── Boot ───────────────────────────────────────────────────────

  // Expose for re-initialization after dynamic HTML replacement (e.g. admin column settings save).
  window.ppStatsInitContainer = initializeStandardStats;

  $(() => {
    $('.standard_stats_container').each(function () {
      initializeStandardStats(this);
    });
  });
})(jQuery);
