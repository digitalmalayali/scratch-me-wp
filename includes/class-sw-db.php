<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * Central place for table names.
 */
class SW_DB {

	public static function campaigns_table() {
		global $wpdb;
		return $wpdb->prefix . 'sw_campaigns';
	}

	public static function leads_table() {
		global $wpdb;
		return $wpdb->prefix . 'sw_leads';
	}

	public static function prizes_table() {
		global $wpdb;
		return $wpdb->prefix . 'sw_prizes';
	}
}
