<?php
/**
 * Meta commande pour la personnalisation.
 *
 * @package LPC\Order
 */

namespace LPC\Order;

use LPC\Cart\Customization;

defined( 'ABSPATH' ) || exit;

/**
 * Class Line_Item_Meta
 */
final class Line_Item_Meta {

	public const ORDER_ITEM_META_KEY = '_lpc_customization';

	public static function register(): void {
		add_action( 'woocommerce_checkout_create_order_line_item', array( __CLASS__, 'save' ), 10, 4 );
	}

	/**
	 * @param \WC_Order_Item_Product $item
	 * @param string                 $cart_item_key
	 * @param array<string, mixed>   $values
	 * @param \WC_Order              $order
	 */
	public static function save( $item, $cart_item_key, $values, $order ): void {
		if ( ! is_a( $item, 'WC_Order_Item_Product' ) ) {
			return;
		}

		$customization = Customization::get_from_cart_item( $values );
		if ( null === $customization ) {
			return;
		}

		$json = wp_json_encode( $customization, JSON_UNESCAPED_UNICODE );
		if ( is_string( $json ) ) {
			$item->add_meta_data( self::ORDER_ITEM_META_KEY, $json, true );
		}

		foreach ( Customization::format_for_display( $customization ) as $row ) {
			$item->add_meta_data( $row['key'], $row['value'], true );
		}

		$addon = isset( $customization['addon_total'] ) ? (float) $customization['addon_total'] : 0.0;
		if ( $addon > 0 ) {
			$item->add_meta_data(
				__( 'Supplément personnalisation', 'leons-product-customizer' ),
				wp_strip_all_tags( wc_price( $addon ) ),
				true
			);
		}
	}
}
