<?php

/**
 * Controller
 * Adds meta boxes to client admin.
 */
class SI_Client_PP_Limits extends SI_Controller {

	public static function init() {
		add_filter( 'si_payment_options', array( __CLASS__, 'limit_enabled_processors' ), 10, 2 );
	}

	public static function limit_enabled_processors( $enabled_processors = array(), $return = 'options' ) {
		if ( 'options' !== $return ) {
			return $enabled_processors;
		}
		$client = si_get_invoice_client();
		if ( ! is_a( $client, 'SI_Client' ) ) {
			return $enabled_processors;
		}

		$enabled_processors = SI_Payment_Processors::enabled_processors();
		$client_enabled = SI_Client_PP_Meta::get_client_processors( $client->get_id() );

		$processor_options = array();
		foreach ( $enabled_processors as $class_name ) {
			if ( ! in_array( $class_name, $client_enabled ) ) {
				// not enabled
				continue;
			}
			if ( method_exists( $class_name, 'get_instance' ) ) {
				$payment_processor = call_user_func( array( $class_name, 'get_instance' ) );
				$processor_options[ $payment_processor->get_slug() ] = $payment_processor->checkout_options();
			}
		}

		return $processor_options;
	}
}
SI_Client_PP_Limits::init();
