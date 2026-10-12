<?php
/**
 * Métaclés plugin.
 *
 * @package LPC
 */

namespace LPC;

defined( 'ABSPATH' ) || exit;

/**
 * Class Meta_Keys
 */
final class Meta_Keys {

	/** Active le customizer sur le produit parent. */
	public const ENABLED = '_lpc_enabled';

	/** Config JSON des zones / vues (string). */
	public const CONFIG = '_lpc_config';

	/**
	 * Ancienne image dos par variation (meta variation) — remplacée par VIEW_IMAGES,
	 * lue uniquement par la reprise de données (Admin\View_Images_Grid::migrate_legacy).
	 */
	public const VARIATION_BACK_IMAGE_ID = '_lpc_back_image_id';

	/** Grille des images de personnalisation : array( couleur => array( vue => attachment ID ) ). */
	public const VIEW_IMAGES = '_lpc_view_images';
}
