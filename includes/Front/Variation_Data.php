<?php
/**
 * Données variation pour le customizer (image dos).
 *
 * @package LPC\Front
 */

namespace LPC\Front;

use LPC\Config\Config_Repository;
use LPC\Meta_Keys;
use WC_Product;
use WC_Product_Variation;

defined( 'ABSPATH' ) || exit;

/**
 * Class Variation_Data
 */
final class Variation_Data {

	public static function register(): void {
		add_filter( 'woocommerce_available_variation', array( __CLASS__, 'filter_available_variation' ), 10, 3 );
	}

	/**
	 * @param array<string, mixed> $data
	 * @return array<string, mixed>
	 */
	public static function filter_available_variation( array $data, $product, $variation ): array {
		if ( ! $product instanceof WC_Product || ! $variation instanceof WC_Product_Variation ) {
			return $data;
		}

		if ( ! Config_Repository::is_enabled( $product ) ) {
			return $data;
		}

		$back_image_id = absint( $variation->get_meta( Meta_Keys::VARIATION_BACK_IMAGE_ID, true ) );
		if ( $back_image_id <= 0 ) {
			return $data;
		}

		$image_props = wc_get_product_attachment_props( $back_image_id, $product );
		if ( ! is_array( $image_props ) || empty( $image_props['src'] ) ) {
			return $data;
		}

		$data['lpc_back_image']      = $image_props;
		$data['lpc_back_image_src']  = $image_props['src'];
		$data['leons_dos_image_src'] = $image_props['src'];

		return $data;
	}
}
