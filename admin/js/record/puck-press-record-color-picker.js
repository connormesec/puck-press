(function ($) {
    jQuery(document).ready(function ($) {
        // Show only the selected record template in the preview on load.
        for (const key in ppRecordTemplates.recordTemplates) {
            $(`.${key}_record_container`).hide();
        }
        $(`.${ppRecordTemplates.selected_template}_record_container`).show();

        createColorPickerController({
            modalId:                '#pp-record-paletteModal',
            openBtnId:              '#pp-record-colorPaletteBtn',
            closeBtnId:             '#pp-record-palette-modal-close',
            cancelBtnId:            '#pp-cancel-save-record-colors',
            saveBtnId:              '#pp-record-palette-save-colors',
            formId:                 '#pp-record-color-palette-form',
            colorFieldsContainerId: '#pp-record-dynamic-color-fields',
            templateSelectorId:     '#pp-record-template-selector',
            templatesData:          ppRecordTemplates,
            templatesKey:           'recordTemplates',
            ajaxAction:             'puck_press_update_record_colors',
            containerSuffix:        '_record_container',
            fontFieldsContainerId:  '#pp-record-dynamic-font-fields',
        });
    });
})(jQuery);
