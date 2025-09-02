<?php

/**
 * CardStack Template
 */
class GameSliderTemplate extends PuckPressTemplate
{
    /**
     * Returns a unique key for the template
     */
    public static function get_key(): string
    {
        return 'gameslider';
    }

    /**
     * Returns a human-readable label
     */
    public static function get_label(): string
    {
        return 'Game Slider';
    }

    protected static function get_directory(): string
    {
        return 'slider-templates';
    }

    public static function forceResetColors(): bool
    {
        return false; //only set to true if you want to reset colors, this will overwrite user settings and should be used in development only
    }

    /**
     * Returns an array of default colors
     */
    public static function get_default_colors(): array
    {
        //colors should be in hex format and be uniquely named
        return [
            'header_text_color' => '#f5f5f5',
            'header_bg_color'   => '#333333',
            'body_bg_color'     => '#cccccc',
            'body_text_color'   => '#000000',
            'nav_arrow_color'   => '#215533',
            'border_color' => '#000000'
        ];
    }

    //use this to set additional js dependencies make sure to also update the registry in the template manager abstract file
    public static function get_js_dependencies()
    {
        return ['jquery', 'glider-js'];
    }

    /**
     * Returns the template output
     */
    public function render(array $games): string
    {
        $output = $this->buildSlider($games);
        // Include the template file and capture output

        return $output;
    }

    public function buildSlider(array $games)
    {
        // Split games into past and future
        $split = $this->split_games_by_time($games);
        $counter = count($split['past_games']);

        $sorted_games = $this->sort_games_by_chronological_order($games);
        ob_start();
?>
        <div class="gameslider_slider_container clearfix">
            <div class="glider-contain" style="max-height: 130px; overflow: hidden;">
                <div class="glider">
                    <?php
                    foreach ($sorted_games as $game) {
                        echo $this->render_game_slide($game);
                    }
                    ?>
                </div>
                <button aria-label="Previous" class="glider-prev">‹</button>
                <button aria-label="Next" class="glider-next">›</button>
            </div>
        </div>
        <script>
            window.gameSliderScrollIndex = <?= json_encode($counter) ?>;
        </script>
    <?php
        return ob_get_clean();
    }

    private function render_game_slide($game)
    {
        $now = new DateTime();
        $is_future_game = false;

        if (!empty($game['game_timestamp'])) {
            try {
                $game_time = new DateTime($game['game_timestamp']);
                $is_future_game = $game_time >= $now;
            } catch (Exception $e) {
                $is_future_game = false;
            }
        }

        $is_unscored_past_game = isset($game['target_score']) && $game['target_score'] === '-';

        // Default fallback logo
        $default_logo = 'https://upload.wikimedia.org/wikipedia/commons/thumb/4/47/TBD-W.svg/768px-TBD-W.svg.png?20200316192217'; // Adjust path as needed

        // Check and assign logos, fallback if empty or invalid
        $opponent_logo = !empty($game['opponent_team_logo']) ? $game['opponent_team_logo'] : $default_logo;
        $target_logo   = !empty($game['target_team_logo']) ? $game['target_team_logo'] : $default_logo;

        ob_start();
    ?>
        <div class="content">
            <div class="entry" style="height: 120px">
                <div class="game_vs_message">
                    <div class="home_or_away"><?= ($game['game_status']) ? $game['game_status'] : esc_html($game['home_or_away'] ?? '') ?></div>
                    <span class="vs">
                        <?= ($is_future_game || $is_unscored_past_game)
                            ? 'VS'
                            : esc_html(($game['target_score'] ?? '') . ' - ' . ($game['opponent_score'] ?? '')) ?>
                    </span>
                </div>
                <div class="hometeam">
                    <div class="thumb">
                        <img src="<?= esc_url($opponent_logo) ?>" alt="<?= esc_attr($game['opponent_team_name'] ?? '') ?>" loading="lazy">
                    </div>
                </div>
                <div class="awayteam_active">
                    <div class="thumb">
                        <img src="<?= esc_url($target_logo) ?>" alt="<?= esc_attr($game['target_team_name'] ?? 'Away Team') ?>" loading="lazy">
                    </div>
                </div>
                <div class="details">
                    <span class="time"><?= esc_html($game['game_date_day'] ?? '') ?></span>
                </div>
            </div>
        </div>
<?php
        return ob_get_clean();
    }
}
