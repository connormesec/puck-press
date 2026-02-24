jQuery(document).ready(function ($) {
    $('#pp-get-example-posts').on('click', function (e) {
        e.preventDefault();

        const $button = $(this);
        const $result = $('#pp-example-posts-result');
        const $container = $('#pp-example-posts-container');
        const $grid = $('#pp-posts-grid');

        $button.prop('disabled', true);
        $result.html('<span class="pp-loading">Loading example posts...</span>');

        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'pp_get_example_posts',
                nonce: ppInstaPost.nonce
            },
            success: (response) => {
                if (response.success) {
                    console.log('Response data:', response.data);
                    $result.html(`<span class="pp-success">✓ Loaded ${response.data.length} example posts</span>`);

                    // Clear existing posts
                    $grid.empty();

                    // Display posts
                    response.data.forEach((post) => {
                        let postHtml = '<div class="pp-post-item">';
                        if (post.image_url) {
                            postHtml += `<img src="data:image/jpeg;base64,${post.image_buffer}" alt="Instagram post" class="pp-post-image" />`;
                        }
                        postHtml += `<div class="pp-post-title">${post.post_title}</div>`;
                        postHtml += `<div class="pp-post-caption">${post.post_body}</div>`;
                        postHtml += `<div class="pp-post-meta">Slug: ${post.slug}</div>`;
                        postHtml += '</div>';

                        $grid.append(postHtml);
                    });

                    $container.show();
                } else {
                    $result.html(`<span class="pp-error">✗ ${response.data}</span>`);
                }
            },
            error: (err) => {
                $result.html('<span class="pp-error">✗ Failed to load posts</span>');
                console.error(err);
            },
            complete: () => {
                $button.prop('disabled', false);
            }
        });
    });

    $('#pp-get-example-posts-and-create').on('click', function (e) {
        e.preventDefault();

        const $button = $(this);
        const $result = $('#pp-example-posts-result');
        const $container = $('#pp-example-posts-container');
        const $grid = $('#pp-posts-grid');

        $button.prop('disabled', true);
        $result.html('<span class="pp-loading">Creating posts...</span>');

        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'pp_get_example_posts_and_create',
                nonce: ppInstaPost.nonce
            },
            success: (response) => {
                if (response.success) {
                    console.log('Response data:', response.data);
                    $result.html(`<span class="pp-success">✓ Created ${response.data.successful_imports.length} example posts</span>`);

                    // Clear existing posts
                    $grid.empty();

                    // Display posts
                    response.data.successful_imports.forEach((post) => {
                        let postHtml = '<div class="pp-post-item">';
                        if (post.image_url) {
                            postHtml += `<img src="data:image/jpeg;base64,${post.image_buffer}" alt="Instagram post" class="pp-post-image" />`;
                        }
                        postHtml += `<div class="pp-post-title">${post.post_title}</div>`;
                        postHtml += `<div class="pp-post-caption">${post.post_body}</div>`;
                        postHtml += `<div class="pp-post-meta">Slug: ${post.slug}</div>`;
                        postHtml += `<div class="pp-post-meta">Post ID: ${post.post_id}</div>`;
                        postHtml += '</div>';

                        $grid.append(postHtml);
                    });

                    response.data.failed_imports.forEach((post) => {
                        let postHtml = '<div class="pp-post-item">';
                        postHtml += `<div class="pp-post-title" style="color: red;">${post.error}</div>`;
                        if (post.post_data.image_url) {
                            postHtml += `<img src="data:image/jpeg;base64,${post.post_data.image_buffer}" alt="Instagram post" class="pp-post-image" />`;
                        }
                        postHtml += `<div class="pp-post-title">${post.post_data.post_title}</div>`;
                        postHtml += `<div class="pp-post-caption">${post.post_data.post_body}</div>`;
                        postHtml += `<div class="pp-post-meta">Slug: ${post.post_data.slug}</div>`;
                        postHtml += '</div>';

                        $grid.append(postHtml);
                    });

                    $container.show();
                } else {
                    console.log('Error response data:', response);
                    $result.html(`<span class="pp-error">✗ ${response.data}</span>`);
                }
            },
            error: (err) => {
                $result.html('<span class="pp-error">✗ Failed to load posts</span>');
                console.error(err);
            },
            complete: () => {
                $button.prop('disabled', false);
            }
        });
    });
});
