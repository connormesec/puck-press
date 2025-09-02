<?php

abstract class Puck_Press_Admin_Card_Abstract
{
    protected $title = '';
    protected $subtitle = '';
    protected $content = '';
    protected $id = '';

    public function __construct(array $args = [])
    {
        $this->setup($args);
    }

    /**
     * Setup properties with default values.
     */
    protected function setup(array $args): void
    {
        $defaults = [
            'title' => '',
            'subtitle' => '',
            'content' => '',
            'id' => '',
            'button_html' => '',
        ];
        $args = array_merge($defaults, $args);

        $this->title = $args['title'];
        $this->subtitle = $args['subtitle'];
        $this->content = $args['content'];
        $this->id = $args['id'];
    }

    // Method to render the card header
    protected function render_header()
    {
        ob_start();
?>
        <div class='pp-card-header'>
            <div>
                <h2 class='pp-card-title' id='pp-card-title-<?php echo $this->id ?>'><?php echo $this->title ?></h2>
                <p class='pp-card-subtitle' id='pp-card-subtitle-<?php echo $this->id ?>'><?php echo $this->subtitle ?></p>
            </div>
            <div>
                <?php echo $this->render_header_button_content(); ?>

                <button class="pp-collapse-button" id="pp-collapse-preview-card" data-target="pp-card-content-<?php echo $this->id ?>">
                    <span class="pp-collapse-icon">▼</span>
                </button>
            </div>
        </div>
<?php
        return ob_get_clean();
    }

    // Abstract method to render the card content
    abstract protected function render_content();

    // Abstract method to render the card content
    abstract protected function render_header_button_content();

    // Method to render the full card
    public function render()
    {
        return "
            <div class='pp-card' id='pp-card-{$this->id}'>
                {$this->render_header()}
                <div class='pp-card-content' id='pp-card-content-{$this->id}'>
                    {$this->render_content()}
                </div>
            </div>
        ";
    }
}
