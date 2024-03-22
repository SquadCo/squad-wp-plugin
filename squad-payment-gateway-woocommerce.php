<?php

/**
 * Plugin Name: 			Squad WooCommerce Payment Gateway
 * Plugin URI: 				https://github.com/SquadInc/squad-wp-plugin
 * Author: 					Squad Developers
 * Author URI: 				http://squadco.com/
 * Description: 			Provides Seamless Payments with Multiple payment options.
 * Version: 				1.0.11
 * WC requires at least: 	8.0
 * WC tested up to: 		8.6
 * License: 				GPL2
 * License URL: 			http://www.gnu.org/licenses/gpl-2.0.txt
 * text-domain: 			squad-payment-gateway
 * 
 * Class WC_Gateway_Squad file.
 *
 * @package WooCommerce\Squad
 */

if (!defined('ABSPATH')) {
	exit; // Exit if accessed directly.
}
define('WC_SQUAD_MAIN_FILE', __FILE__);
define('WC_SQUAD_VERSION', '1.0.11');

if (!in_array('woocommerce/woocommerce.php', apply_filters('active_plugins', get_option('active_plugins')))) return;

add_action('plugins_loaded', 'squad_payment_init', 11);
add_filter('woocommerce_currencies', 'sqaud_add_ngn_currencies');
add_filter('woocommerce_currency_symbol', 'sqaud_add_ngn_currencies_symbol', 10, 2);
add_filter('woocommerce_payment_gateways', 'add_to_woo_squad_payment_gateway', 99);

function squad_payment_init()
{
	if (class_exists('WC_Payment_Gateway')) {
		require_once plugin_dir_path(__FILE__) . '/includes/class-wc-payment-gateway-squad.php';
		// require_once plugin_dir_path( __FILE__ ) . '/includes/class-wc-gateway-squad-subscriptions.php';
	}
}

function add_to_woo_squad_payment_gateway($gateways)
{
	$gateways[] = 'WC_Gateway_Squad';
	return $gateways;
}

function sqaud_add_ngn_currencies($currencies)
{
	$currencies['NGN'] = __('Nigerian Naira', 'squad-payment-gateway');
	$currencies['USD'] = __('United State Dollar', 'squad-payment-gateway');
	return $currencies;
}

function sqaud_add_ngn_currencies_symbol($currency_symbol, $currency)
{
	switch ($currency) {
		case 'NGN':
			$currency_symbol = 'NGN';
			break;
	}
	return $currency_symbol;
}


add_action(
	'before_woocommerce_init',
	function() {
		if ( class_exists( \Automattic\WooCommerce\Utilities\FeaturesUtil::class ) ) {
			\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', __FILE__, true );
		}
	}
);

/**
 * Registers WooCommerce Blocks integration.
 */
function tbz_wc_squad_gateway_woocommerce_block_support() {
	if ( class_exists( 'Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType' ) ) {
		require_once __DIR__.'/includes/blocks/class-wc-gateway-squad-blocks-support.php';
		
		add_action(
			'woocommerce_blocks_payment_method_type_registration',
			static function( Automattic\WooCommerce\Blocks\Payments\PaymentMethodRegistry $payment_method_registry ) {
				$payment_method_registry->register( new WC_Gateway_Squad_Blocks_Support() );
			}
		);
	}
}
add_action( 'woocommerce_blocks_loaded', 'tbz_wc_squad_gateway_woocommerce_block_support' );
