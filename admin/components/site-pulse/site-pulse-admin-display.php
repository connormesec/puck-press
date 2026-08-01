<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once plugin_dir_path( __DIR__ ) . '../../includes/site-pulse/class-puck-press-site-pulse.php';

/**
 * Admin "Site Pulse" tab: enable + recipients + metric toggles, a status panel,
 * and preview / send-test controls. Nothing here emails the client — the only
 * path to the client is the confirm flow triggered from the review email.
 *
 * @package    Puck_Press
 * @subpackage Puck_Press/admin/components/site-pulse
 */
class Puck_Press_Site_Pulse_Admin_Display {

	private $metric_keys = array(
		'traffic'  => 'Website traffic (page views, visits, top pages)',
		'content'  => 'Posts &amp; recaps published',
		'forms'    => 'Contact form submissions',
		'schedule' => 'Games tracked &amp; scored',
		'season'   => 'Standings, record &amp; awards',
	);

	public function render(): string {
		if ( isset( $_POST['pp_site_pulse_save'] ) ) {
			$this->save_settings();
		}

		$enabled      = (int) get_option( Puck_Press_Site_Pulse::OPTION_ENABLED, 0 );
		$review_email = (string) get_option( Puck_Press_Site_Pulse::OPTION_REVIEW_EMAIL, Puck_Press_Site_Pulse::DEFAULT_REVIEW_EMAIL );
		$client_email = (string) get_option( Puck_Press_Site_Pulse::OPTION_CLIENT_EMAIL, '' );
		$from_name    = (string) get_option( Puck_Press_Site_Pulse::OPTION_FROM_NAME, wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES ) );
		$count_emails = (int) get_option( Puck_Press_Site_Pulse::OPTION_COUNT_EMAILS, 0 );
		$form_email   = (string) get_option( Puck_Press_Site_Pulse::OPTION_FORM_EMAIL, get_option( 'admin_email', '' ) );
		$metrics = get_option( Puck_Press_Site_Pulse::OPTION_METRICS, array() );
		if ( ! is_array( $metrics ) ) {
			$metrics = array();
		}
		// Any metric not present in a saved config (incl. metrics added later) defaults on.
		foreach ( array_keys( $this->metric_keys ) as $mk ) {
			if ( ! array_key_exists( $mk, $metrics ) ) {
				$metrics[ $mk ] = 1;
			}
		}

		ob_start();
		?>
		<div class="wrap">
			<h1>Site Pulse</h1>
			<p class="description" style="max-width:680px;">
				A monthly value digest. It emails <strong>you</strong> first for review; from that email you
				click <em>Send to client</em>, confirm, and the recap is sent to your client as if from the site.
			</p>

			<?php $this->render_status_panel(); ?>

			<h2>Settings</h2>
			<form method="post">
				<?php wp_nonce_field( 'pp_site_pulse_settings' ); ?>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row">Enable Site Pulse</th>
						<td>
							<label>
								<input type="checkbox" name="pp_site_pulse_enabled" value="1" <?php checked( $enabled, 1 ); ?> />
								Track traffic and send the monthly digest
							</label>
							<p class="description">When off, no page views are recorded and no digests are sent.</p>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="pp_sp_review_email">Review email (you)</label></th>
						<td>
							<input type="email" id="pp_sp_review_email" name="pp_site_pulse_review_email" class="regular-text" value="<?php echo esc_attr( $review_email ); ?>" />
							<p class="description">The monthly review copy is sent here for your approval.</p>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="pp_sp_client_email">Client email</label></th>
						<td>
							<input type="email" id="pp_sp_client_email" name="pp_site_pulse_client_email" class="regular-text" value="<?php echo esc_attr( $client_email ); ?>" />
							<p class="description">Where the approved digest is sent. Leave blank until you're ready.</p>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="pp_sp_from_name">From name</label></th>
						<td>
							<input type="text" id="pp_sp_from_name" name="pp_site_pulse_from_name" class="regular-text" value="<?php echo esc_attr( $from_name ); ?>" />
							<p class="description">Sender name on the client email (the address is on this site's domain).</p>
						</td>
					</tr>
					<tr>
						<th scope="row">Count emailed contact forms</th>
						<td>
							<label>
								<input type="checkbox" name="pp_site_pulse_count_emails" value="1" <?php checked( $count_emails, 1 ); ?> />
								Count submissions from home-built forms by watching the emails they send
							</label>
							<p class="description">For custom forms that have no plugin hook. Approximate — best paired with a dedicated recipient below.</p>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="pp_sp_form_email">Contact form recipient</label></th>
						<td>
							<input type="email" id="pp_sp_form_email" name="pp_site_pulse_form_email" class="regular-text" value="<?php echo esc_attr( $form_email ); ?>" />
							<p class="description">The address your contact form emails to. Use a dedicated contact inbox for best accuracy (WordPress system emails go to the admin address and would otherwise be miscounted).</p>
						</td>
					</tr>
					<tr>
						<th scope="row">Metrics to include</th>
						<td>
							<?php foreach ( $this->metric_keys as $key => $label ) : ?>
								<label style="display:block;margin-bottom:6px;">
									<input type="checkbox" name="pp_site_pulse_metrics[<?php echo esc_attr( $key ); ?>]" value="1" <?php checked( ! empty( $metrics[ $key ] ) ); ?> />
									<?php echo wp_kses_post( $label ); ?>
								</label>
							<?php endforeach; ?>
						</td>
					</tr>
				</table>
				<?php submit_button( 'Save Settings', 'primary', 'pp_site_pulse_save' ); ?>
			</form>

			<h2>Preview &amp; test</h2>
			<p class="description" style="max-width:680px;">
				Preview renders last month's client email here (nothing is sent). “Send test to me” emails
				the review copy to your review address now, ignoring the once-a-month limit.
			</p>
			<p>
				<button type="button" class="button" id="pp-sp-preview">Preview client email</button>
				<button type="button" class="button button-secondary" id="pp-sp-send-test">Send test to me</button>
				<span id="pp-sp-test-result" style="margin-left:10px;font-style:italic;"></span>
			</p>
			<div id="pp-sp-preview-wrap" style="display:none;margin-top:16px;border:1px solid #dcdcde;border-radius:6px;overflow:hidden;">
				<iframe id="pp-sp-preview-frame" style="width:100%;height:760px;border:0;background:#eceff3;"></iframe>
			</div>
		</div>
		<?php
		return (string) ob_get_clean();
	}

	private function save_settings(): void {
		check_admin_referer( 'pp_site_pulse_settings' );

		update_option( Puck_Press_Site_Pulse::OPTION_ENABLED, isset( $_POST['pp_site_pulse_enabled'] ) ? 1 : 0 );

		$review = sanitize_email( wp_unslash( $_POST['pp_site_pulse_review_email'] ?? '' ) );
		update_option( Puck_Press_Site_Pulse::OPTION_REVIEW_EMAIL, is_email( $review ) ? $review : '' );

		$client = sanitize_email( wp_unslash( $_POST['pp_site_pulse_client_email'] ?? '' ) );
		update_option( Puck_Press_Site_Pulse::OPTION_CLIENT_EMAIL, is_email( $client ) ? $client : '' );

		update_option( Puck_Press_Site_Pulse::OPTION_FROM_NAME, sanitize_text_field( wp_unslash( $_POST['pp_site_pulse_from_name'] ?? '' ) ) );

		update_option( Puck_Press_Site_Pulse::OPTION_COUNT_EMAILS, isset( $_POST['pp_site_pulse_count_emails'] ) ? 1 : 0 );

		$form_email = sanitize_email( wp_unslash( $_POST['pp_site_pulse_form_email'] ?? '' ) );
		update_option( Puck_Press_Site_Pulse::OPTION_FORM_EMAIL, is_email( $form_email ) ? $form_email : '' );

		$posted  = isset( $_POST['pp_site_pulse_metrics'] ) && is_array( $_POST['pp_site_pulse_metrics'] ) ? $_POST['pp_site_pulse_metrics'] : array();
		$metrics = array();
		foreach ( array_keys( $this->metric_keys ) as $key ) {
			$metrics[ $key ] = ! empty( $posted[ $key ] ) ? 1 : 0;
		}
		update_option( Puck_Press_Site_Pulse::OPTION_METRICS, $metrics );

		echo '<div class="updated"><p>Site Pulse settings saved.</p></div>';
	}

	private function render_status_panel(): void {
		$last_sent = (string) get_option( Puck_Press_Site_Pulse::OPTION_LAST_SENT, '' );
		$pending   = get_option( Puck_Press_Site_Pulse::OPTION_PENDING, array() );
		$pending   = is_array( $pending ) ? $pending : array();

		$forwarded = ! empty( $pending['sent_to_client'] )
			? 'Yes — ' . esc_html( (string) ( $pending['month'] ?? '' ) ) . ' sent to client'
			: ( ! empty( $pending ) ? 'Pending your approval (' . esc_html( (string) ( $pending['month'] ?? '' ) ) . ')' : '—' );

		// This month's traffic so far (counter health).
		$views_so_far = 0;
		$top          = '—';
		try {
			require_once plugin_dir_path( __DIR__ ) . '../../includes/site-pulse/class-puck-press-site-pulse-views-wpdb-utils.php';
			$utils        = new Puck_Press_Site_Pulse_Views_Wpdb_Utils();
			$y            = (int) current_time( 'Y' );
			$m            = (int) current_time( 'n' );
			$views_so_far = $utils->get_month_views( $y, $m );
			$pages        = $utils->get_top_pages( $y, $m, 1 );
			if ( ! empty( $pages ) ) {
				$top = $pages[0]['path'] . ' (' . number_format_i18n( $pages[0]['views'] ) . ')';
			}
		} catch ( \Throwable $e ) {
			$views_so_far = 0;
		}
		?>
		<table class="widefat striped" style="max-width:680px;margin:12px 0 24px;">
			<tbody>
				<tr><td style="width:240px;"><strong>Last review sent</strong></td><td><?php echo $last_sent !== '' ? esc_html( $last_sent ) : '—'; ?></td></tr>
				<tr><td><strong>Forwarded to client?</strong></td><td><?php echo wp_kses_post( $forwarded ); ?></td></tr>
				<tr><td><strong>Views recorded this month</strong></td><td><?php echo esc_html( number_format_i18n( $views_so_far ) ); ?> &nbsp; <span style="color:#646970;">top: <?php echo esc_html( $top ); ?></span></td></tr>
			</tbody>
		</table>
		<?php
	}

	/* ===================================================================
	 * AJAX
	 * =================================================================== */

	public function ajax_preview(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => 'Insufficient permissions.' ) );
		}
		check_ajax_referer( 'pp_site_pulse_nonce', 'nonce' );

		require_once plugin_dir_path( __DIR__ ) . '../../includes/site-pulse/class-puck-press-site-pulse-metrics.php';
		require_once plugin_dir_path( __DIR__ ) . '../../includes/site-pulse/class-puck-press-site-pulse-email.php';

		list( $year, $month ) = $this->preview_period();
		$metrics = ( new Puck_Press_Site_Pulse_Metrics() )->collect( $year, $month );
		$html    = ( new Puck_Press_Site_Pulse_Email() )->render( $metrics, 'client' );

		wp_send_json_success( array( 'html' => $html ) );
	}

	public function ajax_send_test(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => 'Insufficient permissions.' ) );
		}
		check_ajax_referer( 'pp_site_pulse_nonce', 'nonce' );

		$result = ( new Puck_Press_Site_Pulse() )->send_review();
		if ( ! empty( $result['sent'] ) ) {
			wp_send_json_success( array( 'message' => 'Review email sent to ' . ( $result['to'] ?? '' ) . '.' ) );
		}
		wp_send_json_error( array( 'message' => 'Send failed. ' . implode( ' ', $result['log'] ?? array() ) ) );
	}

	/** @return array{0:int,1:int} previous calendar month */
	private function preview_period(): array {
		$year  = (int) current_time( 'Y' );
		$month = (int) current_time( 'n' ) - 1;
		if ( $month < 1 ) {
			$month = 12;
			--$year;
		}
		return array( $year, $month );
	}
}
