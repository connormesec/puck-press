<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Mints and validates the single-use approve token carried by the "Send to
 * client" link in the review email.
 *
 * Only the sha1 hash of the token is persisted (in the pp_site_pulse_pending
 * option), so a database read cannot replay the link. The token is scoped to a
 * specific month, expires, and is burned after the client send succeeds.
 *
 * @package    Puck_Press
 * @subpackage Puck_Press/includes/site-pulse
 */
class Puck_Press_Site_Pulse_Token {

	const TTL = 14 * DAY_IN_SECONDS;

	/**
	 * Create a token for $month, store its hash + metadata, and return the
	 * plaintext (which is only ever placed in the email link).
	 */
	public function mint( string $month ): string {
		$token = wp_generate_password( 40, false );
		update_option(
			Puck_Press_Site_Pulse::OPTION_PENDING,
			array(
				'month'          => $month,
				'token_hash'     => sha1( $token ),
				'expires'        => time() + self::TTL,
				'sent_to_client' => false,
			),
			false
		);
		return $token;
	}

	/**
	 * True only if $token matches the stored hash for $month, is unexpired, and
	 * has not already been used to send.
	 */
	public function validate( string $token, string $month ): bool {
		$pending = $this->get_pending();
		if ( empty( $pending ) ) {
			return false;
		}
		if ( ! empty( $pending['sent_to_client'] ) ) {
			return false;
		}
		if ( (string) ( $pending['month'] ?? '' ) !== $month ) {
			return false;
		}
		if ( (int) ( $pending['expires'] ?? 0 ) < time() ) {
			return false;
		}
		$expected = (string) ( $pending['token_hash'] ?? '' );
		if ( $expected === '' ) {
			return false;
		}
		return hash_equals( $expected, sha1( $token ) );
	}

	/**
	 * Whether the pending digest for $month was already sent to the client.
	 */
	public function already_sent( string $month ): bool {
		$pending = $this->get_pending();
		return ! empty( $pending )
			&& (string) ( $pending['month'] ?? '' ) === $month
			&& ! empty( $pending['sent_to_client'] );
	}

	/**
	 * Burn the token: mark the pending digest as sent so it cannot send twice.
	 */
	public function mark_sent(): void {
		$pending = $this->get_pending();
		if ( empty( $pending ) ) {
			return;
		}
		$pending['sent_to_client'] = true;
		update_option( Puck_Press_Site_Pulse::OPTION_PENDING, $pending, false );
	}

	public function get_pending(): array {
		$pending = get_option( Puck_Press_Site_Pulse::OPTION_PENDING, array() );
		return is_array( $pending ) ? $pending : array();
	}
}
