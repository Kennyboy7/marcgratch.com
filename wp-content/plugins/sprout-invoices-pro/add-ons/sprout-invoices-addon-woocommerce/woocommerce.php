<?php
/*
Plugin Name: Sprout Invoices Add-on - WooCommerce Products as Line Items
Plugin URI: https://sproutapps.co/marketplace/woocommerce/
Description: Add WooCommerce products to your items list
Author: Sprout Apps
Version: 1.0
Author URI: https://sproutapps.co
*/

/**
 * Plugin Info for updates
 */
define( 'SA_ADDON_WOOCOMMERCE_VERSION', '1.0' );
define( 'SA_ADDON_WOOCOMMERCE_DOWNLOAD_ID', 273988 );
define( 'SA_ADDON_WOOCOMMERCE_NAME', 'Sprout Invoices WooCommerce Products' );
define( 'SA_ADDON_WOOCOMMERCE_FILE', __FILE__ );
define( 'SA_ADDON_WOOCOMMERCE_PATH', dirname( __FILE__ ) );
define( 'SA_ADDON_WOOCOMMERCE_URL', plugins_url( '', __FILE__ ) );

if ( ! defined( 'SI_DEV' ) ) {
	define( 'SI_DEV', false );
}

// Load up after SI is loaded.
add_action( 'sprout_invoices_loaded', 'sa_load_woocommerce_products_addon' );
function sa_load_woocommerce_products_addon() {
	if ( ! class_exists( 'WC_Product' ) ) {
		return;
	}

	if ( class_exists( 'SI_Woo_Products' ) ) {
		return;
	}

	require_once( 'inc/Woo_Products.php' );
	SI_Woo_Products::init();
}

if ( ! apply_filters( 'is_bundle_addon', false ) ) {
	if ( SI_DEV ) { error_log( 'not bundled: sa_load_woocommerce_products_updates' ); }
	// Load up the updater after si is completely loaded
	add_action( 'sprout_invoices_loaded', 'sa_load_woocommerce_products_updates' );
	function sa_load_woocommerce_products_updates() {
		if ( class_exists( 'SI_Updates' ) ) {
			require_once( 'inc/sa-updates/SA_Updates.php' );
		}
	}
}
