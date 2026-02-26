<?php

/**
 * Arena Schedule Template
 *
 * Collegiate-style layout with a next-game countdown banner, a live-computed
 * season record bar, and compact game rows styled like a major hockey program.
 */
class ArenaTemplate extends PuckPressTemplate
{
    public static function get_key(): string
    {
        return 'arena';
    }

    public static function get_label(): string
    {
        return 'Arena Schedule';
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
            'accent'      => '#8B1A1A',
            'page_bg'     => '#F0F2F4',
            'row_bg'      => '#FFFFFF',
            'row_alt_bg'  => '#F7F8FA',
            'header_bg'   => '#1A1A2E',
            'header_text' => '#FFFFFF',
            'text'        => '#1A1A2E',
            'text_muted'  => '#667085',
            'win_color'   => '#1A7A1A',
            'loss_color'  => '#BB2222',
        ];
    }

    public static function get_color_labels(): array
    {
        return [
            'accent'      => 'Accent Color',
            'page_bg'     => 'Page Background',
            'row_bg'      => 'Row Background',
            'row_alt_bg'  => 'Alternating Row Background',
            'header_bg'   => 'Banner Background',
            'header_text' => 'Banner Text',
            'text'        => 'Primary Text',
            'text_muted'  => 'Secondary Text',
            'win_color'   => 'Win Result Color',
            'loss_color'  => 'Loss / Tie Result Color',
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

    public function render(array $games): string
    {
        $inline_css = self::get_inline_css();
        $css_block  = $inline_css ? '<style>' . $inline_css . '</style>' : '';
        return $css_block . $this->buildArena($games);
    }

    // ── Layout ────────────────────────────────────────────────────────────────

    private function buildArena(array $games): string
    {
        $split    = $this->split_games_by_time($games);
        $upcoming = self::sort_games_by_chronological_order($split['future_games']);
        $past     = self::sort_games_by_chronological_order($split['past_games'], true);

        $html  = '<div class="arena_schedule_container">';
        $html .= $this->renderBanner(!empty($upcoming) ? $upcoming[0] : null);
        $html .= $this->renderRecordBar($split['past_games']);
        $html .= $this->renderList($upcoming, $past);
        $html .= '</div>';

        return $html;
    }

    // ── Next-game banner ──────────────────────────────────────────────────────

    private function renderBanner(?array $game): string
    {
        if (!$game) {
            return '<div class="arena-banner arena-banner--empty"><span class="arena-banner__no-game">No upcoming games scheduled.</span></div>';
        }

        $vs_at        = ($game['home_or_away'] === 'away') ? 'at' : 'vs';
        $opp_name     = $game['opponent_team_name'] ?? 'Opponent';
        $opp_nickname = $game['opponent_team_nickname'] ?? '';
        $target_name  = $game['target_team_name'] ?? '';
        $timestamp   = $game['game_timestamp'] ?? '';
        $time_str    = $game['game_time'] ?? '';

        $date_str = '';
        if (!empty($timestamp)) {
            try {
                $dt       = new DateTime($timestamp);
                $date_str = $dt->format('M j') . (!empty($time_str) ? ' / ' . $time_str : '');
            } catch (Exception $e) {
                $date_str = ($game['game_date_day'] ?? '') . (!empty($time_str) ? ' / ' . $time_str : '');
            }
        }

        // JS Date() needs ISO-8601 with T separator for consistent cross-browser parsing
        $ts_iso = str_replace(' ', 'T', $timestamp);

        $target_logo_html = '';
        if (!empty($game['target_team_logo'])) {
            $target_logo_html = '<img class="arena-banner__logo" src="' . esc_url($game['target_team_logo']) . '" loading="lazy" decoding="async" alt="' . esc_attr($target_name . ' logo') . '" />';
        }

        $opp_logo_html = '';
        if (!empty($game['opponent_team_logo'])) {
            $opp_logo_html = '<img class="arena-banner__logo" src="' . esc_url($game['opponent_team_logo']) . '" loading="lazy" decoding="async" alt="' . esc_attr($opp_name . ' logo') . '" />';
        }

        $html  = '<div class="arena-banner">';

        // Left — matchup info
        $html .= '<div class="arena-banner__left">';
        $html .= '<span class="arena-banner__label">Next Game</span>';
        $html .= '<div class="arena-banner__matchup">';
        $html .= $target_logo_html;
        $html .= '<span class="arena-banner__vs-at">' . esc_html($vs_at) . '</span>';
        $html .= $opp_logo_html;
        $html .= '<span class="arena-banner__opp">' . esc_html($opp_name) . ' ' . esc_html($opp_nickname) . '</span>';
        $html .= '</div>';
        if (!empty($date_str)) {
            $html .= '<span class="arena-banner__date">' . esc_html($date_str) . '</span>';
        }
        if (!empty($game['venue'])) {
            $html .= '<span class="arena-banner__venue">' . esc_html($game['venue']) . '</span>';
        }
        $html .= '</div>';

        // Right — live countdown
        $html .= '<div class="arena-countdown" data-timestamp="' . esc_attr($ts_iso) . '">';
        foreach ([['days', 'Days'], ['hours', 'Hrs'], ['mins', 'Mins'], ['secs', 'Secs']] as [$key, $lbl]) {
            $html .= '<div class="arena-cd__unit">';
            $html .= '<span class="arena-cd__num" data-cd="' . $key . '">--</span>';
            $html .= '<span class="arena-cd__lbl">' . $lbl . '</span>';
            $html .= '</div>';
        }
        $html .= '</div>';

        $html .= '</div>'; // .arena-banner
        return $html;
    }

    // ── Season record bar ─────────────────────────────────────────────────────

    private function renderRecordBar(array $past_games): string
    {
        $wins   = $losses   = $ties   = 0;
        $home_w = $home_l   = $home_t = 0;
        $away_w = $away_l   = $away_t = 0;
        $gf     = $ga       = 0;

        $finals = ['Final', 'Final OT', 'Final SO', 'Final/SO', 'Final/OT'];

        foreach ($past_games as $game) {
            if (!in_array($game['game_status'] ?? '', $finals, true)) {
                continue;
            }

            $ts  = (int)($game['target_score'] ?? 0);
            $os  = (int)($game['opponent_score'] ?? 0);
            $gf += $ts;
            $ga += $os;

            $is_home = ($game['home_or_away'] !== 'away');

            if ($ts > $os) {
                $wins++;
                $is_home ? $home_w++ : $away_w++;
            } elseif ($ts < $os) {
                $losses++;
                $is_home ? $home_l++ : $away_l++;
            } else {
                $ties++;
                $is_home ? $home_t++ : $away_t++;
            }
        }

        if (($wins + $losses + $ties) === 0) {
            return '';
        }

        $stats = [
            ['Record', "{$wins}-{$losses}-{$ties}"],
            ['Home',   "{$home_w}-{$home_l}-{$home_t}"],
            ['Away',   "{$away_w}-{$away_l}-{$away_t}"],
            ['GF',     (string)$gf],
            ['GA',     (string)$ga],
        ];

        $html = '<div class="arena-record-bar" role="region" aria-label="Season record">';
        foreach ($stats as $i => [$lbl, $val]) {
            if ($i > 0) {
                $html .= '<span class="arena-record-bar__div" aria-hidden="true"></span>';
            }
            $html .= '<div class="arena-record-bar__stat">';
            $html .= '<span class="arena-record-bar__lbl">' . esc_html($lbl) . '</span>';
            $html .= '<span class="arena-record-bar__val">' . esc_html($val) . '</span>';
            $html .= '</div>';
        }
        $html .= '</div>';

        return $html;
    }

    // ── Schedule list ─────────────────────────────────────────────────────────

    private function renderList(array $upcoming, array $past): string
    {
        $html = '<div class="arena-schedule">';

        if (!empty($upcoming)) {
            $html .= '<div class="arena-section-head">Upcoming</div>';
            foreach ($upcoming as $i => $game) {
                $html .= $this->renderRow($game, false, $i % 2 === 1);
            }
        }

        if (!empty($past)) {
            $html .= '<div class="arena-section-head">Results</div>';
            foreach ($past as $i => $game) {
                $html .= $this->renderRow($game, true, $i % 2 === 1);
            }
        }

        if (empty($upcoming) && empty($past)) {
            $html .= '<p class="arena-empty">No games available.</p>';
        }

        $html .= '</div>';
        return $html;
    }

    private function renderRow(array $game, bool $is_past, bool $alt): string
    {
        $vs_at     = ($game['home_or_away'] === 'away') ? 'at' : 'vs';
        $alt_class = $alt ? ' arena-row--alt' : '';
        $date_lbl  = $this->rowDate($game);
        $has_promo = !empty($game['promo_text']);

        // Opponent logo
        if (!empty($game['opponent_team_logo'])) {
            $logo_html = '<img class="arena-row__logo" src="' . esc_url($game['opponent_team_logo']) . '" loading="lazy" decoding="async" alt="' . esc_attr(($game['opponent_team_name'] ?? 'Opponent') . ' logo') . '" />';
        } else {
            $logo_html = '<div class="arena-row__logo-ph" aria-hidden="true"></div>';
        }

        $html  = '<div class="arena-row' . $alt_class . '">';

        // Promo toggle — absolutely positioned in lower-right corner of the row
        if ($has_promo) {
            $html .= '<button class="arena-promo-toggle" aria-expanded="false" aria-label="Show promo details"></button>';
        }

        // Col 1: logo
        $html .= '<div class="arena-row__logo-col">' . $logo_html . '</div>';

        // Col 2: opponent info
        $html .= '<div class="arena-row__opp-col">';
        $html .= '<div class="arena-row__date">' . esc_html($date_lbl) . '</div>';
        $html .= '<div class="arena-row__matchup">';
        $html .= '<span class="arena-badge arena-badge--' . esc_attr($vs_at) . '">' . esc_html($vs_at) . '</span>';
        $html .= '<span class="arena-row__opp-name">' . esc_html($game['opponent_team_name'] ?? '') . '</span>';
        $html .= '</div>';
        if (!empty($game['promo_header'])) {
            $html .= '<div class="arena-row__sub">' . esc_html($game['promo_header']) . '</div>';
        }
        $html .= '</div>';

        // Col 3: venue (hidden below 860px)
        $html .= '<div class="arena-row__venue-col">';
        if (!empty($game['venue'])) {
            $html .= '<div class="arena-row__venue">' . esc_html($game['venue']) . '</div>';
        }
        if ($has_promo) {
            $lines = array_filter(array_map('trim', explode("\n", $game['promo_text'])));
            foreach (array_slice($lines, 0, 2) as $line) {
                $html .= '<div class="arena-row__venue-note">' . esc_html($line) . '</div>';
            }
        }
        $html .= '</div>';

        // Col 4: result / action
        $html .= '<div class="arena-row__result-col">';
        if ($is_past) {
            $result = $this->buildResult($game);
            if (!empty($result['display'])) {
                $html .= '<div class="arena-result ' . esc_attr($result['cls']) . '">' . esc_html($result['display']) . '</div>';
            }
            if (!empty($game['post_link'])) {
                $html .= '<a class="arena-row__link" href="' . esc_url($game['post_link']) . '" target="_blank" rel="noopener">&#9658;&nbsp;Recap</a>';
            }
        } else {
            if (!empty($game['promo_ticket_link'])) {
                $html .= '<a class="arena-ticket-btn" href="' . esc_url($game['promo_ticket_link']) . '" target="_blank" rel="noopener">Tickets</a>';
            }
        }
        $html .= '</div>';

        // Promo panel — spans all grid columns, hidden until toggled
        if ($has_promo) {
            $html .= '<div class="arena-promo-panel">';
            if (!empty($game['promo_img_url'])) {
                $html .= '<img class="arena-promo-panel__img" src="' . esc_url($game['promo_img_url']) . '" loading="lazy" decoding="async" alt="' . esc_attr($game['promo_header'] ?? 'Promo') . '" />';
            }
            $has_body = !empty($game['promo_header']) || !empty($game['promo_text']);
            if ($has_body) {
                $html .= '<div class="arena-promo-panel__body">';
                if (!empty($game['promo_header'])) {
                    $html .= '<div class="arena-promo-panel__header">' . esc_html($game['promo_header']) . '</div>';
                }
                if (!empty($game['promo_text'])) {
                    $html .= '<div class="arena-promo-panel__text">' . nl2br(esc_html($game['promo_text'])) . '</div>';
                }
                $html .= '</div>';
            }
            $html .= '</div>';
        }

        $html .= '</div>'; // .arena-row
        return $html;
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    private function rowDate(array $game): string
    {
        $ts   = $game['game_timestamp'] ?? '';
        $time = $game['game_time'] ?? '';

        if (!empty($ts)) {
            try {
                $dt = new DateTime($ts);
                return $dt->format('M j (D)') . (!empty($time) ? ' / ' . $time : '');
            } catch (Exception $e) {
            }
        }

        return ($game['game_date_day'] ?? '') . (!empty($time) ? ' / ' . $time : '');
    }

    private function buildResult(array $game): array
    {
        $status = $game['game_status'] ?? '';
        $ts     = (int)($game['target_score'] ?? 0);
        $os     = (int)($game['opponent_score'] ?? 0);

        if (!in_array($status, ['Final', 'Final OT', 'Final SO', 'Final/SO', 'Final/OT'], true)) {
            return ['display' => '', 'cls' => ''];
        }

        if ($ts > $os) {
            $lbl = 'W';
            $cls = 'arena-result--win';
        } elseif ($ts < $os) {
            $lbl = 'L';
            $cls = 'arena-result--loss';
        } else {
            $lbl = 'T';
            $cls = 'arena-result--tie';
        }

        $suffix = '';
        if (in_array($status, ['Final OT', 'Final/OT'], true)) $suffix = ' (OT)';
        if (in_array($status, ['Final SO', 'Final/SO'], true)) $suffix = ' (SO)';

        return ['display' => "{$lbl}, {$ts}-{$os}{$suffix}", 'cls' => $cls];
    }
}
