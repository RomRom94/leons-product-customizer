<?php
/**
 * Point d'entrée plugin.
 *
 * @package LPC
 */

namespace LPC;

defined( 'ABSPATH' ) || exit;

/**
 * Class Plugin
 */
final class Plugin {

	private static ?Plugin $instance = null;

	public static function instance(): Plugin {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	public function boot(): void {
		Cart\Add_To_Cart::register();
		Cart\Price_Calculator::register();
		Cart\Display::register();
		Order\Line_Item_Meta::register();

		if ( is_admin() ) {
			Admin\Product_Data_Tab::instance()->register();
			Admin\Variation_Back_Image::register();
		} else {
			Front\Assets::register();
			Front\Variation_Data::register();
		}
	}
}
