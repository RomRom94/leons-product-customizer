<?php
/**
 * Supplément personnalisation sur le prix ligne panier.
 *
 * @package LPC\Cart
 */

namespace LPC\Cart;

defined( 'ABSPATH' ) || exit;

/**
 * Class Price_Calculator
 */
final class Price_Calculator {

	public static function register(): void {
		add_action( 'woocommerce_before_calculate_totals', array( __CLASS__, 'apply' ), 20, 1 );
	}

	/**
	 * @param \WC_Cart $cart
	 */
	public static function apply( $cart ): void {
		if ( is_admin() && ! defined( 'DOING_AJAX' ) ) {
			return;
		}

		if ( ! $cart instanceof \WC_Cart ) {
			return;
		}

		foreach ( $cart->get_cart() as $cart_item ) {
			$customization = Customization::get_from_cart_item( $cart_item );
			if ( null === $customization ) {
				continue;
			}

			$addon = isset( $customization['addon_total'] ) ? (float) $customization['addon_total'] : 0.0;
			if ( $addon <= 0 ) {
				continue;
			}

			if ( empty( $cart_item['data'] ) || ! is_a( $cart_item['data'], \WC_Product::class ) ) {
				continue;
			}

			$base = isset( $customization['base_price'] )
				? (float) $customization['base_price']
				: (float) $cart_item['data']->get_price( 'edit' );

			$cart_item['data']->set_price( $base + $addon );
		}
	}
}
