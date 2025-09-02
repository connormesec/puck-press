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
    public function render(array $players): string
    {
        $output = $this->buildCardStack($players);
        // Include the template file and capture output

        return $output;
    }

    public function buildCardStack(array $players)
    {
        $content = '<div class="cardstack_container clearfix">';
        //forwards
        $content .= '<div class="player_group">';
        $content .= '<div class="player_position_title"><h2>Forwards</h2></div>';
        $forwards = $this->getPlayersByPosition($players, 'F');
        foreach ($forwards as $player) {
            $content .= $this->createPlayerCard($player['id'], $player['player_id'], $player['headshot_link'], $player['number'], $player['name'], $player['pos'], $player['hometown'], $player['ht'], $player['wt'], $player['shoots'], $player['year_in_school'] ?? null, $player['last_team'] ?? null, $player['major'] ?? null);
        }
        $content .= '</div>';

        $content .= '<div class="player_group">';
        $content .= '<div class="player_position_title"><h2>Defense</h2></div>';
        $defense = $this->getPlayersByPosition($players, 'D');
        foreach ($defense as $player) {
            $content .= $this->createPlayerCard($player['id'], $player['player_id'], $player['headshot_link'], $player['number'], $player['name'], $player['pos'], $player['hometown'], $player['ht'], $player['wt'], $player['shoots'], $player['year_in_school'] ?? null, $player['last_team'] ?? null, $player['major'] ?? null);
        }
        $content .= '</div>';

        $content .= '<div class="player_group">';
        $content .= '<div class="player_position_title"><h2>Goalies</h2></div>';
        $goalies = $this->getPlayersByPosition($players, 'G');
        foreach ($goalies as $player) {
            $content .= $this->createPlayerCard($player['id'], $player['player_id'], $player['headshot_link'], $player['number'], $player['name'], $player['pos'], $player['hometown'], $player['ht'], $player['wt'], $player['shoots'], $player['year_in_school'] ?? null, $player['last_team'] ?? null, $player['major'] ?? null);
        }
        $content .= '</div>';


        $content .= '</div>';

        return $content;
    }

    private function createPlayerCard($id, $player_id, $headshot_image, $number, $name, $position, $hometown, $ht, $wt, $shoots, $year_in_school = null, $last_team = null, $major = null)
    {
        $fallback_headshot = 'https://www.pathwaysvermont.org/wp-content/uploads/2017/03/avatar-placeholder-e1490629554738.png';

        $year_html = $year_in_school ? "<span class=\"year\">Year: {$year_in_school}</span>" : '';
        $last_team_html = $last_team ? "<div><span class=\"prev_team\">Last Team: {$last_team}</span></div>" : '';

        $card = <<<HTML
        <div class="player_item clearfix" id="{$player_id}" data-primary-key="{$id}">
            <div class="thumb">
                <img src="{$headshot_image}" onerror="this.onerror=null;this.src='{$fallback_headshot}';" alt="player headshot" loading="lazy"/>
            </div>
            <div class="info">
                <div class="label">{$number}</div>
                <h3 class="player_name">{$name}</h3>
                <div class="position">{$position}</div>
                <div class="player_data">
                    <div>
                        <span class="shoots">Shoots: {$shoots}</span>
                        <span class="hometown">Hometown: {$hometown}</span>
                    </div>
                    <div>
                        <span class="height">HT/WT: {$ht} / {$wt}</span>
                        {$year_html}
                    </div>
                    {$last_team_html}
                </div>
            </div>
        </div>
    HTML;

        return $card;
    }

    private function getPlayersByPosition(array $players, string $position): array
    {
        // Filter players by position
        $filtered = array_filter($players, function ($player) use ($position) {
            return isset($player['pos']) && strtoupper($player['pos']) === strtoupper($position);
        });

        // Sort by 'number' ascending
        usort($filtered, function ($a, $b) {
            return (int)$a['number'] <=> (int)$b['number'];
        });

        return $filtered;
    }
}
