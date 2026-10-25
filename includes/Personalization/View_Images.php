<?php
/**
 * Images de personnalisation par couleur et par vue (grille produit).
 *
 * Les images sur lesquelles le canvas dessine ne sont plus l'image principale /
 * la galerie du produit : chaque produit porte une grille couleur × vue
 * (meta `_lpc_view_images`). Une couleur dont une vue n'a pas d'image n'est
 * pas personnalisable.
 *
 * @package LPC\Personalization
 */

namespace LPC\Personalization;

use LPC\Config\Config_Repository;
use LPC\Meta_Keys;
use WC_Product;
use WC_Product_Attribute;
use WC_Product_Variation;
use WP_Term;

defined( 'ABSPATH' ) || exit;

/**
 * Class View_Images
 */
final class View_Images {

	/** Clé de ligne pour un produit sans attribut couleur variable. */
	public const ANY_COLOR = '_all';

	public static function get_color_taxonomy(): string {
		return (string) apply_filters( 'lpc_color_taxonomy', 'pa_couleur' );
	}

	/**
	 * Couleurs proposées en variation, dans l'ordre de l'attribut produit.
	 *
	 * @return array<string, string> slug => libellé
	 */
	public static function get_colors( WC_Product $product ): array {
		$taxonomy   = self::get_color_taxonomy();
		$attributes = $product->get_attributes();
		$attribute  = $attributes[ $taxonomy ] ?? null;

		if ( ! $product->is_type( 'variable' ) || ! $attribute instanceof WC_Product_Attribute || ! $attribute->get_variation() ) {
			return array( self::ANY_COLOR => __( 'Toutes couleurs', 'leons-product-customizer' ) );
		}

		$colors = array();
		foreach ( $attribute->get_terms() ?: array() as $term ) {
			if ( $term instanceof WP_Term ) {
				$colors[ $term->slug ] = $term->name;
			}
		}

		return $colors ?: array( self::ANY_COLOR => __( 'Toutes couleurs', 'leons-product-customizer' ) );
	}

	/**
	 * @param array<string, mixed> $config
	 * @return array<string, string> clé de vue => libellé
	 */
	public static function get_views( array $config ): array {
		// Vues qui portent au moins une zone : une vue « dos » sans zone (sous-maillot, short, veste de
		// training…) n'exige pas d'image et n'est pas affichée. Sans aucune zone déclarée, toutes les vues.
		$used = array();
		foreach ( (array) ( $config['zones'] ?? array() ) as $zone ) {
			if ( is_array( $zone ) && isset( $zone['view'] ) ) {
				$used[ (string) $zone['view'] ] = true;
			}
		}

		$views = array();
		foreach ( (array) ( $config['views'] ?? array() ) as $key => $view ) {
			if ( $used && ! isset( $used[ (string) $key ] ) ) {
				continue;
			}
			$views[ (string) $key ] = is_array( $view ) && isset( $view['label'] ) ? (string) $view['label'] : (string) $key;
		}
		return $views;
	}

	/**
	 * Grille brute enregistrée sur le produit.
	 *
	 * @return array<string, array<string, int>> couleur => ( vue => attachment ID )
	 */
	public static function get_stored_map( int $product_id ): array {
		$raw = get_post_meta( $product_id, Meta_Keys::VIEW_IMAGES, true );
		return self::sanitize_map( is_array( $raw ) ? $raw : array() );
	}

	/**
	 * Grille effective (filtrable, ex. surcharge partenaire côté thème).
	 *
	 * @return array<string, array<string, int>>
	 */
	public static function get_map( int $product_id ): array {
		$map = apply_filters( 'lpc_view_images_map', self::get_stored_map( $product_id ), $product_id );
		return self::sanitize_map( is_array( $map ) ? $map : array() );
	}

	/**
	 * @param array<mixed, mixed> $raw
	 * @return array<string, array<string, int>>
	 */
	public static function sanitize_map( array $raw ): array {
		$map = array();
		foreach ( $raw as $color => $views ) {
			if ( ! is_array( $views ) ) {
				continue;
			}
			$color = sanitize_title( (string) $color );
			if ( '' === $color ) {
				continue;
			}
			foreach ( $views as $view => $attachment_id ) {
				$attachment_id = absint( $attachment_id );
				if ( $attachment_id > 0 ) {
					$map[ $color ][ sanitize_key( (string) $view ) ] = $attachment_id;
				}
			}
		}
		return $map;
	}

	/**
	 * Couleurs dont toutes les vues de la config ont une image.
	 *
	 * @return array<string, array<string, int>> couleur => ( vue => attachment ID )
	 */
	public static function get_complete_colors( WC_Product $product, array $config ): array {
		$views = array_keys( self::get_views( $config ) );
		if ( ! $views ) {
			return array();
		}

		$map      = self::get_map( $product->get_id() );
		$complete = array();
		foreach ( array_keys( self::get_colors( $product ) ) as $color ) {
			$row = array();
			foreach ( $views as $view ) {
				$attachment_id = $map[ $color ][ $view ] ?? 0;
				if ( $attachment_id <= 0 || ! wp_attachment_is_image( $attachment_id ) ) {
					continue 2;
				}
				$row[ $view ] = $attachment_id;
			}
			$complete[ $color ] = $row;
		}
		return $complete;
	}

	/**
	 * Personnalisation affichée sur la fiche : config active et au moins une couleur complète.
	 */
	public static function is_available( WC_Product $product ): bool {
		$config = Config_Repository::get_config_for_product( $product->get_id() );
		return null !== $config && array() !== self::get_complete_colors( $product, $config );
	}

	/**
	 * Couleur d'une ligne panier : celle de la variation, ou celle postée quand la
	 * variation accepte « toutes couleurs ».
	 */
	public static function get_color_for_variation( int $variation_id ): string {
		$variation = $variation_id > 0 ? wc_get_product( $variation_id ) : null;
		if ( ! $variation instanceof WC_Product_Variation ) {
			return self::ANY_COLOR;
		}

		$key   = 'attribute_' . self::get_color_taxonomy();
		$attrs = $variation->get_variation_attributes();
		if ( ! array_key_exists( $key, $attrs ) ) {
			return self::ANY_COLOR;
		}

		$color = (string) $attrs[ $key ];
		if ( '' === $color && isset( $_POST[ $key ] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
			$color = sanitize_title( wp_unslash( (string) $_POST[ $key ] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Missing
		}
		return $color;
	}

	/**
	 * La couleur choisie peut-elle être personnalisée ?
	 *
	 * @param array<string, mixed> $config
	 */
	public static function is_color_available( WC_Product $product, array $config, string $color ): bool {
		return isset( self::get_complete_colors( $product, $config )[ $color ] );
	}

	/**
	 * Données front : images de chaque couleur complète + couleur affichée au chargement.
	 *
	 * @param array<string, mixed> $config
	 * @return array{attribute: string, initialColor: string, colors: array<string, array<string, array<string, string>>>}
	 */
	public static function get_front_data( WC_Product $product, array $config ): array {
		$colors = array();
		foreach ( self::get_complete_colors( $product, $config ) as $color => $row ) {
			foreach ( $row as $view => $attachment_id ) {
				$colors[ $color ][ $view ] = array(
					'src'    => (string) wp_get_attachment_image_url( $attachment_id, 'woocommerce_single' ),
					'srcset' => (string) wp_get_attachment_image_srcset( $attachment_id, 'woocommerce_single' ),
					'sizes'  => (string) wp_get_attachment_image_sizes( $attachment_id, 'woocommerce_single' ),
				);
			}
		}

		// Couleur par défaut de la fiche si elle est complète, sinon la première complète.
		$default = (string) ( $product->get_default_attributes()[ self::get_color_taxonomy() ] ?? '' );
		$initial = isset( $colors[ $default ] ) ? $default : (string) array_key_first( $colors );

		return array(
			'attribute'    => 'attribute_' . self::get_color_taxonomy(),
			'initialColor' => $initial,
			'colors'       => $colors,
		);
	}
}
