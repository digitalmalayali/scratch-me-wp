<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * Handles reading/writing general plugin settings (localization, business
 * profile, security, data) and all date formatting.
 */
class SW_Settings {

	const OPTION_KEY = 'sw_general_settings';

	public static function defaults() {
		return array(
			'business_name'       => get_bloginfo( 'name' ),
			'business_url'        => home_url(),
			'admin_email'         => get_option( 'admin_email' ),
			'timezone'            => wp_timezone_string(),
			'date_format'         => 'DD/MM/YYYY',
			'default_country'     => 'in',
			'notify_admin'        => 1,
			'rate_limit'          => 20,
			'trust_proxy'         => 0,
			'delete_on_uninstall' => 0,
		);
	}

	public static function get_all() {
		$saved = get_option( self::OPTION_KEY, array() );
		return wp_parse_args( is_array( $saved ) ? $saved : array(), self::defaults() );
	}

	public static function get( $key, $default = '' ) {
		$all = self::get_all();
		return isset( $all[ $key ] ) ? $all[ $key ] : $default;
	}

	public static function update( array $data ) {
		$merged = array_merge( self::get_all(), $data );
		update_option( self::OPTION_KEY, $merged );
		return $merged;
	}

	/**
	 * Is this a timezone value WordPress' own timezone selector can produce?
	 * Either a PHP timezone identifier ("Asia/Kolkata") or a manual offset
	 * ("UTC+5.5").
	 */
	public static function is_valid_timezone( $tz ) {
		if ( 'UTC' === $tz || preg_match( '/^UTC[+-]\d{1,2}(\.\d{1,2})?$/', $tz ) ) {
			return true;
		}
		return in_array( $tz, timezone_identifiers_list(), true );
	}

	/**
	 * The timezone chosen in Scratch & Win → Settings, as a DateTimeZone.
	 * Falls back to the WordPress site timezone.
	 */
	public static function display_timezone() {
		$tz = (string) self::get( 'timezone', '' );

		if ( preg_match( '/^UTC([+-])(\d{1,2})(?:\.(\d{1,2}))?$/', $tz, $m ) ) {
			$minutes = isset( $m[3] ) ? (int) round( (float) ( '0.' . $m[3] ) * 60 ) : 0;
			$tz      = sprintf( '%s%02d:%02d', $m[1], (int) $m[2], $minutes );
		}

		if ( $tz ) {
			try {
				return new DateTimeZone( $tz );
			} catch ( Exception $e ) {
				// Fall through to the site timezone.
			}
		}
		return wp_timezone();
	}

	/**
	 * Lead dates are stored with current_time( 'mysql' ), i.e. in the
	 * WordPress site timezone. Convert one to a real Unix timestamp.
	 */
	public static function mysql_to_timestamp( $mysql_datetime ) {
		if ( empty( $mysql_datetime ) ) {
			return 0;
		}
		try {
			$dt = new DateTimeImmutable( $mysql_datetime, wp_timezone() );
			return $dt->getTimestamp();
		} catch ( Exception $e ) {
			return 0;
		}
	}

	/**
	 * Date + time in the admin's chosen format, e.g. "22/03/2027 14:05".
	 */
	public static function format_timestamp( $timestamp ) {
		if ( ! $timestamp ) {
			return '';
		}
		$php_format = ( 'DD/MM/YYYY' === self::get( 'date_format', 'DD/MM/YYYY' ) ) ? 'd/m/Y H:i' : 'm/d/Y H:i';
		return wp_date( $php_format, $timestamp, self::display_timezone() );
	}

	/**
	 * Format a stored lead date as date + time.
	 */
	public static function format_datetime( $mysql_datetime ) {
		return self::format_timestamp( self::mysql_to_timestamp( $mysql_datetime ) );
	}

	/**
	 * Format a stored lead date as a friendly day, e.g. "22 March 2027".
	 */
	public static function format_day( $mysql_datetime ) {
		$ts = self::mysql_to_timestamp( $mysql_datetime );
		return $ts ? wp_date( 'j F Y', $ts, self::display_timezone() ) : '';
	}

	public static function default_already_won_message() {
		return __( 'You already won {prize} on {date}.', 'scratch-win' );
	}

	public static function default_already_lost_message() {
		return __( 'You already played on {date}. Better luck in our next giveaway!', 'scratch-win' );
	}
}
