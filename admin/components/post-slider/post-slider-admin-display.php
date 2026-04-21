<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Puck_Press_Post_Slider_Admin_Display {

    public function render(): string {
        require_once plugin_dir_path( __FILE__ ) . '../../../public/templates/class-puck-press-template-manager-abstract.php';
        require_once plugin_dir_path( __FILE__ ) . '../../../public/templates/class-puck-press-template-abstract.php';
        require_once plugin_dir_path( __FILE__ ) . '../../../public/templates/class-puck-press-post-slider-template-manager.php';
        require_once plugin_dir_path( __FILE__ ) . '../../../includes/post-slider/class-puck-press-post-slider-admin-preview-card.php';

        $shortcode    = '[pp-post-slider post_type="post,pp_insta_post,pp_game_summary" count="6" more_url="/blog/" more_text="More Posts"]';
        $preview_html = Puck_Press_Post_Slider_Admin_Preview_Card::get_all_templates_html();

        ob_start();
        ?>
        <div class="pp-container">
            <main class="pp-main">

                <div class="pp-section-header">
                    <div>
                        <h1 class="pp-section-title">Post Slider</h1>
                        <p class="pp-section-description">A featured story layout that works with any post type, including custom post types like Instagram posts and game recaps.</p>
                    </div>

                    <div class="pp-shortcode-container">
                        <div class="pp-shortcode-label">Post Slider Shortcode</div>
                        <div class="pp-shortcode-input-group">
                            <input
                                type="text"
                                id="pp-post-slider-shortcode"
                                name="pp-post-slider-shortcode"
                                class="pp-shortcode-input"
                                value="<?php echo esc_attr( $shortcode ); ?>"
                                size="<?php echo strlen( $shortcode ); ?>"
                                spellcheck="false"
                                aria-label="shortcode"
                                onfocus="this.select();"
                                readonly>
                            <button class="pp-shortcode-copy-btn" aria-label="Copy shortcode">
                                <svg class="pp-shortcode-copy-icon" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 5H6a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2v-1M8 5a2 2 0 002 2h2a2 2 0 002-2M8 5a2 2 0 012-2h2a2 2 0 012 2m0 0h2a2 2 0 012 2v3m2 4H10m0 0l3-3m-3 3l3 3" />
                                </svg>
                            </button>
                            <div class="pp-shortcode-tooltip">Copied!</div>
                        </div>
                    </div>
                </div>

                <div class="pp-card" style="margin-bottom: 16px;">
                    <div class="pp-card-header">
                        <h2>Shortcode Attributes</h2>
                        <p>Customize which posts and content appear in the slider</p>
                    </div>
                    <div class="pp-card-content" style="padding: 16px 24px;">
                        <table style="border-collapse: collapse; width: 100%; font-size: 0.875rem;">
                            <thead>
                                <tr style="border-bottom: 1px solid #e0e0e0;">
                                    <th style="text-align: left; padding: 8px 12px; font-weight: 600;">Attribute</th>
                                    <th style="text-align: left; padding: 8px 12px; font-weight: 600;">Default</th>
                                    <th style="text-align: left; padding: 8px 12px; font-weight: 600;">Description</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr style="border-bottom: 1px solid #f0f0f0;">
                                    <td style="padding: 8px 12px;"><code>post_type</code></td>
                                    <td style="padding: 8px 12px;"><code>post</code></td>
                                    <td style="padding: 8px 12px;">Any registered post type — e.g. <code>post</code>, <code>pp_insta_post</code>, <code>pp_game_summary</code></td>
                                </tr>
                                <tr style="border-bottom: 1px solid #f0f0f0;">
                                    <td style="padding: 8px 12px;"><code>count</code></td>
                                    <td style="padding: 8px 12px;"><code>6</code></td>
                                    <td style="padding: 8px 12px;">Total number of posts to display. The Cards template shows this many cards in a grid; Stories uses 1 featured + up to 5 in the list.</td>
                                </tr>
                                <tr style="border-bottom: 1px solid #f0f0f0;">
                                    <td style="padding: 8px 12px;"><code>more_url</code></td>
                                    <td style="padding: 8px 12px;"><code>#</code></td>
                                    <td style="padding: 8px 12px;">URL for the "More Posts" button at the bottom of the list</td>
                                </tr>
                                <tr style="border-bottom: 1px solid #f0f0f0;">
                                    <td style="padding: 8px 12px;"><code>more_text</code></td>
                                    <td style="padding: 8px 12px;"><code>More Posts</code></td>
                                    <td style="padding: 8px 12px;">Label for the "More Posts" button</td>
                                </tr>
                                <tr>
                                    <td style="padding: 8px 12px;"><code>team</code></td>
                                    <td style="padding: 8px 12px;"><em>(all teams)</em></td>
                                    <td style="padding: 8px 12px;">Team ID to filter posts. Only <code>pp_insta_post</code> and <code>pp_game_summary</code> posts are team-scoped. Find team IDs in the Teams admin tab. Leave blank to show all posts.</td>
                                </tr>
                            </tbody>
                        </table>
                        <p style="margin: 12px 0 0; color: #666; font-size: 0.8rem;">
                            Examples: <code>[pp-post-slider post_type="pp_insta_post,pp_game_summary" team="5" count="6"]</code> &nbsp;|&nbsp; <code>[pp-post-slider post_type="pp_insta_post,pp_game_summary" count="6"]</code>
                        </p>
                    </div>
                </div>

                <div class="pp-card">
                    <div class="pp-card-header">
                        <div>
                            <h2 class="pp-card-title">Preview</h2>
                            <p class="pp-card-subtitle">Live preview using your most recent posts</p>
                        </div>
                        <div>
                            <button id="pp-post-slider-colorPaletteBtn" class="pp-button pp-button-secondary">
                                Customize Colors &amp; Fonts
                            </button>
                        </div>
                    </div>
                    <div class="pp-card-content" id="pp-post-slider-preview-area">
                        <?php echo $preview_html; ?>
                    </div>
                </div>

            </main>

            <?php include plugin_dir_path( __FILE__ ) . 'post-slider-color-palette-modal.php'; ?>
        </div>
        <?php
        return ob_get_clean();
    }
}
