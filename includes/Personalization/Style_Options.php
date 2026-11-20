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

	/**
	 * Couleur présélectionnée : `default_color` de la config produit (ex. blanc sur un vêtement sombre).
	 *
	 * @param array<string, mixed> $config
	 */
	public static function get_default_color( array $config ): string {
		$color = isset( $config['default_color'] ) ? (string) $config['default_color'] : '';
		return self::is_valid_color( $color ) ? $color : self::DEFAULT_COLOR;
	}

	public static function get_color_hex( string $slug ): string {
		return self::is_valid_color( $slug ) ? self::COLORS[ $slug ]['hex'] : self::COLORS[ self::DEFAULT_COLOR ]['hex'];
	}

	/*
	 * Faces de flocage : police et couleur se choisissent par face (règles client du 20/11/2026).
	 * L'avant couvre aussi les pièces sans dos (short, pantalon d'un ensemble) : un produit sans
	 * flocage au dos n'a donc qu'un seul choix.
	 */
	public const FACE_FRONT = 'front';
	public const FACE_BACK  = 'back';

	/**
	 * Champs POST par face (police, couleur).
	 *
	 * @var array<string, array{font: string, color: string}>
	 */
	public const FACE_FIELDS = array(
		self::FACE_FRONT => array(
			'font'  => 'lpc_font',
			'color' => 'lpc_color',
		),
		self::FACE_BACK  => array(
			'font'  => 'lpc_font_back',
			'color' => 'lpc_color_back',
		),
	);

	/**
	 * Face d'une zone : `face` explicite, sinon déduite de la vue (`back` = dos, tout le reste = avant).
	 *
	 * @param array<string, mixed> $zone
	 */
	public static function get_zone_face( array $zone ): string {
		$face = isset( $zone['face'] ) ? (string) $zone['face'] : '';
		if ( self::FACE_BACK === $face || self::FACE_FRONT === $face ) {
			return $face;
		}
		return isset( $zone['view'] ) && 'back' === $zone['view'] ? self::FACE_BACK : self::FACE_FRONT;
	}

	/**
	 * Faces qui portent au moins une zone texte, dans l'ordre avant → dos.
	 *
	 * @param array<string, mixed> $config
	 * @return string[]
	 */
	public static function get_text_faces( array $config ): array {
		$faces = array();
		foreach ( (array) ( $config['zones'] ?? array() ) as $zone ) {
			if ( is_array( $zone ) && 'text' === ( $zone['type'] ?? '' ) ) {
				$faces[ self::get_zone_face( $zone ) ] = true;
			}
		}
		return array_values( array_filter( array( self::FACE_FRONT, self::FACE_BACK ), static fn( $f ) => isset( $faces[ $f ] ) ) );
	}
}
