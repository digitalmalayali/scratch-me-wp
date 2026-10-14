<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }
/** @var array $settings */
?>
<div class="wrap sw-wrap">
	<h1><?php esc_html_e( 'Scratch & Win Settings', 'scratch-win' ); ?></h1>

	<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
		<?php wp_nonce_field( 'sw_save_settings' ); ?>
		<input type="hidden" name="action" value="sw_save_settings">

		<h2 class="title"><?php esc_html_e( 'Business Profile', 'scratch-win' ); ?></h2>
		<table class="form-table">
			<tr>
				<th><label for="business_name"><?php esc_html_e( 'Business Name', 'scratch-win' ); ?></label></th>
				<td><input type="text" id="business_name" name="business_name" class="regular-text" value="<?php echo esc_attr( $settings['business_name'] ); ?>"></td>
			</tr>
			<tr>
				<th><label for="business_url"><?php esc_html_e( 'Main Site URL', 'scratch-win' ); ?></label></th>
				<td><input type="url" id="business_url" name="business_url" class="regular-text" value="<?php echo esc_attr( $settings['business_url'] ); ?>"></td>
			</tr>
			<tr>
				<th><label for="admin_email"><?php esc_html_e( 'Administrative Contact Email', 'scratch-win' ); ?></label></th>
				<td><input type="email" id="admin_email" name="admin_email" class="regular-text" value="<?php echo esc_attr( $settings['admin_email'] ); ?>"></td>
			</tr>
		</table>

		<h2 class="title"><?php esc_html_e( 'Localization', 'scratch-win' ); ?></h2>
		<table class="form-table">
			<tr>
				<th><label for="timezone"><?php esc_html_e( 'Time Zone', 'scratch-win' ); ?></label></th>
				<td>
					<select id="timezone" name="timezone">
						<?php echo wp_kses( wp_timezone_choice( $settings['timezone'] ), array( 'option' => array( 'value' => true, 'selected' => true ), 'optgroup' => array( 'label' => true ) ) ); ?>
					</select>
					<p class="description"><?php esc_html_e( 'Used for dates shown to visitors, in the Leads list, CSV export and email alerts.', 'scratch-win' ); ?></p>
				</td>
			</tr>
			<tr>
				<th><label for="date_format"><?php esc_html_e( 'Date Format', 'scratch-win' ); ?></label></th>
				<td>
					<select id="date_format" name="date_format">
						<option value="MM/DD/YYYY" <?php selected( $settings['date_format'], 'MM/DD/YYYY' ); ?>>MM/DD/YYYY</option>
						<option value="DD/MM/YYYY" <?php selected( $settings['date_format'], 'DD/MM/YYYY' ); ?>>DD/MM/YYYY</option>
					</select>
				</td>
			</tr>
			<tr>
				<th><?php esc_html_e( 'Phone Format', 'scratch-win' ); ?></th>
				<td>
					<input type="hidden" name="default_country" value="in">
					<p class="description"><?php esc_html_e( 'Phone capture is fixed to India (+91) with 10-digit mobile number entry.', 'scratch-win' ); ?></p>
				</td>
			</tr>
		</table>

		<h2 class="title"><?php esc_html_e( 'Notifications', 'scratch-win' ); ?></h2>
		<table class="form-table">
			<tr>
				<th><?php esc_html_e( 'Admin Alerts', 'scratch-win' ); ?></th>
				<td><label><input type="checkbox" name="notify_admin" value="1" <?php checked( $settings['notify_admin'], 1 ); ?>> <?php esc_html_e( 'Email me when a new phone lead is captured', 'scratch-win' ); ?></label></td>
			</tr>
		</table>

		<h2 class="title"><?php esc_html_e( 'Anti-abuse', 'scratch-win' ); ?></h2>
		<table class="form-table">
			<tr>
				<th><label for="rate_limit"><?php esc_html_e( 'Max new entries per IP per hour', 'scratch-win' ); ?></label></th>
				<td>
					<input type="number" id="rate_limit" name="rate_limit" min="0" max="10000" step="1" class="small-text" value="<?php echo (int) $settings['rate_limit']; ?>">
					<p class="description"><?php esc_html_e( 'Stops one device or bot from entering hundreds of numbers to drain your prizes. Mobile networks often share one IP between many people, so keep this at 20 or more. 0 = no limit. Returning visitors are not counted.', 'scratch-win' ); ?></p>
				</td>
			</tr>
			<tr>
				<th><?php esc_html_e( 'Proxy / Cloudflare', 'scratch-win' ); ?></th>
				<td>
					<label><input type="checkbox" name="trust_proxy" value="1" <?php checked( (int) $settings['trust_proxy'], 1 ); ?>> <?php esc_html_e( 'My site is behind Cloudflare or another proxy / load balancer', 'scratch-win' ); ?></label>
					<p class="description"><?php esc_html_e( 'Tick this only if it is true. It makes the plugin read the visitor\'s real IP from the proxy headers; otherwise every visitor would appear to share the proxy\'s IP.', 'scratch-win' ); ?></p>
				</td>
			</tr>
		</table>

		<h2 class="title"><?php esc_html_e( 'Data', 'scratch-win' ); ?></h2>
		<table class="form-table">
			<tr>
				<th><?php esc_html_e( 'On plugin deletion', 'scratch-win' ); ?></th>
				<td>
					<label><input type="checkbox" name="delete_on_uninstall" value="1" <?php checked( (int) $settings['delete_on_uninstall'], 1 ); ?>> <?php esc_html_e( 'Delete all campaigns, prizes and leads when the plugin is deleted', 'scratch-win' ); ?></label>
					<p class="description"><?php esc_html_e( 'Leave unticked to keep your data if you delete and reinstall the plugin. Deactivating never deletes data.', 'scratch-win' ); ?></p>
				</td>
			</tr>
		</table>

		<?php submit_button( __( 'Save Settings', 'scratch-win' ) ); ?>
	</form>
</div>
