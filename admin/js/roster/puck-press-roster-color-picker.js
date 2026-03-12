(function ($) {
    jQuery(document).ready(function ($) {
        createColorPickerController({
            modalId:                '#pp-roster-paletteModal',
            openBtnId:              '#pp-roster-colorPaletteBtn',
            closeBtnId:             '#pp-roster-palette-modal-close',
            cancelBtnId:            '#pp-cancel-save-roster-colors',
            saveBtnId:              '#pp-roster-palette-save-colors',
            formId:                 '#pp-roster-color-palette-form',
            colorFieldsContainerId: '#pp-roster-dynamic-color-fields',
            templateSelectorId:     '#pp-roster-template-selector',
            templatesData:          ppRosterTemplates,
            templatesKey:           'rosterTemplates',
            ajaxAction:             'puck_press_update_roster_colors',
            containerSuffix:        '_roster_container',
            fontFieldsContainerId:  '#pp-roster-dynamic-font-fields',
            extraData:              () => ({ roster_id: (window.ppRosterAdmin && window.ppRosterAdmin.activeRosterId) ? parseInt(window.ppRosterAdmin.activeRosterId, 10) : 1 }),
            onFontChange: (templateKey, fontKey, cssValue) => {
                document.documentElement.style.setProperty('--pp-pd-font-family', cssValue);
            },
        });
    });
})(jQuery);
