<?php

class StandingsTemplate extends PuckPressTemplate {

    public static function get_key(): string {
        return 'standings';
    }

    public static function get_label(): string {
        return 'Division Standings';
    }

    protected static function get_directory(): string {
        return 'standings-templates';
    }

    public static function get_default_colors(): array {
        return array(
            'table_bg'      => '#ffffff',
            'header_bg'     => '#1a1a2e',
            'header_text'   => '#ffffff',
            'row_text'      => '#111827',
            'highlight_bg'  => '#eff6ff',
            'highlight_text' => '#1a1a2e',
            'pts_bg'        => '#dbeafe',
            'pts_text'      => '#1a1a2e',
            'border'        => '#e5e7eb',
            'title_text'    => '#1a1a2e',
        );
    }

    public static function get_color_labels(): array {
        return array(
            'table_bg'       => 'Table Background',
            'header_bg'      => 'Column Header Background',
            'header_text'    => 'Column Header Text',
            'row_text'       => 'Row Values Text',
            'highlight_bg'   => 'Highlighted Team Row Background',
            'highlight_text' => 'Highlighted Team Row Text',
            'pts_bg'         => 'Pts Column Highlight',
            'pts_text'       => 'Pts Value Text',
            'border'         => 'Table Border Color',
            'title_text'     => 'Division Title Text',
        );
    }

    public static function forceResetColors(): bool {
        return false;
    }

    public static function get_default_fonts(): array {
        return array( 'table_font' => '' );
    }

    public static function get_font_labels(): array {
        return array( 'table_font' => 'Table Font' );
    }

    public static function get_standings_colors( int $team_id ): ?array {
        $key   = static::get_key();
        $saved = get_option( "pp_standings_{$team_id}_template_colors_{$key}", null );
        return is_array( $saved ) ? $saved : null;
    }

    public static function get_standings_fonts( int $team_id ): ?array {
        $key   = static::get_key();
        $saved = get_option( "pp_standings_{$team_id}_template_fonts_{$key}", null );
        return is_array( $saved ) ? $saved : null;
    }

    public function render_with_options( array $values, array $options ): string {
        $rows           = $values['rows'] ?? array();
        $compact        = isset( $values['compact'] ) && filter_var( $values['compact'], FILTER_VALIDATE_BOOLEAN );
        $show_home_away = ! $compact && ( ! isset( $values['show_home_away'] ) || filter_var( $values['show_home_away'], FILTER_VALIDATE_BOOLEAN ) );
        $show_goals     = ! $compact && ( ! isset( $values['show_goals'] )     || filter_var( $values['show_goals'],     FILTER_VALIDATE_BOOLEAN ) );
        $show_pct       = ! $compact && ( ! isset( $values['show_pct'] )       || filter_var( $values['show_pct'],       FILTER_VALIDATE_BOOLEAN ) );
        $show_streak    = ! $compact && ( ! isset( $values['show_streak'] )    || filter_var( $values['show_streak'],    FILTER_VALIDATE_BOOLEAN ) );
        $show_title     = ! isset( $values['show_title'] )     || filter_var( $values['show_title'],     FILTER_VALIDATE_BOOLEAN );
        $highlight      = ! isset( $values['highlight'] )      || filter_var( $values['highlight'],      FILTER_VALIDATE_BOOLEAN );
        $title          = $values['title'] ?? '';
        $division_name  = $values['division_name'] ?? '';

        $any_ties = false;
        $any_sol  = false;
        foreach ( $rows as $row ) {
            if ( (int) ( $row['t'] ?? 0 ) > 0 ) {
                $any_ties = true;
            }
            if ( (int) ( $row['sol'] ?? 0 ) > 0 ) {
                $any_sol = true;
            }
        }
        if ( $compact ) {
            $any_sol  = false;
            $any_ties = false;
        }

        $key          = static::get_key();
        $team_id      = isset( $options['team_id'] ) ? (int) $options['team_id'] : 0;
        $container_id = $team_id > 0 ? 'pp-standings-' . $team_id : '';
        $scope        = $container_id ? '#' . $container_id : ':root';
        $colors       = $team_id > 0 ? self::get_standings_colors( $team_id ) : null;
        $fonts        = $team_id > 0 ? self::get_standings_fonts( $team_id ) : null;
        $inline_css   = self::get_inline_css( $scope, $colors, $fonts );
        $css_block    = $inline_css ? '<style>' . $inline_css . '</style>' : '';

        $display_title = ! empty( $title ) ? $title : $division_name;

        ob_start();
        echo $css_block;
        ?>
        <div class="<?php echo esc_attr( $key ); ?>_container pp-standings-wrapper<?php echo $compact ? ' pp-standings-wrapper--compact' : ''; ?>"<?php echo $container_id ? ' id="' . esc_attr( $container_id ) . '"' : ''; ?>>
            <?php if ( $show_title && ! empty( $display_title ) ) : ?>
            <h3 class="pp-standings-title"><?php echo esc_html( $display_title ); ?></h3>
            <?php endif; ?>
            <div class="pp-standings-scroll">
                <table class="pp-standings-table">
                    <thead>
                        <tr class="pp-standings-header-row">
                            <th class="pp-standings-th pp-standings-th--team">Team</th>
                            <th class="pp-standings-th">GP</th>
                            <th class="pp-standings-th">W</th>
                            <th class="pp-standings-th">L</th>
                            <th class="pp-standings-th">OTL</th>
                            <?php if ( $any_sol ) : ?>
                            <th class="pp-standings-th">SOL</th>
                            <?php endif; ?>
                            <?php if ( $any_ties ) : ?>
                            <th class="pp-standings-th">T</th>
                            <?php endif; ?>
                            <th class="pp-standings-th pp-standings-th--pts">Pts</th>
                            <?php if ( $show_pct ) : ?>
                            <th class="pp-standings-th">P%</th>
                            <?php endif; ?>
                            <?php if ( $show_goals ) : ?>
                            <th class="pp-standings-th">GF</th>
                            <th class="pp-standings-th">GA</th>
                            <th class="pp-standings-th">Diff</th>
                            <?php endif; ?>
                            <?php if ( $show_home_away ) : ?>
                            <th class="pp-standings-th">Home</th>
                            <th class="pp-standings-th">Away</th>
                            <?php endif; ?>
                            <?php if ( $show_streak ) : ?>
                            <th class="pp-standings-th">Strk</th>
                            <th class="pp-standings-th pp-standings-th--l10">L10</th>
                            <?php endif; ?>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ( $rows as $row ) : ?>
                        <?php
                            $is_target  = $highlight && ! empty( $row['is_target'] );
                            $row_class  = 'pp-standings-row' . ( $is_target ? ' pp-standings-row--highlight' : '' );

                            $w   = (int) ( $row['w'] ?? 0 );
                            $l   = (int) ( $row['l'] ?? 0 );
                            $otl = (int) ( $row['otl'] ?? 0 );
                            $sol = (int) ( $row['sol'] ?? 0 );
                            if ( $compact ) {
                                $otl += $sol;
                            }
                            $t   = (int) ( $row['t'] ?? 0 );
                            $gp  = (int) ( $row['gp'] ?? 0 );
                            $pts = (int) ( $row['pts'] ?? 0 );
                            $gf  = (int) ( $row['gf'] ?? 0 );
                            $ga  = (int) ( $row['ga'] ?? 0 );

                            $pct = $gp > 0 ? number_format( $pts / ( $gp * 2 ), 3 ) : '—';
                            if ( $pct !== '—' ) {
                                $pct = ltrim( $pct, '0' ) ?: '.000';
                            }

                            $diff     = $gf - $ga;
                            $diff_str = ( $diff >= 0 ? '+' : '' ) . $diff;
                            $diff_cls = $diff >= 0 ? 'pp-standings-diff--pos' : 'pp-standings-diff--neg';

                            $home_w   = (int) ( $row['home_w'] ?? 0 );
                            $home_l   = (int) ( $row['home_l'] ?? 0 );
                            $home_otl = (int) ( $row['home_otl'] ?? 0 );
                            $away_w   = (int) ( $row['away_w'] ?? 0 );
                            $away_l   = (int) ( $row['away_l'] ?? 0 );
                            $away_otl = (int) ( $row['away_otl'] ?? 0 );

                            $home_record = "{$home_w}-{$home_l}-{$home_otl}";
                            $away_record = "{$away_w}-{$away_l}-{$away_otl}";

                            $team_name = esc_html( $row['team_name'] ?? '' );
                            $team_logo = esc_url( $row['team_logo'] ?? '' );
                            $streak    = esc_html( $row['streak'] ?? '' );
                            $last_10   = esc_html( $row['last_10'] ?? '' );
                        ?>
                        <tr class="<?php echo esc_attr( $row_class ); ?>">
                            <td class="pp-standings-td pp-standings-td--team">
                                <?php if ( $team_logo ) : ?>
                                <img class="pp-standings-team-logo" src="<?php echo $team_logo; ?>" alt="" loading="lazy">
                                <?php endif; ?>
                                <span class="pp-standings-team-name"><?php echo $team_name; ?></span>
                            </td>
                            <td class="pp-standings-td"><?php echo $gp; ?></td>
                            <td class="pp-standings-td"><?php echo $w; ?></td>
                            <td class="pp-standings-td"><?php echo $l; ?></td>
                            <td class="pp-standings-td"><?php echo $otl; ?></td>
                            <?php if ( $any_sol ) : ?>
                            <td class="pp-standings-td"><?php echo $sol; ?></td>
                            <?php endif; ?>
                            <?php if ( $any_ties ) : ?>
                            <td class="pp-standings-td"><?php echo $t; ?></td>
                            <?php endif; ?>
                            <td class="pp-standings-td pp-standings-td--pts"><?php echo $pts; ?></td>
                            <?php if ( $show_pct ) : ?>
                            <td class="pp-standings-td"><?php echo $pct; ?></td>
                            <?php endif; ?>
                            <?php if ( $show_goals ) : ?>
                            <td class="pp-standings-td"><?php echo $gf; ?></td>
                            <td class="pp-standings-td"><?php echo $ga; ?></td>
                            <td class="pp-standings-td <?php echo esc_attr( $diff_cls ); ?>"><?php echo $diff_str; ?></td>
                            <?php endif; ?>
                            <?php if ( $show_home_away ) : ?>
                            <td class="pp-standings-td"><?php echo esc_html( $home_record ); ?></td>
                            <td class="pp-standings-td"><?php echo esc_html( $away_record ); ?></td>
                            <?php endif; ?>
                            <?php if ( $show_streak ) : ?>
                            <td class="pp-standings-td"><?php echo $streak; ?></td>
                            <td class="pp-standings-td"><?php echo $last_10; ?></td>
                            <?php endif; ?>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }
}
