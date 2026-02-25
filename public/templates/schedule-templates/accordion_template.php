<?php

/**
 * Accordion Template
 */
class AccordionTemplate extends PuckPressTemplate
{
    /**
     * Returns a unique key for the template
     */
    public static function get_key(): string
    {
        return 'accordion';
    }

    /**
     * Returns a human-readable label
     */
    public static function get_label(): string
    {
        return 'Accordion Schedule';
    }

    protected static function get_directory(): string
    {
        return 'schedule-templates';
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
            'header_bg' => '#215530',
            'container_bg' => '#ffffff',
            'border'     => '#000000',
            'month_text' => '#ffffff',
            'content_text' => '#000000',
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

    /**
     * Returns the template output
     */
    public function render(array $games): string
    {
        $output = $this->buildAccordionSchedule($games);
        // Include the template file and capture output

        return $output;
    }

    public function buildAccordionSchedule(array $games)
    {
        $grouped_games = self::group_games_by_month($games, false);

        $now = new DateTime();
        $content = '<div class="accordion_schedule_container css-transitions-only-after-page-load">';
        foreach ($grouped_games as $key => $month) {
            $showActive = '';

            $month_date = new DateTime('last day of ' . $key);

            if ($month_date >= $now) {
                $showActive = ' active';
            }
            $content .= '<div class="accordion_month_container">
                                <div class="accordion' . $showActive . '">
                                    <h2 class="accordion_game_month_title">' . self::extract_month($key) . '</h2>
                                    <span class="accordion_title_right_content">open</span>
                                </div>
                            <div class="accordion_panel' . $showActive . '">';

            foreach ($month as $game) {
                $vs_at   = $game['home_or_away'] === 'away' ? 'AT' : 'VS';
                $is_over = $this->is_game_over($game);

                $promotion_section = '';
                if (!$is_over && (!empty($game['promo_header']) || !empty($game['promo_text']) || !empty($game['promo_img_url']))) {
                    $promotion_section = $this->addGameDetails($game['promo_header'], $game['promo_text'], $game['promo_img_url']);
                }

                $game_result_message = $this->gameResultMessage($game);

                $content .= $this->renderAccordionGameRow($game, $vs_at, $promotion_section, $game_result_message, $is_over);
            }
            $content .= '</div></div>';
        }
        $content .= '</div>';
        return $content;
    }

    private function renderAccordionGameRow(array $game, string $vs_at, string $promotion_section, string $result_message, bool $is_over = false): string
    {
        $opponent_logo_html = '';

        if (! empty($game['opponent_team_logo'])) {
            $opponent_logo_html = '<img src="' . esc_url($game['opponent_team_logo']) . '" decoding="async" loading="lazy" alt="' . esc_attr($game['opponent_team_name']) . ' logo" />';
        }

        $ticket_btn_html = '';
        if (! $is_over && ! empty($game['promo_ticket_link'])) {
            $ticket_btn_html = '<a class="accordion_ticket_btn" href="' . esc_url($game['promo_ticket_link']) . '" target="_blank" rel="noopener">BUY TICKETS</a>';
        }

        $recap_btn_html = '';
        if ($is_over && ! empty($game['post_link'])) {
            $recap_btn_html = '<a class="accordion-recap-btn" href="' . esc_url($game['post_link']) . '" target="_blank" rel="noopener">Summary</a>';
        }

        return '
        <div class="accordion_game_list">
            <div class="accordion_date-time">
                <span class="accordion_date">' . esc_html($game['game_date_day']) . '</span>
                <span class="accordion_time">' . esc_html($game['game_time']) . '</span>
            </div>
            <div class="accordion_team_info">
                <img class="accordion_msu_thumb" src="' . esc_url($game['target_team_logo']) . '" decoding="async" loading="lazy" alt="' . esc_attr($game['target_team_name']) . ' logo" />
                <span class="accordion_vs">' . esc_html($vs_at) . '</span>
                ' . $opponent_logo_html . '
                <span class="accordion_team_title">' . esc_html($game['opponent_team_name']) . '</span>
            </div>
            <div class="accordion_game_detail">
                <span class="accordion_game_outcome">' . esc_html($result_message) . '</span>
                ' . $ticket_btn_html . '
                ' . $recap_btn_html . '
            </div>
            ' . $promotion_section . '
        </div>';
    }

    private function addGameDetails($header = '', $text = '', $img_url = '')
    {
        $img_html = '';
        if (! empty($img_url)) {
            $img_html = '<div class="accordion_game_promotion_image">
						<img src="' . $img_url . '" loading="lazy" alt="">
                    </div>';
        }

        $content = '
            <div class="accordion_game_promotions_container" data-toggle-id="461">
				<div class="accordion_game_detail_reveal accordion_game_promotion_item">
					' . $img_html . '
					<div class="accordion_game_promotion_body">
						<p class="accordion_game_promotion_item_header">
							<strong>' . $header . ' </strong>
						</p>
						<p>' . nl2br( $text ) . '</p>
					</div>
				</div>
			</div>
        ';
        return $content;
    }

    /**
     * Returns true if the game is over: either it has a recorded result status,
     * or its timestamp has already passed.
     */
    private function is_game_over(array $game): bool
    {
        if (! empty($game['game_status'])) {
            return true;
        }
        if (! empty($game['game_timestamp']) && strtotime($game['game_timestamp']) < time()) {
            return true;
        }
        return false;
    }

    private function gameResultMessage($game)
    {
        $game_result_message = '';
        if ($game['game_status'] == 'Final') {
            if ($game['target_score'] < $game['opponent_score']) {
                $game_result_message = 'L';
            } elseif ($game['target_score'] > $game['opponent_score']) {
                $game_result_message = 'W';
            } else {
                $game_result_message = 'T';
            }
        } elseif ($game['game_status'] == 'Final OT') {
            if ($game['target_score'] < $game['opponent_score']) {
                $game_result_message = 'OTL';
            } elseif ($game['target_score'] > $game['opponent_score']) {
                $game_result_message = 'OTW';
            } else {
                $game_result_message = 'OT';
            }
        } elseif ($game['game_status'] == 'Final SO') {
            if ($game['target_score'] < $game['opponent_score']) {
                $game_result_message = 'SOL';
            } elseif ($game['target_score'] > $game['opponent_score']) {
                $game_result_message = 'SOW';
            } else {
                $game_result_message = 'SOT';
            }
        } else {
            if ($game['target_score'] < $game['opponent_score']) {
                $game_result_message = 'L';
            } elseif ($game['target_score'] > $game['opponent_score']) {
                $game_result_message = 'W';
            } else {
                $game_result_message = '';
            }
        }

        $final_game_result_message = '';
        if ($game_result_message !== '') {
            $final_game_result_message = $game_result_message . ': ' . $game['target_score'] . ' - ' . $game['opponent_score'];
        }

        return $final_game_result_message;
    }
}
