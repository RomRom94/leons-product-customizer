<?php
/**
 * Template customizer produit.
 *
 * @package LPC
 *
 * @var WC_Product           $product
 * @var array<string, mixed> $config
 * @var string               $placeholder_src
 */

use LPC\Personalization\Style_Options;

defined( 'ABSPATH' ) || exit;

$views = isset( $config['views'] ) && is_array( $config['views'] ) ? $config['views'] : array();
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
?>

<div class="lpc-customizer" id="lpc-customizer" data-lpc-customizer>
	<h3 class="lpc-customizer__title">
		<span class="lpc-customizer__title-icon" aria-hidden="true"><i class="fas fa-pen"></i></span>
		<?php esc_html_e( 'Personnalisation', 'leons-product-customizer' ); ?>
	</h3>

	<div class="lpc-customizer__body">
		<div class="lpc-customizer__previews">
			<?php foreach ( $views as $view_name => $view ) : ?>
				<?php
				if ( ! isset( $view_js_targets[ $view_name ] ) ) {
					continue;
				}
				$targets    = $view_js_targets[ $view_name ];
				$view_label = isset( $view['label'] ) ? (string) $view['label'] : $view_name;
				?>
				<div class="lpc-customizer__view" data-lpc-view="<?php echo esc_attr( $view_name ); ?>">
					<p class="lpc-customizer__view-label"><?php echo esc_html( $view_label ); ?></p>
					<div class="lpc-customizer__canvas-wrap">
						<img
							class="lpc-customizer__image"
							data-lpc-target="<?php echo esc_attr( $targets['image'] ); ?>"
							src="<?php echo esc_url( $placeholder_src ); ?>"
							data-placeholder-src="<?php echo esc_url( $placeholder_src ); ?>"
							alt=""
						/>
						<canvas class="lpc-customizer__canvas" data-lpc-target="<?php echo esc_attr( $targets['canvas'] ); ?>" aria-hidden="true"></canvas>
					</div>
				</div>
			<?php endforeach; ?>
		</div>

		<?php if ( $zones ) : ?>
		<div class="lpc-customizer__form">
		<fieldset class="lpc-customizer__fields">
			<legend class="lpc-customizer__fields-legend screen-reader-text"><?php esc_html_e( 'Personnalisation', 'leons-product-customizer' ); ?></legend>

			<?php foreach ( $zones as $zone_id => $zone ) : ?>
				<?php
				if ( ! is_array( $zone ) || empty( $zone['type'] ) ) {
					continue;
				}
				$zone_id   = sanitize_key( (string) $zone_id );
				$label     = isset( $zone['label'] ) ? (string) $zone['label'] : $zone_id;
				$price     = isset( $zone['price'] ) ? (float) $zone['price'] : 0.0;
				$required  = ! empty( $zone['required'] );
				$max_length = isset( $zone['max_length'] ) ? absint( $zone['max_length'] ) : 0;
				$pattern   = isset( $zone['pattern'] ) ? (string) $zone['pattern'] : '';
				$price_html = $price > 0
					? ' <span class="lpc-customizer__zone-price">+' . wp_kses_post( wc_price( $price ) ) . '</span>'
					: '';
				?>

				<?php if ( $zone['type'] === 'text' ) : ?>
					<p class="form-row lpc-customizer__field">
						<label for="lpc_zone_<?php echo esc_attr( $zone_id ); ?>">
							<?php echo esc_html( $label ); ?>
							<?php echo $price_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- wc_price ?>
							<?php if ( $required ) : ?>
								<abbr class="required" title="<?php esc_attr_e( 'Required', 'woocommerce' ); ?>">*</abbr>
							<?php endif; ?>
						</label>
						<input
							type="text"
							class="input-text lpc-customizer__zone-input"
							id="lpc_zone_<?php echo esc_attr( $zone_id ); ?>"
							name="lpc_zones[<?php echo esc_attr( $zone_id ); ?>]"
							data-lpc-zone="<?php echo esc_attr( $zone_id ); ?>"
							data-lpc-zone-price="<?php echo esc_attr( (string) $price ); ?>"
							value=""
							<?php echo $max_length > 0 ? 'maxlength="' . esc_attr( (string) $max_length ) . '"' : ''; ?>
							<?php echo $pattern !== '' ? 'pattern="' . esc_attr( $pattern ) . '"' : ''; ?>
							<?php echo $required ? 'required' : ''; ?>
						/>
					</p>
				<?php endif; ?>

				<?php if ( $zone['type'] === 'image' ) : ?>
					<p class="form-row lpc-customizer__field lpc-customizer__field--upload">
						<label for="lpc_zone_<?php echo esc_attr( $zone_id ); ?>">
							<?php echo esc_html( $label ); ?>
							<?php echo $price_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- wc_price ?>
							<?php if ( $required ) : ?>
								<abbr class="required" title="<?php esc_attr_e( 'Required', 'woocommerce' ); ?>">*</abbr>
							<?php endif; ?>
						</label>
						<span class="lpc-customizer__upload-row">
							<input
								type="file"
								class="lpc-customizer__zone-input"
								id="lpc_zone_<?php echo esc_attr( $zone_id ); ?>"
								name="lpc_files[<?php echo esc_attr( $zone_id ); ?>]"
								data-lpc-zone="<?php echo esc_attr( $zone_id ); ?>"
								data-lpc-zone-price="<?php echo esc_attr( (string) $price ); ?>"
								accept=".png,.jpg,.jpeg,.svg,image/png,image/jpeg,image/svg+xml"
								<?php echo $required ? 'required' : ''; ?>
							/>
							<button
								type="button"
								class="lpc-customizer__zone-remove"
								data-lpc-zone-remove="<?php echo esc_attr( $zone_id ); ?>"
								hidden
							>
								<i class="fas fa-xmark" aria-hidden="true"></i>
								<span class="screen-reader-text"><?php esc_html_e( 'Retirer le fichier', 'leons-product-customizer' ); ?></span>
							</button>
						</span>
						<span class="lpc-customizer__field-hint"><?php esc_html_e( 'PNG, JPG ou SVG — 2 Mo maximum.', 'leons-product-customizer' ); ?></span>
					</p>
				<?php endif; ?>

			<?php endforeach; ?>

			<?php if ( $has_text_zone ) : ?>
				<p class="form-row lpc-customizer__field lpc-customizer__field--font">
					<label for="lpc_font"><?php esc_html_e( 'Police', 'leons-product-customizer' ); ?></label>
					<select name="lpc_font" id="lpc_font" class="lpc-customizer__font-select" data-lpc-global="font">
						<?php foreach ( Style_Options::FONTS as $font_family => $font_label ) : ?>
							<option value="<?php echo esc_attr( $font_family ); ?>" <?php selected( $font_family, Style_Options::DEFAULT_FONT ); ?>>
								<?php echo esc_html( $font_label ); ?>
							</option>
						<?php endforeach; ?>
					</select>
				</p>

				<div class="lpc-customizer__field lpc-customizer__field--color" role="group" aria-label="<?php esc_attr_e( 'Couleur', 'leons-product-customizer' ); ?>">
					<p class="lpc-customizer__field--color-title"><?php esc_html_e( 'Couleur', 'leons-product-customizer' ); ?></p>
					<div class="lpc-customizer__color-list">
						<?php foreach ( Style_Options::COLORS as $color_slug => $color ) : ?>
							<label class="lpc-customizer__color-swatch">
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
			<?php endif; ?>
		</fieldset>

		<p class="lpc-customizer__total">
			<?php esc_html_e( 'Supplément personnalisation', 'leons-product-customizer' ); ?> :
			<strong data-lpc-addons-total><?php echo wp_kses_post( wc_price( 0 ) ); ?></strong>
		</p>
		</div>
		<?php endif; ?>
	</div>
</div>
