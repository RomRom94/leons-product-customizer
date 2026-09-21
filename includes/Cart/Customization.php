<?php
/**
 * Parse et validation des zones personnalisation (panier).
 *
 * @package LPC\Cart
 */

namespace LPC\Cart;

use LPC\Personalization\Style_Options;

defined( 'ABSPATH' ) || exit;

/**
 * Class Customization
 */
final class Customization {

	public const CART_ITEM_KEY = 'lpc_customization';

	/**
	 * @param array<string, mixed>      $config
	 * @param array<string, mixed>|null $raw_zones
	 * @param array<string, mixed>      $raw_files  $_FILES['lpc_files'] brut (avant normalisation)
	 * @param bool                      $do_upload  false = validation seule, sans écriture disque
	 * @return array<string, mixed>|null
	 */
	public static function parse_from_request( array $config, $raw_zones, array $raw_files = array(), bool $do_upload = true ): ?array {
		$zones_config = isset( $config['zones'] ) && is_array( $config['zones'] ) ? $config['zones'] : array();
		if ( ! $zones_config ) {
			return null;
		}

		$raw_zones = is_array( $raw_zones ) ? $raw_zones : array();
		$files     = Sponsor_Upload::normalize( $raw_files );
		$values    = array();
		$labels    = array();
		$total     = 0.0;
		$has_text  = false;

		foreach ( $zones_config as $zone_id => $zone ) {
			if ( ! is_string( $zone_id ) || $zone_id === '' || ! is_array( $zone ) ) {
				continue;
			}

			$zone_id  = sanitize_key( $zone_id );
			$type     = isset( $zone['type'] ) ? (string) $zone['type'] : '';
			$label    = isset( $zone['label'] ) ? (string) $zone['label'] : $zone_id;
			$required = ! empty( $zone['required'] );
			$price    = isset( $zone['price'] ) ? (float) $zone['price'] : 0.0;

			if ( 'text' === $type ) {
				$max_len   = isset( $zone['max_length'] ) ? absint( $zone['max_length'] ) : 0;
				$pattern   = isset( $zone['pattern'] ) ? (string) $zone['pattern'] : '';
				$raw_value = isset( $raw_zones[ $zone_id ] ) ? (string) wp_unslash( $raw_zones[ $zone_id ] ) : '';
				$value     = sanitize_text_field( trim( $raw_value ) );

				if ( $value === '' ) {
					if ( $required ) {
						/* translators: %s: zone label */
						wc_add_notice( sprintf( __( 'Le champ « %s » est obligatoire.', 'leons-product-customizer' ), esc_html( $label ) ), 'error' );
						return null;
					}
					continue;
				}

				if ( $max_len > 0 && mb_strlen( $value ) > $max_len ) {
					/* translators: %1$s: zone label, %2$d: max length */
					wc_add_notice(
						sprintf( __( '« %1$s » ne doit pas dépasser %2$d caractères.', 'leons-product-customizer' ), esc_html( $label ), $max_len ),
						'error'
					);
					return null;
				}

				if ( $pattern !== '' && ! preg_match( '/^' . $pattern . '$/u', $value ) ) {
					/* translators: %s: zone label */
					wc_add_notice( sprintf( __( 'La valeur de « %s » n’est pas valide.', 'leons-product-customizer' ), esc_html( $label ) ), 'error' );
					return null;
				}

				$values[ $zone_id ] = $value;
				$labels[ $zone_id ] = $label;
				$has_text           = true;

				if ( $price > 0 ) {
					$total += $price;
				}
				continue;
			}

			if ( 'image' === $type ) {
				$file = $files[ $zone_id ] ?? null;
				if ( null === $file || ! Sponsor_Upload::has_file( $file ) ) {
					if ( $required ) {
						/* translators: %s: zone label */
						wc_add_notice( sprintf( __( 'Le fichier « %s » est obligatoire.', 'leons-product-customizer' ), esc_html( $label ) ), 'error' );
						return null;
					}
					continue;
				}

				$validation_error = Sponsor_Upload::validate( $file );
				if ( null !== $validation_error ) {
					wc_add_notice( $validation_error, 'error' );
					return null;
				}

				if ( $do_upload ) {
					$attachment_id = Sponsor_Upload::process( $file, $zone_id );
					if ( is_wp_error( $attachment_id ) ) {
						wc_add_notice( $attachment_id->get_error_message(), 'error' );
						return null;
					}
					$values[ $zone_id ] = array(
						'attachment_id' => $attachment_id,
						'url'           => wp_get_attachment_url( $attachment_id ),
						'filename'      => sanitize_file_name( wp_basename( (string) $file['name'] ) ),
					);
				} else {
					$values[ $zone_id ] = array( 'filename' => sanitize_file_name( wp_basename( (string) $file['name'] ) ) );
				}
				$labels[ $zone_id ] = $label;

				if ( $price > 0 ) {
					$total += $price;
				}
			}
		}

		$result = array(
			'zones'       => $values,
			'labels'      => $labels,
			'addon_total' => round( $total, wc_get_price_decimals() ),
		);

		if ( $has_text ) {
			$font  = isset( $_POST['lpc_font'] ) ? sanitize_text_field( wp_unslash( $_POST['lpc_font'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing
			$color = isset( $_POST['lpc_color'] ) ? sanitize_key( wp_unslash( $_POST['lpc_color'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing

			if ( ! Style_Options::is_valid_font( $font ) ) {
				$font = Style_Options::DEFAULT_FONT;
			}
			if ( ! Style_Options::is_valid_color( $color ) ) {
				$color = Style_Options::DEFAULT_COLOR;
			}

			$result['font']        = $font;
			$result['font_label']  = Style_Options::FONTS[ $font ];
			$result['color']       = $color;
			$result['color_label'] = Style_Options::COLORS[ $color ]['label'];
			$result['color_hex']   = Style_Options::get_color_hex( $color );
		}

		return $result;
	}

	/**
	 * @param array<string, mixed> $cart_item
	 */
	public static function get_from_cart_item( array $cart_item ): ?array {
		if ( empty( $cart_item[ self::CART_ITEM_KEY ] ) || ! is_array( $cart_item[ self::CART_ITEM_KEY ] ) ) {
			return null;
		}

		return $cart_item[ self::CART_ITEM_KEY ];
	}

	/**
	 * @param array<string, mixed> $customization
	 * @return array<int, array<string, string>>
	 */
	public static function format_for_display( array $customization ): array {
		$zones  = isset( $customization['zones'] ) && is_array( $customization['zones'] ) ? $customization['zones'] : array();
		$labels = isset( $customization['labels'] ) && is_array( $customization['labels'] ) ? $customization['labels'] : array();
		$rows   = array();

		$has_text_row = false;

		foreach ( $zones as $zone_id => $value ) {
			if ( ! is_string( $zone_id ) ) {
				continue;
			}

			if ( is_array( $value ) ) {
				if ( empty( $value['filename'] ) ) {
					continue;
				}
				$rows[] = array(
					'key'   => isset( $labels[ $zone_id ] ) ? (string) $labels[ $zone_id ] : $zone_id,
					'value' => (string) $value['filename'],
				);
				continue;
			}

			if ( $value === '' ) {
				continue;
			}
			$rows[] = array(
				'key'   => isset( $labels[ $zone_id ] ) ? (string) $labels[ $zone_id ] : $zone_id,
				'value' => (string) $value,
			);
			$has_text_row = true;
		}

		if ( $has_text_row && isset( $customization['font_label'], $customization['color_label'] ) ) {
			$rows[] = array(
				'key'   => __( 'Police', 'leons-product-customizer' ),
				'value' => (string) $customization['font_label'],
			);
			$rows[] = array(
				'key'   => __( 'Couleur', 'leons-product-customizer' ),
				'value' => (string) $customization['color_label'],
			);
		}

		return $rows;
	}
}
