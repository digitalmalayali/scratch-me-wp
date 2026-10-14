<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

class SW_Deactivator {
	public static function deactivate() {
		// Intentionally leave tables/data intact on deactivation.
		// Uninstall.php handles full cleanup if the user chooses to delete the plugin.
	}
}
