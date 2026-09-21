<?php
/**
 * Image dos par variation (admin produit variable).
 *
 * @package LPC\Admin
 */

namespace LPC\Admin;

use LPC\Config\Config_Repository;
use LPC\Meta_Keys;
use WC_Product_Variation;

defined( 'ABSPATH' ) || exit;

/**
 * Class Variation_Back_Image
 */
final class Variation_Back_Image {

	public static function register(): void {
		add_action( 'woocommerce_product_after_variable_attributes', array( __CLASS__, 'render_field' ), 10, 3 );
		add_action( 'woocommerce_admin_process_variation_object', array( __CLASS__, 'process_variation_object' ), 10, 2 );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue_styles' ), 20 );
	}

	/**
	 * @param int                  $loop
	 * @param array<string, mixed> $variation_data
	 * @param \WP_Post             $variation
	 */
	public static function render_field( $loop, $variation_data, $variation ): void {
		unset( $variation_data );

		$variation_id = $variation instanceof \WP_Post ? (int) $variation->ID : 0;
		if ( $variation_id <= 0 ) {
			return;
		}

		$variation_object = wc_get_product( $variation_id );
		if ( ! $variation_object instanceof WC_Product_Variation ) {
			return;
		}

		$parent_id = (int) $variation_object->get_parent_id();
		if ( ! Config_Repository::is_enabled_for_product_id( $parent_id ) ) {
			return;
		}

		$image_id = absint( $variation_object->get_meta( Meta_Keys::VARIATION_BACK_IMAGE_ID, true ) );
		$thumb    = $image_id > 0 ? wp_get_attachment_image_url( $image_id, 'thumbnail' ) : wc_placeholder_img_src();

		/*
		 * Même markup que l’image variation WooCommerce (upload_image / upload_image_button /
		 * upload_image_id) pour réutiliser wc_meta_boxes_product_variations_media sans JS custom.
		 */
		echo '<p class="form-row upload_image lpc-variation-back-image form-row-full">';
		echo '<label>' . esc_html__( 'Image dos (customizer)', 'leons-product-customizer' ) . '</label>';
		echo '<span class="description">' . esc_html__( 'Cliquez sur l’image (comme l’image variation). Si vide, l’aperçu dos reprend l’image face.', 'leons-product-customizer' ) . '</span>';

		printf(
			'<a href="#" class="upload_image_button tips %1$s" data-tip="%2$s" rel="%3$d">',
			$image_id > 0 ? 'remove' : '',
			$image_id > 0
				? esc_attr__( 'Retirer l’image dos', 'leons-product-customizer' )
				: esc_attr__( 'Choisir une image dos', 'leons-product-customizer' ),
			$variation_id
		);

		printf(
			'<img src="%1$s" alt="" /><input type="hidden" class="upload_image_id lpc_back_image_id" name="lpc_back_image_id[%2$d]" value="%3$s" />',
			esc_url( is_string( $thumb ) ? $thumb : wc_placeholder_img_src() ),
			(int) $loop,
			$image_id > 0 ? (string) $image_id : ''
		);

		echo '</a></p>';
	}

	/**
	 * @param \WC_Product_Variation $variation
	 * @param int                   $loop
	 */
	public static function process_variation_object( $variation, $loop ): void {
		if ( ! $variation instanceof WC_Product_Variation ) {
			return;
		}

		self::persist_from_post( $variation, (int) $loop );
	}

	private static function persist_from_post( WC_Product_Variation $variation, int $loop ): void {
		if ( ! current_user_can( 'edit_products' ) ) {
			return;
		}

		$parent_id = (int) $variation->get_parent_id();
		if ( ! Config_Repository::is_enabled_for_product_id( $parent_id ) ) {
			return;
		}

		if ( ! isset( $_POST['lpc_back_image_id'] ) || ! is_array( $_POST['lpc_back_image_id'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
			return;
		}

		$raw = wp_unslash( $_POST['lpc_back_image_id'] ); // phpcs:ignore WordPress.Security.NonceVerification.Missing
		if ( ! array_key_exists( $loop, $raw ) ) {
			return;
		}

		$image_id = absint( $raw[ $loop ] );
		if ( $image_id > 0 ) {
			$variation->update_meta_data( Meta_Keys::VARIATION_BACK_IMAGE_ID, $image_id );
		} else {
			$variation->delete_meta_data( Meta_Keys::VARIATION_BACK_IMAGE_ID );
		}
	}

	public static function enqueue_styles( string $hook ): void {
		if ( ! in_array( $hook, array( 'post.php', 'post-new.php' ), true ) ) {
			return;
		}

		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		if ( ! $screen || $screen->id !== 'product' ) {
			return;
		}

		wp_register_style( 'lpc-admin-variation-back-image', false, array(), LPC_VERSION );
		wp_enqueue_style( 'lpc-admin-variation-back-image' );
		wp_add_inline_style(
			'lpc-admin-variation-back-image',
			'.lpc-variation-back-image.upload_image{margin-top:12px;padding-top:12px;border-top:1px solid #eee;}'
			. '.lpc-variation-back-image.upload_image .upload_image_button img{display:block;max-width:64px;height:auto;}'
		);
	}
}
