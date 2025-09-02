<?php
class Puck_Press_Schedule_Add_Source_Modal extends Puck_Press_Admin_Modal_Abstract {
    private $team_data;
    private $season_options;

    public function __construct($id = 'pp-add-source-modal') {
        parent::__construct($id, 'Add Data Source', 'Add a new data source for game information');

        $this->team_data = $this->get_team_data();
        $this->season_options = $this->get_season_options();

        $this->set_footer_buttons([
            [
                'class' => 'pp-button-secondary',
                'id'    => 'pp-cancel-add-source',
                'label' => 'Cancel'
            ],
            [
                'class' => 'pp-button-primary',
                'id'    => 'pp-confirm-add-source',
                'label' => 'Add Source'
            ]
        ]);
    }

    protected function render_content() {
        include plugin_dir_path(__FILE__) . '../../partials/schedule/add-schedule-source-form.php';
    }

    private function get_team_data() {
        $url = 'https://lscluster.hockeytech.com/feed/index.php?feed=statviewfeed&view=teamsForSeason&season=-1&division=-1&key=e6867b36742a0c9d&client_code=acha&site_id=2';
        $raw = @file_get_contents($url);
        $clean = substr($raw, 1, -1);
        $json = json_decode($clean, true);
        return $json['teams'] ?? [];
    }

    private function get_season_options() {
        $year = (int) date('Y');
        $month = (int) date('n');
        $start = ($month >= 9) ? $year : $year - 1;
        return [
            ($start - 1) . '-' . $start,
            $start . '-' . ($start + 1),
            ($start + 1) . '-' . ($start + 2),
        ];
    }

    public function get_team_data_array() {
        return $this->team_data;
    }

    public function get_season_options_array() {
        return $this->season_options;
    }

    protected function render_team_option_picker(array $teams_data, string $htmlClassNameKey): string {
        if (!isset($teams_data) || !is_array($teams_data)) {
            return '<!-- Invalid JSON structure -->';
        }

        $output = '<select name="pp-form-input" class="pp-select2-' . $htmlClassNameKey . '" id="pp-game-' . $htmlClassNameKey . '" style="width : 100%;">' . PHP_EOL;

        foreach ($teams_data as $team) {
            if (!isset($team['id'], $team['name'])) {
                continue;
            }

            $value = esc_attr($team['id']);
            $label = esc_html($team['name']);

            $output .= "\t<option value=\"$value\"";

            foreach (['id', 'name', 'nickname', 'logo'] as $key) {
                if (isset($team[$key])) {
                    $data_key = esc_attr($key);
                    $data_val = esc_attr($team[$key]);
                    $output .= " data-$data_key=\"$data_val\"";
                }
            }

            $output .= ">$label</option>" . PHP_EOL;
        }

        $output .= '</select>' . PHP_EOL;

        return $output;
    }
}