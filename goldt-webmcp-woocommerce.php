<?php
/**
 * Plugin Name: Goldnat for WooCommerce
 * Plugin URI: https://goldnat.ai/plugins/goldt-webmcp-woocommerce
 * Description: WooCommerce integration for Goldnat — exposes WooCommerce products, orders, and cart as AI tools via the Goldnat WebMCP Bridge.
 * Version: 1.0.1
 * Author: chgold
 * Author URI: https://goldnat.ai/
 * License: Proprietary
 * Requires at least: 6.0
 * Requires PHP: 7.4
 * Requires Plugins: goldt-webmcp-bridge, woocommerce
 * Text Domain: goldt-webmcp-woocommerce
 *
 * @package GoldtWebMCP\WooCommerce
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'GOLDTWMCP_WC_VERSION', '1.0.1' );
define( 'GOLDTWMCP_WC_PATH', plugin_dir_path( __FILE__ ) );
define( 'GOLDTWMCP_WC_URL', plugin_dir_url( __FILE__ ) );

define( 'GOLDTWMCP_WC_REQUIRED_FREE_VERSION', '0.1.0' );

/**
 * Verify the Free Bridge + WooCommerce are present.
 *
 * @return bool
 */
function goldtwmcp_wc_check_dependencies() {
	if ( ! defined( 'GOLDTWMCP_VERSION' ) ) {
		add_action(
			'admin_notices',
			function () {
				echo '<div class="notice notice-error"><p>';
				echo '<strong>Goldnat for WooCommerce:</strong> Requires the free Goldnat WebMCP Bridge plugin. ';
				echo '<a href="' . esc_url( admin_url( 'plugin-install.php?s=goldt-webmcp-bridge&tab=search' ) ) . '">';
				echo esc_html__( 'Install Goldnat WebMCP Bridge', 'goldt-webmcp-woocommerce' );
				echo '</a></p></div>';
			}
		);
		return false;
	}

	if ( version_compare( GOLDTWMCP_VERSION, GOLDTWMCP_WC_REQUIRED_FREE_VERSION, '<' ) ) {
		add_action(
			'admin_notices',
			function () {
				echo '<div class="notice notice-warning"><p>';
				echo '<strong>Goldnat for WooCommerce:</strong> Requires Goldnat WebMCP Bridge ';
				echo esc_html( GOLDTWMCP_WC_REQUIRED_FREE_VERSION ) . ' or higher.';
				echo '</p></div>';
			}
		);
		return false;
	}

	if ( ! class_exists( 'WooCommerce' ) ) {
		add_action(
			'admin_notices',
			function () {
				echo '<div class="notice notice-error"><p>';
				echo '<strong>Goldnat for WooCommerce:</strong> Requires the WooCommerce plugin.';
				echo '</p></div>';
			}
		);
		return false;
	}

	return true;
}

// Since goldt-webmcp-bridge >= 0.5.9 the core defers register_modules() to
// plugins_loaded priority 9999, so any priority up to 9998 is safe here.
// (Older cores fired the action from the constructor at priority 10 and any
// priority > 10 would silently fail — those older sites need to be upgraded.)
add_action(
	'plugins_loaded',
	function () {
		if ( ! goldtwmcp_wc_check_dependencies() ) {
			return;
		}
		add_action( 'goldtwmcp_register_modules', 'goldtwmcp_wc_register_modules' );
	},
	20
);

add_action(
	'admin_menu',
	function () {
		add_options_page(
			'Goldnat WooCommerce License',
			'Goldnat WC License',
			'manage_options',
			'goldtwmcp-wc-license',
			'goldtwmcp_wc_render_license_page'
		);
	}
);

function goldtwmcp_wc_render_license_page() {
	require_once GOLDTWMCP_WC_PATH . 'includes/class-license-client.php';
	if ( isset( $_POST['goldtwmcp_wc_license_key'] )
			&& check_admin_referer( 'goldtwmcp_wc_license' ) ) {
		$key = sanitize_text_field( wp_unslash( $_POST['goldtwmcp_wc_license_key'] ) );
		update_option( \GoldtWebMCP\WooCommerce\License_Client::KEY_OPTION, $key );
		delete_option( \GoldtWebMCP\WooCommerce\License_Client::CACHE_OPTION );
		echo '<div class="notice notice-success"><p>License key saved. Refresh manifest to re-check.</p></div>';
	}
	$key    = \GoldtWebMCP\WooCommerce\License_Client::get_key();
	$valid  = \GoldtWebMCP\WooCommerce\License_Client::is_valid();
	$status = \GoldtWebMCP\WooCommerce\License_Client::get_status();
	echo '<div class="wrap"><h1>Goldnat WooCommerce — License</h1>';
	echo '<form method="post">';
	wp_nonce_field( 'goldtwmcp_wc_license' );
	echo '<table class="form-table"><tr>';
	echo '<th scope="row"><label for="goldtwmcp_wc_license_key">License Key</label></th>';
	echo '<td><input type="text" id="goldtwmcp_wc_license_key" name="goldtwmcp_wc_license_key" class="regular-text" value="'
		. esc_attr( $key ) . '"><p class="description">Purchase or manage your key at <a href="https://goldnat.ai/" target="_blank">goldnat.ai</a>.</p></td>';
	echo '</tr><tr>';
	echo '<th scope="row">Status</th><td>' . ( $valid ? '✅ Valid' : '❌ Not licensed' );
	if ( is_array( $status ) && isset( $status['status'] ) ) {
		echo ' <code>' . esc_html( $status['status'] ) . '</code>';
	}
	echo '</td></tr></table>';
	submit_button( 'Save License' );
	echo '</form></div>';
}

/**
 * Register WooCommerce module with the Goldnat Bridge.
 *
 * Gated by License_Client: an unlicensed site sees zero WooCommerce tools in
 * the manifest. The AICONNECT_EDITION=pro env override bypasses the check for
 * dev / CI sites.
 *
 * @param \GoldtWebMCP\GoldtWebMCP_Plugin $goldtwmcp Bridge instance.
 * @return void
 */
function goldtwmcp_wc_register_modules( $goldtwmcp ) {
	require_once GOLDTWMCP_WC_PATH . 'includes/class-license-client.php';
	if ( ! \GoldtWebMCP\WooCommerce\License_Client::is_valid() ) {
		return;
	}

	require_once GOLDTWMCP_WC_PATH . 'includes/modules/class-woocommerce-module.php';

	$manifest  = $goldtwmcp->get_manifest_instance();
	$wc_module = new \GoldtWebMCP\Modules\WooCommerce_Module( $manifest );

	$goldtwmcp->register_external_module( 'woocommerce', $wc_module );
}
