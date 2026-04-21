(function ($) {
  jQuery(document).ready(function ($) {
    createColorPickerController({
      modalId:                '#pp-post-slider-paletteModal',
      openBtnId:              '#pp-post-slider-colorPaletteBtn',
      closeBtnId:             '#pp-post-slider-palette-modal-close',
      cancelBtnId:            '#pp-post-slider-cancel-save-colors',
      saveBtnId:              '#pp-post-slider-palette-save-colors',
      formId:                 '#pp-post-slider-color-palette-form',
      colorFieldsContainerId: '#pp-post-slider-dynamic-color-fields',
      templateSelectorId:     '#pp-post-slider-template-selector',
      templatesData:          ppPostSliderData,
      templatesKey:           'postSliderTemplates',
      ajaxAction:             'puck_press_update_post_slider_colors',
      containerSuffix:        '_post_slider_container',
      fontFieldsContainerId:  '#pp-post-slider-dynamic-font-fields',
    });
  });
})(jQuery);
