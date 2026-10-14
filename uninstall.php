<?php
// If uninstall not called from WordPress, exit.
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

// Data is only removed if the site owner ticked "Delete all data when the
// plugin is deleted" in Scratch & Win → Settings (default: keep data safe).
// Developers can also force it with the sw_uninstall_delete_data filter.
$sw_settings      = get_option( 'sw_general_settings', array() );
$sw_opted_in      = is_array( $sw_settings ) && ! empty( $sw_settings['delete_on_uninstall'] );
$sw_should_delete = apply_filters( 'sw_uninstall_delete_data', $sw_opted_in );

if ( $sw_should_delete ) {
	global $wpdb;
	$sw_tables = array(
		$wpdb->prefix . 'sw_leads',
		$wpdb->prefix . 'sw_prizes',
		$wpdb->prefix . 'sw_campaigns',
	);
	foreach ( $sw_tables as $sw_table ) {
		$wpdb->query( "DROP TABLE IF EXISTS {$sw_table}" ); // phpcs:ignore
	}
	delete_option( 'sw_general_settings' );
	delete_option( 'sw_db_version' );

	// Rate-limit counters.
	$wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE '\\_transient\\_sw\\_rl\\_%' OR option_name LIKE '\\_transient\\_timeout\\_sw\\_rl\\_%'" ); // phpcs:ignore
}
