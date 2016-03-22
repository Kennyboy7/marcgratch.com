<?php
/*
Plugin Name: Sprout Invoices Add-on - Client Summary Notification
Plugin URI: https://sproutapps.co/marketplace/
Description: Sends Invoice/Estimate Summary to the Client
Author: Sprout Apps
Version: 1.0
Author URI: https://sproutapps.co
*/

/**
 * Plugin Info for updates
 */
define( 'SA_ADDON_CLIENT_SUMMARY_NOTIFICATION_VERSION', '1.0' );
define( 'SA_ADDON_CLIENT_SUMMARY_NOTIFICATION_DOWNLOAD_ID', 1111 );
define( 'SA_ADDON_CLIENT_SUMMARY_NOTIFICATION_FILE', __FILE__ );
define( 'SA_ADDON_CLIENT_SUMMARY_NOTIFICATION_PATH', dirname( __FILE__ ) );
define( 'SA_ADDON_CLIENT_SUMMARY_NOTIFICATION_NAME', 'Sprout Invoices ID Generation' );
define( 'SA_ADDON_CLIENT_SUMMARY_NOTIFICATION_URL', plugins_url( '', __FILE__ ) );

// Load up after SI is loaded.
add_action( 'sprout_invoices_loaded', 'sa_load_summary_notification_addon', 100 ); // delay for client dashboard check.
function sa_load_summary_notification_addon() {

	// Controller
	require_once( 'inc/SI_Summary_Notification.php' );
	require_once( 'inc/SI_Summary_Notification_Control.php' );
	// Updates
	if ( class_exists( 'SI_Updates' ) ) {
		require_once( 'inc/sa-updates/SA_Updates.php' );
	}
}
