<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Puck_Press_League_News_Admin_Display {

    public function render(): string {
        require_once plugin_dir_path( __FILE__ ) . '../../../public/templates/class-puck-press-template-manager-abstract.php';
        require_once plugin_dir_path( __FILE__ ) . '../../../public/templates/class-puck-press-template-abstract.php';
        require_once plugin_dir_path( __FILE__ ) . '../../../public/templates/class-puck-press-league-news-template-manager.php';
        require_once plugin_dir_path( __FILE__ ) . '../../../includes/league-news/class-puck-press-league-news-api.php';
        require_once plugin_dir_path( __FILE__ ) . '../../../includes/league-news/class-puck-press-league-news-admin-preview-card.php';

        $shortcode    = '[pp-league-news]';
        $source       = get_option( 'pp_league_news_source', 'acha' );
        $acha_cat     = (int) get_option( 'pp_league_news_acha_category', 1 );
        $usphl_cat    = (int) get_option( 'pp_league_news_usphl_category', 13 );
        $count        = (int) get_option( 'pp_league_news_count', 8 );
        $preview_html = Puck_Press_League_News_Admin_Preview_Card::get_all_templates_html();

        $sources = array(
            'acha'  => 'ACHA',
            'usphl' => 'USPHL Premier',
        );

        ob_start();
        ?>
        <div class="pp-container">
            <main class="pp-main">

                <div class="pp-section-header">
                    <div>
                        <h1 class="pp-section-title">League News</h1>
                        <p class="pp-section-description">Display recent news posts from ACHA or USPHL Premier. Posts are cached for 6 hours.</p>
                    </div>

                    <div class="pp-shortcode-container">
                        <div class="pp-shortcode-label">Shortcode</div>
                        <div class="pp-shortcode-input-group">
                            <input
                                type="text"
                                id="pp-league-news-shortcode"
                                name="pp-league-news-shortcode"
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
                        <h2>Settings</h2>
                        <p>Choose which league and category to display</p>
                    </div>
                    <div class="pp-card-content" style="padding: 16px 24px;">
                        <table style="border-collapse: collapse; width: 100%; font-size: 0.875rem;">
                            <tbody>
                                <tr style="border-bottom: 1px solid #f0f0f0;">
                                    <td style="padding: 10px 12px; font-weight: 600; width: 140px;">Source</td>
                                    <td style="padding: 10px 12px;">
                                        <select id="pp-league-news-source" style="padding: 6px 10px; border: 1px solid #ddd; border-radius: 6px; font-size: 0.875rem;">
                                            <?php foreach ( $sources as $val => $label ) : ?>
                                                <option value="<?php echo esc_attr( $val ); ?>" <?php selected( $source, $val ); ?>>
                                                    <?php echo esc_html( $label ); ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </td>
                                </tr>
                                <tr style="border-bottom: 1px solid #f0f0f0;">
                                    <td style="padding: 10px 12px; font-weight: 600;">Category</td>
                                    <td style="padding: 10px 12px;">
                                        <select id="pp-league-news-category" style="padding: 6px 10px; border: 1px solid #ddd; border-radius: 6px; font-size: 0.875rem;">
                                            <!-- populated by JS on load and on source change -->
                                        </select>
                                    </td>
                                </tr>
                                <tr>
                                    <td style="padding: 10px 12px; font-weight: 600;">Count</td>
                                    <td style="padding: 10px 12px;">
                                        <input
                                            type="number"
                                            id="pp-league-news-count"
                                            value="<?php echo esc_attr( $count ); ?>"
                                            min="1"
                                            max="20"
                                            style="width: 80px; padding: 6px 10px; border: 1px solid #ddd; border-radius: 6px; font-size: 0.875rem;">
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                        <div style="margin-top: 16px;">
                            <button id="pp-league-news-save-settings" class="pp-button pp-button-primary">
                                Save Settings
                            </button>
                            <span id="pp-league-news-save-feedback" style="margin-left: 12px; font-size: 0.85rem; color: #3a66cc; display: none;"></span>
                        </div>
                    </div>
                </div>

                <div class="pp-card">
                    <div class="pp-card-header">
                        <div>
                            <h2 class="pp-card-title">Preview</h2>
                            <p class="pp-card-subtitle">Live preview using current settings</p>
                        </div>
                        <div>
                            <button id="pp-league-news-colorPaletteBtn" class="pp-button pp-button-secondary">
                                Customize Colors
                            </button>
                        </div>
                    </div>
                    <div class="pp-card-content" id="pp-league-news-preview-area">
                        <?php echo $preview_html; ?>
                    </div>
                </div>

            </main>

            <?php include plugin_dir_path( __FILE__ ) . 'league-news-color-palette-modal.php'; ?>
        </div>

        <script>
        // Embed saved per-source categories so JS can pre-select on load.
        window._ppLeagueNewsSavedAchaCategory  = <?php echo (int) $acha_cat; ?>;
        window._ppLeagueNewsSavedUsphlCategory = <?php echo (int) $usphl_cat; ?>;
        </script>
        <?php
        return ob_get_clean();
    }
}
