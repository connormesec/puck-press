<?php

/**
 * Abstract Template Class
 */
abstract class PuckPressTemplate
{
    /**
     * Returns a unique key for the template (used for selection)
     */
    abstract public static function get_key(): string;

    /**
     * Returns a human-readable label
     */
    abstract public static function get_label(): string;

    /**
     * Returns an array of default colors
     */
    abstract public static function get_default_colors(): array;

    /**
     * Allows resetting colors to defaults, only for development purposes
     */
    abstract public static function forceResetColors(): bool;

    /**
     * Returns the HTML/CSS/JS template output
     */
    abstract public function render(array $values): string;

    /**
     * Returns the relative subdirectory for this template (e.g., 'schedule-templates' or 'roster-templates')
     */
    abstract protected static function get_directory(): string;

    /**
     * Returns the path to the template file
     */
    public static function get_template_path(): string
    {
        return plugin_dir_path(__FILE__) . static::get_directory() . '/' . static::get_key() . '_template.php';
    }

    /**
     * Returns the path to the CSS file
     */
    public static function get_css_path(): string
    {
        return plugin_dir_path(__FILE__) . static::get_directory() . '/' . static::get_key() . '.css';
    }

    /**
     * Returns the path to the JS file
     */
    public static function get_js_path(): string
    {
        return plugin_dir_path(__FILE__) . static::get_directory() . '/' . static::get_key() . '.js';
    }

    /**
     * Returns the URL to the CSS file
     */
    public static function get_css_url(): string
    {
        return plugin_dir_url(__FILE__) . static::get_directory() . '/' . static::get_key() . '.css';
    }

    /**
     * Returns the URL to the JS file
     */
    public static function get_js_url(): string
    {
        return plugin_dir_url(__FILE__) . static::get_directory() . '/' . static::get_key() . '.js';
    }

    /**
     * Get saved colors for a template
     *
     * @return array Saved colors, or default colors if not saved
     */
    public static function get_template_colors(): array
    {
        // Infer type from the directory name
        $directory = static::get_directory();

        // Normalize the directory to a "type" (strip '-templates')
        $type = str_replace('-templates', '', $directory);

        // Build option name
        $option_name = "pp_{$type}_template_colors_" . static::get_key();

        return get_option($option_name, static::get_default_colors());
    }

    /**
     * Optionally return inline CSS (e.g. using colors from get_option)
     */
    public static function get_inline_css(): ?string
    {
        $colors = static::get_template_colors();
        $template_key = static::get_key();

        if (empty($colors) || !is_array($colors)) {
            return null;
        }

        $css = ':root {';
        foreach ($colors as $key => $val) {
            $css .= "--pp-{$template_key}-{$key}: {$val};";
        }
        $css .= '}';

        return $css;
    }


    public static function get_external_script_registry()
    {
        return static::$external_script_registry ?? [];
    }

    public function split_games_by_time(array $games, ?DateTime $now = null): array
    {
        $now = $now ?? new DateTime(); // Use provided DateTime or default to now
        $past_games = [];
        $future_games = [];

        foreach ($games as $game) {
            if (empty($game['game_timestamp'])) {
                continue;
            }

            try {
                $game_time = new DateTime($game['game_timestamp']);
            } catch (Exception $e) {
                continue; // Skip invalid dates
            }

            if ($game_time < $now) {
                $past_games[] = $game;
            } else {
                $future_games[] = $game;
            }
        }

        return [
            'past_games' => $past_games,
            'future_games' => $future_games
        ];
    }

    /**
     * Sort games in chronological order (ascending by default) based on game_timestamp
     *
     * @param array $games Array of games, each with a 'game_timestamp' field
     * @param bool $reverse If true, sorts in descending order (most recent first)
     * @return array Sorted array of games
     */
    public static function sort_games_by_chronological_order(array $games, bool $reverse = false): array
    {
        usort($games, function ($a, $b) use ($reverse) {
            try {
                $timeA = new DateTime($a['game_timestamp'] ?? '');
                $timeB = new DateTime($b['game_timestamp'] ?? '');
            } catch (Exception $e) {
                return 0; // Consider invalid timestamps equal
            }

            return $reverse
                ? $timeB <=> $timeA // descending
                : $timeA <=> $timeB; // ascending
        });

        return $games;
    }

    /**
     * Group games into an associative array by month and sort chronologically
     * 
     * @param array $games Array of games with game_timestamp property
     * @return array Games grouped by month name in chronological order
     */
    public static function group_games_by_month($games, $remove_year_from_keys = true)
    {
        $grouped_games = [];
        $month_year_keys = []; // To store month-year combinations for sorting

        foreach ($games as $game) {
            // Ensure the game has a timestamp
            if (!isset($game['game_timestamp'])) {
                continue;
            }

            // Try to create a DateTime object from the string timestamp
            try {
                $date = new DateTime($game['game_timestamp']);
            } catch (Exception $e) {
                // Skip this game if the timestamp format is invalid
                continue;
            }

            // Get month name and year
            $month = $date->format('F');
            $year = $date->format('Y');
            $month_year = $month . ' ' . $year;

            // Initialize the month array if it doesn't exist
            if (!isset($grouped_games[$month_year])) {
                $grouped_games[$month_year] = [];
                $month_year_keys[$month_year] = $date->format('Ym'); // Store as YYYYMM for sorting
            }

            // Add the game to the appropriate month-year
            $grouped_games[$month_year][] = $game;
        }

        // Sort by chronological order
        uksort($grouped_games, function ($a, $b) use ($month_year_keys) {
            return $month_year_keys[$a] - $month_year_keys[$b];
        });

        // If removing year from the keys
        if ($remove_year_from_keys) {
            // If you want to remove the year from the keys but keep the order
            $result = [];
            foreach ($grouped_games as $month_year => $games) {
                $month = self::extract_month($month_year);
                $result[$month] = $games;
            }
            return $result;
        }

        return $grouped_games;
    }

    public static function extract_month($month_year)
    {
        return explode(' ', $month_year)[0];;
    }

    static function console_log($output, $with_script_tags = true)
    {
        $js_code = 'console.log(' . json_encode($output, JSON_HEX_TAG) .
            ');';
        if ($with_script_tags) {
            $js_code = '<script>' . $js_code . '</script>';
        }
        echo $js_code;
    }
}
