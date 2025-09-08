<?php

if (!defined('ABSPATH')) {
    exit;
}

class Puck_Press_Instagram_Post_Importer
{
    public function run_daily()
    {
        $messages = [];
        if (!get_option('pp_enable_insta_post', 0)) {
            $messages[] = 'Instagram import feature is disabled.';
            return $messages;
        }
        $existing_post_slugs = $this->get_instagram_post_slugs(-1);
        $fetch_result = $this->fetch_instagram_posts($existing_post_slugs);

        if (!$fetch_result['success']) {
            $messages[] = 'Error fetching Instagram posts: ' . $fetch_result['message'];
            return;
        }

        foreach ($fetch_result['data'] as $post_data) {
            $title = isset($post_data['post_title']) ? $post_data['post_title'] : 'Instagram Post';
            $content = isset($post_data['post_body']) ? $post_data['post_body'] : '';
            $b64_image = isset($post_data['image_buffer']) ? $post_data['image_buffer'] : '';
            $image_name = 'insta-' . $post_data['slug'] . '.jpg';
            $slug = isset($post_data['slug']) ? $post_data['slug'] : '';
            
            // Double check to make sure slug doesn't already exist | also accounts for -1, -2, etc. suffixes
            if (in_array($slug, $existing_post_slugs, true) || preg_grep('/^' . preg_quote($slug, '/') . '-/', $existing_post_slugs)) {
                $messages[] = 'Post with slug ' . $slug . ' already exists.';
                continue;
            }

            // Create the post
            $post_id = $this->create_instagram_post($title, $content, 'publish', $slug, $b64_image, $image_name);

            if (is_wp_error($post_id)) {
                $messages[] = 'Failed to create post for slug ' . $slug . ': ' . $post_id->get_error_message();
                continue;
            }
            $messages[] = 'Successfully created post ID ' . $post_id . ': ' . (mb_strlen($title) > 20 ? mb_substr($title, 0, 20 - 1) . '…' : $title);
        }

        return $messages;
    }

    /**
     * @return array{
     *     success: bool,
     *     data: array<int, array{
     *         slug?: string,
     *         post_title?: string,
     *         post_body?: string,
     *         image_url?: string,
     *         image_buffer?: string,
     *         error?: string
     *     }>
     * }
     */
    public function fetch_instagram_posts($existing_post_ids = array())
    {
        $api_key     = get_option('pp_insta_scraper_api_key', '');
        $insta_handle = get_option('pp_insta_handle', '');

        if (empty($api_key)) {
            return array(
                'success' => false,
                'message' => 'Instagram API key is not set',
            );
        }

        $response = wp_remote_post(
            'https://8qoqtj3pm0.execute-api.us-east-2.amazonaws.com/default/getWpPostFromInstaAPI',
            array(
                'headers' => array(
                    'Authorization' => 'Bearer ' . $api_key,
                    'Content-Type'  => 'application/json',
                ),
                'timeout' => 30,
                'body'    => wp_json_encode(array('instagram_handle' => $insta_handle, 'existing_post_ids' => $existing_post_ids)),
            )
        );

        if (is_wp_error($response)) {
            return array(
                'success' => false,
                'message' => 'Failed to fetch posts: ' . $response->get_error_message(),
            );
        }

        $response_code = wp_remote_retrieve_response_code($response);
        if ($response_code !== 200) {
            return array(
                'success' => false,
                'message' => 'API returned error code: ' . $response_code,
            );
        }

        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            return array(
                'success' => false,
                'message' => 'Invalid JSON response from API',
            );
        }
        // Format posts
        $formatted_posts = array();
        if (!empty($data['new_posts']) && is_array($data['new_posts'])) {
            foreach ($data['new_posts'] as $post) {
                // Ensure all required fields exist and are not empty
                if (empty($post['postSlug']) || empty($post['postTitle']) || empty($post['imgSrc']) || empty($post['featuredImageBuffer'])) {
                    $formatted_posts[] = array(
                        'error' => 'Missing required fields in post data',
                    );
                    continue; // skip this post
                }
                $formatted_posts[] = array(
                    'slug'         => $post['postSlug'],
                    'post_title'   => $post['postTitle'],
                    'post_body'    => $post['postText'] ?? '',
                    'image_url'    => $post['imgSrc'],
                    'image_buffer' => $post['featuredImageBuffer'],
                );
            }
        }

        return array(
            'success' => true,
            'data'    => $formatted_posts,
        );
    }

    /**
     * Create a WordPress post with the "Instagram" tag and optional featured image from base64 buffer.
     *
     * @param string $title        The post title.
     * @param string $content      The post content.
     * @param string $status       Post status (default 'publish').
     * @param string $slug         Optional post slug (defaults to auto-generated from title).
     * @param string $b64_image    Optional base64-encoded image buffer.
     * @param string $image_name   Optional filename for the image (default 'instagram-image.png').
     *
     * @return int|WP_Error Post ID on success, WP_Error on failure.
     */
    public function create_instagram_post($title, $content, $status = 'publish', $slug = '', $b64_image = '', $image_name = 'instagram-image.png')
    {
        // ✅ Ensure "Instagram" tag exists
        $tag_id = term_exists('Instagram', 'post_tag');
        if ($tag_id) {
            $tag_id = (int) $tag_id['term_id'];
        } else {
            $tag    = wp_insert_term('Instagram', 'post_tag');
            $tag_id = ! is_wp_error($tag) ? (int) $tag['term_id'] : 0;
        }

        if (! $tag_id) {
            return new WP_Error('tag_error', 'Could not create or retrieve Instagram tag.');
        }

        // ✅ Create the post
        $postarr = array(
            'post_title'   => $title,
            'post_content' => $content,
            'post_status'  => $status,
            'post_author'  => get_current_user_id(),
        );

        // Sanitize slug if provided
        if (!empty($slug)) {
            $slug = sanitize_title($slug);

            // Check if a post with this slug already exists
            $existing_post = get_page_by_path($slug, OBJECT, 'post');
            if ($existing_post) {
                return new WP_Error('post_exists', 'A post with this slug already exists.');
            }
            $postarr['post_name'] = $slug;
        }

        $post_id = wp_insert_post($postarr, true); // true = return WP_Error on fail
        if (is_wp_error($post_id)) {
            return $post_id;
        }

        // ✅ Assign the "Instagram" tag
        wp_set_post_terms($post_id, array($tag_id), 'post_tag', true);

        // ✅ Handle featured image if provided
        if (! empty($b64_image)) {
            $image_id = $this->save_base64_image_to_media($b64_image, $image_name, $post_id);

            if ($image_id && ! is_wp_error($image_id)) {
                set_post_thumbnail($post_id, $image_id);
            }
        }

        return $post_id;
    }


    /**
     * Save a base64 image buffer to the WordPress media library.
     *
     * @param string $b64_image  Base64-encoded image string.
     * @param string $filename   Filename to save as.
     * @param int    $post_id    Optional post ID to attach to.
     *
     * @return int|WP_Error Attachment ID on success, WP_Error on failure.
     */
    private function save_base64_image_to_media($b64_image, $filename = 'instagram-image.png', $post_id = 0)
    {
        // Decode base64
        $decoded = base64_decode($b64_image);
        if (! $decoded) {
            return new WP_Error('b64_decode_error', 'Failed to decode base64 image.');
        }

        // Upload to WP uploads dir
        $upload_dir = wp_upload_dir();
        $file_path  = trailingslashit($upload_dir['path']) . $filename;

        file_put_contents($file_path, $decoded);

        // Check file type
        $filetype = wp_check_filetype($filename, null);

        // Create attachment
        $attachment = array(
            'post_mime_type' => $filetype['type'],
            'post_title'     => sanitize_file_name(pathinfo($filename, PATHINFO_FILENAME)),
            'post_content'   => '',
            'post_status'    => 'inherit',
        );

        $attach_id = wp_insert_attachment($attachment, $file_path, $post_id);

        // Generate metadata
        require_once ABSPATH . 'wp-admin/includes/image.php';
        $attach_data = wp_generate_attachment_metadata($attach_id, $file_path);
        wp_update_attachment_metadata($attach_id, $attach_data);

        return $attach_id;
    }

    /**
     * Retrieve an array of post slugs for all posts tagged with "Instagram".
     *
     * @param int $limit Number of posts to return. Use -1 for all posts.
     * @return string[] Array of post slugs.
     */
    function get_instagram_post_slugs($limit = -1)
    {
        $args = array(
            'tag_slug__in'    => array('instagram'), // filter by tag slug
            'posts_per_page'  => $limit,
            'post_status'     => 'publish',
            'fields'          => 'ids', // retrieve just post IDs for better performance
        );

        $query = new WP_Query($args);
        $slugs = array();

        if ($query->have_posts()) {
            foreach ($query->posts as $post_id) {
                $slug = get_post_field('post_name', $post_id);
                if ($slug) {
                    $slugs[] = $slug;
                }
            }
        }

        wp_reset_postdata(); // reset after custom query
        return $slugs;
    }


    /**
     * Convert a base64 image buffer into an HTML <img> tag with automatic MIME type detection.
     *
     * @param string $base64_buffer The base64-encoded image data.
     * @param string $alt           Alt text for the image.
     * @param string $class         Optional CSS classes for the image tag.
     *
     * @return string HTML <img> tag with inline base64 image or empty string if invalid.
     */
    function base64_to_img_tag_auto_mime($base64_buffer, $alt = '', $class = '')
    {
        // Validate base64 string
        if (empty($base64_buffer) || !is_string($base64_buffer)) {
            return '';
        }

        // Decode just enough of the base64 string to detect MIME type
        $decoded = base64_decode(substr($base64_buffer, 0, 50));

        $mime_type = 'image/png'; // default
        if (substr($decoded, 0, 4) === "\x89PNG") {
            $mime_type = 'image/png';
        } elseif (substr($decoded, 0, 3) === "\xFF\xD8\xFF") {
            $mime_type = 'image/jpeg';
        } elseif (substr($decoded, 0, 6) === "GIF87a" || substr($decoded, 0, 6) === "GIF89a") {
            $mime_type = 'image/gif';
        }

        // Sanitize alt text and class
        $alt   = esc_attr($alt);
        $class = esc_attr($class);

        // Build data URI
        $src = 'data:' . $mime_type . ';base64,' . $base64_buffer;

        // Return HTML img tag
        return '<img src="' . esc_url($src) . '" alt="' . $alt . '" class="' . $class . '" />';
    }
}
