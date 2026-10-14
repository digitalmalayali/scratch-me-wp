<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * Handles the AJAX flow for phone submission:
 * - Rejects bots (honeypot) and floods (per-IP rate limit).
 * - Validates the phone number (India-only, stored as +91XXXXXXXXXX).
 * - If this phone already entered the campaign, returns their original result
 *   instead of re-rolling (returning_visitor = true).
 * - Otherwise draws a prize, atomically claims its stock, stores the lead and
 *   notifies the admin.
 */
class SW_Ajax {

	/** How many times to redraw if a drawn prize sold out a split second earlier. */
	const MAX_DRAW_ATTEMPTS = 5;

	public function __construct() {
		add_action( 'wp_ajax_sw_submit_phone', array( $this, 'submit_phone' ) );
		add_action( 'wp_ajax_nopriv_sw_submit_phone', array( $this, 'submit_phone' ) );

		// Fresh nonce for pages served from a full-page cache.
		add_action( 'wp_ajax_sw_get_nonce', array( $this, 'get_nonce' ) );
		add_action( 'wp_ajax_nopriv_sw_get_nonce', array( $this, 'get_nonce' ) );
	}

	public function get_nonce() {
		nocache_headers();
		wp_send_json_success( array( 'nonce' => wp_create_nonce( 'sw_scratch_nonce' ) ) );
	}

	public function submit_phone() {
		nocache_headers();

		if ( ! check_ajax_referer( 'sw_scratch_nonce', 'nonce', false ) ) {
			wp_send_json_error(
				array(
					'code'    => 'bad_nonce',
					'message' => __( 'Your session expired. Please try again.', 'scratch-win' ),
				),
				403
			);
		}

		// Honeypot: real visitors never see or fill this field.
		if ( ! empty( $_POST['sw_hp'] ) ) {
			wp_send_json_error( array( 'message' => __( 'Something went wrong. Please try again.', 'scratch-win' ) ), 400 );
		}

		$campaign_id = isset( $_POST['campaign_id'] ) ? absint( $_POST['campaign_id'] ) : 0;
		$raw_input   = isset( $_POST['raw_input'] ) ? substr( sanitize_text_field( wp_unslash( $_POST['raw_input'] ) ), 0, 64 ) : '';

		$campaign = $campaign_id ? SW_Leads::get_campaign( $campaign_id ) : null;
		if ( ! $campaign || 'active' !== $campaign->status ) {
			wp_send_json_error( array( 'message' => __( 'This campaign is no longer active.', 'scratch-win' ) ) );
		}

		$phone_e164 = SW_Leads::normalize_indian_mobile( $raw_input );
		if ( ! $phone_e164 ) {
			wp_send_json_error( array( 'message' => __( 'Please enter a valid 10-digit mobile number.', 'scratch-win' ) ) );
		}

		// Returning visitor: show them what they already got instead of re-rolling.
		$existing_lead = SW_Leads::get_lead_by_phone( $campaign_id, $phone_e164 );
		if ( $existing_lead ) {
			wp_send_json_success( $this->returning_response( $campaign, $existing_lead ) );
		}

		// Questions: validated for new entries only (a returning visitor's
		// result is already decided, and their original answers are kept).
		$questions = SW_Questions::for_campaign( $campaign );
		$checked   = SW_Questions::validate( $questions, isset( $_POST['answers'] ) ? wp_unslash( $_POST['answers'] ) : array() ); // phpcs:ignore -- sanitized per field in validate().
		if ( ! empty( $checked['errors'] ) ) {
			wp_send_json_error(
				array(
					'code'         => 'invalid_answers',
					'message'      => __( 'Please check the highlighted answers.', 'scratch-win' ),
					'field_errors' => $checked['errors'],
				)
			);
		}

		// Only NEW entries count toward the per-IP limit, so returning visitors
		// and typos never lock anyone out.
		$ip = self::get_client_ip();
		if ( $this->is_rate_limited( $ip ) ) {
			wp_send_json_error( array( 'message' => __( 'Too many entries from your network. Please try again later.', 'scratch-win' ) ), 429 );
		}

		/**
		 * Lets add-ons (e.g. an OTP check) stop an entry before the draw.
		 * Return a WP_Error to reject the entry with its message.
		 */
		$check = apply_filters( 'sw_before_draw', true, $campaign, $phone_e164 );
		if ( is_wp_error( $check ) ) {
			wp_send_json_error( array( 'message' => $check->get_error_message() ) );
		}

		// Draw, then atomically claim the prize. If someone else took the last
		// unit in the meantime, redraw without that prize.
		$excluded = array();
		$draw     = null;
		for ( $i = 0; $i < self::MAX_DRAW_ATTEMPTS; $i++ ) {
			$candidate = SW_Leads::draw_prize( $campaign, $excluded );
			if ( 'win' !== $candidate['result'] || ! $candidate['prize_id'] ) {
				$draw = $candidate;
				break;
			}
			if ( SW_Leads::claim_prize_stock( $candidate['prize_id'] ) ) {
				$draw = $candidate;
				break;
			}
			$excluded[] = $candidate['prize_id'];
		}
		if ( null === $draw ) {
			$draw = array(
				'label'       => __( 'No Prize', 'scratch-win' ),
				'result'      => 'lose',
				'prize_id'    => 0,
				'coupon'      => '',
				'description' => '',
				'image'       => '',
			);
		}
		$claimed = ( 'win' === $draw['result'] && $draw['prize_id'] );

		$lead_id = SW_Leads::insert_lead(
			array(
				'campaign_id'       => $campaign_id,
				'phone_e164'        => $phone_e164,
				'country_code'      => 'in',
				'raw_input'         => $raw_input,
				'prize_id'          => $draw['prize_id'],
				'prize_won'         => $draw['label'],
				'prize_coupon'      => $draw['coupon'],
				'prize_description' => $draw['description'],
				'prize_image'       => $draw['image'],
				'answers'           => $checked['answers'],
				'result'            => $draw['result'],
				'ip_address'        => $ip,
				'user_agent'        => isset( $_SERVER['HTTP_USER_AGENT'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) ) : '',
			)
		);

		if ( ! $lead_id ) {
			// Most likely the same number was submitted twice at the same moment
			// and the other request saved first. Give the prize back and show the
			// saved result instead.
			if ( $claimed ) {
				SW_Leads::release_prize_stock( $draw['prize_id'] );
			}
			$existing_lead = SW_Leads::get_lead_by_phone( $campaign_id, $phone_e164 );
			if ( $existing_lead ) {
				wp_send_json_success( $this->returning_response( $campaign, $existing_lead ) );
			}
			wp_send_json_error( array( 'message' => __( 'Something went wrong. Please try again.', 'scratch-win' ) ) );
		}

		$this->bump_rate_limit( $ip );

		SW_Notifications::notify_admin_new_lead( $campaign, $phone_e164, $draw['label'], $draw['result'], $checked['answers'] );

		do_action( 'sw_lead_captured', $lead_id, $campaign, $phone_e164, $draw );

		$template = 'win' === $draw['result'] ? $campaign->win_message : $campaign->lose_message;
		if ( '' === trim( (string) $template ) ) {
			$template = 'win' === $draw['result'] ? __( 'Congratulations! You won: {prize}', 'scratch-win' ) : __( 'So close! Better luck next time.', 'scratch-win' );
		}

		wp_send_json_success(
			array(
				'result'            => $draw['result'],
				'prize'             => $draw['label'],
				'message'           => self::fill_placeholders( $template, $draw['label'], SW_Settings::format_day( current_time( 'mysql' ) ), $draw['coupon'], $phone_e164 ),
				'returning_visitor' => false,
				'prize_image'       => $draw['image'],
				'prize_description' => $draw['description'],
				'coupon_code'       => 'win' === $draw['result'] ? $draw['coupon'] : '',
			)
		);
	}

	/**
	 * Response for a number that already played this campaign.
	 */
	private function returning_response( $campaign, $lead ) {
		$played_on = SW_Settings::format_day( $lead->created_at );

		// Leads saved since 1.4.0 carry their own copy of the prize details.
		// For older leads, fall back to the prize row if it still exists.
		$coupon      = (string) ( $lead->prize_coupon ?? '' );
		$description = (string) ( $lead->prize_description ?? '' );
		$image       = (string) ( $lead->prize_image ?? '' );
		if ( '' === $coupon && '' === $description && '' === $image && ! empty( $lead->prize_id ) ) {
			$prize = SW_Leads::get_prize( $lead->prize_id );
			if ( $prize && (int) $prize->campaign_id === (int) $lead->campaign_id ) {
				$coupon      = (string) $prize->coupon_code;
				$description = (string) $prize->description;
				$image       = (string) $prize->image_url;
			}
		}

		if ( 'win' === $lead->result ) {
			$template = ! empty( $campaign->already_won_message ) ? $campaign->already_won_message : SW_Settings::default_already_won_message();
		} else {
			$template = ! empty( $campaign->already_lost_message ) ? $campaign->already_lost_message : SW_Settings::default_already_lost_message();
		}

		return array(
			'result'            => $lead->result,
			'prize'             => $lead->prize_won,
			'message'           => self::fill_placeholders( $template, $lead->prize_won, $played_on, $coupon, $lead->phone_e164 ),
			'returning_visitor' => true,
			'played_on'         => $played_on,
			'prize_image'       => $image,
			'prize_description' => $description,
			'coupon_code'       => 'win' === $lead->result ? $coupon : '',
		);
	}

	private static function fill_placeholders( $template, $prize, $date, $coupon, $phone = '' ) {
		return str_replace(
			array( '{prize}', '{date}', '{coupon}', '{phone}' ),
			array( $prize, $date, $coupon, self::format_phone( $phone ) ),
			(string) $template
		);
	}

	/**
	 * "+919876543210" -> "+91 98765 43210" for display in messages.
	 */
	private static function format_phone( $phone_e164 ) {
		if ( preg_match( '/^\+91(\d{5})(\d{5})$/', (string) $phone_e164, $m ) ) {
			return '+91 ' . $m[1] . ' ' . $m[2];
		}
		return (string) $phone_e164;
	}

	/**
	 * Fixed one-hour window on NEW entries per IP address. Set "Max new
	 * entries per IP per hour" to 0 in Settings to turn it off.
	 */
	private function rate_limit_state( $ip ) {
		$now  = time();
		$data = get_transient( 'sw_rl_' . md5( $ip ) );
		if ( ! is_array( $data ) || ! isset( $data['start'], $data['count'] ) || ( $now - (int) $data['start'] ) >= HOUR_IN_SECONDS ) {
			$data = array( 'start' => $now, 'count' => 0 );
		}
		return $data;
	}

	private function rate_limit() {
		return (int) apply_filters( 'sw_rate_limit', (int) SW_Settings::get( 'rate_limit', 20 ) );
	}

	private function is_rate_limited( $ip ) {
		$limit = $this->rate_limit();
		if ( $limit <= 0 || '' === $ip ) {
			return false;
		}
		$data = $this->rate_limit_state( $ip );
		return (int) $data['count'] >= $limit;
	}

	private function bump_rate_limit( $ip ) {
		if ( $this->rate_limit() <= 0 || '' === $ip ) {
			return;
		}
		$data = $this->rate_limit_state( $ip );
		$data['count']++;
		$ttl = HOUR_IN_SECONDS - ( time() - (int) $data['start'] );
		set_transient( 'sw_rl_' . md5( $ip ), $data, max( 1, $ttl ) );
	}

	/**
	 * Visitor IP. Uses REMOTE_ADDR, which cannot be faked. Proxy headers
	 * (Cloudflare / X-Forwarded-For) are only read when "Behind a proxy or
	 * Cloudflare" is ticked in Settings — otherwise anyone could spoof them.
	 */
	public static function get_client_ip() {
		$keys = array( 'REMOTE_ADDR' );
		if ( SW_Settings::get( 'trust_proxy', 0 ) ) {
			$keys = array( 'HTTP_CF_CONNECTING_IP', 'HTTP_X_FORWARDED_FOR', 'HTTP_X_REAL_IP', 'REMOTE_ADDR' );
		}

		$found = '';
		foreach ( $keys as $key ) {
			if ( empty( $_SERVER[ $key ] ) ) {
				continue;
			}
			$ip = sanitize_text_field( wp_unslash( $_SERVER[ $key ] ) );
			$ip = trim( explode( ',', $ip )[0] );
			if ( filter_var( $ip, FILTER_VALIDATE_IP ) ) {
				$found = $ip;
				break;
			}
		}
		return (string) apply_filters( 'sw_client_ip', $found );
	}
}
