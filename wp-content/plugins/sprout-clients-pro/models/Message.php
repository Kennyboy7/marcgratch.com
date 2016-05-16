<?php

/**
 * Notification Model
 *
 *
 * @package Sprout_Clients
 * @subpackage Notification
 */
class SC_Message extends SC_Post_Type {

	const POST_TYPE = 'sc_message';

	const STATUS_TEMP = 'temp';
	const STATUS_FUTURE = 'pending';
	const STATUS_COMPLETE = 'sent';

	private static $instances = array();

	private static $meta_keys = array(
		'client_id' => '_client_id', // bool
		'html' => '_html', // bool
		'recipients' => '_recipients',
		'send_time' => '_send_time', // int
		'sent_time' => '_sent_time', // int
	); // A list of meta keys this class cares about. Try to keep them in alphabetical order.


	public static function init() {
		// register Notification post type
		$post_type_args = array(
			'public' => false,
			'has_archive' => false,
			'show_ui' => true,
			'show_in_menu' => 'sprout-client',
			'supports' => array( 'title', 'editor', 'revisions' ),
		);
		self::register_post_type( self::POST_TYPE, 'Message', 'Messages', $post_type_args );
	}

	public static function get_statuses() {
		$statuses = array(
			self::STATUS_TEMP => __( 'Draft', 'sprout-invoices' ),
			self::STATUS_FUTURE => __( 'Scheduled', 'sprout-invoices' ),
			self::STATUS_COMPLETE => __( 'Sent', 'sprout-invoices' ),
		);
		return $statuses;
	}

	protected function __construct( $id ) {
		parent::__construct( $id );
	}

	/**
	 *
	 *
	 * @static
	 * @param int     $id
	 * @return Sprout_Clients_Notification
	 */
	public static function get_instance( $id = 0 ) {
		if ( ! $id ) {
			return null; }

		if ( ! isset( self::$instances[ $id ] ) || ! self::$instances[ $id ] instanceof self ) {
			self::$instances[ $id ] = new self( $id ); }

		if ( ! isset( self::$instances[ $id ]->post->post_type ) ) {
			return null; }

		if ( self::$instances[ $id ]->post->post_type !== self::POST_TYPE ) {
			return null; }

		return self::$instances[ $id ];
	}


	/**
	 * Create a message
	 * @param  array $args
	 * @return int
	 */
	public static function new_message( $passed_args ) {
		if ( isset( $passed_args['recipients'] ) && ! is_array( $passed_args['recipients'] ) ) {
			$passed_args['recipients'] = array( $passed_args['recipients'] );
		}

		$defaults = array(
			'subject' => sprintf( __( 'New Message: %s' , 'sprout-invoices' ), date_i18n( get_option( 'date_format' ).' @ '.get_option( 'time_format' ), current_time( 'timestamp' ) ) ),
			'message' => '',
			'send_time' => time() + ( 60 * 60 * 24 * 7 ),
			'recipients' => array(),
			'html' => 0,
			'client_id' => 0,
		);
		$args = wp_parse_args( $passed_args, $defaults );
		if ( ! $args['client_id'] || empty( $args['recipients'] ) ) {
			return;
		}
		$id = wp_insert_post( array(
			'post_type' => self::POST_TYPE,
			'post_title' => $args['subject'],
			'post_content' => $args['message'],
			'post_content_filtered' => $args['message'],
		) );

		if ( is_wp_error( $id ) ) {
			return 0;
		}

		$message = self::get_instance( $id );
		$message->set_pending();
		$message->set_send_time( $args['send_time'] );
		$message->set_recipients( $args['recipients'] );
		$message->set_client_id( $args['client_id'] );

		if ( $args['html'] ) {
			$message->set_html();
		}

		do_action( 'sa_new_message', $message, $args );
		return $id;
	}

	public function get_status() {
		return $this->post->post_status;
	}

	public function set_status( $status ) {
		// Don't do anything if there's no true change
		$current_status = $this->get_status();
		if ( $current_status === $status ) {
			return;
		}

		// confirm the status exists
		if ( ! in_array( $status, array_keys( self::get_statuses() ) ) ) {
			return;
		}

		$this->post->post_status = $status;
		$this->save_post();
		do_action( 'si_message_status_updated', $this, $status, $current_status );
	}

	public function set_temp() {
		$this->set_status( self::STATUS_TEMP );
	}

	public function set_pending() {
		$this->set_status( self::STATUS_FUTURE );
	}

	public function set_sent() {
		$this->set_sent_time();
		$this->set_status( self::STATUS_COMPLETE );
	}

	public function get_subject() {
		return $this->get_title();
	}

	public function get_message_content() {
		return $this->get_content();
	}

	public function is_disabled() {
		if ( $this->get_status() === self::STATUS_TEMP ) {
			return true;
		}
		return false;
	}

	public function set_client_id( $client_id = 0 ) {
		$this->save_post_meta( array(
			self::$meta_keys['client_id'] => $client_id,
		) );
		return $client_id;
	}

	public function get_client_id() {
		$client_id = $this->get_post_meta( self::$meta_keys['client_id'] );
		return $client_id;
	}

	public function is_html() {
		$html = $this->get_post_meta( self::$meta_keys['html'] );
		return 1 == $html;
	}

	public function set_plain( $plain = 0 ) {
		$this->save_post_meta( array(
			self::$meta_keys['html'] => $plain,
		) );
		return $plain;
	}

	public function set_html( $html = 1 ) {
		$this->save_post_meta( array(
			self::$meta_keys['html'] => $html,
		) );
		return $html;
	}

	public function get_send_time() {
		$time = $this->get_post_meta( self::$meta_keys['send_time'] );
		return $time;
	}

	public function set_send_time( $time = 0 ) {
		$this->save_post_meta( array(
			self::$meta_keys['send_time'] => $time,
		) );
		return $time;
	}

	public function get_sent_time() {
		$time = $this->get_post_meta( self::$meta_keys['sent_time'] );
		return $time;
	}

	public function set_sent_time( $time = 0 ) {
		if ( ! $time ) {
			$time = current_time( 'timestamp' );
		}
		$this->save_post_meta( array(
			self::$meta_keys['sent_time'] => $time,
		) );
		return $time;
	}

	public function get_recipients() {
		$recipients = $this->get_post_meta( self::$meta_keys['recipients'] );
		return $recipients;
	}

	public function set_recipients( $recipients = array() ) {
		if ( ! is_array( $recipients ) ) {
			$recipients = array( $recipients );
		}
		$this->save_post_meta( array(
			self::$meta_keys['recipients'] => $recipients,
		) );
		return $recipients;
	}

	//////////////
	// Utility //
	//////////////

	public static function get_messages_by_client_id( $client_id = 1 ) {
		$message_ids = self::find_by_meta( self::POST_TYPE, array( '_client_id' => $client_id ) );
		return $message_ids;
	}

	public static function get_messages_to_send( $timestamp = 0, $last_send = 0 ) {
		$args = array(
				'post_type' => self::POST_TYPE,
				'post_status' => array( self::STATUS_FUTURE ),
				'posts_per_page' => -1,
				'fields' => 'ids',
				'meta_query' => array(
					'relation' => 'AND',
					array(
						'key' => self::$meta_keys['send_time'],
						'value' => array(
							0,
							// $last_send,
							$timestamp,
							),
						'compare' => 'BETWEEN',
						),
					),
			);
		$message_ids = get_posts( $args );
		return $message_ids;
	}
	// A pretty basic post type. Not much else to do here.
}
