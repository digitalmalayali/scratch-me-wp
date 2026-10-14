<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }
/**
 * @var object|null $campaign
 * @var array       $prizes
 */
$is_new = empty( $campaign );
$campaign_id = $is_new ? 0 : (int) $campaign->id;

$title                = $is_new ? '' : $campaign->title;
$promo_text           = $is_new ? '' : $campaign->promo_text;
$template             = $is_new ? 'default' : $campaign->template;
$background_image     = $is_new ? '' : $campaign->background_image;
$scratch_layer_image  = $is_new ? '' : $campaign->scratch_layer_image;
$status               = $is_new ? 'active' : $campaign->status;
$show_footer_credit   = $is_new ? 1 : (int) $campaign->show_footer_credit;
$require_phone_before = $is_new ? 1 : (int) $campaign->require_phone_before;
$sold_out_mode        = ( ! $is_new && isset( $campaign->sold_out_mode ) && 'lose' === $campaign->sold_out_mode ) ? 'lose' : 'redistribute';
$win_message          = $is_new ? __( 'Congratulations! You won: {prize}', 'scratch-win' ) : $campaign->win_message;
$lose_message         = $is_new ? __( 'So close! Better luck next time.', 'scratch-win' ) : $campaign->lose_message;
$already_won_message  = ( $is_new || empty( $campaign->already_won_message ) ) ? SW_Settings::default_already_won_message() : $campaign->already_won_message;
$already_lost_message = ( $is_new || empty( $campaign->already_lost_message ) ) ? SW_Settings::default_already_lost_message() : $campaign->already_lost_message;

if ( empty( $prizes ) ) {
	$prizes = array(
		(object) array(
			'id' => 0, 'label' => '20% Off Coupon', 'prize_type' => 'Common Coupon', 'coupon_code' => '20OFF',
			'description' => '', 'image_url' => '', 'chance' => 5, 'is_winning' => 1, 'is_active' => 1,
			'stock_total' => null, 'winners_count' => 0,
		),
		(object) array(
			'id' => 0, 'label' => '15% Off Coupon', 'prize_type' => 'Common Coupon', 'coupon_code' => '15OFF',
			'description' => '', 'image_url' => '', 'chance' => 20, 'is_winning' => 1, 'is_active' => 1,
			'stock_total' => null, 'winners_count' => 0,
		),
		(object) array(
			'id' => 0, 'label' => '10% Off Coupon', 'prize_type' => 'Common Coupon', 'coupon_code' => '10OFF',
			'description' => '', 'image_url' => '', 'chance' => 25, 'is_winning' => 1, 'is_active' => 1,
			'stock_total' => null, 'winners_count' => 0,
		),
		(object) array(
			'id' => 0, 'label' => 'No Prize', 'prize_type' => 'No Prize', 'coupon_code' => '',
			'description' => '', 'image_url' => '', 'chance' => 50, 'is_winning' => 0, 'is_active' => 1,
			'stock_total' => null, 'winners_count' => 0,
		),
	);
}

// Footer total = overall win rate (active winning prizes only), matching the
// live JS readout.
$total_chance = 0;
foreach ( $prizes as $p ) {
	if ( ! empty( $p->is_winning ) && ( ! isset( $p->is_active ) || (int) $p->is_active ) ) {
		$total_chance += (float) $p->chance;
	}
}

/**
 * One editable prize row. Fields are named prizes[<key>][field] so each row's
 * checkboxes always stay attached to that row.
 *
 * @param object     $p   Prize (or an empty template object).
 * @param int|string $key Row key; '__INDEX__' in the JS template.
 */
$sw_prize_row = function( $p, $key ) {
	$name        = function( $field ) use ( $key ) {
		return 'prizes[' . $key . '][' . $field . ']';
	};
	$image_url   = $p->image_url ?? '';
	$stock_total = ( isset( $p->stock_total ) && null !== $p->stock_total && '' !== $p->stock_total ) ? (int) $p->stock_total : '';
	$is_active   = ! isset( $p->is_active ) || (int) $p->is_active;
	$is_winning  = ! empty( $p->is_winning );
	$type        = $p->prize_type ?? 'Common Coupon';
	?>
	<tr class="sw-prize-row">
		<td>
			<input type="hidden" name="<?php echo esc_attr( $name( 'id' ) ); ?>" value="<?php echo (int) ( $p->id ?? 0 ); ?>">
			<img class="sw-prize-thumb" src="<?php echo esc_url( $image_url ); ?>" alt="" style="<?php echo $image_url ? '' : 'display:none;'; ?>width:40px;height:40px;object-fit:cover;border-radius:4px;">
			<input type="hidden" name="<?php echo esc_attr( $name( 'image_url' ) ); ?>" class="sw-prize-image-field" value="<?php echo esc_attr( $image_url ); ?>">
			<button type="button" class="button button-small sw-media-upload" data-target-class="sw-prize-image-field"><?php esc_html_e( 'Choose', 'scratch-win' ); ?></button>
			<button type="button" class="button-link sw-remove-image" style="<?php echo $image_url ? '' : 'display:none;'; ?>"><?php esc_html_e( 'Remove', 'scratch-win' ); ?></button>
		</td>
		<td>
			<input type="text" name="<?php echo esc_attr( $name( 'label' ) ); ?>" class="regular-text sw-prize-label" value="<?php echo esc_attr( $p->label ?? '' ); ?>" placeholder="<?php esc_attr_e( 'e.g. Smart Watch', 'scratch-win' ); ?>">
			<br>
			<input type="text" name="<?php echo esc_attr( $name( 'coupon_code' ) ); ?>" class="small-text sw-prize-coupon" style="margin-top:4px;width:100%;" value="<?php echo esc_attr( $p->coupon_code ?? '' ); ?>" placeholder="<?php esc_attr_e( 'Coupon code (optional)', 'scratch-win' ); ?>">
		</td>
		<td>
			<select name="<?php echo esc_attr( $name( 'prize_type' ) ); ?>" class="sw-prize-type">
				<?php foreach ( SW_Leads::PRIZE_TYPES as $t ) : ?>
					<option value="<?php echo esc_attr( $t ); ?>" <?php selected( $type, $t ); ?>><?php echo esc_html( $t ); ?></option>
				<?php endforeach; ?>
			</select>
		</td>
		<td><textarea name="<?php echo esc_attr( $name( 'description' ) ); ?>" rows="2" class="large-text sw-prize-description" placeholder="<?php esc_attr_e( 'Shown to the winner, e.g. how to redeem this prize.', 'scratch-win' ); ?>"><?php echo esc_textarea( $p->description ?? '' ); ?></textarea></td>
		<td><input type="number" name="<?php echo esc_attr( $name( 'chance' ) ); ?>" class="sw-chance-input" min="0" max="100" step="0.01" value="<?php echo esc_attr( isset( $p->chance ) ? (float) $p->chance : 0 ); ?>"></td>
		<td>
			<input type="number" name="<?php echo esc_attr( $name( 'stock_total' ) ); ?>" class="sw-prize-stock" min="0" step="1" value="<?php echo esc_attr( $stock_total ); ?>" placeholder="∞">
			<?php if ( '' !== $stock_total && isset( $p->stock_remaining ) && null !== $p->stock_remaining ) : ?>
				<?php if ( (int) $p->stock_remaining <= 0 ) : ?>
					<span class="sw-sold-out"><?php esc_html_e( 'Sold out', 'scratch-win' ); ?></span>
				<?php else : ?>
					<span class="description" style="display:block;font-size:11px;">
						<?php
						/* translators: %d: units left */
						printf( esc_html__( '%d left', 'scratch-win' ), (int) $p->stock_remaining );
						?>
					</span>
				<?php endif; ?>
			<?php endif; ?>
		</td>
		<td class="sw-winners-count"><?php echo (int) ( $p->winners_count ?? 0 ); ?></td>
		<td>
			<label class="sw-toggle" title="<?php esc_attr_e( 'Active', 'scratch-win' ); ?>">
				<input type="checkbox" name="<?php echo esc_attr( $name( 'is_active' ) ); ?>" class="sw-prize-active" value="1" <?php checked( $is_active ); ?>>
				<span class="sw-toggle-slider"></span>
			</label>
			<br>
			<label style="font-size:11px;"><input type="checkbox" name="<?php echo esc_attr( $name( 'is_winning' ) ); ?>" class="sw-prize-winning" value="1" <?php checked( $is_winning ); ?> <?php disabled( 'No Prize' === $type ); ?>> <?php esc_html_e( 'Is win', 'scratch-win' ); ?></label>
		</td>
		<td><button type="button" class="button sw-remove-prize"><?php esc_html_e( 'Delete', 'scratch-win' ); ?></button></td>
	</tr>
	<?php
};
?>
<div class="wrap sw-wrap">
	<h1><?php echo $is_new ? esc_html__( 'New Campaign', 'scratch-win' ) : esc_html__( 'Edit Campaign', 'scratch-win' ); ?></h1>

	<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
		<?php wp_nonce_field( 'sw_save_campaign' ); ?>
		<input type="hidden" name="action" value="sw_save_campaign">
		<input type="hidden" name="campaign_id" value="<?php echo (int) $campaign_id; ?>">

		<h2 class="title"><?php esc_html_e( 'Campaign & Visual Customization', 'scratch-win' ); ?></h2>
		<table class="form-table">
			<tr>
				<th><label for="title"><?php esc_html_e( 'Campaign Title', 'scratch-win' ); ?></label></th>
				<td><input type="text" id="title" name="title" class="regular-text" value="<?php echo esc_attr( $title ); ?>" required></td>
			</tr>
			<tr>
				<th><label for="promo_text"><?php esc_html_e( 'Promotional Text', 'scratch-win' ); ?></label></th>
				<td><textarea id="promo_text" name="promo_text" rows="3" class="large-text"><?php echo esc_textarea( $promo_text ); ?></textarea></td>
			</tr>
			<tr>
				<th><label for="template"><?php esc_html_e( 'Template', 'scratch-win' ); ?></label></th>
				<td>
					<select id="template" name="template">
						<option value="default" <?php selected( $template, 'default' ); ?>><?php esc_html_e( 'Standard Promotional', 'scratch-win' ); ?></option>
						<option value="holiday" <?php selected( $template, 'holiday' ); ?>><?php esc_html_e( 'Seasonal / Holiday', 'scratch-win' ); ?></option>
						<option value="custom" <?php selected( $template, 'custom' ); ?>><?php esc_html_e( 'Custom (use images below)', 'scratch-win' ); ?></option>
					</select>
				</td>
			</tr>
			<tr>
				<th><label for="background_image"><?php esc_html_e( 'Background Image', 'scratch-win' ); ?></label></th>
				<td>
					<input type="text" id="background_image" name="background_image" class="regular-text sw-media-field" value="<?php echo esc_attr( $background_image ); ?>">
					<button type="button" class="button sw-media-upload" data-target="background_image"><?php esc_html_e( 'Choose Image', 'scratch-win' ); ?></button>
				</td>
			</tr>
			<tr>
				<th><label for="scratch_layer_image"><?php esc_html_e( 'Scratch Layer Image', 'scratch-win' ); ?></label></th>
				<td>
					<input type="text" id="scratch_layer_image" name="scratch_layer_image" class="regular-text sw-media-field" value="<?php echo esc_attr( $scratch_layer_image ); ?>">
					<button type="button" class="button sw-media-upload" data-target="scratch_layer_image"><?php esc_html_e( 'Choose Image', 'scratch-win' ); ?></button>
					<p class="description"><?php esc_html_e( 'The foil/coating layer users scratch off. Leave blank to use the default silver texture.', 'scratch-win' ); ?></p>
				</td>
			</tr>
			<tr>
				<th><label for="status"><?php esc_html_e( 'Status', 'scratch-win' ); ?></label></th>
				<td>
					<select id="status" name="status">
						<option value="active" <?php selected( $status, 'active' ); ?>><?php esc_html_e( 'Active', 'scratch-win' ); ?></option>
						<option value="paused" <?php selected( $status, 'paused' ); ?>><?php esc_html_e( 'Paused', 'scratch-win' ); ?></option>
					</select>
				</td>
			</tr>
			<tr>
				<th><?php esc_html_e( 'Branding', 'scratch-win' ); ?></th>
				<td>
					<label><input type="checkbox" name="show_footer_credit" value="1" <?php checked( $show_footer_credit, 1 ); ?>> <?php esc_html_e( 'Show "Powered by Scratch & Win" footer credit', 'scratch-win' ); ?></label>
				</td>
			</tr>
		</table>

		<h2 class="title"><?php esc_html_e( 'Lead Capture & Rewards', 'scratch-win' ); ?></h2>
		<table class="form-table">
			<tr>
				<th><?php esc_html_e( 'Phone Number Gate', 'scratch-win' ); ?></th>
				<td>
					<label><input type="checkbox" name="require_phone_before" value="1" <?php checked( $require_phone_before, 1 ); ?>> <?php esc_html_e( 'Require phone number BEFORE the scratch card is revealed (unchecked = ask after revealing prize)', 'scratch-win' ); ?></label>
					<p class="description"><?php esc_html_e( 'Phone numbers are captured in India format only: a fixed +91 prefix with a 10-digit mobile number.', 'scratch-win' ); ?></p>
				</td>
			</tr>
			<tr>
				<th><label for="sold_out_mode"><?php esc_html_e( 'When a prize runs out', 'scratch-win' ); ?></label></th>
				<td>
					<select id="sold_out_mode" name="sold_out_mode">
						<option value="redistribute" <?php selected( $sold_out_mode, 'redistribute' ); ?>><?php esc_html_e( 'Give its chance to the prizes that are still in stock (recommended)', 'scratch-win' ); ?></option>
						<option value="lose" <?php selected( $sold_out_mode, 'lose' ); ?>><?php esc_html_e( 'Turn its chance into "No Prize"', 'scratch-win' ); ?></option>
					</select>
					<p class="description"><?php esc_html_e( 'Example: Total Chance is 100% and a 30% prize sells out. With the first option visitors still always win one of the remaining prizes; with the second option 30% of visitors get "No Prize". Once every prize is sold out, everyone gets "No Prize".', 'scratch-win' ); ?></p>
				</td>
			</tr>
			<tr>
				<th><label for="win_message"><?php esc_html_e( 'Win Message', 'scratch-win' ); ?></label></th>
				<td><input type="text" id="win_message" name="win_message" class="regular-text" value="<?php echo esc_attr( $win_message ); ?>"> <p class="description"><?php esc_html_e( 'Use {prize} for the prize name, {coupon} for its coupon code and {phone} for the visitor\'s mobile number.', 'scratch-win' ); ?></p></td>
			</tr>
			<tr>
				<th><label for="lose_message"><?php esc_html_e( 'Lose Message', 'scratch-win' ); ?></label></th>
				<td><input type="text" id="lose_message" name="lose_message" class="regular-text" value="<?php echo esc_attr( $lose_message ); ?>"></td>
			</tr>
			<tr>
				<th><label for="already_won_message"><?php esc_html_e( 'Already Won Message', 'scratch-win' ); ?></label></th>
				<td><input type="text" id="already_won_message" name="already_won_message" class="large-text" value="<?php echo esc_attr( $already_won_message ); ?>"> <p class="description"><?php esc_html_e( 'Shown when a number that already won plays again. Use {prize} for the prize name, {coupon} for its coupon code, {phone} for their mobile number and {date} for the day they won (e.g. 22 March 2027).', 'scratch-win' ); ?></p></td>
			</tr>
			<tr>
				<th><label for="already_lost_message"><?php esc_html_e( 'Already Played Message (no prize)', 'scratch-win' ); ?></label></th>
				<td><input type="text" id="already_lost_message" name="already_lost_message" class="large-text" value="<?php echo esc_attr( $already_lost_message ); ?>"> <p class="description"><?php esc_html_e( 'Shown when a number that did not win plays again. Use {date} for the day they played and {phone} for their mobile number.', 'scratch-win' ); ?></p></td>
			</tr>
		</table>

		<?php
		$questions = $is_new ? array() : SW_Questions::for_campaign( $campaign );
		include SW_PLUGIN_DIR . 'admin/views/question-builder.php';
		?>

		<h3><?php esc_html_e( 'Prizes', 'scratch-win' ); ?></h3>

		<div class="notice notice-warning inline sw-prize-notice">
			<p><?php esc_html_e( 'Chance (%) is the chance of winning each prize. The Total Chance below is your overall win rate and must not go above 100%; the rest of the time the visitor gets a "No Prize" result. Leave Stock blank for unlimited. If you change a prize\'s Stock, the units already won are subtracted from the new total (e.g. Stock 10 with 3 winners leaves 7). Winner(s) counts are never reset.', 'scratch-win' ); ?></p>
		</div>

		<div class="sw-import-row">
			<a href="<?php echo esc_url( SW_PLUGIN_URL . 'assets/sample-prize-list.csv' ); ?>" class="button" download>
				<?php esc_html_e( '⬇ Download Sample Prize List Format (CSV)', 'scratch-win' ); ?>
			</a>
			<label class="button" for="sw-import-file"><?php esc_html_e( '⬆ Import Prizes from CSV', 'scratch-win' ); ?></label>
			<input type="file" id="sw-import-file" accept=".csv,text/csv">
			<span class="description" id="sw-import-status" aria-live="polite"></span>
		</div>

		<table class="wp-list-table widefat fixed striped" id="sw-prizes-table">
			<thead>
				<tr>
					<th style="width:70px;"><?php esc_html_e( 'Image', 'scratch-win' ); ?></th>
					<th><?php esc_html_e( 'Prize Title', 'scratch-win' ); ?></th>
					<th style="width:150px;"><?php esc_html_e( 'Prize Type', 'scratch-win' ); ?></th>
					<th><?php esc_html_e( 'Description', 'scratch-win' ); ?></th>
					<th style="width:100px;"><?php esc_html_e( 'Chance (%)', 'scratch-win' ); ?></th>
					<th style="width:110px;"><?php esc_html_e( 'Stock / Count', 'scratch-win' ); ?></th>
					<th style="width:90px;"><?php esc_html_e( 'Winner(s)', 'scratch-win' ); ?></th>
					<th style="width:70px;"><?php esc_html_e( 'Active', 'scratch-win' ); ?></th>
					<th style="width:60px;"></th>
				</tr>
			</thead>
			<tbody>
			<?php
			foreach ( array_values( $prizes ) as $i => $p ) {
				$sw_prize_row( $p, $i );
			}
			?>
			</tbody>
			<tfoot>
				<tr>
					<td colspan="4" style="text-align:right;"><strong><?php esc_html_e( 'Total Chance (overall win rate):', 'scratch-win' ); ?></strong></td>
					<td><strong id="sw-total-chance"><?php echo esc_html( number_format( $total_chance, 2 ) ); ?></strong>%</td>
					<td colspan="4"></td>
				</tr>
			</tfoot>
		</table>
		<p><button type="button" class="button" id="sw-add-prize"><?php esc_html_e( '+ Add Prize', 'scratch-win' ); ?></button></p>

		<?php
		// "Right now" panel: the real odds a visitor has at this moment, after
		// sold-out and paused prizes are taken into account.
		if ( ! $is_new && ! empty( $campaign ) ) :
			$saved_prizes = SW_Leads::get_prizes( $campaign_id );
			$live         = SW_Leads::effective_chances( $campaign, array(), $saved_prizes );
			$live_win     = min( 100, array_sum( $live ) );
			$sold_out     = array();
			foreach ( $saved_prizes as $sp ) {
				if ( (int) $sp->is_active && (int) $sp->is_winning && ! SW_Leads::prize_available( $sp ) ) {
					$sold_out[] = $sp->label;
				}
			}
			$labels = array();
			foreach ( $saved_prizes as $sp ) {
				if ( isset( $live[ (int) $sp->id ] ) ) {
					$labels[] = sprintf( '%s %s%%', $sp->label, number_format_i18n( $live[ (int) $sp->id ], 1 ) );
				}
			}
			$notice_class = $live_win <= 0 ? 'notice-error' : ( $sold_out ? 'notice-warning' : 'notice-info' );
			?>
			<div class="notice <?php echo esc_attr( $notice_class ); ?> inline sw-live-odds">
				<p>
					<strong><?php esc_html_e( 'Right now:', 'scratch-win' ); ?></strong>
					<?php
					/* translators: 1: win percentage, 2: No Prize percentage */
					echo esc_html( sprintf( __( 'visitors win %1$s%% of the time and get "No Prize" %2$s%% of the time.', 'scratch-win' ), number_format_i18n( $live_win, 1 ), number_format_i18n( 100 - $live_win, 1 ) ) );
					?>
				</p>
				<?php if ( $labels ) : ?>
					<p><?php echo esc_html( implode( '  ·  ', $labels ) ); ?></p>
				<?php endif; ?>
				<?php if ( $sold_out ) : ?>
					<p>
						<?php
						/* translators: %s: list of prize names */
						echo esc_html( sprintf( __( 'Sold out: %s. Raise their Stock / Count to give them out again.', 'scratch-win' ), implode( ', ', $sold_out ) ) );
						?>
					</p>
				<?php endif; ?>
				<?php if ( $live_win <= 0 ) : ?>
					<p><?php esc_html_e( 'No winning prize is available, so every visitor gets "No Prize". Add stock or activate a prize.', 'scratch-win' ); ?></p>
				<?php endif; ?>
			</div>
		<?php endif; ?>

		<?php submit_button( $is_new ? __( 'Create Campaign', 'scratch-win' ) : __( 'Save Campaign', 'scratch-win' ) ); ?>
		<?php // Must stay the LAST field: its absence means the server cut the form short. ?>
		<input type="hidden" name="sw_form_end" value="1">
	</form>

	<?php if ( ! $is_new ) : ?>
		<p><?php esc_html_e( 'Shortcode:', 'scratch-win' ); ?> <code>[scratch_win id="<?php echo (int) $campaign_id; ?>"]</code></p>
	<?php endif; ?>
</div>

<template id="sw-prize-row-template">
	<?php $sw_prize_row( (object) array( 'is_winning' => 1, 'is_active' => 1 ), '__INDEX__' ); ?>
</template>
