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

	/** ID attachment image dos pour une variation (meta variation). */
	public const VARIATION_BACK_IMAGE_ID = '_lpc_back_image_id';
}
