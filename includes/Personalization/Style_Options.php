<?php
/**
 * Options globales de style (police, couleur) pour la personnalisation texte.
 *
 * @package LPC\Personalization
 */

namespace LPC\Personalization;

defined( 'ABSPATH' ) || exit;

/**
 * Class Style_Options
 */
final class Style_Options {

	public const DEFAULT_FONT  = 'Slicker';
	public const DEFAULT_COLOR = 'noir';

	/**
	 * Clé = font-family exact déclaré dans src/fonts/fonts.css du thème.
	 *
	 * @var array<string, string>
	 */
	public const FONTS = array(
		'Slicker' => 'Slicker',
		'PaybAck' => 'Payback',
	);

	/**
	 * @var array<string, array{label: string, hex: string}>
	 */
	public const COLORS = array(
		'noir'  => array(
			'label' => 'Noir',
			'hex'   => '#000000',
		),
		'blanc' => array(
			'label' => 'Blanc',
			'hex'   => '#FFFFFF',
		),
		'or'    => array(
			'label' => 'Or',
			'hex'   => '#D4AF37',
		),
		'bleu'  => array(
			'label' => 'Bleu',
			'hex'   => '#0055A4',
		),
		'jaune' => array(
			'label' => 'Jaune',
			'hex'   => '#FFD700',
		),
		'rouge' => array(
			'label' => 'Rouge',
			'hex'   => '#E30613',
		),
		'rose'  => array(
			'label' => 'Rose',
			'hex'   => '#E75480',
		),
		'vert'  => array(
			'label' => 'Vert',
			'hex'   => '#009640',
		),
	);

	public static function is_valid_font( string $font ): bool {
		return isset( self::FONTS[ $font ] );
	}

	public static function is_valid_color( string $slug ): bool {
		return isset( self::COLORS[ $slug ] );
	}

	public static function get_color_hex( string $slug ): string {
		return self::is_valid_color( $slug ) ? self::COLORS[ $slug ]['hex'] : self::COLORS[ self::DEFAULT_COLOR ]['hex'];
	}
}
