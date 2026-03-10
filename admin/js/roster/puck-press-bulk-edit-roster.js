(function ($) {
  jQuery(() => {

    // ── Field config ─────────────────────────────────────────────────────────
    // To add a new bulk-editable field: add one entry here + one to the PHP
    // $allowed_fields array in ajax_bulk_update_roster_field_callback().
    const FIELDS = {
      hero_image_url: { label: 'Hero Image URL', type: 'url', placeholder: 'https://...' },
      headshot_link:  { label: 'Headshot URL',   type: 'url', placeholder: 'https://...' },
    };

    // Position groups for filtering
    const POS_GROUPS = {
      forwards: ['f', 'c', 'lw', 'rw'],
      defense:  ['d', 'ld', 'rd'],
      goalies:  ['g'],
    };

    // ── Selectors / constants ─────────────────────────────────────────────────
    const MODAL_ID  = '#pp-bulk-roster-modal';
    const TABLE_SEL = '#pp-roster-edits-table';
    const ROW_SEL   = '#pp-roster-edits-table tbody tr:not(.pp-row-deleted)';
    const LIST_ID   = '#pp-bulk-roster-list';
    const COUNT_ID  = '#pp-bulk-roster-count';
    const APPLY_ID  = '#pp-bulk-roster-apply';
    const ERROR_ID  = '#pp-bulk-roster-error';
    const FIELD_SEL = '#pp-bulk-roster-field';
    const VALUE_SEL = '#pp-bulk-roster-value';

    // ── Dim/restore (mirrors selectors in puck-press-roster-edits.js) ─────────
    const dimStyles = () =>
      $('#pp-card-roster-preview, #pp-card-roster-edits-table, .pp-modal').css({
        opacity: '0.5',
        'pointer-events': 'none',
      });
    const restoreStyles = () =>
      $('#pp-card-roster-preview, #pp-card-roster-edits-table, .pp-modal').css({
        opacity: '1',
        'pointer-events': 'auto',
      });

    // ── Populate field selector ───────────────────────────────────────────────
    const $fieldSelect = $(FIELD_SEL);
    Object.entries(FIELDS).forEach(([key, cfg]) => {
      $fieldSelect.append(`<option value="${key}">${cfg.label}</option>`);
    });
    $fieldSelect.append('<option value="__remove_edits__">Remove All Edits</option>');

    // Update value input type/placeholder when field changes
    $fieldSelect.on('change', function () {
      const isRemove = this.value === '__remove_edits__';
      $(VALUE_SEL).toggle(!isRemove);
      $('.pp-bulk-edit-clear-hint').toggle(!isRemove);
      if (!isRemove) {
        const cfg = FIELDS[this.value];
        if (cfg) $(VALUE_SEL).attr('type', cfg.type).attr('placeholder', cfg.placeholder);
      }
    }).trigger('change');

    // ── Open modal ────────────────────────────────────────────────────────────
    $(document).on('click', '#pp-bulk-edit-roster-btn', () => {
      $(ERROR_ID).hide();
      $(VALUE_SEL).val('');
      $fieldSelect.trigger('change');
      buildItemList();
      applyFilters();
      $(MODAL_ID).css('display', 'flex');
    });

    // ── Build item list from DOM ──────────────────────────────────────────────
    function buildItemList() {
      const $list = $(LIST_ID);
      $list.empty();

      $(ROW_SEL).each(function () {
        const $tr     = $(this);
        const id      = $tr.attr('data-player-id') || '';
        const pos     = $tr.attr('data-pos') || '';
        const name    = $tr.attr('data-name') || '';
        const posGroup = getPosGroup(pos);

        $list.append(
          `<li class="pp-bulk-edit-row"
               data-player-id="${escAttr(id)}"
               data-pos="${escAttr(pos.toLowerCase())}"
               data-pos-group="${escAttr(posGroup)}"
               data-name="${escAttr(name.toLowerCase())}">
            <label class="pp-bulk-edit-row__label">
              <input type="checkbox" class="pp-bulk-edit-cb" checked>
              <span class="pp-bulk-pos-badge">${escHtml(pos || '—')}</span>
              <span class="pp-bulk-edit-row__name">${escHtml(name)}</span>
            </label>
          </li>`
        );
      });
    }

    function getPosGroup(pos) {
      const lower = (pos || '').toLowerCase();
      for (const [group, positions] of Object.entries(POS_GROUPS)) {
        if (positions.includes(lower)) return group;
      }
      return 'other';
    }

    // ── Filters ───────────────────────────────────────────────────────────────
    $(document).on('input change', '#pp-bulk-roster-pos, #pp-bulk-roster-name-filter', applyFilters);

    function applyFilters() {
      const posFilter  = $('#pp-bulk-roster-pos').val();
      const nameFilter = $('#pp-bulk-roster-name-filter').val().toLowerCase().trim();

      $(`${LIST_ID} .pp-bulk-edit-row`).each(function () {
        const $row = $(this);
        const rowGroup = $row.data('pos-group');
        const rowName  = String($row.data('name') || '');

        const visible =
          (posFilter === 'all' || rowGroup === posFilter) &&
          (!nameFilter || rowName.includes(nameFilter));

        $row.toggleClass('is-hidden', !visible);
      });

      updateCount();
    }

    // ── Select All / Deselect All ─────────────────────────────────────────────
    $(document).on('click', '#pp-bulk-roster-select-all', () => setVisibleChecked(true));
    $(document).on('click', '#pp-bulk-roster-deselect-all', () => setVisibleChecked(false));

    function setVisibleChecked(checked) {
      $(`${LIST_ID} .pp-bulk-edit-row:not(.is-hidden) .pp-bulk-edit-cb`).prop('checked', checked);
      updateCount();
    }

    $(document).on('change', `${LIST_ID} .pp-bulk-edit-cb`, updateCount);

    function updateCount() {
      const n = $(`${LIST_ID} .pp-bulk-edit-cb:checked`).length;
      $(COUNT_ID).text(`${n} player${n !== 1 ? 's' : ''} selected`);
      $(APPLY_ID).text(`Apply to ${n} Player${n !== 1 ? 's' : ''}`);
    }

    // ── Shared response handler ───────────────────────────────────────────────
    const handleBulkResponse = (response) => {
      if (response.success && response.data) {
        if (response.data.roster_table_html) {
          $(TABLE_SEL).replaceWith(response.data.roster_table_html);
          $(document).trigger('pp:roster-table-replaced');
        }
        if (response.data.roster_preview_html) {
          $('#pp-roster-preview').html(response.data.roster_preview_html);
          for (const key in ppRosterTemplates.rosterTemplates) {
            $(`.${key}_roster_container`).hide();
          }
          $(`.${ppRosterTemplates.selected_template}_roster_container`).show();
        }
      }
      restoreStyles();
    };

    // ── Submit ────────────────────────────────────────────────────────────────
    $(document).on('click', APPLY_ID, () => {
      $(ERROR_ID).hide();

      const ids = [];
      $(`${LIST_ID} .pp-bulk-edit-cb:checked`).each(function () {
        ids.push($(this).closest('.pp-bulk-edit-row').data('player-id'));
      });

      if (!ids.length) {
        showError('Select at least one player.');
        return;
      }

      const field = $(FIELD_SEL).val();

      // ── Remove All Edits branch ──────────────────────────────────────────
      if (field === '__remove_edits__') {
        if (!confirm(`Remove all edits from ${ids.length} player(s)? This reverts them to their original source data.`)) return;
        closeBulkModal();
        dimStyles();
        $.ajax({
          url: ajaxurl,
          method: 'POST',
          data: { action: 'pp_bulk_revert_roster_edits', nonce: ppBulkRoster.nonce, player_ids: JSON.stringify(ids) },
          success: handleBulkResponse,
          error: () => restoreStyles(),
        });
        return;
      }

      const value = $(VALUE_SEL).val().trim();
      const cfg   = FIELDS[field];

      if (value && !value.startsWith('http')) {
        showError('Please enter a valid URL starting with http.');
        return;
      }

      if (!value) {
        const fieldLabel = cfg ? cfg.label : field;
        if (!confirm(`Clear "${fieldLabel}" on ${ids.length} player(s)?`)) return;
      }

      closeBulkModal();
      dimStyles();

      $.ajax({
        url: ajaxurl,
        method: 'POST',
        data: {
          action:     'pp_bulk_update_roster_field',
          nonce:      ppBulkRoster.nonce,
          field,
          value,
          player_ids: JSON.stringify(ids),
        },
        success: handleBulkResponse,
        error: () => restoreStyles(),
      });
    });

    // ── Close / Error ─────────────────────────────────────────────────────────
    function closeBulkModal() {
      $(MODAL_ID).css('display', 'none');
      $(ERROR_ID).hide();
    }

    function showError(msg) {
      $(ERROR_ID).text(msg).show();
    }

    $(document).on('click', '#pp-bulk-roster-close, #pp-bulk-roster-cancel', closeBulkModal);
    $(document).on('click', MODAL_ID, function (e) {
      if (e.target === this) closeBulkModal();
    });

    // ── Helpers ───────────────────────────────────────────────────────────────
    function escHtml(str) {
      return String(str)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;');
    }

    function escAttr(str) {
      return String(str).replace(/"/g, '&quot;').replace(/'/g, '&#39;');
    }

  });
})(jQuery);
