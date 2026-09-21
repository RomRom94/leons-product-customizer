<?php
/**
 * Validation de la config JSON customizer.
 *
 * @package LPC\Config
 */

namespace LPC\Config;

defined( 'ABSPATH' ) || exit;

/**
 * Class Config_Validator
 */
final class Config_Validator {

	/**
	 * @param array<string, mixed> $config
	 */
	public static function validate( array $config ): bool {
		if ( ! isset( $config['zones'] ) || ! is_array( $config['zones'] ) ) {
			return false;
		}

		foreach ( $config['zones'] as $zone_id => $zone ) {
			if ( ! is_string( $zone_id ) || $zone_id === '' ) {
				return false;
			}
			if ( ! is_array( $zone ) ) {
				return false;
			}
			if ( empty( $zone['label'] ) || ! is_string( $zone['label'] ) ) {
				return false;
			}
			if ( empty( $zone['type'] ) || ! is_string( $zone['type'] ) ) {
				return false;
			}
			if ( ! in_array( $zone['type'], array( 'text', 'image' ), true ) ) {
				return false;
			}
			if ( isset( $zone['price'] ) && ! is_numeric( $zone['price'] ) ) {
				return false;
			}
		}

		if ( isset( $config['views'] ) && ! is_array( $config['views'] ) ) {
			return false;
		}

		return true;
	}

	/**
	 * @return array<string, mixed>|null
	 */
	public static function decode_json( string $raw ): ?array {
		$raw = trim( $raw );
		if ( $raw === '' ) {
			return null;
		}

		$decoded = json_decode( $raw, true );
		if ( ! is_array( $decoded ) ) {
			return null;
		}

		return $decoded;
	}
}
