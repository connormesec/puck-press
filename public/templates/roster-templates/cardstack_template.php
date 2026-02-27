<?php

/**
 * CardStack Template
 */
class CardStackTemplate extends PuckPressTemplate
{
    /**
     * Returns a unique key for the template
     */
    public static function get_key(): string
    {
        return 'cardstack';
    }

    /**
     * Returns a human-readable label
     */
    public static function get_label(): string
    {
        return 'Card Stack Roster';
    }

    protected static function get_directory(): string
    {
        return 'roster-templates';
    }

    public static function forceResetColors(): bool
    {
        return false; //only set to true if you want to reset colors, this will overwrite user settings and should be used in development only
    }

    public static function get_js_dependencies(): array
    {
        return [ 'jquery', 'pp-player-detail' ];
    }

    public static function get_color_labels(): array
    {
        return [
            'header_text'   => 'Header Text (Player Detail)',
            'container_bg'  => 'Card Background (Player Detail)',
            'border'        => 'Border Color',
            'month_text'    => 'Position Title Text',
            'content_text'  => 'Content Text',
            'number_text'   => 'Jersey Number Text',
        ];
    }

    public static function get_default_fonts(): array
    {
        return ['roster_font' => ''];
    }

    public static function get_font_labels(): array
    {
        return ['roster_font' => 'Roster Font'];
    }

    public static function get_player_detail_font_vars(): array
    {
        $fonts = static::get_template_fonts();
        $font  = $fonts['roster_font'] ?? '';
        if (empty($font)) return [];
        $safe = str_replace(["'", '"', ';', '}'], '', $font);
        return ['--pp-pd-font-family' => "'{$safe}', sans-serif"];
    }

    /**
     * Returns an array of default colors
     */
    public static function get_default_colors(): array
    {
        //colors should be in hex format and be uniquely names
        return [
            'header_text' => '#215530',
            'container_bg' => '#ffffff',
            'border'     => '#000000',
            'month_text' => '#ffffff',
            'content_text' => '#000000',
            'number_text' => '#ffffff',
        ];
    }

    /**
     * Returns the template output
     */
    public function render_with_options(array $players, array $options): string
    {
        $output = $this->buildCardStack($players);
        // Include the template file and capture output

        return $output;
    }

    public function buildCardStack(array $players)
    {
        global $wpdb;
        $player_ids_with_stats = $wpdb->get_col(
            "SELECT player_id FROM {$wpdb->prefix}pp_roster_stats UNION SELECT player_id FROM {$wpdb->prefix}pp_roster_goalie_stats"
        );
        $players_with_stats = array_flip( $player_ids_with_stats ?: [] );

        $content = '<div class="cardstack_roster_container clearfix">';

        // Skaters (players without assigned positions) - shown first
        $skaters = $this->getPlayersWithoutPositions($players);
        if (!empty($skaters)) {
            $content .= '<div class="player_group">';
            $content .= '<div class="player_position_title"><h2>Skaters</h2></div>';
            foreach ($skaters as $player) {
                $has_stats = isset( $players_with_stats[ $player['player_id'] ?? '' ] );
                $content .= $this->createPlayerCard($player['id'], $player['player_id'], $player['headshot_link'], $player['number'], $player['name'], $player['pos'], $player['hometown'], $player['ht'], $player['wt'], $player['shoots'], $player['year_in_school'] ?? null, $player['last_team'] ?? null, $player['major'] ?? null, $has_stats);
            }
            $content .= '</div>';
        }

        //forwards
        $content .= '<div class="player_group">';
        $content .= '<div class="player_position_title"><h2>Forwards</h2></div>';
        $forwards = $this->getPlayersByPositions($players, ['F', 'C', 'LW', 'RW']);
        foreach ($forwards as $player) {
            $has_stats = isset( $players_with_stats[ $player['player_id'] ?? '' ] );
            $content .= $this->createPlayerCard($player['id'], $player['player_id'], $player['headshot_link'], $player['number'], $player['name'], $player['pos'], $player['hometown'], $player['ht'], $player['wt'], $player['shoots'], $player['year_in_school'] ?? null, $player['last_team'] ?? null, $player['major'] ?? null, $has_stats);
        }
        $content .= '</div>';

        $content .= '<div class="player_group">';
        $content .= '<div class="player_position_title"><h2>Defense</h2></div>';
        $defense = $this->getPlayersByPositions($players, ['D', 'LD', 'RD']);
        foreach ($defense as $player) {
            $has_stats = isset( $players_with_stats[ $player['player_id'] ?? '' ] );
            $content .= $this->createPlayerCard($player['id'], $player['player_id'], $player['headshot_link'], $player['number'], $player['name'], $player['pos'], $player['hometown'], $player['ht'], $player['wt'], $player['shoots'], $player['year_in_school'] ?? null, $player['last_team'] ?? null, $player['major'] ?? null, $has_stats);
        }
        $content .= '</div>';

        $content .= '<div class="player_group">';
        $content .= '<div class="player_position_title"><h2>Goalies</h2></div>';
        $goalies = $this->getPlayersByPositions($players, ['G']);
        foreach ($goalies as $player) {
            $has_stats = isset( $players_with_stats[ $player['player_id'] ?? '' ] );
            $content .= $this->createPlayerCard($player['id'], $player['player_id'], $player['headshot_link'], $player['number'], $player['name'], $player['pos'], $player['hometown'], $player['ht'], $player['wt'], $player['shoots'], $player['year_in_school'] ?? null, $player['last_team'] ?? null, $player['major'] ?? null, $has_stats);
        }
        $content .= '</div>';


        $content .= '</div>';

        return $content;
    }

    private function createPlayerCard(
        $id,
        $player_id,
        $headshot_image,
        $number,
        $name,
        $position,
        $hometown,
        $ht,
        $wt,
        $shoots,
        $year_in_school = null,
        $last_team = null,
        $major = null,
        $has_stats = true
    ) {
        $fallback_headshot = 'https://www.pathwaysvermont.org/wp-content/uploads/2017/03/avatar-placeholder-e1490629554738.png';

        // Clean up hometown (remove trailing country labels)
        if (!empty($hometown)) {
            $hometown = str_replace(
                [', United States', ', Canada'],
                '',
                $hometown
            );
            $hometown = "Hometown: {$hometown}";
        } else {
            $hometown = '';
        }

        $shoots   = !empty($shoots) ? "Shoots: {$shoots}" : '';

        // Height/Weight logic
        if (!empty($ht) && !empty($wt)) {
            $htwt = "HT/WT: {$ht} / {$wt}";
        } elseif (!empty($ht)) {
            $htwt = "HT: {$ht}";
        } elseif (!empty($wt)) {
            $htwt = "WT: {$wt}";
        } else {
            $htwt = '';
        }

        $year_html = $year_in_school ? "<span class=\"year\">Year: {$year_in_school}</span>" : '';
        $last_team_html = $last_team ? "<div><span class=\"prev_team\">Last Team: {$last_team}</span></div>" : '';

        $slug        = sanitize_title( $name );
        $id_attr     = $has_stats ? ' id="' . esc_attr( $slug ) . '"' : '';
        $extra_class = $has_stats ? '' : ' no-stats';

        $link_open  = $has_stats ? '<a class="pp-player-link" href="' . esc_url( home_url( '/player/' . $slug ) ) . '">' : '';
        $link_close = $has_stats ? '</a>' : '';

        $card = <<<HTML
        {$link_open}<div class="player_item clearfix{$extra_class}"{$id_attr} data-primary-key="{$id}">
            <div class="thumb">
                <img src="{$headshot_image}" onerror="this.onerror=null;this.src='{$fallback_headshot}';" alt="{$name} headshot" loading="lazy"/>
            </div>
            <div class="info">
                <div class="label">{$number}</div>
                <h3 class="player_name">{$name}</h3>
                <div class="position">{$position}</div>
                <div class="player_data">
                    <div>
                        <span class="shoots">{$shoots}</span>
                        <span class="hometown">{$hometown}</span>
                    </div>
                    <div>
                        <span class="height">{$htwt}</span>
                        {$year_html}
                    </div>
                    {$last_team_html}
                </div>
            </div>
        </div>{$link_close}
    HTML;

        return $card;
    }


    private function getPlayersByPositions(array $players, array $positions): array
    {
        // Normalize positions to uppercase for consistent comparison
        $positions = array_map('strtoupper', $positions);

        // Filter players by position
        $filtered = array_filter($players, function ($player) use ($positions) {
            return isset($player['pos']) && in_array(strtoupper($player['pos']), $positions, true);
        });

        // Sort by 'number' ascending
        usort($filtered, function ($a, $b) {
            return (int)$a['number'] <=> (int)$b['number'];
        });

        return $filtered;
    }

    private function getPlayersWithoutPositions(array $players): array
    {
        $known = ['F', 'C', 'LW', 'RW', 'D', 'LD', 'RD', 'G'];

        $filtered = array_filter($players, function ($player) use ($known) {
            return empty($player['pos']) || !in_array(strtoupper($player['pos']), $known, true);
        });

        usort($filtered, function ($a, $b) {
            return (int)$a['number'] <=> (int)$b['number'];
        });

        return array_values($filtered);
    }
}
