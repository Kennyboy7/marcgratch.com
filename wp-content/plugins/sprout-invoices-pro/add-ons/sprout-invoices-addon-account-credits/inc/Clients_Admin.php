<?php

/**
 * Account_Credits Controller
 *
 * @package Sprout_Invoice
 * @subpackage Account_Credits
 */
class SI_Account_Credits_Clients_Admin extends SI_Account_Credits {

	public static function init() {

		if ( is_admin() ) {
			// Meta boxes
			add_action( 'admin_init', array( __CLASS__, 'register_meta_boxes' ) );

		}

	}


	/////////////////
	// Meta boxes //
	/////////////////

	/**
	 * Regsiter meta boxes for estimate editing.
	 *
	 * @return
	 */
	public static function register_meta_boxes() {
		// estimate specific
		$args = array(
			'si_account_credit_tracking' => array(
				'title' => __( 'Account Credits', 'sprout-invoices' ),
				'show_callback' => array( __CLASS__, 'show_credit_tracking_meta_box' ),
				'save_callback' => array( __CLASS__, 'save_meta_box_account_credits' ),
				'context' => 'normal',
				'priority' => 'high',
				'save_priority' => 0,
			),
		);
		do_action( 'sprout_meta_box', $args, SI_Client::POST_TYPE );
	}

	/**
	 * Show credit tracking metabox.
	 * @param  WP_Post $post
	 * @param  array $metabox
	 * @return
	 */
	public static function show_credit_tracking_meta_box( $post, $metabox ) {
		$client = SI_Client::get_instance( $post->ID );
		$credits = SI_Account_Credits_Clients::get_associated_credits( $post->ID );
		self::load_addon_view( 'admin/meta-boxes/accounts/credit-entries', array(
				'credits' => $credits,
				'client_id' => $client->get_id(),
		), true );

		// Form
		$fields = self::credit_entry_fields( $post->ID, true );
		self::load_addon_view( 'admin/sections/credit-tracker', array(
				'fields' => $fields,
				'client_id' => $client->get_id(),
		), true );
	}

	public static function save_meta_box_account_credits() {
		// Credit tracking is done via AJAX
	}
}
