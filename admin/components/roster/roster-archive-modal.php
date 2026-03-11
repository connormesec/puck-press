<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Puck_Press_Roster_Archive_Modal extends Puck_Press_Admin_Modal_Abstract {

	public function __construct( string $id = 'pp-roster-archive-modal' ) {
		parent::__construct( $id, 'Archive Roster', 'Save a snapshot of the current roster stats.' );

		$this->set_footer_buttons(
			array(
				array(
					'class' => 'pp-button-secondary',
					'id'    => 'pp-cancel-roster-archive',
					'label' => 'Cancel',
				),
				array(
					'class' => 'pp-button-primary',
					'id'    => 'pp-confirm-roster-archive',
					'label' => 'Archive Roster',
				),
			)
		);
	}

	protected function render_content(): void {
		$season_options = $this->get_season_options();
		?>
		<div class="pp-form-group">
			<label class="pp-label" for="pp-roster-archive-season">Season</label>
			<select id="pp-roster-archive-season" name="pp_roster_archive_season" class="pp-input">
				<option value="">— Select a season —</option>
				<?php foreach ( $season_options as $value => $label ) : ?>
					<option value="<?php echo esc_attr( $value ); ?>"><?php echo esc_html( $label ); ?></option>
				<?php endforeach; ?>
			</select>
		</div>
		<p class="pp-archive-game-count" id="pp-roster-archive-stats-count"></p>
		<p class="pp-archive-error" id="pp-roster-archive-error" style="display:none;"></p>
		<?php
	}

	private function get_season_options(): array {
		$current_year  = (int) date( 'Y' );
		$current_month = (int) date( 'n' );

		$season_start = $current_month >= 7 ? $current_year : $current_year - 1;

		$options = array();
		for ( $start = $season_start + 1; $start >= $season_start - 5; $start-- ) {
			$key             = $start . '-' . ( $start + 1 );
			$options[ $key ] = $key;
		}

		return $options;
	}
}
