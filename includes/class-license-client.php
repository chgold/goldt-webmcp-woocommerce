<?php
/**
 * License client — validates the Goldnat Pro license key against goldnat.ai.
 *
 * Slug-scoped so a different Pro plugin's key can't unlock this one. 24h
 * cache aligned with the 24h refund window. Fail-open on network errors so a
 * paying customer is never hard-blocked by a temporary outage. The
 * AICONNECT_EDITION env override lets dev/CI sites skip the check entirely.
 *
 * @package GoldtWebMCP\WooCommerce
 */

namespace GoldtWebMCP\WooCommerce;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class License_Client {

	const API_URL       = 'https://goldnat.ai/api/licenses/public/validate';
	const EXPECTED_SLUG = 'goldt-webmcp-woocommerce';
	const KEY_OPTION    = 'goldtwmcp_wc_license_key';
	const CACHE_OPTION  = 'goldtwmcp_wc_license_status';
	const CACHE_TTL     = 86400;

	public static function get_key() {
		return trim( (string) get_option( self::KEY_OPTION, '' ) );
	}

	public static function get_status() {
		$raw = get_option( self::CACHE_OPTION, '' );
		if ( ! is_string( $raw ) || $raw === '' ) {
			return null;
		}
		$decoded = json_decode( $raw, true );
		return is_array( $decoded ) ? $decoded : null;
	}

	/**
	 * Whether the license is currently valid for this plugin.
	 *
	 * @return bool
	 */
	public static function is_valid() {
		$env = getenv( 'AICONNECT_EDITION' );
		if ( is_string( $env ) && strtolower( $env ) === 'pro' ) {
			return true;
		}

		$key = self::get_key();
		if ( $key === '' ) {
			return false;
		}

		$cached = self::get_status();
		if ( is_array( $cached ) && isset( $cached['checked_at'] )
				&& ( time() - (int) $cached['checked_at'] ) < self::CACHE_TTL ) {
			return self::grants( $cached );
		}

		$resp = self::call_server( $key );
		if ( $resp === null ) {
			if ( is_array( $cached ) ) {
				return self::grants( $cached );
			}
			self::write_cache( array( 'valid' => true, 'status' => 'error_cached' ) );
			return true;
		}

		self::write_cache( $resp );
		return self::grants( $resp );
	}

	/**
	 * Slug-scoped grant check. error_cached = fail-open network-blip verdict.
	 */
	private static function grants( $data ) {
		if ( ! is_array( $data ) ) {
			return false;
		}
		if ( ( $data['status'] ?? '' ) === 'error_cached' ) {
			return true;
		}
		return in_array( $data['status'] ?? '', array( 'valid', 'valid_no_updates' ), true )
			&& ( $data['plugin_slug'] ?? '' ) === self::EXPECTED_SLUG;
	}

	private static function write_cache( $status ) {
		$status['checked_at'] = time();
		update_option( self::CACHE_OPTION, wp_json_encode( $status ), false );
	}

	private static function call_server( $key ) {
		$url  = getenv( 'AICONNECT_LICENSE_URL' ) ? getenv( 'AICONNECT_LICENSE_URL' ) : self::API_URL;
		$host = parse_url( home_url(), PHP_URL_HOST );
		$body = wp_json_encode(
			array(
				'license_key'   => $key,
				'domain'        => strtolower( (string) $host ),
				'addon_version' => defined( 'GOLDTWMCP_WC_VERSION' ) ? GOLDTWMCP_WC_VERSION : '1.0.0',
			)
		);
		$resp = wp_remote_post(
			$url,
			array(
				'body'    => $body,
				'headers' => array( 'Content-Type' => 'application/json' ),
				'timeout' => 10,
			)
		);
		if ( is_wp_error( $resp ) ) {
			return null;
		}
		$code = wp_remote_retrieve_response_code( $resp );
		if ( $code < 200 || $code >= 300 ) {
			return null;
		}
		$decoded = json_decode( wp_remote_retrieve_body( $resp ), true );
		return is_array( $decoded ) ? $decoded : null;
	}
}
