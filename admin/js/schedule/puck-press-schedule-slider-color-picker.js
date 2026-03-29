(function ($) {
  jQuery(document).ready(function ($) {
    const getActiveScheduleId = () => parseInt($('#pp-active-new-schedule-id').val(), 10) || 1;

    createColorPickerController({
      modalId:                '#pp-slider-paletteModal',
      openBtnId:              '#pp-schedule-slider-colorPaletteBtn',
      closeBtnId:             '#pp-slider-palette-modal-close',
      cancelBtnId:            '#pp-cancel-save-colors',
      saveBtnId:              '#pp-slider-palette-save-colors',
      formId:                 '#pp-slider-color-palette-form',
      colorFieldsContainerId: '#pp-slider-dynamic-color-fields',
      templateSelectorId:     '#pp-slider-template-selector',
      templatesData:          ppSliderTemplates,
      templatesKey:           'sliderTemplates',
      ajaxAction:             'puck_press_update_slider_colors',
      containerSuffix:        '_slider_container',
      calUrlFieldId:          '#pp-slider-cal-url',
      calUrlShowForTemplates: ['scoreboard', 'compact'],
      extraData:              () => ({ schedule_id: getActiveScheduleId() }),
      onSaveSuccess: (response, closeModal) => {
        if (response.data.active_slider_html) {
          $('#pp-game-slider-preview').html(response.data.active_slider_html);
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
