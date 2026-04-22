<?php

class Puck_Press_Teams_Admin_Standings_Card extends Puck_Press_Admin_Card_Abstract {

    private int $team_id;
    private ?Puck_Press_Standings_Admin_Preview_Card $preview_card = null;

    public function __construct( array $args = array(), int $team_id = 0 ) {
        parent::__construct( $args );
        $this->team_id = $team_id;

        if ( $team_id > 0 ) {
            require_once plugin_dir_path( __DIR__ ) . '/../../includes/standings/class-puck-press-standings-wpdb-utils.php';
            require_once plugin_dir_path( __DIR__ ) . '/../../public/templates/class-puck-press-template-manager-abstract.php';
            require_once plugin_dir_path( __DIR__ ) . '/../../public/templates/class-puck-press-standings-template-manager.php';
            require_once plugin_dir_path( __FILE__ ) . '/../teams/class-puck-press-standings-admin-preview-card.php';

            $this->preview_card = new Puck_Press_Standings_Admin_Preview_Card(
                array(
                    'title'    => 'Standings Preview',
                    'subtitle' => 'Preview how standings will appear on the frontend',
                    'id'       => 'standings-preview',
                    'team_id'  => $team_id,
                )
            );
            $this->preview_card->init();
        }
    }

    public function render_header_button_content() {
        ob_start();
        ?>
        <button class="pp-button pp-button-primary" id="pp-refresh-standings-btn" data-team-id="<?php echo esc_attr( $this->team_id ); ?>">
            Refresh Standings
        </button>
        <?php
        return ob_get_clean();
    }

    public function render_content() {
        require_once plugin_dir_path( __DIR__ ) . '/../../includes/standings/class-puck-press-standings-source-resolver.php';

        global $wpdb;
        $team_slug = $wpdb->get_var(
            $wpdb->prepare( "SELECT slug FROM {$wpdb->prefix}pp_teams WHERE id = %d", $this->team_id )
        ) ?: '';

        $source    = Puck_Press_Standings_Source_Resolver::get_regular_season_source( $this->team_id );
        $shortcode = '[pp-standings team="' . esc_attr( $team_slug ) . '"]';

        ob_start();
        ?>
        <div id="pp-standings-admin-content">
            <div style="margin-bottom: 16px;">
                <div class="pp-shortcode-label" style="font-size: 0.75rem; font-weight: 600; text-transform: uppercase; letter-spacing: 0.5px; color: #5f6368; margin-bottom: 4px;">Standings Shortcode</div>
                <div class="pp-shortcode-input-group">
                    <input
                        type="text"
                        class="pp-shortcode-input"
                        value="<?php echo esc_attr( $shortcode ); ?>"
                        size="<?php echo strlen( $shortcode ); ?>"
                        spellcheck="false"
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

            <details style="margin-bottom: 16px; font-size: 0.875rem;">
                <summary style="cursor: pointer; font-weight: 600; color: #1a1a2e;">Shortcode Attributes</summary>
                <table style="border-collapse: collapse; width: 100%; margin-top: 8px;">
                    <thead>
                        <tr style="border-bottom: 1px solid #e0e0e0;">
                            <th style="text-align: left; padding: 6px 10px; font-weight: 600;">Attribute</th>
                            <th style="text-align: left; padding: 6px 10px; font-weight: 600;">Default</th>
                            <th style="text-align: left; padding: 6px 10px; font-weight: 600;">Description</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr style="border-bottom: 1px solid #f0f0f0;">
                            <td style="padding: 6px 10px;"><code>team</code></td>
                            <td style="padding: 6px 10px;"><em>(required)</em></td>
                            <td style="padding: 6px 10px;">Team slug or numeric ID</td>
                        </tr>
                        <tr style="border-bottom: 1px solid #f0f0f0;">
                            <td style="padding: 6px 10px;"><code>compact</code></td>
                            <td style="padding: 6px 10px;"><code>false</code></td>
                            <td style="padding: 6px 10px;">Hide all optional columns (goals, home/away, P%, streak) — shows only Team, GP, W, L, OTL, and Pts</td>
                        </tr>
                        <tr style="border-bottom: 1px solid #f0f0f0;">
                            <td style="padding: 6px 10px;"><code>show_home_away</code></td>
                            <td style="padding: 6px 10px;"><code>true</code></td>
                            <td style="padding: 6px 10px;">Show home / away record columns</td>
                        </tr>
                        <tr style="border-bottom: 1px solid #f0f0f0;">
                            <td style="padding: 6px 10px;"><code>show_goals</code></td>
                            <td style="padding: 6px 10px;"><code>true</code></td>
                            <td style="padding: 6px 10px;">Show GF, GA, and goal differential columns</td>
                        </tr>
                        <tr style="border-bottom: 1px solid #f0f0f0;">
                            <td style="padding: 6px 10px;"><code>show_pct</code></td>
                            <td style="padding: 6px 10px;"><code>true</code></td>
                            <td style="padding: 6px 10px;">Show points percentage (P%) column</td>
                        </tr>
                        <tr style="border-bottom: 1px solid #f0f0f0;">
                            <td style="padding: 6px 10px;"><code>show_streak</code></td>
                            <td style="padding: 6px 10px;"><code>true</code></td>
                            <td style="padding: 6px 10px;">Show streak and last-10 columns</td>
                        </tr>
                        <tr style="border-bottom: 1px solid #f0f0f0;">
                            <td style="padding: 6px 10px;"><code>show_tabs</code></td>
                            <td style="padding: 6px 10px;"><code>true</code></td>
                            <td style="padding: 6px 10px;">Show Division / Overall tab switcher when overall standings data is available</td>
                        </tr>
                        <tr style="border-bottom: 1px solid #f0f0f0;">
                            <td style="padding: 6px 10px;"><code>show_title</code></td>
                            <td style="padding: 6px 10px;"><code>true</code></td>
                            <td style="padding: 6px 10px;">Show division name as the table title</td>
                        </tr>
                        <tr style="border-bottom: 1px solid #f0f0f0;">
                            <td style="padding: 6px 10px;"><code>title</code></td>
                            <td style="padding: 6px 10px;"><em>(division name)</em></td>
                            <td style="padding: 6px 10px;">Override the title text</td>
                        </tr>
                        <tr>
                            <td style="padding: 6px 10px;"><code>highlight</code></td>
                            <td style="padding: 6px 10px;"><code>true</code></td>
                            <td style="padding: 6px 10px;">Highlight your team's row in the table</td>
                        </tr>
                    </tbody>
                </table>
                <p style="margin: 8px 0 0; color: #666; font-size: 0.8rem;">
                    Example: <code>[pp-standings team="<?php echo esc_html( $team_slug ); ?>" compact="true"]</code>
                </p>
            </details>

            <?php $this->render_source_info( $source ); ?>
        </div>

        <?php if ( $this->preview_card ) : ?>
            <?php echo $this->preview_card->render(); ?>
            <?php include plugin_dir_path( __FILE__ ) . 'standings-color-palette-modal.php'; ?>
        <?php endif; ?>
        <?php
        return ob_get_clean();
    }

    private function render_source_info( ?array $source ): void {
        if ( ! $source ) {
            ?>
            <div class="pp-standings-source-info pp-standings-source-info--empty">
                <p>No regular-season schedule source found for this team. Add an ACHA or USPHL schedule source to enable divisional standings.</p>
            </div>
            <?php
            return;
        }

        $other       = json_decode( $source['other_data'] ?? '{}', true );
        $season_id   = $other['season_id'] ?? '—';
        $division_id = $other['division_id'] ?? '—';
        $type_label  = $source['type'] === 'usphlGameScheduleUrl' ? 'USPHL' : 'ACHA';
        ?>
        <div class="pp-standings-source-info" style="margin-bottom: 12px;">
            <p>
                <strong>Detected source:</strong>
                <?php echo esc_html( $source['name'] ); ?>
                <span class="pp-tag pp-tag-<?php echo esc_attr( $source['type'] ); ?>"><?php echo esc_html( $type_label ); ?></span>
            </p>
            <p class="pp-form-help">
                Season ID: <?php echo esc_html( $season_id ); ?>
                <?php if ( $source['type'] !== 'usphlGameScheduleUrl' ) : ?>
                &nbsp;&middot;&nbsp; Division ID: <?php echo esc_html( $division_id ); ?>
                <?php endif; ?>
            </p>
        </div>
        <?php
    }
}
