<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Puck_Press_Teams_Admin_Pages_Card extends Puck_Press_Admin_Card_Abstract {

	private int $team_id;

	public function __construct( int $team_id = 0 ) {
		parent::__construct( array(
			'title'    => 'Team Pages',
			'subtitle' => 'Generate Divi pages for this team',
			'id'       => 'team-pages',
		) );
		$this->team_id = $team_id;
	}

	public function render_header_button_content(): string {
		return '';
	}

	public function render_content(): string {
		$builder  = new Puck_Press_Divi_Page_Builder();
		$page_ids = $builder->get_page_ids( $this->team_id );
		$has_pages = ! empty( $page_ids );

		$max_width        = Puck_Press_Divi_Page_Builder::get_default_max_width();
		$padding          = Puck_Press_Divi_Page_Builder::get_default_padding();
		$header_color     = Puck_Press_Divi_Page_Builder::get_default_header_color()
			?: Puck_Press_Divi_Page_Builder::get_accent_color( $this->team_id )
			?: '#000000';
		$header_font_size = Puck_Press_Divi_Page_Builder::get_default_header_font_size();
		$header_font       = Puck_Press_Divi_Page_Builder::get_default_header_font();
		$header_text_color = Puck_Press_Divi_Page_Builder::get_default_header_text_color();
		$school_url        = Puck_Press_Divi_Page_Builder::get_default_school_url();

		ob_start();
		?>
		<div style="padding:16px 0 8px;">
			<div style="display:flex;gap:16px;align-items:flex-end;flex-wrap:wrap;margin-bottom:16px;">
				<div>
					<label style="display:block;font-size:0.8125rem;font-weight:600;margin-bottom:4px;color:#444;">Content Max-Width</label>
					<input type="text" id="pp-divi-max-width" value="<?php echo esc_attr( $max_width ); ?>" class="pp-form-input" style="width:120px;" placeholder="1080px">
				</div>
				<div>
					<label style="display:block;font-size:0.8125rem;font-weight:600;margin-bottom:4px;color:#444;">Section Padding</label>
					<input type="text" id="pp-divi-padding" value="<?php echo esc_attr( $padding ); ?>" class="pp-form-input" style="width:120px;" placeholder="30px 0px">
				</div>
				<div>
					<label style="display:block;font-size:0.8125rem;font-weight:600;margin-bottom:4px;color:#444;">Header Color</label>
					<input type="color" id="pp-divi-header-color" value="<?php echo esc_attr( $header_color ); ?>" style="width:48px;height:34px;padding:2px;border:1px solid #ccc;border-radius:4px;cursor:pointer;">
				</div>
				<div>
					<label style="display:block;font-size:0.8125rem;font-weight:600;margin-bottom:4px;color:#444;">Header Size</label>
					<input type="text" id="pp-divi-header-font-size" value="<?php echo esc_attr( $header_font_size ); ?>" class="pp-form-input" style="width:90px;" placeholder="1.4rem">
				</div>
				<div>
					<label style="display:block;font-size:0.8125rem;font-weight:600;margin-bottom:4px;color:#444;">Header Font</label>
					<input type="text" id="pp-divi-header-font" value="<?php echo esc_attr( $header_font ); ?>" class="pp-form-input" style="width:140px;" placeholder="e.g. Roboto">
				</div>
				<div>
					<label style="display:block;font-size:0.8125rem;font-weight:600;margin-bottom:4px;color:#444;">Nav Text Color</label>
					<input type="color" id="pp-divi-header-text-color" value="<?php echo esc_attr( $header_text_color ); ?>" style="width:48px;height:34px;padding:2px;border:1px solid #ccc;border-radius:4px;cursor:pointer;">
				</div>
				<div>
					<label style="display:block;font-size:0.8125rem;font-weight:600;margin-bottom:4px;color:#444;">School Site URL</label>
					<input type="url" id="pp-divi-school-url" value="<?php echo esc_attr( $school_url ); ?>" class="pp-form-input" style="width:200px;" placeholder="https://example.edu">
				</div>
				<div style="display:flex;gap:8px;">
					<button
						id="pp-generate-team-pages-btn"
						class="pp-button pp-button-primary"
						<?php echo $has_pages ? 'disabled' : ''; ?>>
						Generate Pages
					</button>
					<button
						id="pp-delete-team-pages-btn"
						class="pp-button pp-button-danger"
						<?php echo ! $has_pages ? 'disabled' : ''; ?>>
						Delete Pages
					</button>
				</div>
			</div>

			<?php if ( $has_pages ) : ?>
			<table class="pp-table" style="margin-top:8px;">
				<thead class="pp-thead">
					<tr>
						<th class="pp-th">Page</th>
						<th class="pp-th">Actions</th>
					</tr>
				</thead>
				<tbody>
					<?php
					$labels = array(
						'home'     => 'Team Home',
						'schedule' => 'Schedule',
						'roster'   => 'Roster',
						'stats'    => 'Stats',
					);
					foreach ( $labels as $key => $label ) :
						$pid = isset( $page_ids[ $key ] ) ? (int) $page_ids[ $key ] : 0;
						if ( ! $pid ) continue;
						$view_url = get_permalink( $pid );
						$edit_url = get_edit_post_link( $pid );
					?>
					<tr>
						<td class="pp-td"><?php echo esc_html( $label ); ?></td>
						<td class="pp-td">
							<a href="<?php echo esc_url( $view_url ); ?>" target="_blank" class="pp-button pp-button-secondary" style="margin-right:6px;">View</a>
							<a href="<?php echo esc_url( $edit_url ); ?>" target="_blank" class="pp-button pp-button-secondary">Edit in Divi</a>
						</td>
					</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
			<?php else : ?>
			<p style="color:#888;font-size:0.875rem;margin:0;">No pages generated yet for this team.</p>
			<?php endif; ?>
		</div>
		<?php
		return ob_get_clean();
	}
}
