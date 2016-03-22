<?php
/*
Plugin Name: Sprout Invoices Add-on - Time Tracking
Plugin URI: https://sproutapps.co/marketplace/time-tracking/
Description: Adds the ability to track time on projects and then import time to invoices.
Author: Sprout Apps
Version: 3.2
Author URI: https://sproutapps.co
*/

/**
 * Plugin Info for updates
 */
define( 'SA_ADDON_TIME_TRACKING_VERSION', '3.2' );
define( 'SA_ADDON_TIME_TRACKING_DOWNLOAD_ID', 7157 );
define( 'SA_ADDON_TIME_TRACKING_NAME', 'Sprout Invoices Time Tracker' );
define( 'SA_ADDON_TIME_TRACKING_FILE', __FILE__ );
define( 'SA_ADDON_TIME_TRACKING_PATH', dirname( __FILE__ ) );
define( 'SA_ADDON_TIME_TRACKING_URL', plugins_url( '', __FILE__ ) );

if ( ! defined( 'SI_DEV' ) ) {
	define( 'SI_DEV', false );
}

// Load up after SI is loaded.
add_action( 'sprout_invoices_loaded', 'sa_load_time_tracking_addon' );
function sa_load_time_tracking_addon() {
	if ( class_exists( 'SI_Time' ) ) {
		return;
	}

	require_once( 'inc/Time.php' );
	require_once( 'inc/Time_Tracking.php' );

	SI_Time::init();
	SI_Time_Tracking_Premium::init();
}

if ( ! apply_filters( 'is_bundle_addon', false ) ) {
	if ( SI_DEV ) { error_log( 'not bundled: sa_load_time_tracking_updates' ); }
	// Load up the updater after si is completely loaded
	add_action( 'sprout_invoices_loaded', 'sa_load_time_tracking_updates' );
	function sa_load_time_tracking_updates() {
		if ( class_exists( 'SI_Updates' ) ) {
			require_once( 'inc/sa-updates/SA_Updates.php' );
		}
	}
}
