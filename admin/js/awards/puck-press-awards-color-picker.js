(function ($) {
    jQuery(document).ready(function ($) {
        if (typeof ppAwardsTemplates === 'undefined') return;

        for (var key in ppAwardsTemplates.awardsTemplates) {
            $('.' + key + '_awards_container').hide();
        }
        $('.' + ppAwardsTemplates.selected_template + '_awards_container').show();

        createColorPickerController({
            modalId:                '#pp-awards-paletteModal',
            openBtnId:              '#pp-awards-colorPaletteBtn',
            closeBtnId:             '#pp-awards-palette-modal-close',
            cancelBtnId:            '#pp-cancel-save-awards-colors',
            saveBtnId:              '#pp-awards-palette-save-colors',
            formId:                 '#pp-awards-color-palette-form',
            colorFieldsContainerId: '#pp-awards-dynamic-color-fields',
            templateSelectorId:     '#pp-awards-template-selector',
            templatesData:          ppAwardsTemplates,
            templatesKey:           'awardsTemplates',
            ajaxAction:             'puck_press_update_awards_colors',
            containerSuffix:        '_awards_container',
            fontFieldsContainerId:  '#pp-awards-dynamic-font-fields',
        });
    });
})(jQuery);
