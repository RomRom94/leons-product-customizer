<?php
/**
 * Affichage du customizer sur la fiche produit.
 *
 * @package LPC\Front
 */

namespace LPC\Front;

use LPC\Config\Config_Repository;
use WC_Product;

defined( 'ABSPATH' ) || exit;

/**
 * Class Template_Loader
 *
 * `render()` est appelé explicitement par le thème
 * (content-single-product.php), en pleine largeur sous la galerie/résumé —
 * plus via le hook `woocommerce_before_add_to_cart_button` (ce qui le
 * confinait dans la colonne résumé). Ses champs restent malgré tout liés au
 * <form> "Ajouter au panier" via l'attribut HTML `form` posé en JS
 * (assets/js/customizer.js), donc la position dans le DOM n'a pas
 * d'incidence sur la soumission.
 */
final class Template_Loader {

	public static function render(): void {
		if ( ! is_product() ) {
			return;
		}

		global $product;
		if ( ! $product instanceof WC_Product ) {
			return;
		}

		$config = Config_Repository::get_config_for_product( (int) $product->get_id() );
		if ( null === $config ) {
			return;
		}

		$placeholder_src = self::get_placeholder_image_src( $product );

		include LPC_PLUGIN_DIR . 'templates/product-customizer.php';
	}

	public static function get_placeholder_image_src( WC_Product $product ): string {
		$image_id = $product->get_image_id();
		if ( $image_id ) {
			$src = wp_get_attachment_image_url( $image_id, 'woocommerce_single' );
			if ( is_string( $src ) && $src !== '' ) {
				return $src;
			}
		}

		return wc_placeholder_img_src( 'woocommerce_single' );
	}
}
