<?php

if (!defined('ABSPATH')) {
    exit;
}

class Puck_Press_Admin_Instagram_Post_Importer_Display
{
    public function render()
    {
        // Manual save for API keys
        if (isset($_POST['pp_save_keys'])) {
            check_admin_referer('pp_insta_post_nonce');

            update_option('pp_insta_scraper_api_key', sanitize_text_field($_POST['pp_insta_scraper_api_key']));
            update_option('pp_insta_handle', sanitize_text_field($_POST['pp_insta_handle']));

            // Save feature toggle checkbox
            $enabled = isset($_POST['pp_enable_insta_post']) ? 1 : 0;
            update_option('pp_enable_insta_post', $enabled);

            echo '<div class="updated"><p>Settings saved.</p></div>';
        }
        $api_key  = esc_attr(get_option('pp_insta_scraper_api_key', ''));
        $insta_handle = esc_attr(get_option('pp_insta_handle', ''));
        $enabled    = get_option('pp_enable_insta_post', 0);
?>
        <div class="wrap">
            <h1>Instagram Post Maker</h1>
            <form method="post">
                <?php wp_nonce_field('pp_insta_post_nonce'); ?>
                <table class="form-table">
                    <tr>
                        <th scope="row">Enable Instagram Post Import Feature</th>
                        <td>
                            <label>
                                <input type="checkbox" name="pp_enable_insta_post" value="1" <?php checked($enabled, 1); ?> />
                                Enable automatic posting of Instagram posts
                            </label>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="pp_insta_scraper_api_key">Instagram API Key</label></th>
                        <td><input type="text" name="pp_insta_scraper_api_key" id="pp_insta_scraper_api_key"
                                value="<?php echo $api_key; ?>" class="regular-text" /></td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="pp_insta_handle">Instagram Handle</label></th>
                        <td><input type="text" name="pp_insta_handle" id="pp_insta_handle"
                                value="<?php echo $insta_handle; ?>" class="regular-text" /></td>
                    </tr>
                </table>
                <?php submit_button('Save Settings', 'primary', 'pp_save_keys'); ?>
            </form>

            <h2>Test API Connections</h2>
            <p>
                <button class="button button-secondary" id="pp-get-example-posts">Get Example Posts</button>
                <button class="button button-secondary" id="pp-get-example-posts-and-create">Get Example Posts And Create Posts</button>
                <span id="pp-example-posts-result"></span>
            </p>

            <div id="pp-example-posts-container" style="display: none; margin-top: 20px;">
                <h3>Example Instagram Posts</h3>
                <div id="pp-posts-grid" style="display: grid; grid-template-columns: repeat(auto-fill, minmax(300px, 1fr)); gap: 20px;">
                    <!-- Posts will be loaded here via AJAX -->
                </div>
            </div>

            <style>
                .pp-post-item {
                    border: 1px solid #ddd;
                    border-radius: 8px;
                    padding: 15px;
                    background: #fff;
                    box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
                }

                .pp-post-image {
                    width: 100%;
                    height: 200px;
                    object-fit: cover;
                    border-radius: 4px;
                    margin-bottom: 10px;
                }

                .pp-post-caption {
                    font-size: 14px;
                    line-height: 1.4;
                    color: #333;
                    margin-bottom: 8px;
                }

                .pp-post-title {
                    font-weight: bold;
                    font-size: 14px;
                    line-height: 1.4;
                    color: #333;
                    margin-bottom: 6px;
                }

                .pp-post-meta {
                    font-size: 12px;
                    color: #666;
                    display: flex;
                    justify-content: space-between;
                    align-items: center;
                }

                .pp-loading {
                    color: #0073aa;
                    font-style: italic;
                }

                .pp-error {
                    color: #dc3232;
                }

                .pp-success {
                    color: #46b450;
                }
            </style>
        </div>
<?php
    }

    public function ajax_get_example_posts()
    {
        // Verify nonce for security
        check_ajax_referer('pp_insta_post_nonce', 'nonce');

        include_once plugin_dir_path(dirname(__FILE__)) . '../../includes/instagram-post-importer/class-puck-press-instagram-post-importer.php';

        // Call your importer logic
        $importer = new Puck_Press_Instagram_Post_Importer();
        $result = $importer->fetch_instagram_posts();

        if ($result['success']) {
            wp_send_json_success($result['data']);
        } else {
            wp_send_json_error($result['message']);
        }
    }

    public function ajax_get_example_posts_and_create()
    {
        // Verify nonce for security
        check_ajax_referer('pp_insta_post_nonce', 'nonce');

        include_once plugin_dir_path(dirname(__FILE__)) . '../../includes/instagram-post-importer/class-puck-press-instagram-post-importer.php';

        // Call your importer logic
        $importer = new Puck_Press_Instagram_Post_Importer();
        $existing_post_slugs = $importer->get_instagram_post_slugs(-1);
        $fetch_result = $importer->fetch_instagram_posts($existing_post_slugs);

        if (!$fetch_result['success']) {
            wp_send_json_error($fetch_result['message']);
            return;
        }

        $successful_imports = [];
        $failed_imports = [];

        foreach ($fetch_result['data'] as $post_data) {
            $title = isset($post_data['post_title']) ? $post_data['post_title'] : 'Instagram Post';
            $content = isset($post_data['post_body']) ? $post_data['post_body'] : '';
            $b64_image = isset($post_data['image_buffer']) ? $post_data['image_buffer'] : '';
            $image_name = 'insta-' . $post_data['slug'] . '.jpg';
            $slug = isset($post_data['slug']) ? $post_data['slug'] : '';

            // Double check to make sure slug doesn't already exist | also accounts for -1, -2, etc. suffixes
            if (in_array($slug, $existing_post_slugs, true) || preg_grep('/^' . preg_quote($slug, '/') . '-/', $existing_post_slugs)) {
                $failed_imports[] = [
                    'post_data' => $post_data,
                    'error' => 'Post with slug ' . $slug . ' already exists.',
                ];
                continue;
            }

            // Create the post
            $post_id = $importer->create_instagram_post($title, $content, 'publish', $slug, $b64_image, $image_name);

            if (is_wp_error($post_id)) {
                $failed_imports[] = [
                    'post_data' => $post_data,
                    'error' => $post_id->get_error_message(),
                ];
                continue;
            }
            $post_data['post_id'] = $post_id;
            $successful_imports[] =  $post_data;
        }

        return wp_send_json_success([
            'successful_imports' => $successful_imports,
            'failed_imports' => $failed_imports,
        ]);
    }
}
