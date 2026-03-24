jQuery(document).ready(function ($) {
  $('#pp-test-openai').on('click', function (e) {
    e.preventDefault();
    const $result = $('#pp-test-openai-result');
    $result.text('Testing...');
    $.post(ppGameSummary.ajax_url, {
      action: 'pp_test_openai_api',
      nonce: ppGameSummary.nonce
    }, (response) => {
      if (response.success) {
        $result.text(response.data);
      } else {
        $result.text('Error: ' + response.data.message);
      }
    });
  });

  $('#pp-game-summary-form').on('submit', function (e) {
    e.preventDefault();

    const $result = $('#pp-game-summary-result');
    const $selected = $('#pp-game-select option:selected');
    const $btn = $('#pp-generate-btn');

    const gameId = $('#pp-game-select').val();
    const sourceType = $selected.data('source-type');
    const teamId = $selected.data('team-id');
    const existingPostLink = $selected.data('post-link') || '';

    if (!gameId) {
      alert('Please select a game first.');
      return;
    }

    $btn.prop('disabled', true).text('Generating...');
    $result.empty();

    $.ajax({
      url: ajaxurl,
      type: 'POST',
      data: {
        action: 'pp_create_game_summary',
        nonce: $('#pp_game_summary_nonce_field').val(),
        game_id: gameId,
        source_type: sourceType,
        team_id: teamId,
        pp_action: 'generate'
      },
      success: (res) => {
        if (!res.success || !res.data) {
          $result.html('<p style="color:red">Failed to generate summary.</p>');
          return;
        }

        const data = res.data;
        let html = '';

        if (existingPostLink) {
          html += `<div class="notice notice-warning inline" style="margin-bottom:12px;">
            <p>A post already exists for this game: <a href="${existingPostLink}" target="_blank">${existingPostLink}</a>. Publishing will overwrite it.</p>
          </div>`;
        }

        if (data.image) {
          html += `<div class="pp-summary-image" style="margin-bottom:12px;">
            <img src="data:image/png;base64,${data.image}" alt="Game Summary" style="width:600px;max-width:100%;" />
          </div>`;
        }

        if (data.blog_data && data.blog_data.title && data.blog_data.body) {
          html += `<div class="pp-summary-blog" style="margin-bottom:12px;">
            <h3>Game Summary</h3>
            <h4>${data.blog_data.title}</h4>
            <p>${data.blog_data.body.replace(/\n/g, '<br/>')}</p>
          </div>`;
        }

        if (data.errors && data.errors.length > 0) {
          html += `<div class="pp-summary-errors" style="color:red;margin-bottom:12px;">
            <strong>Warnings:</strong>
            <ul>${data.errors.map(e => `<li>${e}</li>`).join('')}</ul>
          </div>`;
        }

        const publishLabel = existingPostLink ? 'Overwrite Post' : 'Publish Post';
        html += `<button id="pp-publish-btn" class="button button-primary">${publishLabel}</button>`;

        $result.html(html);

        $('#pp-publish-btn').on('click', function () {
          const $publishBtn = $(this);
          $publishBtn.prop('disabled', true).text('Publishing...');

          $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
              action: 'pp_create_game_summary',
              nonce: $('#pp_game_summary_nonce_field').val(),
              game_id: gameId,
              source_type: sourceType,
              team_id: teamId,
              pp_action: 'generate_and_post',
              overwrite: existingPostLink ? '1' : '0'
            },
            success: (publishRes) => {
              if (publishRes.success && publishRes.data && publishRes.data.post_link && !publishRes.data.post_link.data) {
                const newLink = publishRes.data.post_link;

                // Update the dropdown option to reflect the new post link
                $selected.attr('data-post-link', newLink);
                const currentText = $selected.text().replace(' ✓', '');
                $selected.text(currentText + ' ✓');

                $publishBtn.replaceWith(
                  `<p style="color:green">Post published: <a href="${newLink}" target="_blank">${newLink}</a></p>`
                );
              } else {
                const errMsg = publishRes.data && publishRes.data.errors && publishRes.data.errors.length
                  ? publishRes.data.errors.join(', ')
                  : 'Unknown error';
                $publishBtn.prop('disabled', false).text(publishLabel);
                $result.prepend(`<div class="notice notice-error inline"><p>Publish failed: ${errMsg}</p></div>`);
              }
            },
            error: () => {
              $publishBtn.prop('disabled', false).text(publishLabel);
              $result.prepend('<div class="notice notice-error inline"><p>Publish request failed.</p></div>');
            }
          });
        });
      },
      error: () => {
        $result.html('<p style="color:red">Error generating summary.</p>');
      },
      complete: () => {
        $btn.prop('disabled', false).text('Create Game Summary');
      }
    });
  });
});
