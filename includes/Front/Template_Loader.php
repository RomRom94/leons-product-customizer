<?php
/**
 * Affichage du customizer sur la fiche produit.
 *
 * @package LPC\Front
 */

namespace LPC\Front;

use LPC\Config\Config_Repository;
use LPC\Personalization\View_Images;
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

		// Aucune couleur n'a toutes ses images de personnalisation : pas de bloc.
		$view_images = View_Images::get_front_data( $product, $config );
		if ( ! $view_images['colors'] ) {
			return;
		}

		$initial_images = $view_images['colors'][ $view_images['initialColor'] ];

		include LPC_PLUGIN_DIR . 'templates/product-customizer.php';
	}
}
