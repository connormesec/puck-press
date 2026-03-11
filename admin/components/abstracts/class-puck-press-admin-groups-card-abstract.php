<?php

abstract class Puck_Press_Admin_Groups_Card_Abstract extends Puck_Press_Admin_Card_Abstract {

	abstract protected function get_domain_label(): string;
	abstract protected function get_create_action(): string;
	abstract protected function get_delete_action(): string;
	abstract protected function get_shortcode_hint(): string;
	abstract protected function make_wpdb_utils(): Puck_Press_Group_Aware_Wpdb_Utils;

	protected function render_header_button_content(): string {
		$domain_lc = strtolower( $this->get_domain_label() );
		return '<button class="pp-button pp-button-primary" id="pp-' . esc_attr( $domain_lc ) . '-new-group-btn">+ New Group</button>';
	}

	protected function render_content(): string {
		$groups    = $this->make_wpdb_utils()->get_all_groups();
		$domain_lc = strtolower( $this->get_domain_label() );
		$hint      = $this->get_shortcode_hint();
		ob_start();
		?>
		<div class="pp-groups-table-wrapper">
			<table class="pp-table">
				<thead>
					<tr>
						<th>ID</th>
						<th>Slug</th>
						<th>Name</th>
						<th>Shortcode</th>
						<th>Actions</th>
					</tr>
				</thead>
				<tbody>
				<?php foreach ( $groups as $group ) : ?>
					<tr data-group-id="<?php echo esc_attr( $group['id'] ); ?>">
						<td><?php echo esc_html( $group['id'] ); ?></td>
						<td><code><?php echo esc_html( $group['slug'] ); ?></code></td>
						<td><?php echo esc_html( $group['name'] ); ?></td>
						<td><code><?php echo esc_html( str_replace( '...', $group['slug'], $hint ) ); ?></code></td>
						<td>
							<?php if ( (int) $group['id'] === 1 ) : ?>
								<span class="pp-badge">default</span>
							<?php else : ?>
								<button class="pp-button pp-button-danger pp-delete-group-btn"
										data-group-id="<?php echo esc_attr( $group['id'] ); ?>"
										data-action="<?php echo esc_attr( $this->get_delete_action() ); ?>">
									Delete
								</button>
							<?php endif; ?>
						</td>
					</tr>
				<?php endforeach; ?>
				</tbody>
			</table>
		</div>

		<div id="pp-<?php echo esc_attr( $domain_lc ); ?>-new-group-modal" class="pp-modal" style="display:none;">
			<div class="pp-modal-content">
				<h3>New <?php echo esc_html( $this->get_domain_label() ); ?> Group</h3>
				<div class="pp-form-group">
					<label>Name</label>
					<input type="text" id="pp-<?php echo esc_attr( $domain_lc ); ?>-group-name" class="pp-input" placeholder="Eagles Schedule">
				</div>
				<div class="pp-form-group">
					<label>Slug</label>
					<input type="text" id="pp-<?php echo esc_attr( $domain_lc ); ?>-group-slug" class="pp-input" placeholder="eagles">
				</div>
				<div class="pp-modal-actions">
					<button class="pp-button pp-button-primary" id="pp-<?php echo esc_attr( $domain_lc ); ?>-create-group-btn"
							data-action="<?php echo esc_attr( $this->get_create_action() ); ?>">
						Create
					</button>
					<button class="pp-button pp-button-secondary pp-<?php echo esc_attr( $domain_lc ); ?>-modal-cancel">Cancel</button>
				</div>
			</div>
		</div>
		<?php
		return ob_get_clean();
	}
}
