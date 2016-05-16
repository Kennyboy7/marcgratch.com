<?php
/*
Plugin Name: Sprout Invoices Add-on - Woo Commerce Payments Integration
Plugin URI: https://sproutapps.co/marketplace/woo-commerce-invoicing/
Description: Integrates with Woo Commerce to create an invoice after checkout, as well as update the order status when an invoice is paid.
Author: Sprout Apps
Version: 1.0.3
Author URI: https://sproutapps.co
*/

/**
 * Plugin Info for updates
 */
define( 'SA_ADDON_WOO_COM_INT_VERSION', '1.0.3' );
define( 'SA_ADDON_WOO_COM_INT_DOWNLOAD_ID', 21114 );
define( 'SA_ADDON_WOO_COM_INT_NAME', 'Woo Commerce Payments Integration' );
define( 'SA_ADDON_WOO_COM_INT_FILE', __FILE__ );
define( 'SA_ADDON_WOO_COM_INT_PATH', dirname( __FILE__ ) );
define( 'SA_ADDON_WOO_COM_INT_URL', plugins_url( '', __FILE__ ) );

if ( ! defined( 'SI_DEV' ) ) {
	define( 'SI_DEV', false );
}

// Load up after SI is loaded.
add_action( 'sprout_invoices_loaded', 'sa_load_woo_commerce_si_addon' );
function sa_load_woo_commerce_si_addon() {
	if ( ! class_exists( 'WC_Payment_Gateway' ) ) {
		return;
	}

	if ( class_exists( 'Woo_Integration' ) ) {
		return;
	}

	require_once( 'inc/Woo_Integration.php' );
	require_once( 'inc/Woo_Payments.php' );

	Woo_Integration::init();
}

if ( ! apply_filters( 'is_bundle_addon', false ) ) {
	if ( SI_DEV ) { error_log( 'not bundled: sa_load_woo_commerce_si_updates' ); }
	// Load up the updater after si is completely loaded
	add_action( 'sprout_invoices_loaded', 'sa_load_woo_commerce_si_updates' );
	function sa_load_woo_commerce_si_updates() {
		if ( class_exists( 'SI_Updates' ) ) {
			require_once( 'inc/sa-updates/SA_Updates.php' );
		}
	}
}
