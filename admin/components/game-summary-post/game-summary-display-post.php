<?php

if (!defined('ABSPATH')) {
    exit;
}

class Puck_Press_Admin_Game_Summary_Post_Display
{
    public function render()
    {
        // Manual save for API keys
        if (isset($_POST['pp_save_keys'])) {
            check_admin_referer('pp_save_game_summary_keys');

            update_option('pp_openai_api_key', sanitize_text_field($_POST['pp_openai_api_key']));
            update_option('pp_image_api_key', sanitize_text_field($_POST['pp_image_api_key']));

            // Save feature toggle checkbox
            $enabled = isset($_POST['pp_enable_game_summary_post']) ? 1 : 0;
            update_option('pp_enable_game_summary_post', $enabled);

            echo '<div class="updated"><p>Settings saved.</p></div>';
        }

        $openai_key = esc_attr(get_option('pp_openai_api_key', ''));
        $image_key  = esc_attr(get_option('pp_image_api_key', ''));
        $enabled    = get_option('pp_enable_game_summary_post', 0)
?>
        <div class="wrap">
            <h1>Game Summary Post Maker</h1>
            <form method="post">
                <?php wp_nonce_field('pp_save_game_summary_keys'); ?>
                <table class="form-table">
                    <tr>
                        <th scope="row">Enable Game Summary Post Feature</th>
                        <td>
                            <label>
                                <input type="checkbox" name="pp_enable_game_summary_post" value="1" <?php checked($enabled, 1); ?> />
                                Enable automatic creation of game summary posts
                            </label>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="pp_openai_api_key">OpenAI API Key</label></th>
                        <td><input type="text" name="pp_openai_api_key" id="pp_openai_api_key"
                                value="<?php echo $openai_key; ?>" class="regular-text" /></td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="pp_image_api_key">Image API Key</label></th>
                        <td><input type="text" name="pp_image_api_key" id="pp_image_api_key"
                                value="<?php echo $image_key; ?>" class="regular-text" /></td>
                    </tr>
                </table>
                <?php submit_button('Save Settings', 'primary', 'pp_save_keys'); ?>
            </form>

            <h2>Test API Connections</h2>
            <p>
                <button class="button button-secondary" id="pp-test-openai">Test OpenAI API</button>
                <span id="pp-test-openai-result"></span>
            </p>

            <?php $this->render_game_summary_test(); ?>
        </div>
    <?php
    }

    public function ajax_test_openai()
    {
        check_ajax_referer('pp_game_summary_nonce', 'nonce');

        $api_key = get_option('pp_openai_api_key');
        if (empty($api_key)) {
            wp_send_json_error('OpenAI API key not set.');
        }

        $response = wp_remote_post('https://api.openai.com/v1/chat/completions', [
            'headers' => [
                'Authorization' => 'Bearer ' . $api_key,
                'Content-Type'  => 'application/json',
            ],
            'body' => wp_json_encode([
                'model' => 'gpt-4o-mini',
                'messages' => [
                    ['role' => 'user', 'content' => 'Write a short sample hockey game summary.']
                ],
                'max_tokens' => 100,
            ]),
        ]);

        if (is_wp_error($response)) {
            wp_send_json_error($response->get_error_message());
        }

        $code = wp_remote_retrieve_response_code($response);
        if ($code !== 200) {
            wp_send_json_error('OpenAI API returned status: ' . $code);
        }

        $body = json_decode(wp_remote_retrieve_body($response), true);
        $text = $body['choices'][0]['message']['content'] ?? 'No content returned';

        wp_send_json_success($text);
    }

    function pp_create_game_summary()
    {
        check_ajax_referer('pp_game_summary_nonce', 'nonce');

        $api_key = get_option('pp_image_api_key');
        if (empty($api_key)) {
            wp_send_json_error(['error' => 'Image API key not set.']);
        }

        $game_id     = isset($_POST['game_id']) ? intval($_POST['game_id']) : 0;
        $source_type = isset($_POST['source_type']) ? sanitize_text_field($_POST['source_type']) : '';

        if (!$game_id || !$source_type) {
            wp_send_json_error(['error' => 'No game ID or source type provided.']);
        }

        if (isset($_POST['pp_action'])) {
            $action = sanitize_text_field($_POST['pp_action']);

            include_once plugin_dir_path(dirname(__FILE__)) . '../../includes/game-summary-post/class-puck-press-post-game-summary-initiator.php';

            if ($action === 'generate') {

                $initiator = new Puck_Press_Post_Game_Summary_Initiator($game_id, $source_type);

                $data = [
                    'image' => null,
                    'blog_data'  => null,
                    'errors' => [],
                ];

                // Step 1: get summary data
                try {
                    $summary = $initiator->returnGameDataInImageAPIFormat();
                } catch (Throwable $e) {
                    wp_send_json_error(['error' => 'Failed to generate summary data', 'details' => $e->getMessage()]);
                }

                // Step 2: always try to generate the image
                try {
                    $data['image'] = $initiator->getImageFromImageAPI($summary);
                } catch (Throwable $e) {
                    $data['errors'][] = 'Image generation failed: ' . $e->getMessage();
                }

                // Step 3: try blog summary, but don’t fail hard if it breaks
                try {
                    $data['blog_data'] = $initiator->getGameSummaryFromBlogAPI($summary);
                } catch (Throwable $e) {
                    $data['errors'][] = 'Blog summary failed: ' . $e->getMessage();
                    // Leave $data['blog'] as null
                }

                wp_send_json_success($data);
                wp_die();
            } elseif ($action === 'generate_and_post') {
                include_once plugin_dir_path(dirname(__FILE__)) . '../../includes/game-summary-post/class-puck-press-game-post-creator.php';
                $post_creator = new Puck_Press_Game_Post_Creator();
                $initiator = new Puck_Press_Post_Game_Summary_Initiator($game_id, $source_type);

                $data = [
                    'image' => null,
                    'blog_data'  => null,
                    'errors' => [],
                ];

                // Step 1: get summary data
                try {
                    $summary = $initiator->returnGameDataInImageAPIFormat();
                } catch (Throwable $e) {
                    wp_send_json_error(['error' => 'Failed to generate summary data', 'details' => $e->getMessage()]);
                }

                // Step 2: always try to generate the image
                try {
                    $data['image'] = $initiator->getImageFromImageAPI($summary);
                } catch (Throwable $e) {
                    $data['errors'][] = 'Image generation failed: ' . $e->getMessage();
                }

                // Step 3: try blog summary, but don’t fail hard if it breaks
                try {
                    $data['blog_data'] = $initiator->getGameSummaryFromBlogAPI($summary);
                } catch (Throwable $e) {
                    $data['errors'][] = 'Blog summary failed: ' . $e->getMessage();
                }

                try {
                    if (empty($data['blog_data']) || empty($data['image'])) {
                        throw new Exception('Missing blog text or image for post creation.');
                    } else {
                        $data['post_link'] = $post_creator->testCreatePost($game_id, $data['blog_data']['body'], $data['blog_data']['title'], $data['image']);
                    }
                } catch (Throwable $e) {
                    $data['errors'][] = 'Post creation failed: ' . $e->getMessage();
                }

                wp_send_json_success($data);
                wp_die();
            } else {
                wp_send_json_error(['error' => 'Unknown action.']);
            }
        }
    }

    function render_game_summary_test()
    {
        global $wpdb;

        $table = $wpdb->prefix . "pp_game_schedule_for_display"; // adjust if different
        $today = current_time('Y-m-d');

        // Query past games
        $games = $wpdb->get_results(
            $wpdb->prepare("
            SELECT game_id, source_type, game_timestamp, game_date_day, opponent_team_name, target_score, opponent_score
            FROM $table
            WHERE game_timestamp < %s
            ORDER BY game_timestamp DESC
            LIMIT 50
        ", $today)
        );

    ?>
        <h1>Select Past Game</h1>

        <form id="pp-game-summary-form" method="post">
            <?php wp_nonce_field('pp_game_summary_nonce', 'pp_game_summary_nonce_field'); ?>

            <select name="game_id" id="pp-game-select">
                <option value="">-- Select a past game --</option>
                <?php foreach ($games as $game): ?>
                    <option value="<?php echo esc_attr($game->game_id); ?>"
                        data-source-type="<?php echo esc_attr($game->source_type); ?>">
                        <?php echo esc_html("{$game->game_date_day} vs {$game->opponent_team_name} ({$game->target_score} - {$game->opponent_score})"); ?>
                    </option>
                <?php endforeach; ?>
            </select>

            <button type="submit" name="pp_action" value="generate" class="button button-primary">
                Create Game Summary
            </button>
            <button type="submit" name="pp_action" value="generate_and_post" class="button">
                Create Summary & Publish Post
            </button>
        </form>

        <div id="pp-game-summary-result"></div>
<?php
    }
}
