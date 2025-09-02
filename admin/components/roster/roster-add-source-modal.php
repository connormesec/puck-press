<?php
class Puck_Press_Roster_Add_Source_Modal extends Puck_Press_Admin_Modal_Abstract {


    public function __construct($id = 'pp-add-source-modal') {
        parent::__construct($id, 'Add Data Source', 'Add a new data source for roster information');

        $this->set_footer_buttons([
            [
                'class' => 'pp-button-secondary',
                'id'    => 'pp-cancel-add-source',
                'label' => 'Cancel'
            ],
            [
                'class' => 'pp-button-primary',
                'id'    => 'pp-confirm-roster-add-source',
                'label' => 'Add Source'
            ]
        ]);
    }

    protected function render_content() {
        include plugin_dir_path(__FILE__) . '../../partials/roster/add-roster-source-form.php';
    }
}