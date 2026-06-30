<?php
/**
 * Plugin Name: Goldnat for WooCommerce
 * Plugin URI: https://goldnat.ai/plugins/goldt-webmcp-woocommerce
 * Description: WooCommerce integration for Goldnat — exposes WooCommerce products, orders, and cart as AI tools via the Goldnat WebMCP Bridge.
 * Version: 1.0.0
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

define( 'GOLDTWMCP_WC_VERSION', '1.0.0' );
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

/**
 * Register WooCommerce module with the Goldnat Bridge.
 *
 * @param \GoldtWebMCP\GoldtWebMCP_Plugin $goldtwmcp Bridge instance.
 * @return void
 */
function goldtwmcp_wc_register_modules( $goldtwmcp ) {
	require_once GOLDTWMCP_WC_PATH . 'includes/modules/class-woocommerce-module.php';

	$manifest  = $goldtwmcp->get_manifest_instance();
	$wc_module = new \GoldtWebMCP\Modules\WooCommerce_Module( $manifest );

	$goldtwmcp->register_external_module( 'woocommerce', $wc_module );
}
