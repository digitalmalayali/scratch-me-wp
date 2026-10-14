<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

class SW_Shortcode {

	/** Only localize the frontend data once per page, even with several cards. */
	private static $localized = false;

	public function __construct() {
		add_shortcode( 'scratch_win', array( $this, 'render' ) );
	}

	public function render( $atts ) {
		$atts = shortcode_atts( array( 'id' => 0 ), $atts, 'scratch_win' );

		$campaign_id = absint( $atts['id'] );

		if ( $campaign_id ) {
			// A specific campaign was asked for: show that one or nothing —
			// never silently swap in a different campaign.
			$campaign = SW_Leads::get_campaign( $campaign_id );
		} else {
			$active   = SW_Leads::get_active_campaigns();
			$campaign = ! empty( $active ) ? $active[0] : null;
		}

		if ( ! $campaign || 'active' !== $campaign->status ) {
			if ( current_user_can( 'manage_options' ) ) {
				return '<p>' . esc_html__( 'Scratch & Win: this campaign does not exist or is paused. (Only administrators see this message.)', 'scratch-win' ) . '</p>';
			}
			return '';
		}

		$this->enqueue_assets();

		ob_start();
		include SW_PLUGIN_DIR . 'public/views/scratch-card.php';
		return ob_get_clean();
	}

	private function enqueue_assets() {
		wp_enqueue_style( 'sw-frontend-css', SW_PLUGIN_URL . 'assets/css/frontend.css', array(), SW_VERSION );
		wp_enqueue_script( 'sw-frontend-js', SW_PLUGIN_URL . 'assets/js/scratch.js', array( 'jquery' ), SW_VERSION, true );

		if ( self::$localized ) {
			return;
		}
		self::$localized = true;

		wp_localize_script(
			'sw-frontend-js',
			'SW_Data',
			array(
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'nonce'   => wp_create_nonce( 'sw_scratch_nonce' ),
				'i18n'    => array(
					'invalidPhone' => __( 'Please enter a valid 10-digit mobile number.', 'scratch-win' ),
					'genericError' => __( 'Something went wrong. Please try again.', 'scratch-win' ),
					'couponLabel'  => __( 'Coupon code:', 'scratch-win' ),
					'scratchHere'  => __( 'SCRATCH HERE', 'scratch-win' ),
					'mystery'      => __( 'Your prize is waiting!', 'scratch-win' ),
				),
				'qMsg'    => SW_Questions::js_messages(),
			)
		);
	}
}
