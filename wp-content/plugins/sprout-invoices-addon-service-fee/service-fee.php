<?php
/*
Plugin Name: Sprout Invoices Add-on - Service Fee
Plugin URI: https://sproutapps.co/marketplace/service-fee/
Description: Allows for a service fee to be added based on the payment method chosen.
Author: Sprout Apps
Version: 1.0
Author URI: https://sproutapps.co
*/

/**
 * Plugin Info for updates
 */
define( 'SA_ADDON_SERVICE_FEE_VERSION', '1.0' );
define( 'SA_ADDON_SERVICE_FEE_DOWNLOAD_ID', 228724 );
define( 'SA_ADDON_SERVICE_FEE_NAME', 'Sprout Invoices Service Fee' );
define( 'SA_ADDON_SERVICE_FEE_FILE', __FILE__ );
define( 'SA_ADDON_SERVICE_FEE_PATH', dirname( __FILE__ ) );
define( 'SA_ADDON_SERVICE_FEE_URL', plugins_url( '', __FILE__ ) );

// Load up after SI is loaded.
add_action( 'sprout_invoices_loaded', 'sa_load_service_fee_addon' );
function sa_load_service_fee_addon() {
	if ( class_exists( 'SI_Service_Fee' ) ) {
		return;
	}

	require_once( 'inc/Service_Fee.php' );

	SI_Service_Fee::init();
}

if ( ! apply_filters( 'is_bundle_addon', false ) ) {
	if ( defined( 'SI_DEV' ) ) { error_log( 'not bundled: sa_load_service_fee_updates' ); }
	// Load up the updater after si is completely loaded
	add_action( 'sprout_invoices_loaded', 'sa_load_service_fee_updates' );
	function sa_load_service_fee_updates() {
		if ( class_exists( 'SI_Updates' ) ) {
			require_once( 'inc/sa-updates/SA_Updates.php' );
		}
	}
}
