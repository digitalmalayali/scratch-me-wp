<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

class SW_Notifications {

	public static function notify_admin_new_lead( $campaign, $phone_e164, $prize_label, $result, array $answers = array() ) {
		if ( ! SW_Settings::get( 'notify_admin', 1 ) ) {
			return;
		}

		$to = SW_Settings::get( 'admin_email', get_option( 'admin_email' ) );
		if ( empty( $to ) || ! is_email( $to ) ) {
			return;
		}

		$business = SW_Settings::get( 'business_name', get_bloginfo( 'name' ) );

		/* translators: %s: business name */
		$subject = sprintf( __( '[%s] New Scratch & Win lead captured', 'scratch-win' ), wp_specialchars_decode( $business, ENT_QUOTES ) );

		$message  = __( 'A new phone lead was captured via Scratch & Win.', 'scratch-win' ) . "\n\n";
		/* translators: %s: campaign title */
		$message .= sprintf( __( 'Campaign: %s', 'scratch-win' ), $campaign->title ) . "\n";
		/* translators: %s: phone number */
		$message .= sprintf( __( 'Phone: %s', 'scratch-win' ), $phone_e164 ) . "\n";
		/* translators: %s: WIN or LOSE */
		$message .= sprintf( __( 'Result: %s', 'scratch-win' ), strtoupper( $result ) ) . "\n";
		/* translators: %s: prize name */
		$message .= sprintf( __( 'Prize: %s', 'scratch-win' ), $prize_label ) . "\n";
		/* translators: %s: date and time */
		$message .= sprintf( __( 'Time: %s', 'scratch-win' ), SW_Settings::format_timestamp( time() ) ) . "\n\n";
		if ( $answers ) {
			$message .= __( 'Answers:', 'scratch-win' ) . "\n" . SW_Questions::answers_text( $answers ) . "\n\n";
		}
		$message .= admin_url( 'admin.php?page=scratch-win-leads&campaign_id=' . (int) $campaign->id ) . "\n";

		wp_mail( $to, $subject, $message );
	}
}
