<?php

require_once plugin_dir_path(__FILE__) . '../class-puck-press-render-utils-abstract.php';

class Puck_Press_Schedule_Render_Utils extends Puck_Press_Render_Utils_Abstract
{
    public function __construct()
    {
        $this->load_dependencies();

        $this->template_manager = new Puck_Press_Schedule_Template_Manager();
        $this->wpdb_utils       = new Puck_Press_Schedule_Wpdb_Utils();

        $this->games     = $this->wpdb_utils->get_all_table_data('pp_game_schedule_for_display', 'ARRAY_A');
        $this->templates = $this->template_manager->get_all_templates();

        $this->selected_template_key = $this->template_manager->get_current_template_key();
    }

    public function load_dependencies(): void
    {
        require_once plugin_dir_path(__FILE__) . '../../public/templates/class-puck-press-template-manager-abstract.php';
        require_once plugin_dir_path(__FILE__) . '../../public/templates/class-puck-press-schedule-template-manager.php';
        require_once plugin_dir_path(__FILE__) . '../class-puck-press-wpdb-utils-base-abstract.php';
        require_once plugin_dir_path(__FILE__) . 'class-puck-press-schedule-wpdb-utils.php';
    }

    protected function build_schema(): string
    {
        if (empty($this->games)) return '';

        $events = [];
        foreach ($this->games as $game) {
            $start_date = !empty($game['game_timestamp'])
                ? (new DateTime($game['game_timestamp']))->format(DateTime::ATOM)
                : $game['game_date_day'];

            $is_home   = $game['home_or_away'] === 'home';
            $home_name = $is_home ? $game['target_team_name'] : $game['opponent_team_name'];
            $away_name = $is_home ? $game['opponent_team_name'] : $game['target_team_name'];

            $event = [
                '@type'       => 'SportsEvent',
                'name'        => $game['target_team_name'] . ' vs ' . $game['opponent_team_name'],
                'startDate'   => $start_date,
                'homeTeam'    => ['@type' => 'SportsTeam', 'name' => $home_name],
                'awayTeam'    => ['@type' => 'SportsTeam', 'name' => $away_name],
                'eventStatus' => 'https://schema.org/EventScheduled',
            ];

            if (!empty($game['venue'])) {
                $event['location'] = ['@type' => 'Place', 'name' => $game['venue']];
            }

            $events[] = $event;
        }

        $schema = [
            '@context' => 'https://schema.org',
            '@graph'   => $events,
        ];

        return '<script type="application/ld+json">'
            . wp_json_encode($schema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT)
            . '</script>';
    }
}
