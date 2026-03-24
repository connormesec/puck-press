(function ($) {
  jQuery(document).ready(function ($) {

    var $source   = $('#pp-league-news-source');
    var $category = $('#pp-league-news-category');

    // Populate category dropdown for a given source, pre-selecting savedValue.
    function populateCategories(source, savedValue) {
      var cats = ppLeagueNewsData.categories[source] || {};
      $category.empty();
      $.each(cats, function (id, label) {
        var selected = parseInt(id) === parseInt(savedValue) ? ' selected' : '';
        $category.append('<option value="' + id + '"' + selected + '>' + label + '</option>');
      });
    }

    // On page load — populate using current source + saved category for that source.
    var initialSource = $source.val();
    var savedCategory = initialSource === 'usphl'
      ? window._ppLeagueNewsSavedUsphlCategory
      : window._ppLeagueNewsSavedAchaCategory;
    populateCategories(initialSource, savedCategory);

    // On source change — repopulate categories, pre-select saved value for that source.
    $source.on('change', function () {
      var src   = $(this).val();
      var saved = src === 'usphl'
        ? window._ppLeagueNewsSavedUsphlCategory
        : window._ppLeagueNewsSavedAchaCategory;
      populateCategories(src, saved);
    });

    // Save settings.
    $('#pp-league-news-save-settings').on('click', function () {
      var $btn      = $(this);
      var $feedback = $('#pp-league-news-save-feedback');
      var source    = $source.val();
      var category  = $category.val();
      var count     = $('#pp-league-news-count').val();

      $btn.prop('disabled', true).text('Saving...');
      $feedback.hide();

      $.post(ppLeagueNewsData.ajax_url, {
        action:   'pp_save_league_news_settings',
        nonce:    ppLeagueNewsData.nonce,
        source:   source,
        category: category,
        count:    count,
      }, function (response) {
        if (response.success) {
          // Update saved category for this source so switching back pre-selects correctly.
          if (source === 'usphl') {
            window._ppLeagueNewsSavedUsphlCategory = parseInt(category);
          } else {
            window._ppLeagueNewsSavedAchaCategory = parseInt(category);
          }
          $feedback.text('Saved!').show();
          if (response.data && response.data.preview_html) {
            var $area = $('#pp-league-news-preview-area');
            $area.html(response.data.preview_html);
            $area.find('.pp-ln-card-container').each(function () {
              if (typeof ppLnCardInitContainer === 'function') {
                ppLnCardInitContainer(this);
              }
            });
          }
          setTimeout(function () { $feedback.fadeOut(); }, 3000);
        } else {
          $feedback.text('Error saving settings.').show();
        }
      }).always(function () {
        $btn.prop('disabled', false).text('Save Settings');
      });
    });

    // Shortcode copy button.
    var $copyInput = $('#pp-league-news-shortcode');
    $copyInput.siblings('.pp-shortcode-copy-btn').on('click', function () {
      navigator.clipboard.writeText($copyInput.val()).then(function () {
        var $tooltip = $copyInput.siblings('.pp-shortcode-tooltip');
        $tooltip.addClass('pp-shortcode-tooltip--visible');
        setTimeout(function () { $tooltip.removeClass('pp-shortcode-tooltip--visible'); }, 2000);
      });
    });

  });
})(jQuery);
