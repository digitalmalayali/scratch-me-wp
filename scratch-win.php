<?php
/**
 * Plugin Name: Scratch Me | Digital Malayali
 * Plugin URI:  https://www.digitalmalayali.in/scratch-me
 * Description: An Open Source Scratch & Win Software Developed For Marketers By Digital Malayali Partner - Bestin Jacob
 * Version:     1.6.1
 * Requires PHP: 7.4
 * Requires at least: 5.5
 * Author:      Digital Malayali | Bestin Jacob
 * Author URI:  https://www.digitalmalayali.in
 * License:     GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: scratch-win
 * Domain Path: /languages
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // No direct access.
}

// ---------------------------------------------------------------------
// Constants
// ---------------------------------------------------------------------
define( 'SW_VERSION', '1.6.1' );
define( 'SW_DB_VERSION', '1.6' );
define( 'SW_PLUGIN_FILE', __FILE__ );
define( 'SW_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'SW_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'SW_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );

// ---------------------------------------------------------------------
// Includes
// ---------------------------------------------------------------------
require_once SW_PLUGIN_DIR . 'includes/class-sw-activator.php';
require_once SW_PLUGIN_DIR . 'includes/class-sw-deactivator.php';
require_once SW_PLUGIN_DIR . 'includes/class-sw-db.php';
require_once SW_PLUGIN_DIR . 'includes/class-sw-settings.php';
require_once SW_PLUGIN_DIR . 'includes/class-sw-questions.php';
require_once SW_PLUGIN_DIR . 'includes/class-sw-leads.php';
require_once SW_PLUGIN_DIR . 'includes/class-sw-shortcode.php';
require_once SW_PLUGIN_DIR . 'includes/class-sw-ajax.php';
require_once SW_PLUGIN_DIR . 'includes/class-sw-export.php';
require_once SW_PLUGIN_DIR . 'includes/class-sw-notifications.php';

if ( is_admin() ) {
	require_once SW_PLUGIN_DIR . 'admin/class-sw-admin.php';
}

// ---------------------------------------------------------------------
// Activation / Deactivation
// ---------------------------------------------------------------------
register_activation_hook( __FILE__, array( 'SW_Activator', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'SW_Deactivator', 'deactivate' ) );

// ---------------------------------------------------------------------
// Bootstrap
// ---------------------------------------------------------------------
function sw_run_plugin() {
	load_plugin_textdomain( 'scratch-win', false, dirname( SW_PLUGIN_BASENAME ) . '/languages' );

	// Maybe upgrade DB schema.
	SW_Activator::maybe_upgrade();

	new SW_Shortcode();
	new SW_Ajax();

	if ( is_admin() ) {
		new SW_Admin();
	}
}
add_action( 'plugins_loaded', 'sw_run_plugin' );
