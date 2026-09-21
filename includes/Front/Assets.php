<?php
/**
 * Scripts et styles front customizer.
 *
 * @package LPC\Front
 */

namespace LPC\Front;

use LPC\Config\Config_Repository;
use WC_Product;

defined( 'ABSPATH' ) || exit;

/**
 * Class Assets
 */
final class Assets {

	public static function register(): void {
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'enqueue' ), 20 );
	}

	public static function enqueue(): void {
		if ( ! is_product() ) {
			return;
		}

		$product_id = (int) get_queried_object_id();
		if ( $product_id <= 0 ) {
			return;
		}

		$product = wc_get_product( $product_id );
		if ( ! $product instanceof WC_Product ) {
			return;
		}

		$config = Config_Repository::get_config_for_product( $product_id );
		if ( null === $config ) {
			return;
		}

		wp_enqueue_style(
			'lpc-customizer',
			LPC_PLUGIN_URL . 'assets/css/customizer.css',
			array(),
			LPC_VERSION
		);

		wp_enqueue_script(
			'lpc-customizer',
			LPC_PLUGIN_URL . 'assets/js/customizer.js',
			array( 'jquery' ),
			LPC_VERSION,
			true
		);

		wp_localize_script(
			'lpc-customizer',
			'lpcCustomizer',
			array(
				'productId'      => (int) $product->get_id(),
				'config'         => $config,
				'formattedZero'  => wp_strip_all_tags( wc_price( 0 ) ),
				'currencySymbol' => get_woocommerce_currency_symbol(),
				'decimalSep'     => wc_get_price_decimal_separator(),
				'thousandSep'    => wc_get_price_thousand_separator(),
				'decimals'       => wc_get_price_decimals(),
				'i18n'           => array(
					'addonsTotal' => __( 'Personnalisation', 'leons-product-customizer' ),
				),
			)
		);
	}
}
