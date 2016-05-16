<?php

/**
 * Account_Credits Controller
 *
 * @package Sprout_Invoice
 * @subpackage Account_Credits
 */
class SI_Account_Credits_Widgets extends SI_Account_Credits {

	public static function init() {

		if ( is_admin() ) {
			// Dash widgets
			add_action( 'si_dashboard_setup', array( __CLASS__, 'add_dashboard_widgets' ) );
			add_action( 'wp_dashboard_setup', array( __CLASS__, 'add_dashboard_widgets' ), 10, 0 );
		}

	}

	//////////////
	// Widgets //
	//////////////

	public static function add_dashboard_widgets( $context = 'dashboard' ) {

		if ( ! current_user_can( 'edit_sprout_invoices' ) ) {
			return;
		}

		add_meta_box(
			'credit_tracker',
			__( 'Credit Tracker', 'sprout-invoices' ),
			array( __CLASS__, 'credit_tracker_dashboard' ),
			$context,
			'normal',
			'high'
		);

	}

	public static function credit_tracker_dashboard() {
		$fields = self::credit_entry_fields();
		if ( ! current_user_can( 'edit_sprout_invoices' ) ) {
			unset( $fields['credit_type_id']['description'] );
		}
		self::load_addon_view( 'admin/dashboards/credit-tracker', array(
				'fields' => $fields,
		), true );
	}
}
