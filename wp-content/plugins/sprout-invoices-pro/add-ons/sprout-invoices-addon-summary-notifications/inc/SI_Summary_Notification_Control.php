<?php

/**
 * Controller
 */
class SI_Summary_Notification_Control extends SI_Controller {
	const DOW = 'si_summary_notification_dow_v1';
	const FREQ = 'si_summary_notification_freq_v1';
	const LAST_SEND = 'si_summary_notification_last_send';
	const NEXT_SEND = 'si_summary_notification_next_send';
	private static $dow;
	private static $frequency;
	private static $last_send;
	private static $next_send;

	public static function init() {
		self::$dow = self::get_dayofweek();
		self::$frequency = self::get_frequency();
		self::$last_send = get_option( self::LAST_SEND, 0 );
		self::$next_send = get_option( self::NEXT_SEND, 0 );

		// register settings
		self::register_settings();

		// Schedule next send when options are saved/updated
		add_action( 'update_option_' . self::DOW, array( __CLASS__, 'set_next_send_time_on_dow_save' ), 10, 2 );
		add_action( 'add_option_' . self::DOW, array( __CLASS__, 'set_next_send_time_on_dow_save' ), 10, 2 );

		if ( self::DEBUG ) {
			//add_action( 'admin_init', array( __CLASS__, 'maybe_send_summary_notifications' ) );
			//add_action( 'admin_init', array( __CLASS__, 'test_send' ) );
		} else {
			add_action( self::DAILY_CRON_HOOK, array( __CLASS__, 'maybe_send_summary_notifications' ) );
		}
	}

	public static function get_dayofweek() {
		$dow = get_option( self::DOW, 'fri' );
		return $dow;
	}

	public static function get_frequency() {
		$freq = get_option( self::FREQ, 'disabled' );
		return $freq;
	}

	public static function day_of_week_label() {
		switch ( self::$dow ) {
			case 'sun':
				$name = 'Sunday';
				break;
			case 'mon':
				$name = 'Monday';
				break;
			case 'tue':
				$name = 'Tuesday';
				break;
			case 'wed':
				$name = 'Wednesday';
				break;
			case 'thur':
				$name = 'Thursday';
				break;
			case 'fri':
				$name = 'Friday';
				break;
			case 'sat':
			default:
				$name = 'Saturday';
				break;
		}
		return $name;
	}

	/**
	 * Hooked on init add the settings page and options.
	 *
	 */
	public static function register_settings() {

		if ( self::$next_send ) {
			$description = sprintf( '%s<br/><code>%s</code>', __( 'Select a frequency for the client summary notifications.', 'sprout-invoices' ), sprintf( __( 'Next scheduled send is: %s', 'sprout-invoices' ), date( 'l jS \of F Y h:i:s A', self::$next_send ) ) );
		} else {
			$description = sprintf( '%s', __( 'Select a frequency for the client summary notifications.', 'sprout-invoices' ) );
		}
		// Settings
		$settings = array(
			'notifications_summary' => array(
				'weight' => 30.2,
				'tab' => 'settings',
				'settings' => array(
					self::FREQ => array(
						'label' => __( 'Client Summary Frequency', 'sprout-invoices' ),
						'option' => array(
							'type' => 'select',
							'options' => array(
								'disabled' => __( 'Disabled', 'sprout-invoices' ),
								'weekly' => __( 'Weekly', 'sprout-invoices' ),
								'bi-weekly' => __( 'Bi-weekly', 'sprout-invoices' ),
								'monthly' => __( 'Monthly', 'sprout-invoices' ),
								),
							'default' => self::get_frequency(),
							'description' => $description,
						),
					),
					self::DOW => array(
						'label' => __( 'Client Summary Day of Week', 'sprout-invoices' ),
						'option' => array(
							'type' => 'select',
							'options' => array(
								'sun' => __( 'Sunday', 'sprout-invoices' ),
								'mon' => __( 'Monday', 'sprout-invoices' ),
								'tue' => __( 'Tuesday', 'sprout-invoices' ),
								'wed' => __( 'Wednesday', 'sprout-invoices' ),
								'thur' => __( 'Thursday', 'sprout-invoices' ),
								'fri' => __( 'Friday', 'sprout-invoices' ),
								'sat' => __( 'Saturday', 'sprout-invoices' ),
								),
							'default' => self::get_dayofweek(),
							'description' => __( 'Select the day of the week the summaries should be sent. Any updates will reset the next send date to the latest day of the week.', 'sprout-invoices' ),
						),
					),
				),
			),
		);
		do_action( 'sprout_settings', $settings, self::SETTINGS_PAGE );
	}

	public static function maybe_send_summary_notifications() {
		// Notifications can be suppressed
		if ( apply_filters( 'suppress_notifications', false ) ) {
			return;
		}

		// frequency is set as disabled.
		if ( 'disabled' === self::$frequency ) {
			return;
		}

		// If this is the first send
		if ( 0 === self::$next_send ) {
			self::set_next_send_time();
		}

		$current_time = current_time( 'timestamp' );

		// Check if it's time to send yet.
		if ( self::$next_send > $current_time ) {
			return;
		}

		$clients = SI_Post_Type::find_by_meta( SI_Client::POST_TYPE );
		foreach ( $clients as $client_id ) {
			$client = SI_Client::get_instance( $client_id );
			$records = array();
			$records['invoices'] = self::get_clients_open_invoices( $client );
			$records['estimates'] = self::get_clients_open_estimates( $client );
			if ( empty( $records['invoices'] ) && empty( $records['estimates'] ) ) {
				continue;
			}
			self::send_summary_notification( $client, $records );
		}

		update_option( self::LAST_SEND, $current_time );
		self::set_next_send_time( 'future' );
	}

	public static function set_next_send_time_on_dow_save( $old_value = '', $new_value = '' ) {
		if ( $old_value === $new_value ) {
			return;
		}
		self::$frequency = self::get_frequency();
		if ( 'disabled' === self::$frequency ) {
			return;
		}
		self::$dow = $new_value;
		self::set_next_send_time();
	}

	public static function set_next_send_time( $time_is = 'now' ) {
		$time = current_time( 'timestamp' );
		switch ( self::$frequency ) {
			case 'weekly':
				if ( 'now' !== $time_is ) {
					$time = $time + (60 * 60 * 24 * 6);
				}
				$next_time = strtotime( self::day_of_week_label(), $time );
				break;
			case 'monthly':
				if ( 'now' !== $time_is ) {
					$time = $time + (60 * 60 * 24 * 28);
				}
				$next_time = strtotime( self::day_of_week_label(), $time );
				break;

			case 'bi-weekly':
				if ( 'now' !== $time_is ) {
					$time = $time + (60 * 60 * 24 * 13);
				}
				$next_time = strtotime( self::day_of_week_label(), $time );
				break;
			default:
				return;
				break;
		}
		update_option( self::NEXT_SEND, $next_time );
	}

	public static function send_summary_notification( SI_Client $client, $records = array() ) {
		if ( empty( $records ) ) { // nothing to summarize
			return;
		}
		if ( ! is_a( $client, 'SI_Client' ) ) {
			return;
		}
		$user_ids = $client->get_associated_users();
		if ( empty( $user_ids ) ) {
			return;
		}
		foreach ( $user_ids as $user_id ) {
			$to = SI_Notifications::get_user_email( $user_id );
			$data = array(
				'user_id' => $user_id,
				'client' => $client,
				'records' => $records,
				'to' => $to,
				'_time' => strtotime( 'Today' ), // prevents duplicates from being sent but unique
			);
			SI_Notifications::send_notification( 'client_summary', $data, $to );
		}
	}

	public static function get_clients_open_invoices( SI_Client $client ) {
		$args = array(
				'post_type' => SI_Invoice::POST_TYPE,
				'post_status' => array( SI_Invoice::STATUS_PENDING, SI_Invoice::STATUS_PARTIAL ),
				'posts_per_page' => -1,
				'fields' => 'ids',
				'si_bypass_filter' => true,
				'meta_query' => array(
					array(
						'key' => '_client_id',
						'value' => $client->get_id(),
						),
					),
			);

		return get_posts( $args );
	}

	public static function get_clients_open_estimates( SI_Client $client ) {
		$args = array(
				'post_type' => SI_Estimate::POST_TYPE,
				'post_status' => array( SI_Estimate::STATUS_PENDING ),
				'posts_per_page' => -1,
				'fields' => 'ids',
				'si_bypass_filter' => true,
				'meta_query' => array(
					array(
						'key' => '_client_id',
						'value' => $client->get_id(),
						),
					),
			);

		return get_posts( $args );
	}

	public static function test_send() {

		self::set_next_send_time( 'now' );

		$client = SI_Client::get_instance( 742 );
		$records = array();
		$records['invoices'] = self::get_clients_open_invoices( $client );
		$records['estimates'] = self::get_clients_open_estimates( $client );
		if ( empty( $records['invoices'] ) && empty( $records['estimates'] ) ) {
			return;
		}
		self::send_summary_notification( $client, $records );
	}
}
SI_Summary_Notification_Control::init();
