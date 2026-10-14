<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

class SW_Activator {

	public static function activate() {
		self::create_tables();
		self::set_default_options();
		update_option( 'sw_db_version', SW_DB_VERSION );
	}

	public static function maybe_upgrade() {
		if ( get_option( 'sw_db_version' ) !== SW_DB_VERSION ) {
			self::create_tables();
			update_option( 'sw_db_version', SW_DB_VERSION );
		}
	}

	private static function create_tables() {
		global $wpdb;
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$charset_collate = $wpdb->get_charset_collate();

		$campaigns_table = SW_DB::campaigns_table();
		$leads_table     = SW_DB::leads_table();
		$prizes_table    = SW_DB::prizes_table();

		$sql = "CREATE TABLE {$campaigns_table} (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			title VARCHAR(255) NOT NULL DEFAULT '',
			promo_text TEXT NULL,
			template VARCHAR(50) NOT NULL DEFAULT 'default',
			background_image VARCHAR(500) NULL,
			scratch_layer_image VARCHAR(500) NULL,
			status VARCHAR(20) NOT NULL DEFAULT 'active',
			show_footer_credit TINYINT(1) NOT NULL DEFAULT 1,
			require_phone_before TINYINT(1) NOT NULL DEFAULT 1,
			sold_out_mode VARCHAR(20) NOT NULL DEFAULT 'redistribute',
			questions LONGTEXT NULL,
			win_probability INT UNSIGNED NOT NULL DEFAULT 50,
			win_message TEXT NULL,
			lose_message TEXT NULL,
			already_won_message TEXT NULL,
			already_lost_message TEXT NULL,
			created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
			updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
			PRIMARY KEY  (id)
		) {$charset_collate};";
		dbDelta( $sql );

		$sql2 = "CREATE TABLE {$prizes_table} (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			campaign_id BIGINT UNSIGNED NOT NULL,
			label VARCHAR(255) NOT NULL,
			prize_type VARCHAR(100) NOT NULL DEFAULT 'Common Coupon',
			coupon_code VARCHAR(100) NULL,
			description TEXT NULL,
			image_url VARCHAR(500) NULL,
			chance DECIMAL(6,2) NOT NULL DEFAULT 0,
			weight INT UNSIGNED NOT NULL DEFAULT 1,
			is_winning TINYINT(1) NOT NULL DEFAULT 1,
			is_active TINYINT(1) NOT NULL DEFAULT 1,
			stock_total INT NULL,
			stock_remaining INT NULL,
			winners_count INT UNSIGNED NOT NULL DEFAULT 0,
			sort_order INT UNSIGNED NOT NULL DEFAULT 0,
			created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY  (id),
			KEY campaign_id (campaign_id)
		) {$charset_collate};";
		dbDelta( $sql2 );

		$sql3 = "CREATE TABLE {$leads_table} (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			campaign_id BIGINT UNSIGNED NOT NULL,
			phone_e164 VARCHAR(32) NOT NULL,
			country_code VARCHAR(8) NULL,
			raw_input VARCHAR(64) NULL,
			prize_id BIGINT UNSIGNED NULL,
			prize_won VARCHAR(255) NULL,
			prize_coupon VARCHAR(100) NULL,
			prize_description TEXT NULL,
			prize_image VARCHAR(500) NULL,
			answers LONGTEXT NULL,
			result VARCHAR(20) NOT NULL DEFAULT 'lose',
			ip_address VARCHAR(64) NULL,
			user_agent VARCHAR(255) NULL,
			created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
			deleted_at DATETIME NULL DEFAULT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY phone_campaign (campaign_id, phone_e164),
			KEY campaign_id (campaign_id),
			KEY created_at (created_at),
			KEY deleted_at (deleted_at)
		) {$charset_collate};";
		dbDelta( $sql3 );
	}

	private static function set_default_options() {
		$defaults = SW_Settings::defaults();
		if ( false === get_option( 'sw_general_settings' ) ) {
			add_option( 'sw_general_settings', $defaults );
		}

		// Create a default demo campaign if none exists.
		global $wpdb;
		$table = SW_DB::campaigns_table();
		$count = $wpdb->get_var( "SELECT COUNT(*) FROM {$table}" );
		if ( ! $count ) {
			$wpdb->insert(
				$table,
				array(
					'title'                => __( 'Scratch & Win!', 'scratch-win' ),
					'promo_text'           => __( 'Scratch the card below for a chance to win a special prize!', 'scratch-win' ),
					'template'             => 'default',
					'status'               => 'active',
					'show_footer_credit'   => 1,
					'require_phone_before' => 1,
					'win_message'          => __( 'Congratulations! You won: {prize}', 'scratch-win' ),
					'lose_message'         => __( 'So close! Better luck next time.', 'scratch-win' ),
					'already_won_message'  => __( 'You already won {prize} on {date}.', 'scratch-win' ),
					'already_lost_message' => __( 'You already played on {date}. Better luck in our next giveaway!', 'scratch-win' ),
				)
			);
			$campaign_id = $wpdb->insert_id;

			$prizes_table = SW_DB::prizes_table();
			$wpdb->insert(
				$prizes_table,
				array(
					'campaign_id'     => $campaign_id,
					'label'           => '10% Off Coupon',
					'prize_type'      => 'Common Coupon',
					'coupon_code'     => '10OFF',
					'description'     => __( 'Get 10% off your next purchase.', 'scratch-win' ),
					'chance'          => 20,
					'weight'          => 1,
					'is_winning'      => 1,
					'is_active'       => 1,
					'stock_total'     => null,
					'stock_remaining' => null,
				)
			);
			$wpdb->insert(
				$prizes_table,
				array(
					'campaign_id' => $campaign_id,
					'label'       => 'No Prize',
					'prize_type'  => 'No Prize',
					'chance'      => 80,
					'weight'      => 1,
					'is_winning'  => 0,
					'is_active'   => 1,
				)
			);
		}
	}
}
