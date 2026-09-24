<?php
/**
 * Plugin Name:       Leon's Product Customizer
 * Description:       Personnalisation produit (flocage) : zones, preview canvas, prix additionnels WooCommerce.
 * Version:           1.0.16
 * Requires at least: 6.2
 * Requires PHP:      7.4
 * Author:            Leon Sport
 * Text Domain:       leons-product-customizer
 * Domain Path:       /languages
 *
 * @package LPC
 */

defined( 'ABSPATH' ) || exit;

define( 'LPC_VERSION', '1.0.16' );
define( 'LPC_PLUGIN_FILE', __FILE__ );
define( 'LPC_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'LPC_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

spl_autoload_register(
	static function ( $class ) {
		$prefix = 'LPC\\';
		if ( strpos( $class, $prefix ) !== 0 ) {
			return;
		}
		$rel  = substr( $class, strlen( $prefix ) );
		$path = LPC_PLUGIN_DIR . 'includes/' . str_replace( '\\', '/', $rel ) . '.php';
		if ( is_readable( $path ) ) {
			require $path;
		}
	}
);

if ( is_readable( LPC_PLUGIN_DIR . 'vendor/autoload.php' ) ) {
	require_once LPC_PLUGIN_DIR . 'vendor/autoload.php';
}

require_once LPC_PLUGIN_DIR . 'includes/Plugin.php';

add_action(
	'before_woocommerce_init',
	static function () {
		if ( class_exists( '\Automattic\WooCommerce\Utilities\FeaturesUtil' ) ) {
			\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', LPC_PLUGIN_FILE, true );
			\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'cart_checkout_blocks', LPC_PLUGIN_FILE, true );
		}
	}
);

add_action(
	'woocommerce_loaded',
	static function () {
		LPC\Plugin::instance()->boot();
	},
	5
);

add_action(
	'plugins_loaded',
	static function (): void {
		if ( class_exists( '\\WooCommerce', false ) ) {
			return;
		}
		add_action(
			'admin_notices',
			static function (): void {
				if ( ! current_user_can( 'activate_plugins' ) ) {
					return;
				}
				echo '<div class="notice notice-warning"><p>';
				echo esc_html__( 'Leon\'s Product Customizer nécessite WooCommerce actif.', 'leons-product-customizer' );
				echo '</p></div>';
			}
		);
	},
	999
);
