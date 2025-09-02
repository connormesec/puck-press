<?php


abstract class Puck_Press_Admin_Modal_Abstract {
    protected $id;
    protected $title;
    protected $subtitle;
    protected $form_fields = [];
    protected $footer_buttons = [];

    public function __construct($id, $title, $subtitle) {
        $this->id       = esc_attr($id);
        $this->title    = esc_html($title);
        $this->subtitle = esc_html($subtitle);
    }

    abstract protected function render_content();

    protected function render_header() {
        return "
            <div class='pp-modal-header'>
                <h3 class='pp-modal-title'>{$this->title}</h3>
                <p class='pp-modal-subtitle'>{$this->subtitle}</p>
            </div>
        ";
    }

    protected function render_footer() {
        $buttons_html = '';
        foreach ($this->footer_buttons as $button) {
            $class   = esc_attr($button['class']);
            $id      = esc_attr($button['id']);
            $label   = esc_html($button['label']);
            $buttons_html .= "<button class='pp-button {$class}' id='{$id}'>{$label}</button>";
        }

        return "<div class='pp-modal-footer'>{$buttons_html}</div>";
    }

    public function render() {
        ob_start(); ?>
        <div class="pp-modal-overlay" id="<?php echo $this->id; ?>">
            <div class="pp-modal">
                <button class="pp-modal-close" id="pp-modal-close">✕</button>
                <?php echo $this->render_header(); ?>
                <div class="pp-modal-content">
                    <?php $this->render_content(); ?>
                </div>
                <?php echo $this->render_footer(); ?>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }

    public function set_footer_buttons(array $buttons) {
        $this->footer_buttons = $buttons;
    }
}