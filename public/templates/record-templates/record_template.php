<?php

class RecordTemplate extends PuckPressTemplate
{
    public static function get_key(): string
    {
        return 'record';
    }

    public static function get_label(): string
    {
        return 'Default Record Card';
    }

    protected static function get_directory(): string
    {
        return 'record-templates';
    }

    public static function get_default_colors(): array
    {
        return [
            'card_bg'     => '#1a1a2e',
            'header_text' => '#ffffff',
            'label_text'  => '#a0a0b0',
            'value_text'  => '#ffffff',
            'border'      => '#0f3460',
            'accent'      => '#e94560',
            'split_bg'    => '#0f3460',
        ];
    }

    public static function get_color_labels(): array
    {
        return [
            'card_bg'     => 'Card Background',
            'header_text' => 'Header Text',
            'label_text'  => 'Label / Secondary Text',
            'value_text'  => 'Value Text',
            'border'      => 'Border Color',
            'accent'      => 'Record Accent Color',
            'split_bg'    => 'Home / Away Row Background',
        ];
    }

    public static function forceResetColors(): bool
    {
        return false;
    }

    public static function get_default_fonts(): array
    {
        return ['record_font' => ''];
    }

    public static function get_font_labels(): array
    {
        return ['record_font' => 'Card Font'];
    }

    public function render_with_options(array $values, array $options): string
    {
        $wins   = (int) ($values['wins']   ?? 0);
        $losses = (int) ($values['losses'] ?? 0);
        $otl    = (int) ($values['otl']    ?? 0);
        $ties   = (int) ($values['ties']   ?? 0);
        $gf     = (int) ($values['gf']     ?? 0);
        $ga     = (int) ($values['ga']     ?? 0);
        $diff   = $gf - $ga;
        $gp     = $wins + $losses + $otl + $ties;

        $home_wins   = (int) ($values['home_wins']   ?? 0);
        $home_losses = (int) ($values['home_losses'] ?? 0);
        $home_otl    = (int) ($values['home_otl']    ?? 0);
        $home_ties   = (int) ($values['home_ties']   ?? 0);
        $home_gf     = (int) ($values['home_gf']     ?? 0);
        $home_ga     = (int) ($values['home_ga']     ?? 0);

        $away_wins   = (int) ($values['away_wins']   ?? 0);
        $away_losses = (int) ($values['away_losses'] ?? 0);
        $away_otl    = (int) ($values['away_otl']    ?? 0);
        $away_ties   = (int) ($values['away_ties']   ?? 0);
        $away_gf     = (int) ($values['away_gf']     ?? 0);
        $away_ga     = (int) ($values['away_ga']     ?? 0);

        $show_home_away = !isset($values['show_home_away']) || filter_var($values['show_home_away'], FILTER_VALIDATE_BOOLEAN);
        $show_goals     = !isset($values['show_goals'])     || filter_var($values['show_goals'],     FILTER_VALIDATE_BOOLEAN);
        $show_diff      = !isset($values['show_diff'])      || filter_var($values['show_diff'],      FILTER_VALIDATE_BOOLEAN);
        $title          = esc_html($values['title'] ?? 'Team Record');

        // W-L-OTL (always three segments); append -T only if regulation ties exist
        $record_str = "{$wins}-{$losses}-{$otl}";
        if ($ties > 0) {
            $record_str .= "-{$ties}T";
        }

        $diff_str   = ($diff >= 0 ? '+' : '') . $diff;
        $diff_class = $diff >= 0 ? 'positive' : 'negative';

        $home_record = "{$home_wins}-{$home_losses}-{$home_otl}" . ($home_ties > 0 ? "-{$home_ties}T" : '');
        $away_record = "{$away_wins}-{$away_losses}-{$away_otl}" . ($away_ties > 0 ? "-{$away_ties}T" : '');

        $key = static::get_key();

        ob_start();
        ?>
        <div class="pp-record-card <?php echo esc_attr($key); ?>_record_container">
            <div class="pp-record-header">
                <span class="pp-record-title"><?php echo $title; ?></span>
                <div class="pp-record-stat-row pp-record-stat-row--main">
                    <div class="pp-record-stat">
                        <span class="pp-record-stat-value"><?php echo $gp; ?></span>
                        <span class="pp-record-stat-label">GP</span>
                    </div>
                    <div class="pp-record-stat-divider">–</div>
                    <div class="pp-record-stat">
                        <span class="pp-record-stat-value"><?php echo $wins; ?></span>
                        <span class="pp-record-stat-label">W</span>
                    </div>
                    <div class="pp-record-stat-divider">–</div>
                    <div class="pp-record-stat">
                        <span class="pp-record-stat-value"><?php echo $losses; ?></span>
                        <span class="pp-record-stat-label">L</span>
                    </div>
                    <div class="pp-record-stat-divider">–</div>
                    <div class="pp-record-stat">
                        <span class="pp-record-stat-value"><?php echo $otl; ?></span>
                        <span class="pp-record-stat-label">OTL</span>
                    </div>
                    <?php if ($ties > 0): ?>
                    <div class="pp-record-stat-divider">–</div>
                    <div class="pp-record-stat">
                        <span class="pp-record-stat-value"><?php echo $ties; ?></span>
                        <span class="pp-record-stat-label">T</span>
                    </div>
                    <?php endif; ?>
                </div>
            </div>

            <div class="pp-record-stats">

                <?php if ($show_goals): ?>
                <div class="pp-record-goals-row">
                    <div class="pp-record-goal-stat">
                        <span class="pp-record-stat-label">GF</span>
                        <span class="pp-record-stat-value"><?php echo $gf; ?></span>
                    </div>
                    <div class="pp-record-goal-stat">
                        <span class="pp-record-stat-label">GA</span>
                        <span class="pp-record-stat-value"><?php echo $ga; ?></span>
                    </div>
                    <?php if ($show_diff): ?>
                    <div class="pp-record-goal-stat">
                        <span class="pp-record-stat-label">DIFF</span>
                        <span class="pp-record-stat-value pp-record-diff <?php echo $diff_class; ?>"><?php echo $diff_str; ?></span>
                    </div>
                    <?php endif; ?>
                </div>
                <?php endif; ?>

                <?php if ($show_home_away): ?>
                <div class="pp-record-split-section">
                    <div class="pp-record-split-row pp-record-split-row--home">
                        <span class="pp-record-split-label">Home</span>
                        <span class="pp-record-split-record"><?php echo esc_html($home_record); ?></span>
                        <?php if ($show_goals): ?>
                        <span class="pp-record-split-goals"><?php echo "{$home_gf} GF / {$home_ga} GA"; ?></span>
                        <?php endif; ?>
                    </div>
                    <div class="pp-record-split-row pp-record-split-row--away">
                        <span class="pp-record-split-label">Away</span>
                        <span class="pp-record-split-record"><?php echo esc_html($away_record); ?></span>
                        <?php if ($show_goals): ?>
                        <span class="pp-record-split-goals"><?php echo "{$away_gf} GF / {$away_ga} GA"; ?></span>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endif; ?>

            </div>
        </div>
        <?php
        return ob_get_clean();
    }
}
