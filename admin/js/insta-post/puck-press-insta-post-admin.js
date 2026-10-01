jQuery(document).ready(function ($) {
  // ── Team handle save ────────────────────────────────────────────────────────

  $(document).on('click', '.pp-save-team-handle', function () {
    const $btn    = $(this);
    const teamId  = $btn.data('team-id');
    const $row    = $btn.closest('tr');
    const handle  = $row.find('.pp-team-handle').val().trim();
    const enabled = $row.find('.pp-team-enabled').is(':checked') ? '1' : '0';
    const $result = $row.find('.pp-save-team-result');

    $btn.prop('disabled', true);
    $result.html('');

    $.ajax({
      url: ajaxurl,
      type: 'POST',
      data: {
        action:  'pp_save_team_handle',
        nonce:   ppInstaPost.nonce,
        team_id: teamId,
        handle:  handle,
        enabled: enabled,
      },
      success: (response) => {
        if (response.success) {
          $result.html('<span class="pp-success">Saved</span>');
        } else {
          $result.html('<span class="pp-error">' + response.data + '</span>');
        }
      },
      error: () => {
        $result.html('<span class="pp-error">Request failed</span>');
      },
      complete: () => {
        $btn.prop('disabled', false);
      },
    });
  });

  // ── Fetch test posts ────────────────────────────────────────────────────────

  $('#pp-fetch-team-posts').on('click', function () {
    const teamId = $('#pp-test-team-select').val();
    if (!teamId) {
      $('#pp-fetch-result').html('<span class="pp-error">Select a team first.</span>');
      return;
    }

    const $btn       = $(this);
    const $result    = $('#pp-fetch-result');
    const $container = $('#pp-test-posts-container');
    const $grid      = $('#pp-test-posts-grid');

    $btn.prop('disabled', true);
    $result.html('<span class="pp-loading">Fetching posts…</span>');
    $container.hide();
    $grid.empty();

    $.ajax({
      url: ajaxurl,
      type: 'POST',
      data: {
        action:  'pp_get_team_example_posts',
        nonce:   ppInstaPost.nonce,
        team_id: teamId,
      },
      success: (response) => {
        if (!response.success) {
          $result.html('<span class="pp-error">✗ ' + response.data + '</span>');
          return;
        }

        const posts = response.data;
        $result.html('<span class="pp-success">✓ ' + posts.length + ' post(s) fetched</span>');

        if (posts.length === 0) {
          $grid.html('<p>No new posts found.</p>');
          $container.show();
          return;
        }

        posts.forEach((post) => {
          const $card = buildPostCard(post, teamId);
          $grid.append($card);
        });

        $container.show();
      },
      error: () => {
        $result.html('<span class="pp-error">✗ Request failed</span>');
      },
      complete: () => {
        $btn.prop('disabled', false);
      },
    });
  });

  // ── Build a post preview card ────────────────────────────────────────────────

  function buildPostCard(post, teamId) {
    const $card = $('<div class="pp-post-item"></div>');

    if (post.image_buffer) {
      $card.append(
        $('<img class="pp-post-image" alt="Instagram post" />').attr(
          'src',
          'data:image/jpeg;base64,' + post.image_buffer
        )
      );
    }

    $card.append('<div class="pp-post-title">' + escapeHtml(post.post_title) + '</div>');
    $card.append('<div class="pp-post-caption">' + escapeHtml(post.post_body) + '</div>');
    $card.append('<div class="pp-post-meta">Slug: ' + escapeHtml(post.slug) + '</div>');
    $card.append('<div class="pp-post-meta">ID: ' + escapeHtml(post.insta_id) + '</div>');

    const $actions   = $('<div class="pp-post-actions"></div>');
    const $createBtn = $('<button class="button button-primary pp-create-post-btn">Create Post</button>');
    const $status    = $('<span class="pp-create-status"></span>');

    $createBtn.on('click', function () {
      handleCreatePost($(this), $status, post, teamId);
    });

    $actions.append($createBtn).append($status);
    $card.append($actions);

    return $card;
  }

  // ── Create a single post ─────────────────────────────────────────────────────

  function handleCreatePost($btn, $status, post, teamId) {
    $btn.prop('disabled', true);
    $status.html('<span class="pp-loading">Creating…</span>');

    $.ajax({
      url: ajaxurl,
      type: 'POST',
      data: {
        action:       'pp_create_team_insta_post',
        nonce:        ppInstaPost.nonce,
        team_id:      teamId,
        insta_id:     post.insta_id,
        post_title:   post.post_title,
        post_body:    post.post_body,
        slug:         post.slug,
        image_buffer: post.image_buffer,
      },
      success: (response) => {
        if (response.success) {
          $status.html('<span class="pp-success">✓ Created (ID ' + response.data.post_id + ')</span>');
          $btn.prop('disabled', true).text('Created');
        } else {
          $status.html('<span class="pp-error">✗ ' + response.data + '</span>');
          $btn.prop('disabled', false);
        }
      },
      error: () => {
        $status.html('<span class="pp-error">✗ Request failed</span>');
        $btn.prop('disabled', false);
      },
    });
  }

  // ── Repair featured images ──────────────────────────────────────────────────

  $('#pp-insta-repair-check').on('click', function () {
    runRepair(true);
  });

  $('#pp-insta-repair-run').on('click', function () {
    runRepair(false);
  });

  function runRepair(dryRun) {
    const $buttons = $('#pp-insta-repair-check, #pp-insta-repair-run');
    const $result  = $('#pp-insta-repair-result');
    const totals   = { relinked: 0, regenerated: 0, errors: 0 };

    $buttons.prop('disabled', true);
    $result.html('<span class="pp-loading">' + (dryRun ? 'Checking…' : 'Repairing…') + '</span>');

    const step = () => {
      $.ajax({
        url: ajaxurl,
        type: 'POST',
        data: {
          action:  'pp_repair_insta_images',
          nonce:   ppInstaPost.nonce,
          dry_run: dryRun ? 1 : 0,
        },
        success: (response) => {
          if (!response.success) {
            $result.html('<span class="pp-error">✗ ' + escapeHtml(response.data) + '</span>');
            $buttons.prop('disabled', false);
            return;
          }

          const report = response.data.report;
          totals.relinked    += report.relinked;
          totals.regenerated += report.regenerated;
          totals.errors      += report.errors;

          // Keep going while the batch made progress and posts are left over.
          const progressed = report.relinked + report.regenerated > 0;
          if (!dryRun && report.deferred > 0 && progressed) {
            $result.html(
              '<span class="pp-loading">Repairing… ' + (totals.relinked + totals.regenerated) +
              ' fixed so far, ' + report.deferred + ' left</span>'
            );
            step();
            return;
          }

          let html = '<span class="' + (report.errors ? 'pp-error' : 'pp-success') + '">' + escapeHtml(response.data.summary) + '</span>';
          if (!dryRun) {
            html += '<br>Total this session: relinked ' + totals.relinked + ', regenerated sizes for ' + totals.regenerated +
              (totals.errors ? ', ' + totals.errors + ' failed' : '') + '.';
          }
          $result.html(html);
          $buttons.prop('disabled', false);
        },
        error: () => {
          $result.html('<span class="pp-error">✗ Request failed</span>');
          $buttons.prop('disabled', false);
        },
      });
    };

    step();
  }

  // ── Utility ──────────────────────────────────────────────────────────────────

  function escapeHtml(str) {
    if (!str) return '';
    return String(str)
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;');
  }
});
