<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Site Pulse — monthly client value digest.
 *
 * Responsibilities:
 *  - First-party, privacy-friendly traffic counter (JS beacon -> public endpoint
 *    -> aggregate table). Built this way because client sites run LiteSpeed
 *    full-page cache: a server-side counter never runs on a cache hit, but the
 *    beacon fires from the browser on cached and uncached pages alike.
 *  - Monthly digest generation, owner review email, and a prefetch-safe
 *    confirm-then-send flow to the client. (Added in later build steps.)
 *
 * @package    Puck_Press
 * @subpackage Puck_Press/includes/site-pulse
 */
class Puck_Press_Site_Pulse {

	const OPTION_ENABLED      = 'pp_site_pulse_enabled';
	const OPTION_REVIEW_EMAIL = 'pp_site_pulse_review_email';
	const OPTION_CLIENT_EMAIL = 'pp_site_pulse_client_email';
	const OPTION_FROM_NAME    = 'pp_site_pulse_from_name';
	const OPTION_METRICS      = 'pp_site_pulse_metrics';
	const OPTION_LAST_SENT    = 'pp_site_pulse_last_sent';
	const OPTION_PENDING      = 'pp_site_pulse_pending';
	const OPTION_SECRET       = 'pp_site_pulse_secret';
	const OPTION_FORM_COUNTS  = 'pp_site_pulse_form_counts';
	const OPTION_COUNT_EMAILS = 'pp_site_pulse_count_emails';
	const OPTION_FORM_EMAIL   = 'pp_site_pulse_form_email';

	const DEFAULT_REVIEW_EMAIL = 'connormesec@gmail.com';

	const HIT_ACTION   = 'pp_site_pulse_hit';
	const CONFIRM_VAR  = 'pp_site_pulse_confirm';

	/** @var Puck_Press_Site_Pulse_Views_Wpdb_Utils|null */
	private $views_utils = null;

	/** Guards against counting more than one form submission per HTTP request. */
	private $counted_this_request = false;

	/**
	 * Register all front-end + endpoint hooks. Called on `init` (so it is present
	 * for logged-out admin-ajax requests too) with raw add_action — the deferred
	 * loader has already run by the time `init` fires.
	 */
	public function register_hooks(): void {
		// Ensure the beacon/approve secret exists even on installs that updated the
		// plugin without re-activating (activate() also bootstraps it). One-time write.
		if ( ! get_option( self::OPTION_SECRET ) ) {
			update_option( self::OPTION_SECRET, wp_generate_password( 32, false ) );
		}

		add_action( 'wp_ajax_' . self::HIT_ACTION, array( $this, 'handle_hit' ) );
		add_action( 'wp_ajax_nopriv_' . self::HIT_ACTION, array( $this, 'handle_hit' ) );
		add_action( 'wp_footer', array( $this, 'print_beacon' ) );
		add_action( 'template_redirect', array( $this, 'maybe_handle_confirm_request' ) );

		// Contact form submissions. Divi and Contact Form 7 are detected automatically;
		// any other (incl. home-built) form is counted by calling, in its success path:
		//   do_action( 'pp_site_pulse_form_submission' );
		add_action( 'et_pb_contact_form_submit', array( $this, 'record_divi_form_submission' ), 10, 3 );
		add_action( 'wpcf7_mail_sent', array( $this, 'record_form_submission' ) );
		add_action( 'pp_site_pulse_form_submission', array( $this, 'record_form_submission' ) );

		// Opt-in catch-all for home-built forms that have no hook: count the email they
		// send. Observe-only (returns $atts unchanged). The per-request guard in
		// record_form_submission() prevents double counting when a hook also fired.
		add_filter( 'wp_mail', array( $this, 'maybe_count_contact_email' ) );
	}

	/* ===================================================================
	 * Traffic counter — beacon + endpoint
	 * =================================================================== */

	/**
	 * Print the tiny, dependency-free beacon snippet into the footer. Because it
	 * becomes part of the rendered HTML it is served on cached pages too, so it
	 * fires for every visitor regardless of LiteSpeed cache state. All filtering
	 * (logged-in, bots, etc.) happens server-side at the endpoint.
	 */
	public function print_beacon(): void {
		if ( ! get_option( self::OPTION_ENABLED ) ) {
			return;
		}

		$ajax_url = admin_url( 'admin-ajax.php' );
		$secret   = (string) get_option( self::OPTION_SECRET, '' );
		if ( $secret === '' ) {
			return;
		}
		?>
<script id="pp-site-pulse-beacon">
(function(){
	try{
		var ns='1';
		try{ if(window.sessionStorage){ if(sessionStorage.getItem('pp_sp_seen')){ ns='0'; } else { sessionStorage.setItem('pp_sp_seen','1'); } } }catch(e){}
		if(!navigator.sendBeacon){ return; }
		var b=new URLSearchParams();
		b.append('action','<?php echo esc_js( self::HIT_ACTION ); ?>');
		b.append('k','<?php echo esc_js( $secret ); ?>');
		b.append('p',location.pathname);
		b.append('ns',ns);
		navigator.sendBeacon('<?php echo esc_url( $ajax_url ); ?>', b);
	}catch(e){}
})();
</script>
		<?php
	}

	/**
	 * Public endpoint that records a beacon hit. Always returns 204 (even when a
	 * hit is skipped) so nothing about the filtering leaks and the beacon stays
	 * fire-and-forget.
	 */
	public function handle_hit(): void {
		nocache_headers();

		// Feature gate.
		if ( ! get_option( self::OPTION_ENABLED ) ) {
			$this->end_hit();
		}

		// Static site-wide key (safe to embed in cached HTML; only gates counting).
		$secret   = (string) get_option( self::OPTION_SECRET, '' );
		$provided = isset( $_POST['k'] ) ? sanitize_text_field( wp_unslash( $_POST['k'] ) ) : '';
		if ( $secret === '' || ! hash_equals( $secret, $provided ) ) {
			$this->end_hit();
		}

		// Same-origin guard.
		$referer = isset( $_SERVER['HTTP_REFERER'] ) ? esc_url_raw( wp_unslash( $_SERVER['HTTP_REFERER'] ) ) : '';
		if ( $referer !== '' ) {
			$ref_host  = wp_parse_url( $referer, PHP_URL_HOST );
			$home_host = wp_parse_url( home_url(), PHP_URL_HOST );
			if ( $ref_host && $home_host && strcasecmp( $ref_host, $home_host ) !== 0 ) {
				$this->end_hit();
			}
		}

		// Exclude signed-in users (owner/admins/editors).
		if ( is_user_logged_in() ) {
			$this->end_hit();
		}

		// Exclude bots that do execute JS.
		$ua = isset( $_SERVER['HTTP_USER_AGENT'] ) ? (string) wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) : '';
		if ( $this->is_bot( $ua ) ) {
			$this->end_hit();
		}

		$path = isset( $_POST['p'] ) ? (string) wp_unslash( $_POST['p'] ) : '/';
		if ( $this->is_excluded_path( $path ) ) {
			$this->end_hit();
		}

		// Light abuse throttle (hashed IP, ephemeral, never stored in the counter).
		if ( $this->is_rate_limited() ) {
			$this->end_hit();
		}

		$new_session = isset( $_POST['ns'] ) && $_POST['ns'] === '1';

		$this->get_views_utils()->record_hit( $path, $new_session );
		$this->end_hit();
	}

	private function end_hit(): void {
		status_header( 204 );
		exit;
	}

	private function is_bot( string $ua ): bool {
		if ( trim( $ua ) === '' ) {
			return true;
		}
		$needles = apply_filters(
			'pp_site_pulse_bot_user_agents',
			array(
				'bot', 'crawl', 'spider', 'slurp', 'facebookexternalhit', 'headless',
				'preview', 'monitor', 'curl', 'wget', 'python-requests', 'axios',
				'node-fetch', 'lighthouse', 'pingdom', 'uptimerobot', 'gtmetrix',
			)
		);
		$ua_lower = strtolower( $ua );
		foreach ( $needles as $needle ) {
			if ( $needle !== '' && strpos( $ua_lower, strtolower( $needle ) ) !== false ) {
				return true;
			}
		}
		return false;
	}

	private function is_excluded_path( string $path ): bool {
		$only_path = (string) wp_parse_url( $path, PHP_URL_PATH );
		$only_path = strtolower( $only_path );
		foreach ( array( '/wp-admin', '/wp-json', '/wp-login', '/xmlrpc', '/feed' ) as $needle ) {
			if ( strpos( $only_path, $needle ) !== false ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Allow at most ~30 hits per minute per client. The transient name is a salted
	 * hash of the IP; the IP itself is never stored.
	 */
	private function is_rate_limited(): bool {
		$ip = isset( $_SERVER['REMOTE_ADDR'] ) ? (string) wp_unslash( $_SERVER['REMOTE_ADDR'] ) : '';
		if ( $ip === '' ) {
			return false;
		}
		$key   = 'pp_sp_rl_' . substr( sha1( $ip . '|' . wp_salt() ), 0, 20 );
		$count = (int) get_transient( $key );
		if ( $count >= 30 ) {
			return true;
		}
		set_transient( $key, $count + 1, MINUTE_IN_SECONDS );
		return false;
	}

	private function get_views_utils(): Puck_Press_Site_Pulse_Views_Wpdb_Utils {
		if ( null === $this->views_utils ) {
			require_once plugin_dir_path( __FILE__ ) . 'class-puck-press-site-pulse-views-wpdb-utils.php';
			$this->views_utils = new Puck_Press_Site_Pulse_Views_Wpdb_Utils();
		}
		return $this->views_utils;
	}

	/* ===================================================================
	 * Contact form submissions
	 * =================================================================== */

	/**
	 * Divi contact form callback. Divi fires this on every submit (including
	 * validation errors), so only count genuine successes.
	 *
	 * @param array $processed_fields_values Submitted field values.
	 * @param mixed $et_contact_error        Falsy when the submission succeeded.
	 */
	public function record_divi_form_submission( $processed_fields_values = array(), $et_contact_error = false, $form_data = array() ): void {
		if ( empty( $processed_fields_values ) || ! empty( $et_contact_error ) ) {
			return;
		}
		$this->record_form_submission();
	}

	/**
	 * Count one successful contact form submission for the current month. Used by
	 * Contact Form 7 (wpcf7_mail_sent), the generic pp_site_pulse_form_submission
	 * action (for home-built forms), and the Divi handler above.
	 *
	 * Submissions are rare, so a per-month option counter is sufficient (no need for
	 * the atomic table the high-frequency pageview counter uses).
	 */
	public function record_form_submission(): void {
		if ( ! get_option( self::OPTION_ENABLED ) ) {
			return;
		}
		// One submission == one HTTP request. Guard so a form that fires both an action
		// hook and one or more emails (or several emails) is still counted only once.
		if ( $this->counted_this_request ) {
			return;
		}
		$this->counted_this_request = true;

		$key    = date( 'Y-m', $this->now_ts() );
		$counts = get_option( self::OPTION_FORM_COUNTS, array() );
		if ( ! is_array( $counts ) ) {
			$counts = array();
		}
		$counts[ $key ] = ( isset( $counts[ $key ] ) ? (int) $counts[ $key ] : 0 ) + 1;

		// Keep only the most recent 14 months.
		if ( count( $counts ) > 14 ) {
			ksort( $counts );
			$counts = array_slice( $counts, -14, null, true );
		}
		update_option( self::OPTION_FORM_COUNTS, $counts, false );
	}

	/**
	 * Opt-in catch-all for home-built forms with no action hook: when enabled, count an
	 * outgoing email addressed to the configured contact recipient as one submission.
	 * Approximate by design — observe-only filter, always returns $atts untouched.
	 *
	 * @param array $atts wp_mail arguments (to, subject, message, headers, attachments).
	 * @return array Unmodified $atts.
	 */
	public function maybe_count_contact_email( $atts ) {
		if ( ! is_array( $atts ) ) {
			return $atts;
		}
		if ( ! get_option( self::OPTION_ENABLED ) || ! get_option( self::OPTION_COUNT_EMAILS ) ) {
			return $atts;
		}
		if ( $this->counted_this_request ) {
			return $atts; // already counted this request (e.g. a real hook fired)
		}

		$recipient = strtolower( trim( (string) get_option( self::OPTION_FORM_EMAIL, get_option( 'admin_email', '' ) ) ) );
		if ( $recipient === '' ) {
			return $atts;
		}

		// Normalize the "to" field (string, comma-separated, or array) to lowercase addresses.
		$to = $atts['to'] ?? '';
		if ( ! is_array( $to ) ) {
			$to = preg_split( '/[,;]\s*/', (string) $to );
		}
		$to = array_map( fn( $addr ) => strtolower( trim( (string) $addr ) ), (array) $to );
		if ( ! in_array( $recipient, $to, true ) ) {
			return $atts;
		}

		// Never count Site Pulse's own review/client emails.
		$own = array_filter( array(
			strtolower( (string) get_option( self::OPTION_REVIEW_EMAIL, '' ) ),
			strtolower( (string) get_option( self::OPTION_CLIENT_EMAIL, '' ) ),
		) );
		if ( ! empty( array_intersect( $own, $to ) ) ) {
			return $atts;
		}

		// Skip common WordPress system notifications that also land on admin_email.
		$subject  = strtolower( (string) ( $atts['subject'] ?? '' ) );
		$excluded = apply_filters(
			'pp_site_pulse_excluded_mail_subjects',
			array(
				'password', 'login details', 'username', 'please moderate', 'comment',
				'site health', 'technical issue', 'automatically updated', 'has been updated',
				'new user', 'admin email', 'personal data', 'erase', 'site pulse',
			)
		);
		foreach ( $excluded as $needle ) {
			if ( $needle !== '' && strpos( $subject, strtolower( $needle ) ) !== false ) {
				return $atts;
			}
		}

		$this->record_form_submission();
		return $atts;
	}

	/* ===================================================================
	 * Monthly run (cron gate)
	 * =================================================================== */

	/**
	 * Cron entry point. Sends the owner review email once per calendar month, the
	 * first time cron runs on/after the 1st. Reports on the previous calendar month.
	 *
	 * @return array Log lines.
	 */
	public function maybe_run_monthly(): array {
		if ( ! get_option( self::OPTION_ENABLED ) ) {
			return array( 'Site Pulse: disabled.' );
		}

		$cycle = $this->current_month_key();
		if ( (string) get_option( self::OPTION_LAST_SENT, '' ) === $cycle ) {
			return array( "Site Pulse: review already sent for {$cycle}." );
		}

		list( $year, $month ) = $this->reporting_period();
		$result = $this->send_review( $year, $month );

		if ( ! empty( $result['sent'] ) ) {
			update_option( self::OPTION_LAST_SENT, $cycle, false );
			$this->get_views_utils()->prune_old( 14 );
			return array_merge(
				array( sprintf( 'Site Pulse: review for %04d-%02d sent to %s.', $year, $month, $result['to'] ?? '' ) ),
				$result['log'] ?? array()
			);
		}

		return array_merge( array( 'Site Pulse: review send FAILED.' ), $result['log'] ?? array() );
	}

	/* ===================================================================
	 * Sending
	 * =================================================================== */

	/**
	 * Render and email the owner review copy. Mints a fresh single-use approve
	 * token tied to the reporting month.
	 *
	 * @param int|null $year  Reporting year; defaults to the previous calendar month.
	 * @param int|null $month Reporting month.
	 * @return array{sent:bool, to:string, log:array}
	 */
	public function send_review( ?int $year = null, ?int $month = null ): array {
		if ( null === $year || null === $month ) {
			list( $year, $month ) = $this->reporting_period();
		}
		$this->load_send_deps();

		$month_key = sprintf( '%04d-%02d', $year, $month );
		$to        = $this->review_email();
		if ( $to === '' ) {
			return array( 'sent' => false, 'to' => '', 'log' => array( 'No review email configured.' ) );
		}

		$metrics = ( new Puck_Press_Site_Pulse_Metrics() )->collect( $year, $month );
		$token   = ( new Puck_Press_Site_Pulse_Token() )->mint( $month_key );
		$approve = add_query_arg(
			array(
				self::CONFIRM_VAR => '1',
				'pp_sp_month'     => $month_key,
				'pp_sp_token'     => $token,
			),
			home_url( '/' )
		);

		$html    = ( new Puck_Press_Site_Pulse_Email() )->render( $metrics, 'review', $approve );
		$subject = sprintf(
			/* translators: 1: site name, 2: month label */
			'🏒 Site Pulse review — %1$s · %2$s',
			$metrics['site_name'],
			$metrics['month_label']
		);

		$sent = wp_mail( $to, $subject, $html, $this->headers( $this->review_email() ) );

		return array(
			'sent' => (bool) $sent,
			'to'   => $to,
			'log'  => array( $sent ? "Review emailed to {$to}." : "wp_mail() returned false for {$to}." ),
		);
	}

	/**
	 * Render and email the client copy for a reporting month (YYYY-MM).
	 */
	public function send_to_client( string $month_key ): bool {
		$this->load_send_deps();

		$to = $this->client_email();
		if ( $to === '' ) {
			return false;
		}

		list( $year, $month ) = $this->parse_month_key( $month_key );
		if ( ! $year || ! $month ) {
			return false;
		}

		$metrics = ( new Puck_Press_Site_Pulse_Metrics() )->collect( $year, $month );
		$html    = ( new Puck_Press_Site_Pulse_Email() )->render( $metrics, 'client' );
		$subject = sprintf(
			/* translators: %s: month label */
			'Your %s website recap 🏒',
			$metrics['month_label']
		);

		return (bool) wp_mail( $to, $subject, $html, $this->headers( $this->review_email() ) );
	}

	/**
	 * Build per-message email headers. From is on the site's own domain (so PHP
	 * mail() isn't sending as an unauthenticated foreign domain); Reply-To routes
	 * replies to the agency owner.
	 *
	 * @return string[]
	 */
	private function headers( string $reply_to ): array {
		$host = (string) wp_parse_url( home_url(), PHP_URL_HOST );
		$host = preg_replace( '/^www\./i', '', $host );
		$from_email = 'site-pulse@' . ( $host ?: 'localhost' );
		$from_name  = (string) get_option( self::OPTION_FROM_NAME, '' );
		if ( $from_name === '' ) {
			$from_name = get_bloginfo( 'name' );
		}

		$headers = array(
			'Content-Type: text/html; charset=UTF-8',
			sprintf( 'From: %s <%s>', $from_name, $from_email ),
		);
		if ( $reply_to !== '' ) {
			$headers[] = 'Reply-To: ' . $reply_to;
		}
		return $headers;
	}

	/* ===================================================================
	 * Confirm/approve flow (prefetch-safe, single-use)
	 * =================================================================== */

	/**
	 * Handle the approve link. GET renders a confirmation form and NEVER sends
	 * (so email-client link prefetching is harmless); only an explicit POST with a
	 * valid nonce + single-use token sends to the client.
	 */
	public function maybe_handle_confirm_request(): void {
		if ( ! isset( $_GET[ self::CONFIRM_VAR ] ) ) {
			return;
		}

		nocache_headers();
		$this->load_send_deps();

		$month = isset( $_REQUEST['pp_sp_month'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['pp_sp_month'] ) ) : '';
		$token_util = new Puck_Press_Site_Pulse_Token();
		$is_post    = isset( $_SERVER['REQUEST_METHOD'] ) && strtoupper( (string) $_SERVER['REQUEST_METHOD'] ) === 'POST';

		if ( $token_util->already_sent( $month ) ) {
			$this->render_confirm_notice( 'Already sent', 'This digest has already been sent to your client. ✅', true );
		}

		if ( $is_post ) {
			$nonce = isset( $_POST['_wpnonce'] ) ? sanitize_text_field( wp_unslash( $_POST['_wpnonce'] ) ) : '';
			if ( ! wp_verify_nonce( $nonce, 'pp_site_pulse_confirm' ) ) {
				$this->render_confirm_notice( 'Could not verify', 'This action could not be verified. Please reopen the link from your email.', false );
			}
			$token = isset( $_POST['pp_sp_token'] ) ? sanitize_text_field( wp_unslash( $_POST['pp_sp_token'] ) ) : '';
			if ( ! $token_util->validate( $token, $month ) ) {
				$this->render_confirm_notice( 'Link expired', 'This link has expired or was already used.', false );
			}
			if ( $this->client_email() === '' ) {
				$this->render_confirm_notice( 'No client email', 'No client email address is configured in Site Pulse settings.', false );
			}
			if ( $this->send_to_client( $month ) ) {
				$token_util->mark_sent();
				$this->render_confirm_notice( 'Sent', 'Your Site Pulse digest is on its way to the client. ✅', true );
			}
			$this->render_confirm_notice( 'Send failed', 'Sorry — sending failed. Please try again in a moment.', false );
		}

		// GET → render the confirmation form only (no send).
		$token = isset( $_GET['pp_sp_token'] ) ? sanitize_text_field( wp_unslash( $_GET['pp_sp_token'] ) ) : '';
		if ( ! $token_util->validate( $token, $month ) ) {
			$this->render_confirm_notice( 'Link expired', 'This link has expired or was already used.', false );
		}
		$this->render_confirm_form( $month, $token );
	}

	private function render_confirm_form( string $month, string $token ): void {
		$action = esc_url( add_query_arg( array( self::CONFIRM_VAR => '1' ), home_url( '/' ) ) );
		$client = $this->client_email();
		$site   = get_bloginfo( 'name' );
		$label  = $this->month_key_label( $month );

		$body  = '<h2 style="margin:10px 0 4px;">Send the ' . esc_html( $label ) . ' Site Pulse to your client?</h2>';
		$body .= '<p style="color:#5b6573;margin:0 0 18px;">This will email the recap to:</p>';
		$body .= '<div style="display:inline-block;text-align:left;background:#f6f8fb;border:1px solid #e6eaf0;border-radius:8px;padding:14px 20px;margin-bottom:22px;">';
		$body .= '<div style="font-weight:700;">' . esc_html( $site ) . '</div>';
		$body .= '<div style="color:#5b6573;">' . esc_html( $client !== '' ? $client : 'No client email configured' ) . '</div></div>';
		$body .= '<form method="post" action="' . $action . '">';
		$body .= '<input type="hidden" name="pp_sp_month" value="' . esc_attr( $month ) . '">';
		$body .= '<input type="hidden" name="pp_sp_token" value="' . esc_attr( $token ) . '">';
		$body .= wp_nonce_field( 'pp_site_pulse_confirm', '_wpnonce', true, false );
		$body .= '<button type="submit" style="display:inline-block;background:#1f9d57;color:#fff;border:0;font-weight:700;padding:14px 26px;border-radius:8px;font-size:15px;cursor:pointer;">Confirm — send it now</button>';
		$body .= '</form>';
		$body .= '<p style="font-size:12px;color:#8a94a3;margin-top:20px;">This link is single-use and expires in 14 days.</p>';

		$this->render_page( 'Confirm send', $body );
	}

	private function render_confirm_notice( string $title, string $message, bool $success ): void {
		$color = $success ? '#1f9d57' : '#b4453a';
		$body  = '<h2 style="margin:10px 0 8px;color:' . esc_attr( $color ) . ';">' . esc_html( $title ) . '</h2>';
		$body .= '<p style="color:#3a434f;font-size:15px;">' . esc_html( $message ) . '</p>';
		$this->render_page( $title, $body );
	}

	/**
	 * Output a minimal standalone HTML page and stop — these are front-end
	 * interceptions that render outside the theme.
	 */
	private function render_page( string $title, string $body_html ): void {
		status_header( 200 );
		nocache_headers();
		$site = get_bloginfo( 'name' );
		echo '<!DOCTYPE html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">';
		echo '<meta name="robots" content="noindex,nofollow">';
		echo '<title>' . esc_html( $title ) . ' — ' . esc_html( $site ) . '</title></head>';
		echo '<body style="margin:0;background:#eceff3;font-family:-apple-system,Segoe UI,Roboto,Helvetica,Arial,sans-serif;color:#1f2733;">';
		echo '<div style="max-width:520px;margin:48px auto;padding:0 16px;">';
		echo '<div style="background:#fff;border:1px solid #dfe4ea;border-radius:10px;padding:34px 26px;text-align:center;">';
		echo '<div style="font-size:13px;color:#8a94a3;">' . esc_html( $site ) . '</div>';
		echo $body_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- assembled from escaped parts above.
		echo '</div></div></body></html>';
		exit;
	}

	/* ===================================================================
	 * Helpers
	 * =================================================================== */

	private function load_send_deps(): void {
		require_once plugin_dir_path( __FILE__ ) . 'class-puck-press-site-pulse-views-wpdb-utils.php';
		require_once plugin_dir_path( __FILE__ ) . 'class-puck-press-site-pulse-metrics.php';
		require_once plugin_dir_path( __FILE__ ) . 'class-puck-press-site-pulse-email.php';
		require_once plugin_dir_path( __FILE__ ) . 'class-puck-press-site-pulse-token.php';
	}

	private function review_email(): string {
		$email = (string) get_option( self::OPTION_REVIEW_EMAIL, self::DEFAULT_REVIEW_EMAIL );
		if ( $email === '' ) {
			$email = (string) get_option( 'admin_email', '' );
		}
		return is_email( $email ) ? $email : '';
	}

	private function client_email(): string {
		$email = (string) get_option( self::OPTION_CLIENT_EMAIL, '' );
		return is_email( $email ) ? $email : '';
	}

	/** Current calendar month "YYYY-MM" (the send cycle key), PP_FAKE_NOW aware. */
	private function current_month_key(): string {
		return date( 'Y-m', $this->now_ts() );
	}

	/** Previous calendar month [year, month] relative to now (the reporting period). */
	private function reporting_period(): array {
		$ts    = $this->now_ts();
		$year  = (int) date( 'Y', $ts );
		$month = (int) date( 'n', $ts ) - 1;
		if ( $month < 1 ) {
			$month = 12;
			--$year;
		}
		return array( $year, $month );
	}

	private function now_ts(): int {
		if ( defined( 'PP_FAKE_NOW' ) && PP_FAKE_NOW ) {
			$ts = strtotime( (string) PP_FAKE_NOW );
			if ( $ts ) {
				return $ts;
			}
		}
		return (int) current_time( 'timestamp' );
	}

	/** @return array{0:int,1:int} */
	private function parse_month_key( string $month_key ): array {
		if ( ! preg_match( '/^(\d{4})-(\d{2})$/', $month_key, $m ) ) {
			return array( 0, 0 );
		}
		return array( (int) $m[1], (int) $m[2] );
	}

	private function month_key_label( string $month_key ): string {
		list( $y, $m ) = $this->parse_month_key( $month_key );
		if ( ! $y || ! $m ) {
			return $month_key;
		}
		return date_i18n( 'F Y', mktime( 0, 0, 0, $m, 1, $y ) );
	}
}
