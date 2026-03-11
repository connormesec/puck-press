<?php

/**
 * Slate Template
 */
class SlateTemplate extends PuckPressTemplate
{
    public static function get_key(): string
    {
        return 'slate';
    }

    public static function get_label(): string
    {
        return 'Slate Schedule';
    }

    protected static function get_directory(): string
    {
        return 'schedule-templates';
    }

    public static function forceResetColors(): bool
    {
        return false;
    }

    public static function get_default_colors(): array
    {
        return [
            'accent'          => '#009DA5',
            'page_bg'         => '#F0F4F5',
            'card_bg'         => '#FFFFFF',
            'text'            => '#1A1A2E',
            'ticket_btn_bg'   => '#111111',
            'ticket_btn_text' => '#FFFFFF',
        ];
    }

    public static function get_default_fonts(): array
    {
        return ['schedule_font' => ''];
    }

    public static function get_font_labels(): array
    {
        return ['schedule_font' => 'Schedule Font'];
    }

    public function render_with_options(array $games, array $options): string
    {
        $slug         = $options['schedule_slug'] ?? '';
        $schedule_id  = isset($options['schedule_id']) ? (int) $options['schedule_id'] : 0;
        $container_id = $slug ? 'pp-sched-' . sanitize_html_class($slug) : '';
        $scope        = $container_id ? '#' . $container_id : ':root';
        $colors       = $schedule_id > 0 ? self::get_schedule_colors($schedule_id) : null;
        $fonts        = $schedule_id > 0 ? self::get_schedule_fonts($schedule_id) : null;
        $inline_css   = self::get_inline_css($scope, $colors, $fonts);
        $css_block    = $inline_css ? '<style>' . $inline_css . '</style>' : '';
        return $css_block . $this->buildSlateSchedule($games, $options['is_archive'] ?? false, $container_id);
    }

    private function buildSlateSchedule(array $games, bool $is_archive = false, string $container_id = ''): string
    {
        $id_attr = $container_id ? ' id="' . esc_attr($container_id) . '"' : '';
        $html = '<div class="slate_schedule_container"' . $id_attr . '>';

        if ($is_archive) {
            $all_grouped = self::group_games_by_month($games, false);
            $reversed    = array_reverse($all_grouped, true);
            foreach ($reversed as $month_year => $month_games) {
                $html .= $this->renderMonthSection($month_year, array_reverse($month_games), true);
            }
        } else {
            $games_split       = $this->split_games_by_time($games);
            $upcoming_by_month = self::group_games_by_month($games_split['future_games'], false);
            $past_by_month     = array_reverse(self::group_games_by_month($games_split['past_games'], false));

            // Tab buttons
            $html .= '<div class="slate-tabs">';
            $html .= '<button class="slate-tab-btn slate-tab-active" data-slate-tab="slate-upcoming">Upcoming</button>';
            $html .= '<button class="slate-tab-btn" data-slate-tab="slate-results">Results</button>';
            $html .= '</div>';

            // Upcoming panel
            $html .= '<div class="slate-panel" id="slate-upcoming">';
            if (empty($upcoming_by_month)) {
                $html .= '<p class="slate-no-games">No upcoming games scheduled.</p>';
            } else {
                foreach ($upcoming_by_month as $month_year => $month_games) {
                    $html .= $this->renderMonthSection($month_year, $month_games, false);
                }
            }
            $html .= '</div>';

            // Results panel
            $html .= '<div class="slate-panel" id="slate-results" style="display:none;">';
            if (empty($past_by_month)) {
                $html .= '<p class="slate-no-games">No results yet.</p>';
            } else {
                foreach ($past_by_month as $month_year => $month_games) {
                    $html .= $this->renderMonthSection($month_year, array_reverse($month_games), true);
                }
            }
            $html .= '</div>';
        }

        $html .= '</div>'; // .slate_schedule_container

        return $html;
    }

    private function renderMonthSection(string $month_year, array $month_games, bool $is_past): string
    {
        $month_label = self::extract_month($month_year);
        $html  = '<div class="slate-month-section">';
        $html .= '<h2 class="slate-month-heading">' . esc_html($month_label) . '</h2>';
        foreach ($month_games as $game) {
            $html .= $is_past ? $this->renderPastCard($game) : $this->renderUpcomingCard($game);
        }
        $html .= '</div>';
        return $html;
    }

    private function renderUpcomingCard(array $game): string
    {
        $vs_at      = ($game['home_or_away'] === 'away') ? '@' : 'vs.';
        $home_class = ($game['home_or_away'] === 'home') ? 'slate-home' : 'slate-away';
        $date_label = $this->formatDate($game);
        $logo_html  = $this->opponentLogoHtml($game);

        $html  = '<div class="slate-game-card ' . $home_class . '">';

        // Main row
        $html .= '<div class="slate-game-main-row">';
        $html .= $this->renderDateCol($date_label, $game['game_time'] ?? '');
        $html .= '<div class="slate-match-col">';
        $html .= '<span class="slate-vs-at">' . esc_html($vs_at) . '</span>';
        $html .= $logo_html;
        $html .= '<span class="slate-opp-name">' . esc_html($game['opponent_team_name'] ?? '') . '</span>';
        $html .= '</div>';
        if (!empty($game['promo_ticket_link'])) {
            $html .= '<div class="slate-action-col">';
            $html .= '<a class="slate-ticket-btn" href="' . esc_url($game['promo_ticket_link']) . '" target="_blank" rel="noopener">';
            $html .= 'Buy Tickets ';
            $html .= '<svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M7 10l5 5 5-5z"/></svg>';
            $html .= '</a>';
            $html .= '</div>';
        }
        $html .= '</div>'; // .slate-game-main-row

        // Promo section
        $has_promo = !empty($game['promo_header']) || !empty($game['promo_text']);
        if ($has_promo) {
            $html .= '<div class="slate-promo-section">';
            if (!empty($game['promo_header'])) {
                $html .= '<div class="slate-promo-header-line">';
                $html .= '<svg class="slate-icon-flag" xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M14.4 6L14 4H5v17h2v-7h5.6l.4 2h7V6z"/></svg>';
                $html .= '<em>' . esc_html($game['promo_header']) . '</em>';
                $html .= '</div>';
            }
            if (!empty($game['promo_text'])) {
                $items = array_filter(array_map('trim', explode("\n", $game['promo_text'])));
                if (!empty($items)) {
                    $html .= '<ul class="slate-promo-items">';
                    foreach ($items as $item) {
                        $html .= '<li>';
                        $html .= '<svg class="slate-icon-plus" xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M19 3H5a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V5a2 2 0 0 0-2-2zm-2 10h-4v4h-2v-4H7v-2h4V7h2v4h4v2z"/></svg>';
                        $html .= esc_html($item);
                        $html .= '</li>';
                    }
                    $html .= '</ul>';
                }
            }
            $html .= '</div>'; // .slate-promo-section
        }

        // Footer row (venue)
        if (!empty($game['venue'])) {
            $html .= '<div class="slate-footer-row">';
            $html .= '<span class="slate-venue">';
            $html .= '<svg class="slate-icon-pin" xmlns="http://www.w3.org/2000/svg" width="13" height="13" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M12 2C8.13 2 5 5.13 5 9c0 5.25 7 13 7 13s7-7.75 7-13c0-3.87-3.13-7-7-7zm0 9.5c-1.38 0-2.5-1.12-2.5-2.5s1.12-2.5 2.5-2.5 2.5 1.12 2.5 2.5-1.12 2.5-2.5 2.5z"/></svg>';
            $html .= esc_html($game['venue']);
            $html .= '</span>';
            $html .= '</div>';
        }

        $html .= '</div>'; // .slate-game-card
        return $html;
    }

    private function renderPastCard(array $game): string
    {
        $vs_at      = ($game['home_or_away'] === 'away') ? '@' : 'vs.';
        $home_class = ($game['home_or_away'] === 'home') ? 'slate-home' : 'slate-away';
        $date_label = $this->formatDate($game);
        $logo_html  = $this->opponentLogoHtml($game);
        $result     = $this->formatResult($game);

        $html  = '<div class="slate-game-card ' . $home_class . '">';
        $html .= '<div class="slate-game-main-row">';
        $html .= $this->renderDateCol($date_label, $game['game_time'] ?? '');
        $html .= '<div class="slate-match-col">';
        $html .= '<span class="slate-vs-at">' . esc_html($vs_at) . '</span>';
        $html .= $logo_html;
        $html .= '<span class="slate-opp-name">' . esc_html($game['opponent_team_name'] ?? '') . '</span>';
        $html .= '</div>';
        if (!empty($result['display'])) {
            $html .= '<div class="slate-result-col">';
            $html .= '<span class="slate-score ' . esc_attr($result['css_class']) . '">' . esc_html($result['display']) . '</span>';
            $html .= '</div>';
        }
        if (!empty($game['post_link'])) {
            $html .= '<div class="slate-action-col">';
            $html .= '<a class="slate-recap-btn" href="' . esc_url($game['post_link']) . '" target="_blank" rel="noopener">Summary</a>';
            $html .= '</div>';
        }
        $html .= '</div>'; // .slate-game-main-row
        $html .= '</div>'; // .slate-game-card
        return $html;
    }

    private function renderDateCol(string $date_label, string $time): string
    {
        return '<div class="slate-date-col">'
            . '<span class="slate-date-day">' . esc_html($date_label) . '</span>'
            . '<span class="slate-date-time">' . esc_html($time) . '</span>'
            . '</div>';
    }

    private function opponentLogoHtml(array $game): string
    {
        if (empty($game['opponent_team_logo'])) {
            return '';
        }
        return '<img class="slate-opp-logo" src="' . esc_url($game['opponent_team_logo']) . '" loading="lazy" decoding="async" alt="' . esc_attr(($game['opponent_team_name'] ?? 'Opponent') . ' logo') . '" />';
    }

    private function formatDate(array $game): string
    {
        if (!empty($game['game_timestamp'])) {
            try {
                $dt = new DateTime($game['game_timestamp']);
                return $dt->format('D, M j'); // e.g., "Sat, Feb 21"
            } catch (Exception $e) {
                // fall through
            }
        }
        return $game['game_date_day'] ?? '';
    }

    private function formatResult(array $game): array
    {
        $status  = $game['game_status'] ?? '';
        $t_score = (int)($game['target_score'] ?? 0);
        $o_score = (int)($game['opponent_score'] ?? 0);

        if (!in_array($status, ['Final', 'Final OT', 'Final SO', 'Final/SO', 'Final/OT'], true)) {
            return ['display' => '', 'css_class' => ''];
        }

        if ($t_score > $o_score) {
            $label = 'W';
        } elseif ($t_score < $o_score) {
            $label = 'L';
        } else {
            $label = 'T';
        }

        $suffix = '';
        if ($status === 'Final OT' || $status === 'Final/OT') {
            $suffix = ' (OT)';
        } elseif ($status === 'Final SO' || $status === 'Final/SO') {
            $suffix = ' (SO)';
        }

        $css_class = ($label === 'W') ? 'slate-win' : (($label === 'L') ? 'slate-loss' : 'slate-tie');
        $display   = $label . ' ' . $t_score . '-' . $o_score . $suffix;

        return ['display' => $display, 'css_class' => $css_class];
    }
}
