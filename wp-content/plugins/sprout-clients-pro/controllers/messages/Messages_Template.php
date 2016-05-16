<?php

/**
 * Send messages, apply shortcodes and create management screen.
 *
 * @package Sprout_Client
 * @subpackage Notification
 */
class Sprout_Clients_Messages_Template extends SC_Controller {
	const FORMAT_DEFAULT_OPTION = 'sc_format_default';
	const HTML_TEMPLATE = 'sc_html_template_v4';
	const PLAINTEXT_TEMPLATE = 'sc_plaintext_template_v4';
	protected static $default_format;
	protected static $html_template;
	protected static $plaintext_template;

	public static function init() {
		self::$default_format = trim( get_option( Sprout_Clients_Messages_Template::FORMAT_DEFAULT_OPTION, 1 ) );
		self::$html_template = get_option( self::HTML_TEMPLATE, self::get_default_message_template() );
		self::$plaintext_template = get_option( self::PLAINTEXT_TEMPLATE, self::get_default_message_template( false ) );
		self::register_settings();
	}

	public static function wrap_message_with_body_template( SC_Message $message ) {
		if ( $message->is_html() ) {
			$template = self::$html_template;
		} else {
			$template = self::$plaintext_template;
		}
		$recipients = $message->get_recipients();
		if ( is_array( $recipients ) ) {
			$reciepient = $recipients[0];
		}
		return str_replace(
			array( '[message]', '[name]' ),
			array( $message->get_message_content(), sc_get_users_full_name( $reciepient ) ),
		$template );
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
			'sc_message_templates' => array(
				'title' => __( 'Message Formatting' , 'sprout-invoices' ),
				'weight' => 100,
				'tab' => SC_Controller::SETTINGS_PAGE,
				'settings' => array(
					self::FORMAT_DEFAULT_OPTION => array(
						'label' => __( 'Default Format' , 'sprout-invoices' ),
						'option' => array(
							'label' => __( 'Default messages to HTML', 'sprout-invoices' ),
							'type' => 'checkbox',
							'default' => self::$default_format,
							'value' => 1,
							'description' => sprintf( __( 'When creating new messages the format will be set by this option' , 'sprout-invoices' ) ),
							),
						),

					self::HTML_TEMPLATE => array(
						'label' => __( 'HTML Template' , 'sprout-invoices' ),
						'option' => array(
							'type' => 'textarea',
							'attributes' => array( 'class' => 'sc_redactor large-text code' ),
							'cols' => '50',
							'rows' => '20',
							'default' => self::$html_template,
							'description' => sprintf( __( 'The [message] shortcode is replaced by the message content.' , 'sprout-invoices' ) ),
							),
						),

					self::PLAINTEXT_TEMPLATE => array(
						'label' => __( 'Plaintext Template' , 'sprout-invoices' ),
						'option' => array(
							'type' => 'textarea',
							'attributes' => array( 'class' => 'large-text code' ),
							'cols' => '50',
							'rows' => '20',
							'default' => self::$plaintext_template,
							'description' => sprintf( __( 'The [message] shortcode is replaced by the message content.' , 'sprout-invoices' ) ),
							),
						),
					),
				),
			);
		do_action( 'sprout_settings', $settings, self::SETTINGS_PAGE );

	}

	public static function get_default_message_template( $html = true ) {
		$format = 'plaintext';
		if ( $html ) {
			$format = 'html';
		}
		$template = self::load_view_to_string( 'messages/' . $format, array() );
		return $template;
	}
}
