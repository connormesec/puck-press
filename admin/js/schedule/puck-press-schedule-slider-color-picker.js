(function ($) {
  jQuery(document).ready(function ($) {
    const getActiveScheduleId = () => parseInt($('#pp-active-new-schedule-id').val(), 10) || 1;

    let _sliderStash = null;

    createColorPickerController({
      modalId:                '#pp-slider-paletteModal',
      openBtnId:              '#pp-schedule-slider-colorPaletteBtn',
      closeBtnId:             '#pp-slider-palette-modal-close',
      cancelBtnId:            '#pp-cancel-save-colors',
      saveBtnId:              '#pp-slider-palette-save-colors',
      formId:                 '#pp-slider-color-palette-form',
      colorFieldsContainerId: '#pp-slider-dynamic-color-fields',
      fontFieldsContainerId:  '#pp-slider-dynamic-font-fields',
      templateSelectorId:     '#pp-slider-template-selector',
      templatesData:          ppSliderTemplates,
      templatesKey:           'sliderTemplates',
      ajaxAction:             'puck_press_update_slider_colors',
      containerSuffix:        '_slider_container',
      calUrlFieldId:          '#pp-slider-cal-url',
      calUrlShowForTemplates: ['scoreboard', 'compact'],
      extraData:              () => ({ schedule_id: getActiveScheduleId() }),
      onFontChange: (templateKey, fontKey, cssValue) => {
        const el = document.getElementById('pp-slider-' + getActiveScheduleId());
        if (el) el.style.setProperty('--pp-' + templateKey + '-' + fontKey, cssValue);
      },
      onOpen: function () {
        _sliderStash = $('#pp-game-slider-preview').html();
        $.ajax({
          url:  ajaxurl,
          type: 'POST',
          data: { action: 'pp_get_schedule_preview', schedule_id: getActiveScheduleId() },
          success: function (response) {
            if (!response.success) return;
            const d = response.data;
            if (d.slider_preview_html) {
              $('#pp-game-slider-preview').html(d.slider_preview_html);
              if (typeof ppSliderTemplates !== 'undefined') {
                for (const k of Object.keys(ppSliderTemplates.sliderTemplates)) {
                  $('.' + k + '_slider_container').hide();
                }
                const active = d.selected_slider_template || ppSliderTemplates.selected_template;
                if (active) {
                  $('.' + active + '_slider_container').show();
                  ppSliderTemplates.selected_template = active;
                }
              }
            }
            if (typeof gameScheduleInitializers !== 'undefined') {
              gameScheduleInitializers.forEach(function (fn) { if (typeof fn === 'function') fn(); });
            }
          },
        });
      },
      onTemplateChange: function () {
        if (typeof gameScheduleInitializers !== 'undefined') {
          gameScheduleInitializers.forEach(function (fn) { if (typeof fn === 'function') fn(); });
        }
      },
      onClose: function () {
        if (_sliderStash !== null) {
          $('#pp-game-slider-preview').html(_sliderStash);
          _sliderStash = null;
          if (typeof gameScheduleInitializers !== 'undefined') {
            gameScheduleInitializers.forEach(function (fn) { if (typeof fn === 'function') fn(); });
          }
        }
      },
      onSaveSuccess: (response, closeModal) => {
        if (response.data.active_slider_html) {
          $('#pp-game-slider-preview').html(response.data.active_slider_html);
          if (typeof gameScheduleInitializers !== 'undefined') {
            gameScheduleInitializers.forEach(function (fn) { if (typeof fn === 'function') fn(); });
          }
        }
        closeModal();
      },
    });

    // Live preview: the shared controller sets vars on :root, but the slider
    // preview is scoped to #pp-slider-{id} via inline <style>, so :root vars
    // lose specificity. Mirror changes onto the scoped container element too.
    $(document).on('input', '#pp-slider-dynamic-color-fields .pp-color-pallette-color-value', function () {
      const el = document.getElementById('pp-slider-' + getActiveScheduleId());
      if (!el) return;
      const match = ($(this).attr('id') || '').match(/^pp-(\w+)-(\w+)-color-text-input$/);
      if (match) el.style.setProperty('--pp-' + match[1] + '-' + match[2], $(this).val());
    });
  });
})(jQuery);
