<?php
/**
 * Onglet produit : activation et config JSON du customizer.
 *
 * @package LPC\Admin
 */

namespace LPC\Admin;

use LPC\Config\Config_Repository;
use LPC\Config\Config_Validator;
use LPC\Meta_Keys;
use WC_Admin_Meta_Boxes;
use WC_Product;

defined( 'ABSPATH' ) || exit;

/**
 * Class Product_Data_Tab
 */
final class Product_Data_Tab {

	private static ?self $inst = null;

	public static function instance(): self {
		if ( null === self::$inst ) {
			self::$inst = new self();
		}
		return self::$inst;
	}

	public function register(): void {
		add_filter( 'woocommerce_product_data_tabs', array( $this, 'add_tab' ) );
		add_action( 'woocommerce_product_data_panels', array( $this, 'render_panel' ) );
		add_action( 'woocommerce_admin_process_product_object', array( $this, 'persist' ), 15, 1 );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_scripts' ) );
	}

	/**
	 * @param array<string, mixed> $tabs
	 * @return array<string, mixed>
	 */
	public function add_tab( array $tabs ): array {
		$tabs['lpc_customizer'] = array(
			'label'    => esc_html__( 'Personnalisation', 'leons-product-customizer' ),
			'target'   => 'lpc_customizer_panel',
			'priority' => 93,
			'class'    => array(),
		);
		return $tabs;
	}

	public function render_panel(): void {
		global $post;

		if ( ! $post instanceof \WP_Post || $post->post_type !== 'product' ) {
			return;
		}

		$product_id   = absint( $post->ID );
		$config_json  = Config_Repository::get_editor_json( $product_id );
		$product      = wc_get_product( $product_id );
		$template     = $product instanceof WC_Product
			? Config_Repository::get_template_for_product( $product )
			: Config_Repository::DEFAULT_TEMPLATE;
		$default_json = Config_Repository::get_default_config_json( $template );

		echo '<div id="lpc_customizer_panel" class="panel woocommerce_options_panel hidden">';

		woocommerce_wp_checkbox(
			array(
				'id'          => Meta_Keys::ENABLED,
				'label'       => esc_html__( 'Activer le customizer', 'leons-product-customizer' ),
				'description' => esc_html__( 'Affiche le configurateur de flocage sur la fiche produit.', 'leons-product-customizer' ),
			)
		);

		echo '<p class="form-field lpc-config-field">';
		echo '<label for="lpc_config_textarea">' . esc_html__( 'Configuration JSON', 'leons-product-customizer' ) . '</label>';
		printf(
			'<textarea id="lpc_config_textarea" name="%1$s" rows="18" class="large-text code" style="width:100%%;max-width:48em;font-family:monospace;">%2$s</textarea>',
			esc_attr( Meta_Keys::CONFIG ),
			esc_textarea( $config_json )
		);
		echo '</p>';

		if ( is_string( $default_json ) && $default_json !== '' ) {
			printf(
				'<p class="form-field"><button type="button" class="button" id="lpc_load_default_template">%s</button></p>',
				/* translators: %s: template slug (e.g. "pantalons-de-training") */
				esc_html( sprintf( __( 'Charger le modèle %s', 'leons-product-customizer' ), $template ) )
			);
		}

		echo '<p class="description" style="padding:0 12px 12px;">';
		echo esc_html(
			sprintf(
				/* translators: %s: template filename (e.g. "pantalons-de-training.json") */
				__( 'Laissez la configuration vide pour utiliser le modèle par défaut (%s.json). Les zones définissent les champs, prix et positions canvas.', 'leons-product-customizer' ),
				$template
			)
		);
		echo '</p>';

		echo '</div>';
	}

	/**
	 * @param WC_Product $product
	 */
	public function persist( $product ): void {
		if ( ! $product instanceof WC_Product ) {
			return;
		}
		if ( ! isset( $_POST['woocommerce_meta_nonce'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
			return;
		}
		if ( ! current_user_can( 'edit_products' ) ) {
			return;
		}

		$enabled = isset( $_POST[ Meta_Keys::ENABLED ] ) ? 'yes' : 'no'; // phpcs:ignore WordPress.Security.NonceVerification.Missing

		if ( ! isset( $_POST[ Meta_Keys::CONFIG ] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
			$product->update_meta_data( Meta_Keys::ENABLED, $enabled );
			$product->save();
			return;
		}

		$raw = wp_unslash( $_POST[ Meta_Keys::CONFIG ] ); // phpcs:ignore WordPress.Security.NonceVerification.Missing
		$raw = is_string( $raw ) ? trim( $raw ) : '';

		$normalized = null;
		if ( $raw !== '' ) {
			$decoded = Config_Validator::decode_json( $raw );
			if ( null === $decoded ) {
				WC_Admin_Meta_Boxes::add_error(
					__( 'Configuration JSON invalide : syntaxe incorrecte.', 'leons-product-customizer' )
				);
				return;
			}

			if ( ! Config_Validator::validate( $decoded ) ) {
				WC_Admin_Meta_Boxes::add_error(
					__( 'Configuration JSON invalide : structure attendue (zones, label, type, price).', 'leons-product-customizer' )
				);
				return;
			}

			$normalized = wp_json_encode( $decoded, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES );
			if ( ! is_string( $normalized ) ) {
				WC_Admin_Meta_Boxes::add_error(
					__( 'Impossible d’enregistrer la configuration JSON.', 'leons-product-customizer' )
				);
				return;
			}
		}

		$product->update_meta_data( Meta_Keys::ENABLED, $enabled );
		if ( null === $normalized ) {
			$product->delete_meta_data( Meta_Keys::CONFIG );
		} else {
			$product->update_meta_data( Meta_Keys::CONFIG, $normalized );
		}

		$product->save();
		wc_delete_product_transients( $product->get_id() );
	}

	public function enqueue_scripts( string $hook ): void {
		if ( ! in_array( $hook, array( 'post.php', 'post-new.php' ), true ) ) {
			return;
		}

		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		if ( ! $screen || $screen->id !== 'product' ) {
			return;
		}

		$product_id   = absint( get_the_ID() );
		$product      = $product_id ? wc_get_product( $product_id ) : null;
		$template     = $product instanceof WC_Product
			? Config_Repository::get_template_for_product( $product )
			: Config_Repository::DEFAULT_TEMPLATE;
		$default_json = Config_Repository::get_default_config_json( $template );
		if ( ! is_string( $default_json ) || $default_json === '' ) {
			return;
		}

		wp_enqueue_script(
			'lpc-admin-product',
			LPC_PLUGIN_URL . 'assets/js/admin-product.js',
			array(),
			LPC_VERSION,
			true
		);

		wp_add_inline_script(
			'lpc-admin-product',
			'window.lpcAdminProduct = ' . wp_json_encode(
				array(
					'defaultJson'    => $default_json,
					'templateLabel'  => $template,
				)
			) . ';',
			'before'
		);
	}
}
