<?php
/*
Plugin Name: Sprout Invoices Add-on - Account_Credits
Plugin URI: https://sproutapps.co/marketplace/credit-tracking/
Description: Adds the ability to track credit on invoices and then import credit to invoices.
Author: Sprout Apps
Version: 1.2.1
Author URI: https://sproutapps.co
*/

/**
 * Plugin Info for updates
 */
define( 'SA_ADDON_ACCOUNT_CREDITS_VERSION', '1.2.1' );
define( 'SA_ADDON_ACCOUNT_CREDITS_DOWNLOAD_ID', 7157 );
define( 'SA_ADDON_ACCOUNT_CREDITS_NAME', 'Sprout Invoices Credit Tracker' );
define( 'SA_ADDON_ACCOUNT_CREDITS_FILE', __FILE__ );
define( 'SA_ADDON_ACCOUNT_CREDITS_PATH', dirname( __FILE__ ) );
define( 'SA_ADDON_ACCOUNT_CREDITS_URL', plugins_url( '', __FILE__ ) );

if ( ! defined( 'SI_DEV' ) ) {
	define( 'SI_DEV', false );
}

// Load up after SI is loaded.
add_action( 'sprout_invoices_loaded', 'sa_load_account_credits_addon' );
function sa_load_account_credits_addon() {
	if ( class_exists( 'SI_Credit' ) ) {
		return;
	}

	require_once( 'inc/Credit.php' );
	require_once( 'inc/Account_Credits.php' );
	require_once( 'inc/Account_Credits_AJAX.php' );
	require_once( 'inc/Account_Credits_Widgets.php' );
	require_once( 'inc/Clients.php' );
	require_once( 'inc/Clients_Admin.php' );

	require_once( 'inc/Invoices_Admin.php' );

	require_once( 'inc/Credits_Payment.php' );

	require_once( 'inc/Dashboard_Shortcode.php' );

	SI_Credit::init();
	SI_Account_Credits::init();
	SI_Account_Credits_AJAX::init();
	SI_Account_Credits_Widgets::init();
	SI_Account_Credits_Clients_Admin::init();
	SI_Account_Credits_Invoices_Admin::init();
	SI_Account_Credits_Dashboard_Shortcode::init();
	// Credits_Payment::init();

	require_once( 'inc/Credit_Importer.php' );
	SI_Account_Credits_Importer::register();
}

if ( ! apply_filters( 'is_bundle_addon', false ) ) {
	if ( SI_DEV ) { error_log( 'not bundled: sa_load_account_credits_updates' ); }
	// Load up the updater after si is completely loaded
	add_action( 'sprout_invoices_loaded', 'sa_load_account_credits_updates' );
	function sa_load_account_credits_updates() {
		if ( class_exists( 'SI_Updates' ) ) {
			require_once( 'inc/sa-updates/SA_Updates.php' );
		}
	}
}
