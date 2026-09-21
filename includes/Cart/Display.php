<?php
/**
 * Affichage personnalisation dans le panier / checkout.
 *
 * @package LPC\Cart
 */

namespace LPC\Cart;

defined( 'ABSPATH' ) || exit;

/**
 * Class Display
 */
final class Display {

	public static function register(): void {
		add_filter( 'woocommerce_get_item_data', array( __CLASS__, 'get_item_data' ), 10, 2 );
	}

	/**
	 * @param array<int, array<string, string>> $item_data
	 * @param array<string, mixed>              $cart_item
	 * @return array<int, array<string, string>>
	 */
	public static function get_item_data( $item_data, $cart_item ) {
		$customization = Customization::get_from_cart_item( $cart_item );
		if ( null === $customization ) {
			return $item_data;
		}

		foreach ( Customization::format_for_display( $customization ) as $row ) {
			$item_data[] = array(
				'key'   => $row['key'],
				'value' => wc_clean( $row['value'] ),
			);
		}

		return $item_data;
	}
}
