<?php
/**
 * Template customizer produit.
 *
 * @package LPC
 *
 * @var WC_Product           $product
 * @var array<string, mixed> $config
 * @var array<string, array<string, string>> $initial_images vue => { src, srcset, sizes } de la couleur affichée au chargement
 */

use LPC\Personalization\Style_Options;
use LPC\Personalization\View_Images;

defined( 'ABSPATH' ) || exit;

// Seulement les vues qui portent une zone (View_Images::get_views) : pas d'onglet « Dos » vide.
$views = isset( $config['views'] ) && is_array( $config['views'] ) ? array_intersect_key( $config['views'], View_Images::get_views( $config ) ) : array();
$zones = isset( $config['zones'] ) && is_array( $config['zones'] ) ? $config['zones'] : array();

$has_text_zone = false;
foreach ( $zones as $zone ) {
	if ( is_array( $zone ) && isset( $zone['type'] ) && 'text' === $zone['type'] ) {
		$has_text_zone = true;
		break;
	}
}

// Noms des targets JS par vue (front/back sont les seules vues gérées par le JS actuel).
$view_js_targets = array(
	'front' => array( 'image' => 'faceImage', 'canvas' => 'faceCanvas' ),
	'back'  => array( 'image' => 'backImage', 'canvas' => 'backCanvas' ),
);

// Vues affichées ; à partir de deux, une seule est visible à la fois, choisie par onglets.
$preview_views = array_intersect_key( $views, $view_js_targets );
$has_tabs      = count( $preview_views ) > 1;
$active_view   = (string) array_key_first( $preview_views );
?>

<div class="lpc-customizer" id="lpc-customizer" data-lpc-customizer>
	<div class="lpc-customizer__head">
		<h3 class="lpc-customizer__title"><?php esc_html_e( 'Personnalisation', 'leons-product-customizer' ); ?></h3>
		<p class="lpc-customizer__intro"><?php esc_html_e( 'Ajoutez texte et logos : l’aperçu se met à jour en direct.', 'leons-product-customizer' ); ?></p>
	</div>

	<p class="lpc-customizer__unavailable" data-lpc-unavailable role="status" hidden>
		<?php esc_html_e( 'La personnalisation n’est pas disponible pour cette couleur.', 'leons-product-customizer' ); ?>
	</p>

	<div class="lpc-customizer__body" data-lpc-body>
		<div class="lpc-customizer__previews">
			<?php if ( $has_tabs ) : ?>
				<div class="lpc-customizer__tabs" role="tablist" aria-label="<?php esc_attr_e( 'Vue de l’aperçu', 'leons-product-customizer' ); ?>" data-lpc-tabs>
					<?php foreach ( $preview_views as $view_name => $view ) : ?>
						<?php $is_active = $view_name === $active_view; ?>
						<button
							type="button"
							class="lpc-customizer__tab"
							id="lpc-tab-<?php echo esc_attr( $view_name ); ?>"
							role="tab"
							aria-controls="lpc-view-<?php echo esc_attr( $view_name ); ?>"
							aria-selected="<?php echo $is_active ? 'true' : 'false'; ?>"
							tabindex="<?php echo $is_active ? '0' : '-1'; ?>"
							data-lpc-tab="<?php echo esc_attr( $view_name ); ?>"
						>
							<?php echo esc_html( isset( $view['label'] ) ? (string) $view['label'] : $view_name ); ?>
						</button>
					<?php endforeach; ?>
				</div>
			<?php endif; ?>

			<?php foreach ( $preview_views as $view_name => $view ) : ?>
				<?php
				$targets    = $view_js_targets[ $view_name ];
				$view_label = isset( $view['label'] ) ? (string) $view['label'] : $view_name;
				$image      = $initial_images[ $view_name ] ?? array( 'src' => '', 'srcset' => '', 'sizes' => '' );
				?>
				<div
					class="lpc-customizer__view"
					data-lpc-view="<?php echo esc_attr( $view_name ); ?>"
					<?php if ( $has_tabs ) : ?>
						id="lpc-view-<?php echo esc_attr( $view_name ); ?>"
						role="tabpanel"
						aria-labelledby="lpc-tab-<?php echo esc_attr( $view_name ); ?>"
						<?php echo $view_name === $active_view ? '' : 'hidden'; ?>
					<?php endif; ?>
				>
					<?php if ( ! $has_tabs ) : ?>
						<p class="lpc-customizer__view-label"><?php echo esc_html( $view_label ); ?></p>
					<?php endif; ?>
					<div class="lpc-customizer__canvas-wrap">
						<img
							class="lpc-customizer__image"
							data-lpc-target="<?php echo esc_attr( $targets['image'] ); ?>"
							src="<?php echo esc_url( $image['src'] ); ?>"
							<?php if ( $image['srcset'] ) : ?>
								srcset="<?php echo esc_attr( $image['srcset'] ); ?>"
								sizes="<?php echo esc_attr( $image['sizes'] ); ?>"
							<?php endif; ?>
							alt=""
						/>
						<canvas class="lpc-customizer__canvas" data-lpc-target="<?php echo esc_attr( $targets['canvas'] ); ?>" aria-hidden="true"></canvas>
					</div>
				</div>
			<?php endforeach; ?>
		</div>

		<?php if ( $zones ) : ?>
		<?php
		// Champs regroupés : textes, puis logos et sponsors, puis style du flocage (police, couleur).
		$text_zones  = array_filter( $zones, static fn( $z ) => is_array( $z ) && 'text' === ( $z['type'] ?? '' ) );
		$image_zones = array_filter( $zones, static fn( $z ) => is_array( $z ) && 'image' === ( $z['type'] ?? '' ) );
		$field_head  = static function ( string $zone_id, array $zone ): void {
			$label    = isset( $zone['label'] ) ? (string) $zone['label'] : $zone_id;
			$price    = isset( $zone['price'] ) ? (float) $zone['price'] : 0.0;
			$required = ! empty( $zone['required'] );
			?>
			<label class="lpc-customizer__field-head" for="lpc_zone_<?php echo esc_attr( $zone_id ); ?>">
				<span class="lpc-customizer__field-label">
					<?php echo esc_html( $label ); ?>
					<?php if ( $required ) : ?>
						<abbr class="required" title="<?php esc_attr_e( 'Required', 'woocommerce' ); ?>">*</abbr>
					<?php endif; ?>
				</span>
				<?php if ( $price > 0 ) : ?>
					<span class="lpc-customizer__zone-price">+<?php echo wp_kses_post( wc_price( $price ) ); ?></span>
				<?php endif; ?>
			</label>
			<?php
		};
		?>
		<div class="lpc-customizer__form">
		<fieldset class="lpc-customizer__fields">
			<legend class="lpc-customizer__fields-legend screen-reader-text"><?php esc_html_e( 'Personnalisation', 'leons-product-customizer' ); ?></legend>

			<?php if ( $text_zones ) : ?>
				<div class="lpc-customizer__group" role="group" aria-labelledby="lpc-group-text">
					<p class="lpc-customizer__group-title" id="lpc-group-text"><?php esc_html_e( 'Textes', 'leons-product-customizer' ); ?></p>
					<?php foreach ( $text_zones as $zone_id => $zone ) : ?>
						<?php
						$zone_id    = sanitize_key( (string) $zone_id );
						$price      = isset( $zone['price'] ) ? (float) $zone['price'] : 0.0;
						$required   = ! empty( $zone['required'] );
						$max_length = isset( $zone['max_length'] ) ? absint( $zone['max_length'] ) : 0;
						$pattern    = isset( $zone['pattern'] ) ? (string) $zone['pattern'] : '';
						?>
						<div class="form-row lpc-customizer__field">
							<?php $field_head( $zone_id, $zone ); ?>
							<input
								type="text"
								class="input-text lpc-customizer__zone-input"
								id="lpc_zone_<?php echo esc_attr( $zone_id ); ?>"
								name="lpc_zones[<?php echo esc_attr( $zone_id ); ?>]"
								data-lpc-zone="<?php echo esc_attr( $zone_id ); ?>"
								data-lpc-zone-price="<?php echo esc_attr( (string) $price ); ?>"
								value=""
								autocomplete="off"
								<?php echo $max_length > 0 ? 'maxlength="' . esc_attr( (string) $max_length ) . '"' : ''; ?>
								<?php echo $pattern !== '' ? 'pattern="' . esc_attr( $pattern ) . '"' : ''; ?>
								<?php echo $required ? 'required' : ''; ?>
							/>
							<?php if ( $max_length > 0 ) : ?>
								<span class="lpc-customizer__field-hint">
									<?php /* translators: %d: max characters */ printf( esc_html__( '%d caractères maximum', 'leons-product-customizer' ), (int) $max_length ); ?>
								</span>
							<?php endif; ?>
						</div>
					<?php endforeach; ?>
				</div>
			<?php endif; ?>

			<?php if ( $image_zones ) : ?>
				<div class="lpc-customizer__group" role="group" aria-labelledby="lpc-group-images">
					<p class="lpc-customizer__group-title" id="lpc-group-images"><?php esc_html_e( 'Logos et sponsors', 'leons-product-customizer' ); ?></p>
					<?php foreach ( $image_zones as $zone_id => $zone ) : ?>
						<?php
						$zone_id  = sanitize_key( (string) $zone_id );
						$price    = isset( $zone['price'] ) ? (float) $zone['price'] : 0.0;
						$required = ! empty( $zone['required'] );
						?>
						<div class="form-row lpc-customizer__field lpc-customizer__field--upload">
							<?php $field_head( $zone_id, $zone ); ?>
							<div class="lpc-customizer__upload-row">
								<?php /* Le libellé enveloppe le champ fichier (masqué) : toute la zone en pointillés est cliquable. */ ?>
								<label class="lpc-customizer__upload" for="lpc_zone_<?php echo esc_attr( $zone_id ); ?>">
									<input
										type="file"
										class="lpc-customizer__zone-input lpc-customizer__upload-input"
										id="lpc_zone_<?php echo esc_attr( $zone_id ); ?>"
										name="lpc_files[<?php echo esc_attr( $zone_id ); ?>]"
										data-lpc-zone="<?php echo esc_attr( $zone_id ); ?>"
										data-lpc-zone-price="<?php echo esc_attr( (string) $price ); ?>"
										accept=".png,.jpg,.jpeg,.svg,image/png,image/jpeg,image/svg+xml"
										<?php echo $required ? 'required' : ''; ?>
									/>
									<span class="lpc-customizer__upload-icon" aria-hidden="true"><i class="fas fa-plus"></i></span>
									<span class="lpc-customizer__upload-text">
										<span class="lpc-customizer__upload-name" data-lpc-file-name="<?php echo esc_attr( $zone_id ); ?>" data-empty="<?php esc_attr_e( 'Ajouter un fichier', 'leons-product-customizer' ); ?>"><?php esc_html_e( 'Ajouter un fichier', 'leons-product-customizer' ); ?></span>
										<span class="lpc-customizer__upload-hint"><?php esc_html_e( 'PNG, JPG ou SVG — 2 Mo maximum', 'leons-product-customizer' ); ?></span>
									</span>
								</label>
								<button
									type="button"
									class="lpc-customizer__zone-remove"
									data-lpc-zone-remove="<?php echo esc_attr( $zone_id ); ?>"
									hidden
								>
									<i class="fas fa-xmark" aria-hidden="true"></i>
									<span class="screen-reader-text"><?php esc_html_e( 'Retirer le fichier', 'leons-product-customizer' ); ?></span>
								</button>
							</div>
						</div>
					<?php endforeach; ?>
				</div>
			<?php endif; ?>

			<?php if ( $has_text_zone ) : ?>
				<div class="lpc-customizer__group" role="group" aria-labelledby="lpc-group-style">
					<p class="lpc-customizer__group-title" id="lpc-group-style"><?php esc_html_e( 'Style du flocage', 'leons-product-customizer' ); ?></p>

					<div class="lpc-customizer__field lpc-customizer__field--font" role="radiogroup" aria-labelledby="lpc-font-title">
						<p class="lpc-customizer__field-head" id="lpc-font-title"><span class="lpc-customizer__field-label"><?php esc_html_e( 'Police', 'leons-product-customizer' ); ?></span></p>
						<div class="lpc-customizer__font-list">
							<?php foreach ( Style_Options::FONTS as $font_family => $font_label ) : ?>
								<label class="lpc-customizer__font-option">
									<input
										type="radio"
										name="lpc_font"
										value="<?php echo esc_attr( $font_family ); ?>"
										data-lpc-global="font"
										<?php checked( $font_family, Style_Options::DEFAULT_FONT ); ?>
									/>
									<span class="lpc-customizer__font-sample" style="font-family: '<?php echo esc_attr( $font_family ); ?>', sans-serif;" aria-hidden="true">10</span>
									<span class="lpc-customizer__font-label"><?php echo esc_html( $font_label ); ?></span>
								</label>
							<?php endforeach; ?>
						</div>
					</div>

					<div class="lpc-customizer__field lpc-customizer__field--color" role="radiogroup" aria-labelledby="lpc-color-title">
						<p class="lpc-customizer__field-head" id="lpc-color-title">
							<span class="lpc-customizer__field-label"><?php esc_html_e( 'Couleur', 'leons-product-customizer' ); ?></span>
						</p>
						<div class="lpc-customizer__color-list">
							<?php foreach ( Style_Options::COLORS as $color_slug => $color ) : ?>
								<label class="lpc-customizer__color-swatch" title="<?php echo esc_attr( $color['label'] ); ?>">
									<input
										type="radio"
										name="lpc_color"
										value="<?php echo esc_attr( $color_slug ); ?>"
										data-lpc-global="color"
										data-hex="<?php echo esc_attr( $color['hex'] ); ?>"
										<?php checked( $color_slug, Style_Options::DEFAULT_COLOR ); ?>
									/>
									<span class="lpc-customizer__color-dot" style="background-color: <?php echo esc_attr( $color['hex'] ); ?>;" aria-hidden="true"></span>
									<span class="lpc-customizer__color-label"><?php echo esc_html( $color['label'] ); ?></span>
								</label>
							<?php endforeach; ?>
						</div>
					</div>
				</div>
			<?php endif; ?>
		</fieldset>

		<p class="lpc-customizer__total">
			<span><?php esc_html_e( 'Supplément personnalisation', 'leons-product-customizer' ); ?></span>
			<strong data-lpc-addons-total><?php echo wp_kses_post( wc_price( 0 ) ); ?></strong>
		</p>
		</div>
		<?php endif; ?>
	</div>
</div>
