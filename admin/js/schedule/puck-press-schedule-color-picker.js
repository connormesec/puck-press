(function ($) {
    jQuery(document).ready(function ($) {
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
        });
    });
})(jQuery);
