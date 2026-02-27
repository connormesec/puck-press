<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

class Puck_Press_Player_Page_Admin_Display {

    public function render(): string
    {
        global $wpdb;
        $players = $wpdb->get_results(
            "SELECT name FROM {$wpdb->prefix}pp_roster_for_display ORDER BY name ASC",
            ARRAY_A
        );

        ob_start();
        ?>
        <div class="pp-container">
            <main class="pp-main">
                <div class="pp-section-header">
                    <div>
                        <h1 class="pp-section-title">Player Page</h1>
                        <p class="pp-section-description">Customize the appearance of <code>/player/{slug}</code> pages. Changes preview live in the iframe.</p>
                    </div>
                </div>

                <div class="pp-player-page-editor">

                    <!-- ── Controls panel ── -->
                    <div class="pp-player-page-controls">
                        <div class="pp-player-page-section">
                            <p class="pp-player-page-section-title">Colors</p>
                            <div id="pp-pd-color-fields"></div>
                        </div>
                        <div class="pp-player-page-section">
                            <p class="pp-player-page-section-title">Font</p>
                            <div id="pp-pd-font-fields"></div>
                        </div>
                        <div class="pp-player-page-controls-footer">
                            <button id="pp-pd-save" class="pp-button pp-button-primary">Save Colors</button>
                            <span id="pp-pd-save-msg"></span>
                        </div>
                    </div>

                    <!-- ── Preview panel ── -->
                    <div class="pp-player-page-preview">
                        <?php if ( ! empty( $players ) ) : ?>
                        <div class="pp-player-page-preview-header">
                            <label for="pp-pd-player-select">Previewing:</label>
                            <select id="pp-pd-player-select" class="pp-input pp-player-page-player-select">
                                <?php foreach ( $players as $p ) :
                                    $url = home_url( '/player/' . sanitize_title( $p['name'] ) );
                                ?>
                                <option value="<?php echo esc_url( $url ); ?>"><?php echo esc_html( $p['name'] ); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <iframe
                            id="pp-pd-preview-iframe"
                            class="pp-player-page-iframe"
                            src="about:blank"
                        ></iframe>
                        <?php else : ?>
                        <p class="pp-coming-soon">No players found. Add roster data first.</p>
                        <?php endif; ?>
                    </div>

                </div>
            </main>
        </div>
        <?php
        return ob_get_clean();
    }
}
