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

        include_once plugin_dir_path(__FILE__) . 'class-puck-press-post-game-summary-initiator.php';

        foreach ($completed_games as $game) {

            // Guard 2: a custom URL or prior auto-post permalink is already stored
            if (!empty($game->post_link)) {
                $messages[] = "Game {$game->game_id} already has a post link. Skipping.";
                continue;
            }

            // Guard 1: a WordPress post with _game_id meta already exists
            if ($this->game_post_exists($game->game_id)) {
                $messages[] = "Post for game {$game->game_id} already exists. Skipping.";
                continue;
            }

            $initiator = new Puck_Press_Post_Game_Summary_Initiator($game->game_id, $game->source_type);

            $data = [
                'image'     => null,
                'blog_data' => null,
                'errors'    => [],
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

            // Step 3: try blog summary, but don't fail hard if it breaks
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
                    $post_title = $data['blog_data']['title'];
                    $permalink  = $this->testCreatePost($game->game_id, $data['blog_data']['body'], $post_title, $data['image']);

                    if (!is_wp_error($permalink)) {
                        $this->save_post_link_for_game($game->game_id, $permalink);
                        $messages[] = 'Post created successfully: ' . $permalink;
                    } else {
                        $messages[] = 'Post creation failed: ' . $permalink->get_error_message();
                        $data['errors'][] = 'Post creation failed: ' . $permalink->get_error_message();
                    }
                }
            } catch (Throwable $e) {
                $data['errors'][] = 'Post creation failed: ' . $e->getMessage();
            }
        }
        return $messages;
    }

    /**
     * Scan all published WordPress posts and link any that match a game in the
     * schedule back into pp_game_schedule_mods / pp_game_schedule_for_display.
     *
     * Handles both slug formats:
     *   - Old: post_name = sanitize_title($game_id), _game_id meta = same value
     *   - New: post_name = sanitize_title($title . '-' . $game_id), _game_id meta ends with '-{game_id_slug}'
     *
     * Returns an array of human-readable result messages.
     */
    public function autodiscover_post_links()
    {
        global $wpdb;

        $messages  = [];
        $found     = 0;
        $skipped   = 0;

        // Get all games that don't already have a post_link stored
        $games = $wpdb->get_results(
            "SELECT game_id FROM {$this->table_name}
             WHERE post_link IS NULL OR post_link = ''"
        );

        if (empty($games)) {
            $messages[] = 'All games already have post links. Nothing to discover.';
            return $messages;
        }

        // Build a map of _game_id meta value => post_id for all published posts
        $posts_with_meta = get_posts([
            'post_type'      => 'post',
            'post_status'    => 'publish',
            'posts_per_page' => -1,
            'meta_key'       => '_game_id',
            'fields'         => 'ids',
        ]);

        $meta_lookup = []; // _game_id meta value => post_id
        foreach ($posts_with_meta as $post_id) {
            $val = get_post_meta($post_id, '_game_id', true);
            if ($val !== '' && $val !== false) {
                $meta_lookup[$val] = $post_id;
            }
        }

        foreach ($games as $game) {
            $game_id      = $game->game_id;
            $game_id_slug = sanitize_title($game_id);
            $matched_id   = null;

            // Try 1: exact _game_id meta match (old format)
            if (isset($meta_lookup[$game_id_slug])) {
                $matched_id = $meta_lookup[$game_id_slug];
            }

            // Try 2: _game_id meta ends with '-{game_id_slug}' (new title-based slug format)
            if (!$matched_id) {
                $suffix = '-' . $game_id_slug;
                $suffix_len = strlen($suffix);
                foreach ($meta_lookup as $meta_val => $post_id) {
                    if (strlen($meta_val) > $suffix_len &&
                        substr($meta_val, -$suffix_len) === $suffix) {
                        $matched_id = $post_id;
                        break;
                    }
                }
            }

            // Try 3: post_name = game_id_slug (old posts that may have no meta)
            if (!$matched_id) {
                $fallback = get_posts([
                    'post_type'      => 'post',
                    'post_status'    => 'publish',
                    'name'           => $game_id_slug,
                    'posts_per_page' => 1,
                    'fields'         => 'ids',
                ]);
                if ($fallback) {
                    $matched_id = $fallback[0];
                    // Back-fill the _game_id meta so future cron runs detect it correctly
                    if (!get_post_meta($matched_id, '_game_id', true)) {
                        update_post_meta($matched_id, '_game_id', $game_id_slug);
                    }
                }
            }

            if ($matched_id) {
                $permalink = get_permalink($matched_id);
                $this->save_post_link_for_game($game_id, $permalink);
                $messages[] = "Linked game {$game_id} → {$permalink}";
                $found++;
            } else {
                $skipped++;
            }
        }

        $messages[] = "Done. Found and linked {$found} post(s). {$skipped} game(s) had no matching post.";
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
     * Check whether a WordPress post already exists for a given game_id
     * by querying the _game_id post meta set during creation.
     */
    private function game_post_exists($game_id)
    {
        $posts = get_posts([
            'post_type'      => 'post',
            'meta_key'       => '_game_id',
            'meta_value'     => sanitize_title($game_id),
            'post_status'    => 'publish',
            'posts_per_page' => 1,
            'fields'         => 'ids',
        ]);
        return !empty($posts);
    }

    /**
     * Persist a post permalink back into the schedule pipeline so it survives
     * table rebuilds and appears in the admin games table immediately.
     *
     * 1. Upsert into pp_game_schedule_mods (persists through any rebuild)
     * 2. Directly update pp_game_schedule_for_display (shows immediately)
     */
    private function save_post_link_for_game($game_id, $permalink)
    {
        global $wpdb;

        $mods_table    = $wpdb->prefix . 'pp_game_schedule_mods';
        $display_table = $wpdb->prefix . 'pp_game_schedule_for_display';
        $current_time  = current_time('mysql');

        // Upsert into mods so the link survives the next full rebuild
        $existing_id = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM $mods_table WHERE external_id = %s AND edit_action = 'update' LIMIT 1",
            $game_id
        ));

        if ($existing_id) {
            $existing_json   = $wpdb->get_var($wpdb->prepare(
                "SELECT edit_data FROM $mods_table WHERE id = %d",
                intval($existing_id)
            ));
            $existing_fields = !empty($existing_json) ? (json_decode($existing_json, true) ?: []) : [];
            $existing_fields['post_link'] = $permalink;

            $wpdb->update(
                $mods_table,
                ['edit_data' => wp_json_encode($existing_fields), 'updated_at' => $current_time],
                ['id' => intval($existing_id)],
                ['%s', '%s'],
                ['%d']
            );
        } else {
            $wpdb->insert(
                $mods_table,
                [
                    'external_id' => $game_id,
                    'edit_action' => 'update',
                    'edit_data'   => wp_json_encode(['external_id' => $game_id, 'post_link' => $permalink]),
                    'created_at'  => $current_time,
                    'updated_at'  => $current_time,
                ],
                ['%s', '%s', '%s', '%s', '%s']
            );
        }

        // Also update for_display directly so the column is visible right now
        $wpdb->update(
            $display_table,
            ['post_link' => $permalink],
            ['game_id' => $game_id],
            ['%s'],
            ['%s']
        );
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
            'post_author'  => 1,
            'post_name'    => $slug,
        );

        $post_id = wp_insert_post($post_data);

        if (!is_wp_error($post_id)) {
            // Store the sanitized game_id in meta for reliable duplicate detection
            update_post_meta($post_id, '_game_id', sanitize_title($slug));

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

    /**
     * Create a post with an SEO-friendly slug (title + game_id suffix).
     * Returns the permalink on success or a WP_Error on failure.
     */
    public function testCreatePost($game_id, $post_body, $post_title, $image_buffer)
    {
        // Guard: double-check meta-based duplicate detection
        if ($this->game_post_exists($game_id)) {
            $msg = "Post for game_id {$game_id} already exists. Skipping creation.";
            error_log($msg);
            return new WP_Error('post_exists', $msg);
        }

        // SEO-friendly slug: "{post-title}-{game_id}"
        $slug = sanitize_title(($post_title ?? '') . '-' . $game_id);

        $post_id = $this->create_game_post($slug, $post_body, $post_title, $image_buffer);

        if (is_wp_error($post_id) || !$post_id) {
            $msg = "Failed to create post for game_id {$game_id}.";
            error_log($msg);
            return new WP_Error('post_failed', $msg);
        }

        return get_permalink($post_id);
    }
}
