<?php
/**
 * Onglet Personnalisation : grille des images de personnalisation (couleur × vue).
 *
 * @package LPC\Admin
 */

namespace LPC\Admin;

use LPC\Config\Config_Repository;
use LPC\Meta_Keys;
use LPC\Personalization\View_Images;
use WC_Product;
use WC_Product_Variation;

defined( 'ABSPATH' ) || exit;

/**
 * Class View_Images_Grid
 */
final class View_Images_Grid {

	private const FIELD        = 'lpc_view_images';
	private const AJAX_ACTION  = 'lpc_match_view_images';
	private const NONCE_ACTION = 'lpc_view_images';
	private const MIGRATED     = 'lpc_view_images_migrated';

	public static function register(): void {
		add_action( 'woocommerce_admin_process_product_object', array( __CLASS__, 'persist' ), 14, 1 );
		add_action( 'wp_ajax_' . self::AJAX_ACTION, array( __CLASS__, 'ajax_match_by_filename' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue' ) );
		add_action( 'admin_init', array( __CLASS__, 'migrate_legacy' ) );
	}

	/**
	 * Préfixe de nom de fichier reconnu par l'import automatique.
	 */
	public static function get_filename_prefix( WC_Product $product ): string {
		return 'lpc-' . $product->get_slug() . '-';
	}

	public static function render( WC_Product $product ): void {
		$config = Config_Repository::get_effective_config( $product );
		$views  = null === $config ? array() : View_Images::get_views( $config );

		echo '<div class="lpc-view-images" data-lpc-view-images>';
		echo '<h4 class="lpc-view-images__title">' . esc_html__( 'Images de personnalisation', 'leons-product-customizer' ) . '</h4>';

		if ( ! $views ) {
			echo '<p class="description">' . esc_html__( 'La configuration ne déclare aucune vue.', 'leons-product-customizer' ) . '</p></div>';
			return;
		}

		$colors   = View_Images::get_colors( $product );
		$map      = View_Images::get_stored_map( $product->get_id() );
		$missing  = 0;
		$example  = self::get_filename_prefix( $product ) . (string) array_key_first( $colors ) . '-' . sanitize_title( (string) reset( $views ) ) . '.webp';

		echo '<p class="description">';
		echo esc_html__( 'Images sur lesquelles le flocage est dessiné, distinctes des photos du produit. Même cadrage pour toutes les couleurs, 1200×1600. Une couleur à laquelle il manque une image n’est pas personnalisable.', 'leons-product-customizer' );
		echo '</p>';

		echo '<table class="widefat striped lpc-view-images__table"><thead><tr>';
		echo '<th scope="col">' . esc_html__( 'Couleur', 'leons-product-customizer' ) . '</th>';
		foreach ( $views as $view_label ) {
			echo '<th scope="col">' . esc_html( $view_label ) . '</th>';
		}
		echo '<th scope="col">' . esc_html__( 'Statut', 'leons-product-customizer' ) . '</th>';
		echo '</tr></thead><tbody>';

		foreach ( $colors as $color => $color_label ) {
			$row_complete = true;
			echo '<tr data-lpc-color="' . esc_attr( $color ) . '">';
			echo '<th scope="row">' . esc_html( $color_label ) . '</th>';

			foreach ( $views as $view => $view_label ) {
				$attachment_id = $map[ $color ][ $view ] ?? 0;
				$thumb         = $attachment_id > 0 ? wp_get_attachment_image_url( $attachment_id, 'thumbnail' ) : '';
				if ( ! $thumb ) {
					$attachment_id = 0;
					$row_complete  = false;
				}

				printf(
					'<td><div class="lpc-view-images__cell%1$s" data-lpc-cell data-view="%2$s">'
					. '<button type="button" class="lpc-view-images__pick" data-lpc-pick aria-label="%3$s">'
					. '<img src="%4$s" alt=""%5$s /><span class="lpc-view-images__add"%6$s>+</span></button>'
					. '<button type="button" class="lpc-view-images__remove" data-lpc-remove aria-label="%7$s"%8$s>&times;</button>'
					. '<input type="hidden" name="%9$s" value="%10$s" data-lpc-input /></div></td>',
					$attachment_id > 0 ? ' is-filled' : '',
					esc_attr( $view ),
					/* translators: 1: color, 2: view */
					esc_attr( sprintf( __( 'Choisir l’image %1$s — %2$s', 'leons-product-customizer' ), $color_label, $view_label ) ),
					esc_url( (string) $thumb ),
					$attachment_id > 0 ? '' : ' hidden',
					$attachment_id > 0 ? ' hidden' : '',
					esc_attr__( 'Retirer l’image', 'leons-product-customizer' ),
					$attachment_id > 0 ? '' : ' hidden',
					esc_attr( self::FIELD . '[' . $color . '][' . $view . ']' ),
					$attachment_id > 0 ? esc_attr( (string) $attachment_id ) : ''
				);
			}

			if ( ! $row_complete ) {
				++$missing;
			}
			printf(
				'<td><span class="lpc-view-images__status %1$s" data-lpc-status>%2$s</span></td>',
				$row_complete ? 'is-ok' : 'is-missing',
				$row_complete
					? esc_html__( 'Personnalisable', 'leons-product-customizer' )
					: esc_html__( 'Incomplète — désactivée', 'leons-product-customizer' )
			);
			echo '</tr>';
		}

		echo '</tbody></table>';

		echo '<p class="lpc-view-images__summary" data-lpc-summary>';
		if ( $missing > 0 ) {
			/* translators: %1$d: incomplete colors, %2$d: total colors */
			echo esc_html( sprintf( _n( '%1$d couleur sur %2$d incomplète.', '%1$d couleurs sur %2$d incomplètes.', $missing, 'leons-product-customizer' ), $missing, count( $colors ) ) );
		} else {
			esc_html_e( 'Toutes les couleurs sont personnalisables.', 'leons-product-customizer' );
		}
		echo '</p>';

		echo '<p class="lpc-view-images__import">';
		printf(
			'<button type="button" class="button" data-lpc-match data-product="%1$d">%2$s</button> ',
			(int) $product->get_id(),
			esc_html__( 'Associer depuis la médiathèque par nom de fichier', 'leons-product-customizer' )
		);
		echo '<span class="description">';
		/* translators: %s: example filename */
		echo wp_kses( sprintf( __( 'Nom attendu : %s (couleur et vue telles qu’affichées dans la grille, en minuscules sans accents).', 'leons-product-customizer' ), '<code>' . esc_html( $example ) . '</code>' ), array( 'code' => array() ) );
		echo '</span> <span class="lpc-view-images__match-result" data-lpc-match-result role="status"></span>';
		echo '</p>';

		if ( View_Images::ANY_COLOR !== array_key_first( $colors ) ) {
			echo '<p class="description">' . esc_html__( 'Les lignes suivent les couleurs des variations enregistrées : après avoir ajouté une couleur, mettez le produit à jour pour la voir apparaître.', 'leons-product-customizer' ) . '</p>';
		}

		echo '</div>';
	}

	/**
	 * @param WC_Product $product
	 */
	public static function persist( $product ): void {
		if ( ! $product instanceof WC_Product || ! current_user_can( 'edit_products' ) ) {
			return;
		}
		if ( ! isset( $_POST['woocommerce_meta_nonce'], $_POST[ self::FIELD ] ) || ! is_array( $_POST[ self::FIELD ] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
			return;
		}

		$map = View_Images::sanitize_map( wp_unslash( $_POST[ self::FIELD ] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Missing,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized

		// Les couleurs/vues absentes du formulaire (ex. couleur retirée des variations) sont conservées.
		$stored = View_Images::get_stored_map( $product->get_id() );
		foreach ( wp_unslash( $_POST[ self::FIELD ] ) as $color => $views ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
			$color = sanitize_title( (string) $color );
			foreach ( array_keys( (array) $views ) as $view ) {
				unset( $stored[ $color ][ sanitize_key( (string) $view ) ] );
			}
			if ( empty( $stored[ $color ] ) ) {
				unset( $stored[ $color ] );
			}
		}
		$map = array_replace_recursive( $stored, $map );

		if ( $map ) {
			$product->update_meta_data( Meta_Keys::VIEW_IMAGES, $map );
		} else {
			$product->delete_meta_data( Meta_Keys::VIEW_IMAGES );
		}
	}

	/**
	 * Cherche dans la médiathèque les fichiers lpc-{produit}-{couleur}-{vue}.* du produit.
	 */
	public static function ajax_match_by_filename(): void {
		check_ajax_referer( self::NONCE_ACTION, 'nonce' );

		$product = wc_get_product( absint( $_POST['product_id'] ?? 0 ) );
		if ( ! $product instanceof WC_Product || ! current_user_can( 'edit_post', $product->get_id() ) ) {
			wp_send_json_error( null, 403 );
		}

		$config = Config_Repository::get_effective_config( $product );
		$views  = null === $config ? array() : View_Images::get_views( $config );
		$prefix = self::get_filename_prefix( $product );

		// Nom de fichier → [couleur, vue] ; la vue est reconnue par sa clé (front) ou son libellé (face).
		$expected = array();
		foreach ( array_keys( View_Images::get_colors( $product ) ) as $color ) {
			foreach ( $views as $view => $view_label ) {
				$expected[ $prefix . $color . '-' . sanitize_title( $view_label ) ] = array( $color, $view );
				$expected[ $prefix . $color . '-' . $view ]                          = array( $color, $view );
			}
		}

		global $wpdb;
		$rows = $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->prepare(
				"SELECT post_id, meta_value FROM {$wpdb->postmeta} WHERE meta_key = '_wp_attached_file' AND meta_value LIKE %s ORDER BY post_id DESC",
				'%' . $wpdb->esc_like( $prefix ) . '%'
			)
		);

		$found = array();
		foreach ( $rows as $row ) {
			$name = strtolower( pathinfo( (string) $row->meta_value, PATHINFO_FILENAME ) );
			$name = preg_replace( '/-scaled$/', '', $name );
			if ( ! isset( $expected[ $name ] ) || ! wp_attachment_is_image( (int) $row->post_id ) ) {
				continue;
			}
			list( $color, $view ) = $expected[ $name ];
			if ( isset( $found[ $color ][ $view ] ) ) {
				continue; // Le plus récent l'emporte (ORDER BY post_id DESC).
			}
			$found[ $color ][ $view ] = array(
				'id'    => (int) $row->post_id,
				'thumb' => (string) wp_get_attachment_image_url( (int) $row->post_id, 'thumbnail' ),
			);
		}

		wp_send_json_success( $found );
	}

	/**
	 * Reprise unique de l'ancien fonctionnement vers la grille, pour que les fiches
	 * restent personnalisables tant que les images dédiées ne sont pas fournies :
	 * - vue « front » : image de la variation (ancien fond de la vue face) ;
	 * - vue « back » : ancienne image dos par variation (`_lpc_back_image_id`).
	 * Pour chaque couleur, la première variation qui a une image l'emporte ; ce qui
	 * est déjà dans la grille n'est jamais écrasé.
	 */
	public static function migrate_legacy(): void {
		if ( get_option( self::MIGRATED ) || ! current_user_can( 'edit_products' ) ) {
			return;
		}

		global $wpdb;
		$product_ids = $wpdb->get_col( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->prepare( "SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key = %s AND meta_value = 'yes'", Meta_Keys::ENABLED )
		);

		foreach ( $product_ids as $product_id ) {
			$product = wc_get_product( (int) $product_id );
			if ( ! $product instanceof WC_Product ) {
				continue;
			}

			$legacy = array();
			foreach ( $product->get_children() as $variation_id ) {
				$variation = wc_get_product( $variation_id );
				if ( ! $variation instanceof WC_Product_Variation ) {
					continue;
				}
				$color = View_Images::get_color_for_variation( $variation->get_id() );
				if ( '' === $color ) {
					continue;
				}
				$front = (int) get_post_meta( $variation->get_id(), '_thumbnail_id', true );
				$back  = absint( $variation->get_meta( Meta_Keys::VARIATION_BACK_IMAGE_ID, true ) );
				if ( $front > 0 && empty( $legacy[ $color ]['front'] ) ) {
					$legacy[ $color ]['front'] = $front;
				}
				if ( $back > 0 && empty( $legacy[ $color ]['back'] ) ) {
					$legacy[ $color ]['back'] = $back;
				}
			}

			if ( $legacy ) {
				$map = array_replace_recursive( View_Images::sanitize_map( $legacy ), View_Images::get_stored_map( $product->get_id() ) );
				update_post_meta( $product->get_id(), Meta_Keys::VIEW_IMAGES, $map );
			}
		}

		update_option( self::MIGRATED, gmdate( 'c' ), false );
	}

	public static function enqueue( string $hook ): void {
		if ( ! in_array( $hook, array( 'post.php', 'post-new.php' ), true ) ) {
			return;
		}
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		if ( ! $screen || 'product' !== $screen->id ) {
			return;
		}

		wp_enqueue_media();
		wp_enqueue_script(
			'lpc-admin-view-images',
			LPC_PLUGIN_URL . 'assets/js/admin-view-images.js',
			array( 'jquery' ),
			LPC_VERSION,
			true
		);
		wp_localize_script(
			'lpc-admin-view-images',
			'lpcViewImages',
			array(
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'action'  => self::AJAX_ACTION,
				'nonce'   => wp_create_nonce( self::NONCE_ACTION ),
				'i18n'    => array(
					'frameTitle'  => __( 'Image de personnalisation', 'leons-product-customizer' ),
					'frameButton' => __( 'Utiliser cette image', 'leons-product-customizer' ),
					'ok'          => __( 'Personnalisable', 'leons-product-customizer' ),
					'missing'     => __( 'Incomplète — désactivée', 'leons-product-customizer' ),
					/* translators: %d: number of images */
					'matched'     => __( '%d image(s) associée(s) — mettez le produit à jour pour enregistrer.', 'leons-product-customizer' ),
					'noMatch'     => __( 'Aucun fichier correspondant dans la médiathèque.', 'leons-product-customizer' ),
					'error'       => __( 'La recherche a échoué.', 'leons-product-customizer' ),
					'allOk'       => __( 'Toutes les couleurs sont personnalisables.', 'leons-product-customizer' ),
					/* translators: 1: incomplete colors, 2: total colors */
					'someMissing' => __( '%1$d couleur(s) sur %2$d incomplète(s).', 'leons-product-customizer' ),
				),
			)
		);

		wp_register_style( 'lpc-admin-view-images', false, array(), LPC_VERSION );
		wp_enqueue_style( 'lpc-admin-view-images' );
		wp_add_inline_style(
			'lpc-admin-view-images',
			'.lpc-view-images{padding:12px;border-top:1px solid #eee}'
			. '.lpc-view-images__title{margin:0 0 6px;font-size:14px}'
			. '.lpc-view-images__table{margin:12px 0;width:auto}'
			. '.lpc-view-images__table th,.lpc-view-images__table td{vertical-align:middle}'
			. '.lpc-view-images__cell{position:relative;width:60px;height:80px}'
			. '.lpc-view-images__pick{display:flex;align-items:center;justify-content:center;width:60px;height:80px;padding:0;border:1px dashed #8c8f94;border-radius:4px;background:#f6f7f7;cursor:pointer;overflow:hidden}'
			. '.lpc-view-images__cell.is-filled .lpc-view-images__pick{border-style:solid;border-color:#c3c4c7}'
			. '.lpc-view-images__pick img{width:100%;height:100%;object-fit:cover}'
			. '.lpc-view-images__add{font-size:22px;color:#50575e}'
			. '.lpc-view-images__remove{position:absolute;top:-6px;right:-6px;width:20px;height:20px;padding:0;border:0;border-radius:50%;background:#d63638;color:#fff;line-height:20px;cursor:pointer}'
			. '.lpc-view-images__status{display:inline-block;padding:2px 8px;border-radius:10px;font-size:12px}'
			. '.lpc-view-images__status.is-ok{background:#edfaef;color:#00663a}'
			. '.lpc-view-images__status.is-missing{background:#fcf0f1;color:#8a2424}'
			. '.lpc-view-images__summary{font-weight:600}'
			. '.lpc-view-images__match-result{margin-left:6px}'
		);
	}
}
