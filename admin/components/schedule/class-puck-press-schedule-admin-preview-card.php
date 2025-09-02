<?php
class Puck_Press_Schedule_Admin_Preview_Card extends Puck_Press_Admin_Card_Abstract
{
    protected $template_manager;
    protected $wpdb_utils;
    protected $games;
    protected $templates;
    protected $selected_template_key;

    public function __construct(array $args = [])
    {
        parent::__construct($args);
        $this->template_manager = new Puck_Press_Schedule_Template_Manager();
        $this->wpdb_utils = new Puck_Press_Schedule_Wpdb_Utils();
    }

    public static function create_and_init()
    {
        $instance = new self();
        $instance->init();
        return $instance;
    }

    public function init()
    {
        $this->games = $this->wpdb_utils->get_all_table_data('pp_game_schedule_for_display', 'ARRAY_A');
        $this->templates = $this->template_manager->get_all_templates();
        $this->selected_template_key = $this->template_manager->get_current_template_key();

        $this->template_manager->enqueue_all_template_assets();
    }

    public function render_content()
    {
        ob_start();
?>
        <div id="pp-schedule-preview-wrapper" class="loading">
            <div class="spinner-container">
                <span class="spinner is-active big-spinner"></span> <!-- WP native spinner -->
            </div>
            <div id="pp-game-schedule-preview">
                <?php echo $this->get_all_templates_html(); ?>
            </div>
        </div>
    <?php
        return ob_get_clean();
    }

    public function render_header_button_content()
    {
        ob_start();
    ?>
        <button class="pp-button pp-button-primary" id="pp-schedule-colorPaletteBtn">
            <i>🎨</i>
            Customize Colors
        </button>
<?php
        return ob_get_clean();
    }

    // 🔁 Echo all templates
    public function render_all_templates()
    {
        echo $this->get_all_templates_html();
    }

    // 🔍 Echo one template
    public function render_template($template_name)
    {
        echo $this->get_template_html($template_name);
    }

    // 🧾 Return HTML string of all templates
    public function get_all_templates_html()
    {
        $output = '';

        foreach ($this->templates as $template) {
            $output .= $template->render($this->games);
        }

        return $output;
    }

    // 🧾 Return HTML string for a specific template
    public function get_template_html($template_name)
    {
        foreach ($this->templates as $template) {
            if ($template->get_key() === $template_name) {
                return $template->render($this->games);
            }
        }

        return '<p>Template not found: ' . esc_html($template_name) . '</p>';
    }

    public function render_current_template()
    {
        return $this->render_template($this->selected_template_key);
    }

    public function get_current_template_html()
    {
        return $this->get_template_html($this->selected_template_key);
    }
}
