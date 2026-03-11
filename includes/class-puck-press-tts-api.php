<?php

/**
 * Class Puck_Press_Tts_Api
 *
 * Shared TimeToScore API credentials and signing logic used by both the
 * schedule and roster USPHL processors.
 *
 * @package    Puck_Press
 * @subpackage Puck_Press/includes
 */
class Puck_Press_Tts_Api {

	const TTS_SECRET     = '7csjfsXdUYuLs1Nq2datfxIdrpOjgFln';
	const TTS_AUTH_KEY   = 'leagueapps';
	const TTS_LEAGUE_ID  = '2';
	const TTS_STAT_CLASS = '1';
	const TTS_BASE_URL   = 'https://api.usphl.timetoscore.com';
	const TTS_BODY_MD5   = 'd41d8cd98f00b204e9800998ecf8427e'; // MD5 of empty string

	/**
	 * Builds a signed request URL for the TimeToScore API.
	 *
	 * The signature is HMAC-SHA256 over "GET\n/{endpoint}\n{query_string}".
	 * PHP's hash_hmac zero-pads keys ≤ 64 bytes (RFC 2104), which matches the
	 * Node.js deriveKey() behaviour for this 32-byte secret.
	 *
	 * @param string $endpoint API endpoint name (e.g. 'get_schedule').
	 * @param array  $params   Query parameters to include (before signing).
	 * @return string          Fully signed URL ready to fetch.
	 */
	public static function build_signed_url( string $endpoint, array $params ): string {
		$qs        = http_build_query( $params );
		$message   = "GET\n/{$endpoint}\n{$qs}";
		$signature = hash_hmac( 'sha256', $message, self::TTS_SECRET );
		return self::TTS_BASE_URL . "/{$endpoint}?{$qs}&auth_signature={$signature}";
	}
}
