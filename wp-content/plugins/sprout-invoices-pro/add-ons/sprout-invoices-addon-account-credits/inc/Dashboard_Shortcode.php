<?php

/**
 *
 * @package SI_Account_Credits
 * @subpackage Dashboard_Shortcode
 */
class SI_Account_Credits_Dashboard_Shortcode extends SI_Account_Credits {
	const SHORTCODE = 'sprout_invoices_credits_dashboard';

	public static function init() {
		do_action( 'sprout_shortcode', self::SHORTCODE, array( __CLASS__, 'dashboard' ) );

	}

	public static function dashboard( $atts = array() ) {
		do_action( 'sprout_invoices_credits_dashboard' );

		$user_id = 0;
		if ( class_exists( 'SI_Client_Dashboard' ) ) {
			$valid_client_ids = SI_Client_Dashboard::validate_token();
			if ( isset( $_GET[ SI_Client_Dashboard::USER_QUERY_ARG ] ) && $valid_client_ids ) {
				$user_id = (int) $_GET[ SI_Client_Dashboard::USER_QUERY_ARG ];
				$client_ids = $valid_client_ids;
			}
		}
		if ( ! $user_id && is_user_logged_in() ) {
			$user_id = get_current_user_id();
		}

		if ( $user_id ) {
			if ( empty( $client_ids ) ) {
				$client_ids = SI_Client::get_clients_by_user( $user_id );
			}
			if ( ! empty( $client_ids ) ) {
				$view = '';
				// show a dashboard for each client associated.
				foreach ( $client_ids as $client_id ) {
					$view .= self::dashboard_view( $client_id );
				}
				return $view;
			}
		}
		// no client associated
		do_action( 'sprout_invoices_credits_dashboard_no_client' );
		return self::blank_dashboard_view();
	}

	public static function dashboard_view( $client_id ) {
		$client = SI_Client::get_instance( $client_id );
		if ( ! is_a( $client, 'SI_Client' ) ) {
			return;
		}

		$credits = SI_Account_Credits_Clients::get_associated_credits( $client_id );
		return self::load_addon_view_to_string( 'shortcodes/credits-dashboard', array(
				'credits' => $credits,
				'client_id' => $client_id
			), true );

	}

	public static function blank_dashboard_view() {
		return self::load_addon_view_to_string( 'shortcodes/no-associated-client', array(), true );
	}


}