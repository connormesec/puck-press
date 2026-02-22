(function ($) {
    jQuery(document).ready(function ($) {
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
            // no fontFieldsContainerId — slider has no font support
        });
    });
})(jQuery);
