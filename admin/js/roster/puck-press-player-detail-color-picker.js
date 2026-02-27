(function ($) {
    jQuery(document).ready(function ($) {
        createColorPickerController({
            modalId:                '#pp-player-detail-paletteModal',
            openBtnId:              '#pp-player-detail-colorPaletteBtn',
            closeBtnId:             '#pp-player-detail-palette-modal-close',
            cancelBtnId:            '#pp-cancel-save-player-detail-colors',
            saveBtnId:              '#pp-player-detail-palette-save-colors',
            formId:                 '#pp-player-detail-color-palette-form',
            colorFieldsContainerId: '#pp-player-detail-dynamic-color-fields',
            fontFieldsContainerId:  '#pp-player-detail-dynamic-font-fields',
            templateSelectorId:     '#pp-player-detail-template-selector',
            templatesData:          ppPlayerDetailColors,
            templatesKey:           'pdColors',
            ajaxAction:             'puck_press_update_player_detail_colors',
            // No preview container in the admin — suffix targets a non-existent class.
            containerSuffix:        '_player_detail_preview',
            onFontChange: (_templateKey, _fontKey, cssValue) => {
                document.documentElement.style.setProperty('--pp-pd-font-family', cssValue);
            },
        });
    });
})(jQuery);
