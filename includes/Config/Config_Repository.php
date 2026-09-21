<?php
/**
 * Lecture config customizer (defaults + meta produit).
 *
 * @package LPC\Config
 */

namespace LPC\Config;

use LPC\Meta_Keys;
use WC_Product;
use WP_Term;

defined( 'ABSPATH' ) || exit;

/**
 * Class Config_Repository
 */
final class Config_Repository {

	public const DEFAULT_TEMPLATE = 'maillot';

	/**
	 * @return array<string, mixed>|null
	 */
	public static function get_default_config( string $template = self::DEFAULT_TEMPLATE ): ?array {
		$template = sanitize_file_name( $template );
		if ( $template === '' ) {
			return null;
		}

		$path = LPC_PLUGIN_DIR . 'config/defaults/' . $template . '.json';
		if ( ! is_readable( $path ) ) {
			return null;
		}

		$raw     = file_get_contents( $path ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
		$decoded = Config_Validator::decode_json( is_string( $raw ) ? $raw : '' );
		if ( null === $decoded || ! Config_Validator::validate( $decoded ) ) {
			return null;
		}

		return $decoded;
	}

	/**
	 * @return array<string, mixed>|null
	 */
	public static function get_default_config_json( string $template = self::DEFAULT_TEMPLATE ): ?string {
		$config = self::get_default_config( $template );
		if ( null === $config ) {
			return null;
		}

		$json = wp_json_encode( $config, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES );
		return is_string( $json ) ? $json : null;
	}

	public static function is_enabled( WC_Product $product ): bool {
		return 'yes' === $product->get_meta( Meta_Keys::ENABLED, true );
	}

	public static function is_enabled_for_product_id( int $product_id ): bool {
		if ( $product_id <= 0 ) {
			return false;
		}
		$product = wc_get_product( $product_id );
		return $product instanceof WC_Product && self::is_enabled( $product );
	}

	/**
	 * Config effective pour le front (meta produit ou fallback default).
	 *
	 * @return array<string, mixed>|null
	 */
	public static function get_config_for_product( int $product_id ): ?array {
		if ( $product_id <= 0 ) {
			return null;
		}

		$product = wc_get_product( $product_id );
		if ( ! $product instanceof WC_Product || ! self::is_enabled( $product ) ) {
			return null;
		}

		$raw = $product->get_meta( Meta_Keys::CONFIG, true );
		if ( is_string( $raw ) && trim( $raw ) !== '' ) {
			$decoded = Config_Validator::decode_json( $raw );
			if ( null !== $decoded && Config_Validator::validate( $decoded ) ) {
				return $decoded;
			}
		}

		return self::get_default_config( self::get_template_for_product( $product ) );
	}

	/**
	 * Résout le slug de template par défaut.
	 *
	 * Priorité au slug du produit lui-même (permet de distinguer deux produits qui
	 * partagent une même catégorie WooCommerce, ex. "sac-classic" / "sac-a-dos" sous
	 * "Bagageries") ; sinon on retombe sur la première catégorie WC du produit qui
	 * correspond à un fichier config/defaults/{slug}.json.
	 */
	public static function get_template_for_product( WC_Product $product ): string {
		$product_slug = sanitize_file_name( $product->get_slug() );
		if ( '' !== $product_slug && is_readable( LPC_PLUGIN_DIR . 'config/defaults/' . $product_slug . '.json' ) ) {
			return $product_slug;
		}

		foreach ( $product->get_category_ids() as $term_id ) {
			$term = get_term( (int) $term_id, 'product_cat' );
			if ( ! $term instanceof WP_Term ) {
				continue;
			}
			$slug = sanitize_file_name( $term->slug );
			if ( is_readable( LPC_PLUGIN_DIR . 'config/defaults/' . $slug . '.json' ) ) {
				return $slug;
			}
		}
		return self::DEFAULT_TEMPLATE;
	}

	/**
	 * Valeur brute pour l'éditeur admin (meta ou vide).
	 */
	public static function get_editor_json( int $product_id ): string {
		if ( $product_id <= 0 ) {
			return '';
		}

		$raw = get_post_meta( $product_id, Meta_Keys::CONFIG, true );
		if ( ! is_string( $raw ) || trim( $raw ) === '' ) {
			return '';
		}

		$decoded = Config_Validator::decode_json( $raw );
		if ( null === $decoded ) {
			return $raw;
		}

		$pretty = wp_json_encode( $decoded, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES );
		return is_string( $pretty ) ? $pretty : $raw;
	}
}
