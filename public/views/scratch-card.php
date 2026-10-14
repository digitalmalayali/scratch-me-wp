<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }
/** @var object $campaign */
$require_before = (int) $campaign->require_phone_before === 1;
$bg_image       = ! empty( $campaign->background_image ) ? $campaign->background_image : '';
$scratch_image  = ! empty( $campaign->scratch_layer_image ) ? $campaign->scratch_layer_image : '';
$sw_questions   = SW_Questions::visible( SW_Questions::for_campaign( $campaign ) );
$sw_uid         = (int) $campaign->id . '-' . wp_rand( 1000, 999999 );

/**
 * Phone form markup, shared by the "before" and "after" gates.
 */
$sw_phone_form = function( $button_text, $with_questions ) use ( $sw_questions, $sw_uid ) {
	?>
	<form class="sw-phone-form" novalidate>
		<?php $sw_phone_id = 'sw-phone-' . $sw_uid . ( $with_questions ? '-q' : '' ); ?>
		<?php if ( $with_questions && $sw_questions ) : ?>
			<label class="sw-q-label sw-phone-label" for="<?php echo esc_attr( $sw_phone_id ); ?>"><?php esc_html_e( 'Mobile number', 'scratch-win' ); ?> <span class="sw-q-required" aria-hidden="true">*</span></label>
		<?php endif; ?>
		<div class="sw-phone-row">
			<span class="sw-country-badge" aria-hidden="true">🇮🇳 +91</span>
			<input type="tel" id="<?php echo esc_attr( $sw_phone_id ); ?>" class="sw-phone-input" inputmode="numeric" autocomplete="tel-national"
				aria-label="<?php esc_attr_e( '10-digit mobile number', 'scratch-win' ); ?>"
				placeholder="<?php esc_attr_e( '10-digit mobile number', 'scratch-win' ); ?>">
		</div>
		<?php
		if ( $with_questions ) {
			SW_Questions::render_fields( $sw_questions, $sw_uid );
		}
		?>
		<div class="sw-hp" aria-hidden="true">
			<label><?php esc_html_e( 'Leave this field empty', 'scratch-win' ); ?>
				<input type="text" name="sw_hp" class="sw-hp-input" tabindex="-1" autocomplete="off" value="">
			</label>
		</div>
		<button type="submit" class="sw-btn sw-unlock-btn"><?php echo esc_html( $button_text ); ?></button>
	</form>
	<p class="sw-error" role="alert" style="display:none;"></p>
	<?php
};
?>
<div class="sw-container sw-template-<?php echo esc_attr( $campaign->template ); ?>"
	 data-campaign-id="<?php echo (int) $campaign->id; ?>"
	 data-require-before="<?php echo $require_before ? '1' : '0'; ?>"
	 data-scratch-image="<?php echo esc_url( $scratch_image ); ?>">

	<h3 class="sw-title"><?php echo esc_html( $campaign->title ); ?></h3>
	<?php if ( ! empty( $campaign->promo_text ) ) : ?>
		<p class="sw-promo-text"><?php echo nl2br( esc_html( $campaign->promo_text ) ); ?></p>
	<?php endif; ?>

	<div class="sw-stage" <?php echo $bg_image ? 'style="background-image:url(\'' . esc_url( $bg_image ) . '\');"' : ''; ?>>

		<!-- Phone gate shown BEFORE scratching (default) -->
		<div class="sw-phone-gate sw-phone-gate-before" <?php echo $require_before ? '' : 'style="display:none;"'; ?>>
			<p class="sw-gate-copy"><?php esc_html_e( 'Enter your mobile number to unlock your scratch card:', 'scratch-win' ); ?></p>
			<?php $sw_phone_form( __( 'Unlock My Card', 'scratch-win' ), $require_before ); ?>
		</div>

		<!-- Scratch canvas area -->
		<div class="sw-scratch-wrapper" <?php echo $require_before ? 'style="display:none;"' : ''; ?>>
			<div class="sw-prize-reveal">
				<div>
					<img class="sw-prize-image" src="" alt="" style="display:none;">
					<span class="sw-prize-label"></span>
				</div>
			</div>
			<canvas class="sw-scratch-canvas" width="320" height="200" aria-label="<?php esc_attr_e( 'Scratch card', 'scratch-win' ); ?>"></canvas>
		</div>
		<p class="sw-scratch-hint" <?php echo $require_before ? 'style="display:none;"' : ''; ?>><?php esc_html_e( 'Scratch the card with your finger or mouse!', 'scratch-win' ); ?></p>

		<!-- Phone gate shown AFTER scratching (alt flow) -->
		<div class="sw-phone-gate sw-phone-gate-after" style="display:none;">
			<p class="sw-gate-copy"><?php esc_html_e( 'Enter your mobile number to see and claim your prize:', 'scratch-win' ); ?></p>
			<?php $sw_phone_form( __( 'Claim My Prize', 'scratch-win' ), ! $require_before ); ?>
		</div>

		<!-- Result message -->
		<div class="sw-result" style="display:none;" aria-live="polite">
			<p class="sw-returning-badge" style="display:none;"><?php esc_html_e( 'Welcome back!', 'scratch-win' ); ?></p>
			<img class="sw-result-image" src="" alt="" style="display:none;">
			<p class="sw-result-message"></p>
			<p class="sw-result-description" style="display:none;"></p>
			<p class="sw-result-coupon" style="display:none;"></p>
		</div>
	</div>

	<?php if ( ! empty( $campaign->show_footer_credit ) ) : ?>
		<p class="sw-footer-credit"><?php esc_html_e( 'Powered by Scratch & Win', 'scratch-win' ); ?></p>
	<?php endif; ?>
</div>
