jQuery(document).ready(function ($) {
    $('#pp-test-openai').on('click', function (e) {
        e.preventDefault();
        let $result = $('#pp-test-openai-result');
        $result.text('Testing...');
        $.post(ppGameSummary.ajax_url, {
            action: 'pp_test_openai_api',
            nonce: ppGameSummary.nonce
        }, function (response) {
            if (response.success) {
                $result.text(response.data);
            } else {
                $result.text('Error: ' + response.data.message);
            }
        });
    });

    let clickedBtn = null;

    $('#pp-game-summary-form button[type="submit"]').on('click', function () {
        clickedBtn = $(this);
    });

    $('#pp-game-summary-form').on('submit', function (e) {
        e.preventDefault();

        const $form = $(this);
        const $result = $('#pp-game-summary-result');

        const gameId = $('#pp-game-select').val();
        const sourceType = $('#pp-game-select option:selected').data('source-type');
        const actionType = clickedBtn ? clickedBtn.val() : 'generate'; // "generate" or "generate_and_post"

        if (!gameId) {
            alert('Please select a game first.');
            return;
        }

        // Disable clicked button + show spinner
        clickedBtn.prop('disabled', true);
        const originalText = clickedBtn.text();
        clickedBtn.html(
            '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Loading...'
        );

        $result.empty();

        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'pp_create_game_summary',
                nonce: $('#pp_game_summary_nonce_field').val(),
                game_id: gameId,
                source_type: sourceType,
                pp_action: actionType // <--- tells PHP which button
            },
            success: function (res) {
                console.log(res);
                if (res.success && res.data) {
                    console.log(res.data);

                    let html = '';

                    // Image (if present)
                    if (res.data.image) {
                        html += `
                        <div class="pp-summary-image mb-3">
                            <img src="data:image/png;base64,${res.data.image}"
                                 alt="Game Summary"
                                 style="width: 600px; max-width: 100%;" />
                        </div>
                    `;
                    }

                    // Blog text (if present)
                    if (res.data.blog_data.body && res.data.blog_data.title) {
                        html += `
                        <div class="pp-summary-blog">
                            <h3>Game Summary</h3>
                            <h4>${res.data.blog_data.title}</h4>
                            <p>${res.data.blog_data.body.replace(/\n/g, '<br/>')}</p>
                        </div>
                    `;
                    }

                    // Errors (optional warnings)
                    if (res.data.errors && res.data.errors.length > 0) {
                        html += `
                        <div class="pp-summary-errors" style="color: red;">
                            <strong>Warnings:</strong>
                            <ul>
                                ${res.data.errors.map(e => `<li>${e}</li>`).join('')}
                            </ul>
                        </div>
                    `;
                    }

                    $result.html(html || '<p>No summary data returned.</p>');
                } else {
                    $result.html('<p>Failed to load summary.</p>');
                }
            },
            error: function (err) {
                console.error(err);
                $result.html('<p style="color:red">Error creating summary.</p>');
            },
            complete: function () {
                clickedBtn.prop('disabled', false).html(originalText);
            }
        });
    });


});


