<?php

/**
 * Plugin Name: BridgerPay Woocommerce
 * Plugin URI:
 * Description: The Bridgerpay Woocommerce plugin enables you to easily accept payments through your Woocommerce store. <a href="https://bridgerpay.com/">https://bridgerpay.com</a>
 * Version: 1.2.6
 * Stable tag: 1.2.6
 * Requires at least: 5.6
 * Tested up to: 6.6.1
 * Text Domain:       bridgerpay
 * Domain Path:       /languages
 */


$woo_plugin_path = trailingslashit( WP_PLUGIN_DIR ) . 'woocommerce/woocommerce.php';

if (in_array( $woo_plugin_path, wp_get_active_and_valid_plugins()))
{
define('BRIDGERPAY_DIR', plugin_dir_path(__FILE__));
define('BRIDGERPAY_PATH', plugin_dir_url(__FILE__));

require_once BRIDGERPAY_DIR . 'includes/autoload.php';

add_action( 'plugins_loaded', 'init_bridgerpay_gateway_class', 11 );
add_filter( 'woocommerce_payment_gateways', 'add_bridgerpay_gateway_class' );

function init_bridgerpay_gateway_class() {
    require_once BRIDGERPAY_DIR . 'includes/class-wc-bridgerpay-gateway.php';
}

function add_bridgerpay_gateway_class( $methods ) {
    $methods[] = 'WC_Bridgerpay_Gateway';
    return $methods;
}

function loadBridgerPayLibrary() {
    require_once BRIDGERPAY_DIR . 'includes/classes/Payment.php';
    require_once BRIDGERPAY_DIR . 'includes/classes/Order.php';
    require_once BRIDGERPAY_DIR . 'includes/classes/Response.php';
}
}
