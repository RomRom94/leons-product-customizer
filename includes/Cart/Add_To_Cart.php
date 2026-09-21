<?php
/**
 * Données personnalisation à l’ajout panier.
 *
 * @package LPC\Cart
 */

namespace LPC\Cart;

use LPC\Config\Config_Repository;
use WC_Product;

defined( 'ABSPATH' ) || exit;

/**
 * Class Add_To_Cart
 */
final class Add_To_Cart {

	public static function register(): void {
		add_filter( 'woocommerce_add_to_cart_validation', array( __CLASS__, 'validate' ), 10, 4 );
		add_filter( 'woocommerce_add_cart_item_data', array( __CLASS__, 'add_cart_item_data' ), 10, 4 );
		add_filter( 'woocommerce_get_cart_item_from_session', array( __CLASS__, 'get_cart_item_from_session' ), 10, 3 );
	}

	/**
	 * @param bool $passed
	 * @param int  $product_id
	 * @param int  $quantity
	 * @param int  $variation_id
	 */
	public static function validate( $passed, $product_id, $quantity, $variation_id = 0 ) {
		if ( ! $passed ) {
			return false;
		}

		$parent_id = (int) $product_id;
		$config    = Config_Repository::get_config_for_product( $parent_id );
		if ( null === $config ) {
			return $passed;
		}

		$raw   = isset( $_POST['lpc_zones'] ) ? wp_unslash( $_POST['lpc_zones'] ) : array(); // phpcs:ignore WordPress.Security.NonceVerification.Missing
		$files = isset( $_FILES['lpc_files'] ) && is_array( $_FILES['lpc_files'] ) ? $_FILES['lpc_files'] : array(); // phpcs:ignore WordPress.Security.NonceVerification.Missing
		if ( null === Customization::parse_from_request( $config, is_array( $raw ) ? $raw : array(), $files, false ) ) {
			return false;
		}

		return $passed;
	}

	/**
	 * @param array<string, mixed> $cart_item_data
	 * @param int                  $product_id
	 * @param int                  $variation_id
	 * @return array<string, mixed>
	 */
	public static function add_cart_item_data( $cart_item_data, $product_id, $variation_id, $quantity ) {
		$parent_id = (int) $product_id;
		$config    = Config_Repository::get_config_for_product( $parent_id );
		if ( null === $config ) {
			return $cart_item_data;
		}

		$raw    = isset( $_POST['lpc_zones'] ) ? wp_unslash( $_POST['lpc_zones'] ) : array(); // phpcs:ignore WordPress.Security.NonceVerification.Missing
		$files  = isset( $_FILES['lpc_files'] ) && is_array( $_FILES['lpc_files'] ) ? $_FILES['lpc_files'] : array(); // phpcs:ignore WordPress.Security.NonceVerification.Missing
		$parsed = Customization::parse_from_request( $config, is_array( $raw ) ? $raw : array(), $files, true );
		if ( null === $parsed ) {
			return $cart_item_data;
		}

		$line_product_id = $variation_id > 0 ? (int) $variation_id : $parent_id;
		$product         = wc_get_product( $line_product_id );
		if ( $product instanceof WC_Product ) {
			$parsed['base_price'] = (float) $product->get_price( 'edit' );
		}

		$cart_item_data[ Customization::CART_ITEM_KEY ] = $parsed;

		return $cart_item_data;
	}

	/**
	 * @param array<string, mixed> $cart_item
	 * @param array<string, mixed> $values
	 * @param string               $cart_item_key
	 * @return array<string, mixed>
	 */
	public static function get_cart_item_from_session( $cart_item, $values, $cart_item_key ) {
		if ( isset( $values[ Customization::CART_ITEM_KEY ] ) && is_array( $values[ Customization::CART_ITEM_KEY ] ) ) {
			$cart_item[ Customization::CART_ITEM_KEY ] = $values[ Customization::CART_ITEM_KEY ];
		}

		return $cart_item;
	}
}
