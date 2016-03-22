<?php

/**
 * Updates class
 *
 * @package Sprout_Invoice
 * @subpackage Updates
 */
class SA_ADDON_CLIENT_PP_OPTIONS_ID_Updates extends SI_Updates {

	public static function init() {
		self::$license_key = trim( get_option( self::LICENSE_KEY_OPTION, '' ) );
		self::$license_status = get_option( self::LICENSE_STATUS, false );
		self::register_settings();

		if ( is_admin() ) {
			add_action( 'admin_init', array( __CLASS__, 'init_edd_udpater' ) );
		}
	}

	public static function init_edd_udpater() {

		// setup the updater
		$edd_updater = new EDD_SL_Plugin_Updater_SA_Mod( self::PLUGIN_URL, SA_ADDON_CLIENT_PP_OPTIONS_ID_FILE, array(
				'item_id' => SA_ADDON_CLIENT_PP_OPTIONS_ID_DOWNLOAD_ID,		// Set the download_id manually
				'version' 	=> SA_ADDON_CLIENT_PP_OPTIONS_ID_VERSION,		// current version number
				'license' 	=> self::license_key(),	 		// license key (used get_option above to retrieve from DB)
				'item_name' => SA_ADDON_CLIENT_PP_OPTIONS_ID_NAME, 		// name of this plugin
				'author' 	=> 'Sprout Apps',// author of this plugin
			)
		);

		// $edd_updater->api_request( 'plugin_latest_version', array( 'slug' => basename( self::PLUGIN_FILE, '.php') ) );

		// uncomment this line for testing
		// set_site_transient( 'update_plugins', null );
	}
}
SA_ADDON_CLIENT_PP_OPTIONS_ID_Updates::init();
