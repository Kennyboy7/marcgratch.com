<?php

/**
 * Send messages, apply shortcodes and create management screen.
 *
 * @package Sprout_Client
 * @subpackage Notification
 */
class Sprout_Clients_Messages extends SC_Controller {

	public static function init() {
		if ( self::DEBUG ) {
			add_action( 'init', array( __CLASS__, 'maybe_send_messages' ) );
		} else {
			add_action( self::CRON_HOOK, array( __CLASS__, 'maybe_send_messages' ) );
		}
	}

	public static function maybe_send_messages() {
		$timestamp = current_time( 'timestamp' );
		$last_send = get_option( 'sc_last_bulk_send', 0 );
		if ( $last_send && $timestamp < ( $last_send + 60 * 5 ) ) {
			// sent in the last 5 minutes
			error_log( 'maybe_send_messages but already did: ' . print_r( $last_send, true ) );
			return;
		}
		if ( doing_action( 'sc_send_messages' ) ) {
			do_action( 'sc_error', 'Attempted to Send Messages Concurantly', $message );
			return;
		}
		self::send_messages( $timestamp, $last_send );
	}

	public static function send_messages( $timestamp = 0, $last_send = 0 ) {
		do_action( 'sc_send_messages' );
		if ( ! $timestamp ) {
			$timestamp = apply_filters( 'sc_get_messages_to_send', current_time( 'timestamp' ) );
		}
		if ( ! $last_send ) {
			$last_send = get_option( 'sc_last_bulk_send', 0 );
		}
		$messages = SC_Message::get_messages_to_send( $timestamp, $last_send );

		if ( empty( $messages ) ) {
			return;
		}

		foreach ( $messages as $message_id ) {
			$message = SC_Message::get_instance( $message_id );
			self::send_message( $message );
		}

		update_option( 'sc_last_bulk_send', $timestamp ); // controllers need to set this.
	}

	public static function send_message( SC_Message $message ) {
		if ( $message->is_disabled() ) {
			do_action( 'sc_error', 'Attempted to Send Disabled Message', $message );
			return;
		}
		$send_time = $message->get_send_time();
		if ( $send_time > current_time( 'timestamp' ) ) {
			do_action( 'sc_error', 'Attempted to Send Message Too Early', $message );
			return;
		}
		$sent_time = $message->get_sent_time();
		if ( $sent_time ) {
			do_action( 'sc_error', 'Attempted to Send Message Already Sent', $message );
			return;
		}

		$email_body = Sprout_Clients_Messages_Template::wrap_message_with_body_template( $message );
		$recipients = $message->get_recipients();
		foreach ( $recipients as $user_id ) {
			$user = get_userdata( $user_id );
			$data = array(
					'is_html' => $message->is_html(),
					'subject' => $message->get_title(),
					'client_id' => $message->get_client_id(),
					'message' => $message->get_content(),
					'body' => $email_body,
					'from' => '',
					'from_name' => '',
					'to' => $user->user_email,
					'to_name' => sc_get_users_full_name( $user_id ),
				);
			$data = apply_filters( 'sc_send_message_data', $data, $message );
			$status = Sprout_Clients_Messages_Route::route_message( $data );
		}

		$message->set_sent();
	}
}
