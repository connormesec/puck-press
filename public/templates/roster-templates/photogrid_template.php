<?php

/**
 * PhotoGrid Template
 *
 * Displays players grouped by position in a responsive photo grid.
 * Each card shows a portrait headshot, jersey number, position, and name.
 */
class PhotoGridTemplate extends PuckPressTemplate
{
    public static function get_key(): string
    {
        return 'photogrid';
    }

    public static function get_label(): string
    {
        return 'Photo Grid Roster';
    }

    protected static function get_directory(): string
    {
        return 'roster-templates';
    }

    public static function forceResetColors(): bool
    {
        return false;
    }

    /**
     * Returns an array of default colors
     */
    public static function get_default_colors(): array
    {
        return [
            'accent_color'      => '#2a8fa8', // teal — used for section headings, number, and position text
            'player_name_color' => '#1a1a1a', // dark — player name below the meta line
            'card_bg'           => '#ffffff', // background of each player card
            'page_bg'           => '#f0f0f0', // overall container background
        ];
    }

    /**
     * Renders the photo grid roster
     */
    public function render(array $players): string
    {
        $output = '<div class="photogrid_roster_container">';

        // Players with no recognized position
        $skaters = $this->getPlayersWithoutPositions($players);
        if (!empty($skaters)) {
            $output .= $this->buildSection('Skaters', $skaters);
        }

        $forwards = $this->getPlayersByPositions($players, ['F', 'C', 'LW', 'RW']);
        if (!empty($forwards)) {
            $output .= $this->buildSection('Forwards', $forwards);
        }

        $defense = $this->getPlayersByPositions($players, ['D', 'LD', 'RD']);
        if (!empty($defense)) {
            $output .= $this->buildSection('Defensemen', $defense);
        }

        $goalies = $this->getPlayersByPositions($players, ['G']);
        if (!empty($goalies)) {
            $output .= $this->buildSection('Goalies', $goalies);
        }

        $output .= '</div>';

        return $output;
    }

    // -------------------------------------------------------------------------
    // Private helpers
    // -------------------------------------------------------------------------

    private function buildSection(string $heading, array $players): string
    {
        $html  = '<div class="photogrid_section">';
        $html .= '<h2 class="photogrid_heading">' . esc_html($heading) . '</h2>';
        $html .= '<div class="photogrid_grid">';
        foreach ($players as $player) {
            $html .= $this->createCard($player);
        }
        $html .= '</div>';
        $html .= '</div>';
        return $html;
    }

    private function createCard(array $player): string
    {
        $fallback   = 'https://www.pathwaysvermont.org/wp-content/uploads/2017/03/avatar-placeholder-e1490629554738.png';
        $img        = !empty($player['headshot_link']) ? esc_url($player['headshot_link']) : $fallback;
        $name       = esc_html($player['name'] ?? '');
        $number     = !empty($player['number']) ? '#' . esc_html($player['number']) : '';
        $pos        = esc_html($player['pos'] ?? '');
        $id         = esc_attr($player['player_id'] ?? '');
        $primary_key = esc_attr($player['id'] ?? '');

        // Build the "#76 | Forward" meta line
        if ($number && $pos) {
            $meta = $number . ' <span class="photogrid_sep">|</span> ' . $pos;
        } elseif ($number) {
            $meta = $number;
        } elseif ($pos) {
            $meta = $pos;
        } else {
            $meta = '';
        }

        return <<<HTML
<div class="photogrid_card" id="{$id}" data-primary-key="{$primary_key}">
    <div class="photogrid_img_wrap">
        <img src="{$img}" onerror="this.onerror=null;this.src='{$fallback}';" alt="{$name}" loading="lazy" />
    </div>
    <div class="photogrid_info">
        <div class="photogrid_meta">{$meta}</div>
        <div class="photogrid_name">{$name}</div>
    </div>
</div>
HTML;
    }

    private function getPlayersByPositions(array $players, array $positions): array
    {
        $positions = array_map('strtoupper', $positions);
        $filtered  = array_filter($players, function ($p) use ($positions) {
            return isset($p['pos']) && in_array(strtoupper($p['pos']), $positions, true);
        });
        usort($filtered, fn($a, $b) => (int) $a['number'] <=> (int) $b['number']);
        return array_values($filtered);
    }

    private function getPlayersWithoutPositions(array $players): array
    {
        $known    = ['F', 'C', 'LW', 'RW', 'D', 'LD', 'RD', 'G'];
        $filtered = array_filter($players, function ($p) use ($known) {
            return empty($p['pos']) || !in_array(strtoupper($p['pos']), $known, true);
        });
        usort($filtered, fn($a, $b) => (int) $a['number'] <=> (int) $b['number']);
        return array_values($filtered);
    }
}
