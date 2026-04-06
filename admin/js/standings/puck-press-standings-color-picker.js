(function ($) {
  $(function () {
    if (typeof ppStandingsTemplates === 'undefined' || typeof createColorPickerController === 'undefined') {
      return;
    }

    var selected = ppStandingsTemplates.selected_template;

    $('.standings_container').hide();
    if (selected) {
      $('.' + selected + '_container').show();
    }

    createColorPickerController({
      modalId: '#pp-standings-paletteModal',
      openBtnId: '#pp-standings-colorPaletteBtn',
      closeBtnId: '#pp-standings-palette-modal-close',
      cancelBtnId: '#pp-cancel-save-standings-colors',
      saveBtnId: '#pp-standings-palette-save-colors',
      formId: '#pp-standings-color-palette-form',
      colorFieldsContainerId: '#pp-standings-dynamic-color-fields',
      fontFieldsContainerId: '#pp-standings-dynamic-font-fields',
      templateSelectorId: '#pp-standings-template-selector',
      templatesData: ppStandingsTemplates,
      templatesKey: 'standingsTemplates',
      ajaxAction: 'puck_press_update_standings_colors',
      containerSuffix: '_container',
      extraData: { team_id: (window.ppStandingsAdmin || {}).teamId || 0 }
    });
  });
})(jQuery);
