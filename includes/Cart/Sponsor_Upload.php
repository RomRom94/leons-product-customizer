<?php
/**
 * Upload et sanitization des fichiers sponsor (zones type "image").
 *
 * @package LPC\Cart
 */

namespace LPC\Cart;

use enshrined\svgSanitize\Sanitizer;
use WP_Error;

defined( 'ABSPATH' ) || exit;

/**
 * Class Sponsor_Upload
 */
final class Sponsor_Upload {

	public const MAX_SIZE_BYTES = 2 * 1024 * 1024;

	/**
	 * @var array<string, string> extension => MIME attendu
	 */
	private const ALLOWED_TYPES = array(
		'png'  => 'image/png',
		'jpg'  => 'image/jpeg',
		'jpeg' => 'image/jpeg',
		'svg'  => 'image/svg+xml',
	);

	/**
	 * Normalise la structure $_FILES['lpc_files'] (indexée par zone) en
	 * `zone_id => array{name, type, tmp_name, error, size}`.
	 *
	 * @param array<string, mixed> $files_input
	 * @return array<string, array<string, mixed>>
	 */
	public static function normalize( array $files_input ): array {
		if ( ! isset( $files_input['name'] ) || ! is_array( $files_input['name'] ) ) {
			return array();
		}

		$normalized = array();
		foreach ( $files_input['name'] as $zone_id => $name ) {
			if ( ! is_string( $zone_id ) ) {
				continue;
			}
			$normalized[ $zone_id ] = array(
				'name'     => $name,
				'type'     => $files_input['type'][ $zone_id ] ?? '',
				'tmp_name' => $files_input['tmp_name'][ $zone_id ] ?? '',
				'error'    => $files_input['error'][ $zone_id ] ?? UPLOAD_ERR_NO_FILE,
				'size'     => $files_input['size'][ $zone_id ] ?? 0,
			);
		}

		return $normalized;
	}

	/**
	 * @param array<string, mixed> $file
	 */
	public static function has_file( array $file ): bool {
		$error = $file['error'] ?? UPLOAD_ERR_NO_FILE;
		return UPLOAD_ERR_NO_FILE !== $error;
	}

	/**
	 * Validation légère (format, taille, erreur upload) — pas d'écriture disque.
	 * Utilisée à la fois en pré-validation (woocommerce_add_to_cart_validation)
	 * et avant traitement effectif.
	 *
	 * @param array<string, mixed> $file
	 */
	public static function validate( array $file ): ?string {
		$error = $file['error'] ?? UPLOAD_ERR_NO_FILE;
		if ( UPLOAD_ERR_OK !== $error ) {
			return __( 'Erreur lors du téléversement du fichier.', 'leons-product-customizer' );
		}

		$size = (int) ( $file['size'] ?? 0 );
		if ( $size <= 0 || $size > self::MAX_SIZE_BYTES ) {
			return __( 'Le fichier dépasse la taille maximale autorisée (2 Mo).', 'leons-product-customizer' );
		}

		$ext = strtolower( pathinfo( (string) ( $file['name'] ?? '' ), PATHINFO_EXTENSION ) );
		if ( ! isset( self::ALLOWED_TYPES[ $ext ] ) ) {
			return __( 'Format de fichier non autorisé (PNG, JPG ou SVG uniquement).', 'leons-product-customizer' );
		}

		$tmp_name = (string) ( $file['tmp_name'] ?? '' );
		if ( '' === $tmp_name || ! is_uploaded_file( $tmp_name ) ) {
			return __( 'Fichier téléversé invalide.', 'leons-product-customizer' );
		}

		return null;
	}

	/**
	 * Sanitize et stocke le fichier comme attachment. Retourne l'ID de
	 * l'attachment ou une WP_Error.
	 *
	 * @param array<string, mixed> $file
	 * @return int|WP_Error
	 */
	public static function process( array $file, string $zone_id ) {
		$error_message = self::validate( $file );
		if ( null !== $error_message ) {
			return new WP_Error( 'lpc_invalid_upload', $error_message );
		}

		$ext     = strtolower( pathinfo( (string) $file['name'], PATHINFO_EXTENSION ) );
		$content = file_get_contents( (string) $file['tmp_name'] ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
		if ( ! is_string( $content ) || '' === $content ) {
			return new WP_Error( 'lpc_invalid_upload', __( 'Impossible de lire le fichier téléversé.', 'leons-product-customizer' ) );
		}

		if ( 'svg' === $ext ) {
			$sanitized = self::sanitize_svg( $content );
			if ( null === $sanitized ) {
				return new WP_Error( 'lpc_invalid_svg', __( 'Le fichier SVG fourni n’a pas pu être validé.', 'leons-product-customizer' ) );
			}
			$content = $sanitized;
			$mime    = 'image/svg+xml';
		} else {
			$finfo     = finfo_open( FILEINFO_MIME_TYPE );
			$real_mime = $finfo ? finfo_buffer( $finfo, $content ) : false;
			if ( $finfo ) {
				finfo_close( $finfo );
			}
			if ( ! in_array( $real_mime, array( 'image/png', 'image/jpeg' ), true ) ) {
				return new WP_Error( 'lpc_invalid_mime', __( 'Le contenu du fichier ne correspond pas à une image PNG/JPG valide.', 'leons-product-customizer' ) );
			}
			$mime = $real_mime;
		}

		$filename = sanitize_file_name( wp_basename( (string) $file['name'] ) );
		$upload   = wp_upload_bits( $filename, null, $content );
		if ( ! empty( $upload['error'] ) ) {
			return new WP_Error( 'lpc_upload_failed', (string) $upload['error'] );
		}

		require_once ABSPATH . 'wp-admin/includes/image.php';

		$attachment_id = wp_insert_attachment(
			array(
				'post_mime_type' => $mime,
				'post_title'     => sanitize_file_name( pathinfo( $upload['file'], PATHINFO_FILENAME ) ),
				'post_content'   => '',
				'post_status'    => 'private',
			),
			$upload['file']
		);

		if ( ! $attachment_id || is_wp_error( $attachment_id ) ) {
			return new WP_Error( 'lpc_attachment_failed', __( 'Impossible d’enregistrer le fichier téléversé.', 'leons-product-customizer' ) );
		}

		if ( 'image/svg+xml' !== $mime ) {
			$metadata = wp_generate_attachment_metadata( $attachment_id, $upload['file'] );
			wp_update_attachment_metadata( $attachment_id, $metadata );
		}

		update_post_meta( $attachment_id, '_lpc_sponsor_zone', sanitize_key( $zone_id ) );

		return $attachment_id;
	}

	private static function sanitize_svg( string $content ): ?string {
		if ( false === stripos( $content, '<svg' ) ) {
			return null;
		}

		$sanitizer = new Sanitizer();
		$sanitizer->removeRemoteReferences( true );
		$sanitizer->minify( true );

		$clean = $sanitizer->sanitize( $content );

		return false === $clean ? null : $clean;
	}
}
