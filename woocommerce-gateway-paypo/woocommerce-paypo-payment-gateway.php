<?php
/**
 * Plugin Name: WooCommerce PayPo Payment Gateway
 * Description: Integracja z systemem płatności PayPo
 * Version: 2.6.0
 * Author: Inspire Labs
 * Author URI: https://inspirelabs.pl
 * Text Domain: woocommerce-gateway-paypo
 * Domain Path: /languages
 * Requires at least: 5.0.0
 * Requires PHP: 7.2.0
 * WC requires at least: 4.0
 * WC tested up to: 5.7.1
 *
 * @package PayPo
 */

if ( ! defined( 'ABSPATH' ) ) :
	exit; // Exit if accessed directly.
endif;

// Check if WooCommerce is active.
if ( ! in_array( 'woocommerce/woocommerce.php', apply_filters( 'active_plugins', get_option( 'active_plugins' ) ) ) ) :
	return;
endif;

// Autoloader.
require __DIR__ . '/vendor/autoload.php';

// Run Woocommerce Gateway
add_action( 'plugins_loaded', function () {
	if ( class_exists( 'PayPo\\Plugin' ) ) {
		$plugin_info = new \PayPo\Plugin_Info( __FILE__ );
		new \PayPo\Plugin( $plugin_info );
	}
}, 99 );