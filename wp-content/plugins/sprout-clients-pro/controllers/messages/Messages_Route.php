<?php


/**
* Messaging Routing
*/
class Sprout_Clients_Messages_Route extends SC_Controller {
	const PRIVATE_RECORDS_TYPE = 'sc_message_log';
	const FROM_NAME = 'sc_message_from_name';
	const FROM_EMAIL = 'sc_message_from_email';
	protected static $from_name;
	protected static $from_email;

	public static function init() {
		self::$from_name = get_option( self::FROM_NAME, get_bloginfo( 'name' ) );
		self::$from_email = get_option( self::FROM_EMAIL, get_bloginfo( 'admin_email' ) );
		self::register_settings();
	}



	///////////////
	// Settings //
	///////////////

	/**
	 * Hooked on init add the settings page and options.
	 *
	 */
	public static function register_settings() {
		// Settings
		$settings = array(
			'sc_message_routing' => array(
				'title' => __( 'Message Routing' , 'sprout-invoices' ),
				'weight' => 110,
				'tab' => SC_Controller::SETTINGS_PAGE,
				'settings' => array(
					self::FROM_NAME => array(
						'label' => __( 'From name', 'sprout-invoices' ),
						'option' => array(
							'type' => 'text',
							'default' => self::$from_name,
							),
						),
					self::FROM_EMAIL => array(
						'label' => __( 'From email', 'sprout-invoices' ),
						'option' => array(
							'type' => 'text',
							'default' => self::$from_email,
							),
						),
					),
				),
			);
		do_action( 'sprout_settings', $settings, self::SETTINGS_PAGE );
	}

	/**
	 * Send the message
	 * @param  array  $data
	 * @return
	 */
	public static function route_message( $data = array() ) {

		$data = apply_filters( 'sc_data_sending_message', $data );

		// Don't send messages with empty titles or content
		if ( '' === $data['to'] || '' === $data['subject'] || '' === $data['body'] ) {
			do_action( 'sc_error', __CLASS__ . '::' . __FUNCTION__ . ' - Notifications: Message Has No Content', $data );
			return;
		}

		$from_email = ( '' === $data['from'] ) ? self::$from_email : $data['from'];
		$from_name = ( '' === $data['from_name'] ) ? self::$from_name : $data['from_name'] ;

		if ( $data['is_html'] ) {
			$headers = array(
				'From: '.$from_name.' <'.$from_email.'>',
				'Content-Type: text/html',
			);
		} else {
			$headers = array(
				'From: '.$from_name.' <'.$from_email.'>',
			);
		}
		$headers = implode( "\r\n", $headers ) . "\r\n";
		$filtered_headers = apply_filters( 'sc_message_headers', $headers, $data );
		// Use the wp_email function
		$sent = wp_mail( $data['to'], $data['subject'], $data['body'], $filtered_headers );

		$record_id = (int) SC_Internal_Records::new_record( $data, self::PRIVATE_RECORDS_TYPE, $data['client_id'], sprintf( __( 'Message sent to %s' , 'sprout-invoices' ), $data['to_name'] ), 0, false );
	}
}
