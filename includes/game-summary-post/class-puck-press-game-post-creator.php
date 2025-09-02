<?php
if (! defined('ABSPATH')) exit;

class Puck_Press_Game_Post_Creator
{

    private $table_name;

    public function __construct()
    {
        global $wpdb;
        $this->table_name = $wpdb->prefix . "pp_game_schedule_for_display";
    }

    /**
     * Entry point for cron job
     */
    public function run_daily()
    {
        $messages = [];
        $completed_games = $this->get_recent_completed_games(3);
        if (empty($completed_games)) {
            $messages[] = 'No completed games found.';
            return $messages;
        }
        if (!get_option('pp_enable_game_summary_post', 0)) {
            $messages[] = 'Game summary post feature is disabled.';
            return $messages;
        }

        $existing_slugs = $this->get_existing_game_slugs();
        include_once plugin_dir_path(__FILE__) . 'class-puck-press-post-game-summary-initiator.php';

        foreach ($completed_games as $game) {
            $slug = sanitize_title($game->game_id);
            if (!in_array($slug, $existing_slugs, true)) {
                
                $initiator = new Puck_Press_Post_Game_Summary_Initiator($game->game_id, $game->source_type);

                $data = [
                    'image' => null,
                    'blog_data'  => null,
                    'errors' => [],
                ];

                // Step 1: get summary data
                try {
                    $summary = $initiator->returnGameDataInImageAPIFormat();
                    $messages[] = 'Summary data generated successfully.';
                } catch (Throwable $e) {
                    $messages[] = 'Failed to generate summary data: ' . $e->getMessage();
                    wp_send_json_error(['error' => 'Failed to generate summary data', 'details' => $e->getMessage()]);
                }

                // Step 2: always try to generate the image
                try {
                    $data['image'] = $initiator->getImageFromImageAPI($summary);
                    $messages[] = 'Image generated successfully.';
                } catch (Throwable $e) {
                    $messages[] = 'Failed to generate image: ' . $e->getMessage();
                    $data['errors'][] = 'Image generation failed: ' . $e->getMessage();

                }

                // Step 3: try blog summary, but don’t fail hard if it breaks
                try {
                    $data['blog_data'] = $initiator->getGameSummaryFromBlogAPI($summary);
                    $messages[] = 'Blog summary generated successfully.';
                } catch (Throwable $e) {
                    $messages[] = 'Failed to generate blog summary: ' . $e->getMessage();
                    $data['errors'][] = 'Blog summary failed: ' . $e->getMessage();
                }

                try {
                    if (empty($data['blog_data']) || empty($data['image'])) {
                        $messages[] = 'Missing blog text or image for post creation.';
                        throw new Exception('Missing blog text or image for post creation.');
                    } else {
                        $data['post_link'] = $this->testCreatePost($game->game_id, $data['blog_data']['body'], $data['blog_data']['title'], $data['image']);
                        $messages[] = 'Post created successfully: ' . $data['post_link'];
                    }
                } catch (Throwable $e) {
                    $data['errors'][] = 'Post creation failed: ' . $e->getMessage();
                }
            } else {
                $messages[] = "Post with slug {$slug} already exists. Skipping creation.";
            }
        }
        return $messages;
    }
    /**
     * Get 3 most recent completed games
     */
    private function get_recent_completed_games($limit = 3)
    {
        global $wpdb;

        $sql = $wpdb->prepare("
            SELECT *
            FROM {$this->table_name}
            WHERE game_status REGEXP %s
              AND game_timestamp < NOW()
            ORDER BY game_timestamp DESC
            LIMIT %d
        ", '^Final', $limit);

        return $wpdb->get_results($sql);
    }

    /**
     * Get existing game slugs (post_name) for published game posts
     */
    private function get_existing_game_slugs()
    {
        global $wpdb;

        // Faster than get_posts() when many posts exist
        $sql = "
            SELECT post_name 
            FROM {$wpdb->posts}
            WHERE post_type = 'post'
              AND post_status = 'publish'
        ";

        $results = $wpdb->get_col($sql);
        return array_map('sanitize_title', $results);
    }

    /**
     * Create a new post for the given game, optionally attaching a featured image
     */
    private function create_game_post($slug, $post_body, $post_title, $image_buffer = null)
    {
        $post_data = array(
            'post_title'   => $post_title,
            'post_content' => $post_body,
            'post_status'  => 'publish',
            'post_author'  => 1, // Or change to specific author ID
            'post_name'    => $slug,
        );

        $post_id = wp_insert_post($post_data);

        if (!is_wp_error($post_id)) {
            // Add metadata
            update_post_meta($post_id, '_game_id', $slug);

            // Handle featured image if provided
            if (!empty($image_buffer)) {
                $this->attach_featured_image_from_base64($post_id, $image_buffer, $slug);
            }
        }

        return $post_id;
    }

    /**
     * Decode a base64 image and set it as the featured image for a post
     */
    private function attach_featured_image_from_base64($post_id, $image_buffer, $slug)
    {
        // Clean base64 string (remove data:image/png;base64, if present)
        if (strpos($image_buffer, 'base64,') !== false) {
            $image_buffer = explode('base64,', $image_buffer)[1];
        }

        $decoded = base64_decode($image_buffer);
        if (!$decoded) {
            error_log("Failed to decode base64 image for game post {$post_id}");
            return;
        }

        // Prepare upload directory
        $upload_dir = wp_upload_dir();
        if (!empty($upload_dir['error'])) {
            error_log("Upload dir error: " . $upload_dir['error']);
            return;
        }

        $filename = 'game-summary-' . sanitize_title($slug) . '-' . time() . '.png';
        $file_path = trailingslashit($upload_dir['path']) . $filename;

        // Save file
        file_put_contents($file_path, $decoded);

        // Create attachment
        $filetype = wp_check_filetype($filename, null);
        $attachment = array(
            'post_mime_type' => $filetype['type'],
            'post_title'     => sanitize_file_name(pathinfo($filename, PATHINFO_FILENAME)),
            'post_content'   => '',
            'post_status'    => 'inherit'
        );

        $attach_id = wp_insert_attachment($attachment, $file_path, $post_id);

        // Generate metadata & update
        require_once(ABSPATH . 'wp-admin/includes/image.php');
        $attach_data = wp_generate_attachment_metadata($attach_id, $file_path);
        wp_update_attachment_metadata($attach_id, $attach_data);

        // Set as featured image
        set_post_thumbnail($post_id, $attach_id);
    }

    public function testCreatePost($game_id, $post_body, $post_title, $image_buffer)
    {
        $existing_slugs = $this->get_existing_game_slugs();
        $slug = sanitize_title($game_id);

        if (in_array($slug, $existing_slugs, true)) {
            $msg = "Post with slug {$slug} already exists. Skipping creation.";
            error_log($msg);
            return new WP_Error('post_exists', $msg);
        }

        $post_id = $this->create_game_post($slug, $post_body, $post_title, $image_buffer);

        if (is_wp_error($post_id) || !$post_id) {
            $msg = "Failed to create post for game_id {$game_id}.";
            error_log($msg);
            return new WP_Error('post_failed', $msg);
        }

        return get_permalink($post_id);
    }
}
