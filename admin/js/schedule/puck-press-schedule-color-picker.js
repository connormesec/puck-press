(function ($) {
  jQuery(document).ready(function ($) {

    const getActiveScheduleId = () => parseInt($('#pp-active-new-schedule-id').val(), 10) || 1;

    let _previewStash = null;
    let _sliderStash  = null;

    createColorPickerController({
      modalId:                '#pp-schedule-paletteModal',
      openBtnId:              '#pp-schedule-colorPaletteBtn',
      closeBtnId:             '#pp-schedule-palette-modal-close',
      cancelBtnId:            '#pp-cancel-save-colors',
      saveBtnId:              '#pp-sched-palette-save-colors',
      formId:                 '#pp-color-palette-form',
      colorFieldsContainerId: '#pp-dynamic-color-fields',
      templateSelectorId:     '#pp-template-selector',
      templatesData:          ppScheduleTemplates,
      templatesKey:           'scheduleTemplates',
      ajaxAction:             'puck_press_update_schedule_colors',
      containerSuffix:        '_schedule_container',
      fontFieldsContainerId:  '#pp-schedule-dynamic-font-fields',
      extraData:              () => ({ schedule_id: getActiveScheduleId() }),

      onOpen: function () {
        // Stash current active-only preview HTML so cancel can restore it.
        _previewStash = $('#pp-game-schedule-preview').html();
        _sliderStash  = $('#pp-game-slider-preview').html();

        // Fetch all templates for the current schedule so real-time switching works.
        $.ajax({
          url:  ajaxurl,
          type: 'POST',
          data: { action: 'pp_get_schedule_preview', schedule_id: getActiveScheduleId() },
          success: function (response) {
            if (!response.success) return;
            const d = response.data;
            if (d.game_preview_html)   $('#pp-game-schedule-preview').html(d.game_preview_html);
            if (d.slider_preview_html) $('#pp-game-slider-preview').html(d.slider_preview_html);
            // Show only the currently selected template container.
            if (d.selected_template) {
              for (const k of Object.keys(ppScheduleTemplates.scheduleTemplates)) {
                $('.' + k + '_schedule_container').hide();
              }
              $('.' + d.selected_template + '_schedule_container').show();
            }
            if (d.selected_slider_template && typeof ppSliderTemplates !== 'undefined') {
              for (const k of Object.keys(ppSliderTemplates.sliderTemplates)) {
                $('.' + k + '_slider_container').hide();
              }
              $('.' + d.selected_slider_template + '_slider_container').show();
            }
            if (typeof gameScheduleInitializers !== 'undefined') {
              gameScheduleInitializers.forEach(function (fn) { if (typeof fn === 'function') fn(); });
            }
          },
        });
      },

      onSaveSuccess: function (response, closeModal) {
        // Replace preview with freshly-rendered active template from the server.
        if (response.data.active_preview_html) {
          $('#pp-game-schedule-preview').html(response.data.active_preview_html);
        }
        if (response.data.active_slider_html) {
          $('#pp-game-slider-preview').html(response.data.active_slider_html);
        }
        if (typeof gameScheduleInitializers !== 'undefined') {
          gameScheduleInitializers.forEach(function (fn) { if (typeof fn === 'function') fn(); });
        }
        _previewStash = null;
        _sliderStash  = null;
        closeModal();
      },

      onClose: function () {
        // Cancel — restore the stashed active-only HTML.
        if (_previewStash !== null) {
          $('#pp-game-schedule-preview').html(_previewStash);
          _previewStash = null;
        }
        if (_sliderStash !== null) {
          $('#pp-game-slider-preview').html(_sliderStash);
          _sliderStash = null;
        }
        if (typeof gameScheduleInitializers !== 'undefined') {
          gameScheduleInitializers.forEach(function (fn) { if (typeof fn === 'function') fn(); });
        }
      },
    });
  });
})(jQuery);
