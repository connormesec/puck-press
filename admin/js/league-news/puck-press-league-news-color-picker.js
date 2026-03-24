(function ($) {
  jQuery(document).ready(function ($) {
    createColorPickerController({
      modalId:                '#pp-league-news-paletteModal',
      openBtnId:              '#pp-league-news-colorPaletteBtn',
      closeBtnId:             '#pp-league-news-palette-modal-close',
      cancelBtnId:            '#pp-league-news-cancel-save-colors',
      saveBtnId:              '#pp-league-news-palette-save-colors',
      formId:                 '#pp-league-news-color-palette-form',
      colorFieldsContainerId: '#pp-league-news-dynamic-color-fields',
      templateSelectorId:     '#pp-league-news-template-selector',
      templatesData:          ppLeagueNewsTemplates,
      templatesKey:           'leagueNewsTemplates',
      ajaxAction:             'puck_press_update_league_news_colors',
      containerSuffix:        '_league_news_container',
      onSaveSuccess: function (response) {
        if (response.data && response.data.preview_html) {
          $('#pp-league-news-preview-area').html(response.data.preview_html);
        }
      },
    });
  });
})(jQuery);
