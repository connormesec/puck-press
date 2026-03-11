(function ($) {
  jQuery(() => {

    // ── Field config ─────────────────────────────────────────────────────────
    // To add a new bulk-editable field: add one entry here + one to the PHP
    // $allowed_fields array in ajax_bulk_update_schedule_field_callback().
    const FIELDS = {
      promo_ticket_link: { label: 'Ticket Link',   type: 'url',  placeholder: 'https://...' },
      venue:             { label: 'Venue',          type: 'text', placeholder: 'Arena name'  },
      promo_header:      { label: 'Promo Header',   type: 'text', placeholder: 'Game night!' },
      promo_text:        { label: 'Promo Text',     type: 'text', placeholder: 'Promotional text' },
      promo_img_url:     { label: 'Promo Image URL', type: 'url', placeholder: 'https://...' },
    };

    // ── Selectors / constants ─────────────────────────────────────────────────
    const MODAL_ID    = '#pp-bulk-schedule-modal';
    const TABLE_SEL   = '#pp-games-table';
    const ROW_SEL     = '#pp-games-table tbody tr:not(.pp-row-deleted)';
    const LIST_ID     = '#pp-bulk-schedule-list';
    const COUNT_ID    = '#pp-bulk-schedule-count';
    const APPLY_ID    = '#pp-bulk-schedule-apply';
    const ERROR_ID    = '#pp-bulk-schedule-error';
    const FIELD_SEL   = '#pp-bulk-schedule-field';
    const VALUE_SEL   = '#pp-bulk-schedule-value';

    // ── Dim/restore (mirrors selectors in puck-press-edits-table.js) ──────────
    const dimStyles = () =>
      $('#pp-card-game-schedule-preview, #pp-card-schedule-game-list, #pp-card-game-schedule-edits, .pp-modal, .pp-card').css({
        opacity: '0.5',
        'pointer-events': 'none',
      });
    const restoreStyles = () =>
      $('#pp-card-game-schedule-preview, #pp-card-schedule-game-list, #pp-card-game-schedule-edits, .pp-modal, .pp-card').css({
        opacity: '1',
        'pointer-events': 'auto',
      });

    // ── Populate field selector ───────────────────────────────────────────────
    const $fieldSelect = $(FIELD_SEL);
    Object.entries(FIELDS).forEach(([key, cfg]) => {
      $fieldSelect.append(`<option value="${key}">${cfg.label}</option>`);
    });

    // Update value input type/placeholder when field changes
    $fieldSelect.on('change', function () {
      const cfg = FIELDS[this.value];
      if (!cfg) return;
      $(VALUE_SEL).attr('type', cfg.type).attr('placeholder', cfg.placeholder);
    }).trigger('change');

    // ── Open modal ────────────────────────────────────────────────────────────
    $(document).on('click', '#pp-bulk-edit-schedule-btn', () => {
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
        const $tr      = $(this);
        const id       = $tr.attr('data-id') || '';
        const ha       = $tr.attr('data-home-or-away') || '';
        const venue    = $tr.attr('data-venue') || '';
        const ts       = parseInt($tr.attr('data-timestamp') || '0', 10);
        const opponent = $tr.attr('data-opponent') || '';

        const dateStr = ts
          ? new Date(ts * 1000).toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' })
          : '';
        const haLabel = ha ? ha.charAt(0).toUpperCase() + ha.slice(1) : '';

        $list.append(
          `<li class="pp-bulk-edit-row"
               data-game-id="${escAttr(id)}"
               data-home-or-away="${escAttr(ha)}"
               data-venue="${escAttr(venue.toLowerCase())}"
               data-timestamp="${ts}">
            <label class="pp-bulk-edit-row__label">
              <input type="checkbox" class="pp-bulk-edit-cb" checked>
              <span class="pp-bulk-ha-badge pp-bulk-ha-badge--${escAttr(ha)}">${escHtml(haLabel)}</span>
              <span class="pp-bulk-edit-row__date">${escHtml(dateStr)}</span>
              <span class="pp-bulk-edit-row__opponent">${escHtml(opponent)}</span>
              <span class="pp-bulk-edit-row__venue">${escHtml(venue)}</span>
            </label>
          </li>`
        );
      });
    }

    // ── Filters ───────────────────────────────────────────────────────────────
    $(document).on(
      'input change',
      '#pp-bulk-schedule-ha, #pp-bulk-schedule-contains, #pp-bulk-schedule-excludes, [name="pp-bulk-schedule-date"]',
      applyFilters
    );

    function applyFilters() {
      const ha           = $('#pp-bulk-schedule-ha').val();
      const contains     = $('#pp-bulk-schedule-contains').val().toLowerCase().trim();
      const excludes     = $('#pp-bulk-schedule-excludes').val().toLowerCase().trim();
      const upcomingOnly = $('[name="pp-bulk-schedule-date"]:checked').val() === 'upcoming';
      const now          = Math.floor(Date.now() / 1000);

      $(`${LIST_ID} .pp-bulk-edit-row`).each(function () {
        const $row  = $(this);
        const rowHa = $row.data('home-or-away');
        const rowVn = String($row.data('venue') || '');
        const rowTs = parseInt($row.data('timestamp') || '0', 10);

        const visible =
          (ha === 'all' || rowHa === ha) &&
          (!contains || rowVn.includes(contains)) &&
          (!excludes || !rowVn.includes(excludes)) &&
          (!upcomingOnly || rowTs > now);

        $row.toggleClass('is-hidden', !visible);
      });

      updateCount();
    }

    // ── Select All / Deselect All ─────────────────────────────────────────────
    $(document).on('click', '#pp-bulk-schedule-select-all', () => setVisibleChecked(true));
    $(document).on('click', '#pp-bulk-schedule-deselect-all', () => setVisibleChecked(false));

    function setVisibleChecked(checked) {
      $(`${LIST_ID} .pp-bulk-edit-row:not(.is-hidden) .pp-bulk-edit-cb`).prop('checked', checked);
      updateCount();
    }

    $(document).on('change', `${LIST_ID} .pp-bulk-edit-cb`, updateCount);

    function updateCount() {
      const n = $(`${LIST_ID} .pp-bulk-edit-cb:checked`).length;
      $(COUNT_ID).text(`${n} game${n !== 1 ? 's' : ''} selected`);
      $(APPLY_ID).text(`Apply to ${n} Game${n !== 1 ? 's' : ''}`);
    }

    // ── Submit ────────────────────────────────────────────────────────────────
    $(document).on('click', APPLY_ID, () => {
      $(ERROR_ID).hide();

      const ids = [];
      $(`${LIST_ID} .pp-bulk-edit-cb:checked`).each(function () {
        ids.push($(this).closest('.pp-bulk-edit-row').data('game-id'));
      });

      if (!ids.length) {
        showError('Select at least one game.');
        return;
      }

      const field = $(FIELD_SEL).val();
      const value = $(VALUE_SEL).val().trim();
      const cfg   = FIELDS[field];

      if (value && cfg && cfg.type === 'url' && !value.startsWith('http')) {
        showError('Please enter a valid URL starting with http.');
        return;
      }

      if (!value) {
        const fieldLabel = cfg ? cfg.label : field;
        if (!confirm(`Clear "${fieldLabel}" on ${ids.length} game(s)?`)) return;
      }

      closeBulkModal();
      dimStyles();

      $.ajax({
        url: ajaxurl,
        method: 'POST',
        data: {
          action:      'pp_bulk_update_schedule_field',
          nonce:       ppBulkSchedule.nonce,
          schedule_id: (window.ppScheduleAdmin && window.ppScheduleAdmin.activeScheduleId) ? window.ppScheduleAdmin.activeScheduleId : 1,
          field,
          value,
          game_ids: JSON.stringify(ids),
        },
        success: (response) => {
          if (response.success && response.data) {
            if (response.data.games_table_html) {
              $(TABLE_SEL).replaceWith(response.data.games_table_html);
              $(document).trigger('pp:schedule-table-replaced');
            }
            if (response.data.schedule_preview_html) {
              $('#pp-game-schedule-preview').html(response.data.schedule_preview_html);
              for (const key in ppScheduleTemplates.scheduleTemplates) {
                $(`.${key}_schedule_container`).hide();
              }
              $(`.${ppScheduleTemplates.selected_template}_schedule_container`).show();
            }
            if (response.data.slider_preview_html) {
              $('#pp-game-slider-preview').html(response.data.slider_preview_html);
              for (const key in ppSliderTemplates.sliderTemplates) {
                $(`.${key}_slider_container`).hide();
              }
              $(`.${ppSliderTemplates.selected_template}_slider_container`).show();
            }
            if (typeof gameScheduleInitializers !== 'undefined') {
              gameScheduleInitializers.forEach(fn => { if (typeof fn === 'function') fn(); });
            }
          }
          restoreStyles();
        },
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

    $(document).on('click', '#pp-bulk-schedule-close, #pp-bulk-schedule-cancel', closeBulkModal);
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
