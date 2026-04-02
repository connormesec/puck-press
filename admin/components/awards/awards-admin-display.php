<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

require_once plugin_dir_path( dirname( dirname( __DIR__ ) ) ) . 'includes/awards/class-puck-press-awards-wpdb-utils.php';

class Puck_Press_Awards_Admin_Display {

    private $wpdb_utils;

    public function __construct() {
        $this->wpdb_utils = new Puck_Press_Awards_Wpdb_Utils();
    }

    public function render(): string {
        $this->wpdb_utils->maybe_create_or_update_tables();

        $years        = $this->wpdb_utils->get_distinct_years();
        $parents      = $this->wpdb_utils->get_distinct_parent_names();
        $active_year  = isset( $_GET['award_year'] ) ? sanitize_text_field( $_GET['award_year'] ) : ( $years[0] ?? '' );
        $active_group = isset( $_GET['award_group'] ) ? sanitize_text_field( $_GET['award_group'] ) : '';

        $awards = $this->wpdb_utils->get_all_awards( $active_year ?: null );
        if ( $active_group ) {
            $awards = array_filter(
                $awards,
                function ( $a ) use ( $active_group ) {
                    return strtolower( $a['parent_name'] ?? '' ) === strtolower( $active_group );
                }
            );
            $awards = array_values( $awards );
        }

        ob_start();
        ?>
        <div class="pp-container">
            <main class="pp-main">

                <div class="pp-section-header">
                    <div>
                        <h1 class="pp-section-title">Awards</h1>
                        <p class="pp-section-description">Create and manage player awards, all-conference teams, and other accolades.</p>
                    </div>
                </div>

                <div class="pp-card" style="margin-bottom:1.5rem;">
                    <div class="pp-card-body" style="display:flex;align-items:center;gap:1rem;flex-wrap:wrap;">
                        <button type="button" id="pp-add-award-btn" class="button button-primary">+ New Award</button>
                        <button type="button" id="pp-awards-colorPaletteBtn" class="button">Customize Colors</button>

                        <?php if ( ! empty( $parents ) ) : ?>
                        <label for="pp-award-group-filter" style="margin-left:auto;">Group:</label>
                        <select id="pp-award-group-filter" class="pp-select" style="min-width:120px;">
                            <option value="">All</option>
                            <?php foreach ( $parents as $pn ) : ?>
                                <option value="<?php echo esc_attr( $pn ); ?>" <?php selected( $active_group, $pn ); ?>><?php echo esc_html( $pn ); ?></option>
                            <?php endforeach; ?>
                        </select>
                        <?php endif; ?>

                        <?php if ( ! empty( $years ) ) : ?>
                        <label for="pp-award-year-filter">Year:</label>
                        <select id="pp-award-year-filter" class="pp-select" style="min-width:100px;">
                            <option value="">All</option>
                            <?php foreach ( $years as $y ) : ?>
                                <option value="<?php echo esc_attr( $y ); ?>" <?php selected( $active_year, $y ); ?>><?php echo esc_html( $y ); ?></option>
                            <?php endforeach; ?>
                        </select>
                        <?php endif; ?>
                    </div>
                </div>

                <?php if ( empty( $awards ) ) : ?>
                    <div class="pp-card">
                        <div class="pp-card-body" style="text-align:center;padding:3rem 1rem;color:#888;">
                            No awards found. Click "+ New Award" to create one.
                        </div>
                    </div>
                <?php else : ?>
                    <?php foreach ( $awards as $award ) : ?>
                        <?php
                        $players  = $this->wpdb_utils->get_players_for_award( (int) $award['id'] );
                        $icon_html = $this->render_icon( $award );
                        ?>
                        <div class="pp-card pp-award-card" data-award-id="<?php echo esc_attr( $award['id'] ); ?>" style="margin-bottom:1.5rem;">
                            <div class="pp-card-header" style="display:flex;align-items:center;gap:0.75rem;flex-wrap:wrap;">
                                <span style="font-size:1.5rem;line-height:1;"><?php echo $icon_html; ?></span>
                                <span style="font-weight:700;font-size:0.75rem;background:#e8f0fe;color:#1a56db;padding:2px 8px;border-radius:3px;"><?php echo esc_html( $award['year'] ); ?></span>
                                <strong><?php echo esc_html( $award['name'] ); ?></strong>
                                <?php if ( ! empty( $award['parent_name'] ) ) : ?>
                                    <span style="color:#888;">&middot; Group: <?php echo esc_html( $award['parent_name'] ); ?></span>
                                <?php endif; ?>
                                <?php if ( ! (int) ( $award['show_in_shortcode'] ?? 1 ) ) : ?>
                                    <span style="font-size:0.7rem;color:#a00;background:#fee;padding:2px 8px;border-radius:3px;">Hidden from shortcode</span>
                                <?php endif; ?>
                                <code style="margin-left:auto;font-size:0.75rem;color:#666;background:#f5f5f5;padding:2px 6px;border-radius:3px;">[pp-awards award="<?php echo esc_attr( $award['slug'] ); ?>"]</code>
                            </div>
                            <div class="pp-card-body" style="padding:0;">
                                <?php if ( ! empty( $players ) ) : ?>
                                <table class="widefat striped" style="border:0;">
                                    <thead>
                                        <tr>
                                            <th style="width:60px;">Pos</th>
                                            <th>Player</th>
                                            <th>Team</th>
                                            <th style="width:40px;"></th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ( $players as $p ) : ?>
                                        <tr data-player-row-id="<?php echo esc_attr( $p['id'] ); ?>">
                                            <td><?php echo esc_html( $p['position'] ); ?></td>
                                            <td>
                                                <?php echo esc_html( $p['player_name'] ); ?>
                                                <?php if ( (int) $p['is_external'] ) : ?>
                                                    <span style="font-size:0.7rem;color:#888;margin-left:4px;">(external)</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <?php if ( ! empty( $p['team_logo_url'] ) ) : ?>
                                                    <img src="<?php echo esc_url( $p['team_logo_url'] ); ?>" alt="<?php echo esc_attr( $p['team_name'] ); ?>" style="width:24px;height:24px;object-fit:contain;vertical-align:middle;margin-right:4px;">
                                                <?php endif; ?>
                                                <?php echo esc_html( $p['team_name'] ); ?>
                                            </td>
                                            <td>
                                                <button type="button" class="button pp-remove-award-player-btn" data-id="<?php echo esc_attr( $p['id'] ); ?>" title="Remove player">&times;</button>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                                <?php else : ?>
                                    <p style="padding:1rem;color:#888;margin:0;">No players added yet.</p>
                                <?php endif; ?>
                            </div>
                            <div class="pp-card-footer" style="display:flex;align-items:center;gap:0.5rem;padding:0.75rem 1rem;border-top:1px solid #e0e0e0;flex-wrap:wrap;">
                                <button type="button" class="button pp-add-award-player-btn" data-award-id="<?php echo esc_attr( $award['id'] ); ?>">+ Add Player</button>
                                <button type="button" class="button pp-bulk-add-team-btn" data-award-id="<?php echo esc_attr( $award['id'] ); ?>">+ Add Team</button>
                                <button type="button" class="button pp-edit-award-btn"
                                    data-award-id="<?php echo esc_attr( $award['id'] ); ?>"
                                    data-name="<?php echo esc_attr( $award['name'] ); ?>"
                                    data-year="<?php echo esc_attr( $award['year'] ); ?>"
                                    data-parent-name="<?php echo esc_attr( $award['parent_name'] ); ?>"
                                    data-icon-type="<?php echo esc_attr( $award['icon_type'] ); ?>"
                                    data-icon-value="<?php echo esc_attr( $award['icon_value'] ); ?>"
                                    data-sort-order="<?php echo esc_attr( $award['sort_order'] ); ?>"
                                    data-slug="<?php echo esc_attr( $award['slug'] ); ?>"
                                >Edit</button>
                                <?php
                                $is_visible = (int) ( $award['show_in_shortcode'] ?? 1 );
                                ?>
                                <button type="button" class="button pp-toggle-award-visibility-btn"
                                    data-award-id="<?php echo esc_attr( $award['id'] ); ?>"
                                    data-visible="<?php echo $is_visible; ?>"
                                    title="<?php echo $is_visible ? 'Hide from shortcode' : 'Show in shortcode'; ?>"
                                ><?php echo $is_visible ? 'Hide from Shortcode' : 'Show in Shortcode'; ?></button>
                                <button type="button" class="button pp-delete-award-btn" data-award-id="<?php echo esc_attr( $award['id'] ); ?>" style="margin-left:auto;color:#a00;">Delete</button>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>

                <?php
                $parent_names = $this->wpdb_utils->get_distinct_parent_names();
                $all_awards   = $active_year ? $awards : $this->wpdb_utils->get_all_awards();
                ?>
                <div class="pp-card" style="margin-top:2rem;">
                    <div class="pp-card-header"><strong>Shortcodes</strong></div>
                    <div class="pp-card-body" style="font-size:0.85rem;line-height:1.8;">
                        <?php if ( ! empty( $parent_names ) ) : ?>
                        <p style="margin:0 0 0.5rem;font-weight:600;">By Parent Group <span style="color:#888;font-weight:400;">(with year dropdown)</span></p>
                        <table class="widefat striped" style="border:0;margin-bottom:1.25rem;">
                            <tbody>
                                <?php foreach ( $parent_names as $pn ) : ?>
                                <tr>
                                    <td style="width:50%;"><code>[pp-awards parent="<?php echo esc_attr( $pn ); ?>"]</code></td>
                                    <td><?php echo esc_html( $pn ); ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                        <?php endif; ?>

                        <?php if ( ! empty( $all_awards ) ) : ?>
                        <p style="margin:0 0 0.5rem;font-weight:600;">By Individual Award <span style="color:#888;font-weight:400;">(single award, no dropdown)</span></p>
                        <table class="widefat striped" style="border:0;margin-bottom:1.25rem;">
                            <tbody>
                                <?php foreach ( $all_awards as $sa ) : ?>
                                <tr>
                                    <td style="width:50%;"><code>[pp-awards award="<?php echo esc_attr( $sa['slug'] ); ?>"]</code></td>
                                    <td><?php echo esc_html( $sa['year'] . ' ' . $sa['name'] ); ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                        <?php endif; ?>

                        <p style="color:#888;margin:0.75rem 0 0;">
                            <strong>Optional attributes:</strong>
                            <code>columns="6"</code> (default 6),
                            <code>show_headshots="false"</code>,
                            <code>link_players="false"</code>
                        </p>
                    </div>
                </div>

            </main>
        </div>

        <?php
        include plugin_dir_path( __FILE__ ) . 'awards-add-award-modal.php';
        include plugin_dir_path( __FILE__ ) . 'awards-edit-award-modal.php';
        include plugin_dir_path( __FILE__ ) . 'awards-add-player-modal.php';
        include plugin_dir_path( __FILE__ ) . 'awards-bulk-add-team-modal.php';
        include plugin_dir_path( __FILE__ ) . 'awards-color-palette-modal.php';
        ?>
        <?php
        return ob_get_clean();
    }

    private function render_icon( array $award ): string {
        if ( $award['icon_type'] === 'image' && ! empty( $award['icon_value'] ) ) {
            return '<img src="' . esc_url( $award['icon_value'] ) . '" alt="" style="width:1.5rem;height:1.5rem;object-fit:contain;">';
        }
        return esc_html( $award['icon_value'] ?: '🏅' );
    }
}
