<?php

/**
 * Hooks for registered shortcodes and shortcode callbacks
 *
 * @package Sprout_Invoice
 * @subpackage Notification
 */
class SI_Notifications_Premium extends SI_Notifications_Control {

	public static function init() {
		// register notifications
		add_filter( 'sprout_notifications', array( __CLASS__, 'register_notifications' ) );
		// Shortcodes
		// parent class should have all shortcodes, regardless if they're premium

		// Hook actions that would send a notification
		self::notification_hooks();

		add_filter( 'show_upgrade_messaging', '__return_false' );
	}


	/**
	 * Hooks for all notifications
	 * @return
	 */
	private static function notification_hooks() {
		// Notifications can be suppressed
		if ( apply_filters( 'suppress_notifications', false ) ) {
			return;
		}
		// Lead generation
		add_action( 'estimate_submitted', array( __CLASS__, 'estimate_submitted_notification' ), 10, 1 );
		// payments
		add_action( 'payment_complete', array( __CLASS__, 'deposit_notification' ), 10, 2 );
		// payment reminder
		if ( self::DEBUG ) {
			add_action( 'init', array( __CLASS__, 'maybe_send_invoice_payment_reminder' ) );
		} else {
			add_action( self::CRON_HOOK, array( __CLASS__, 'maybe_send_invoice_payment_reminder' ) );
		}

		// Admin
		add_action( 'estimate_submitted', array( __CLASS__, 'admin_lead_submitted' ), 10, 2 );
	}

	public static function register_notifications( $notifications = array() ) {
		$default_notifications = array(
				// Lead Generation
				'estimate_received' => array(
					'name' => __( 'Lead Received', 'sprout-invoices' ),
					'description' => __( 'Customize the email that is sent to a prospective client after a lead is submitted.', 'sprout-invoices' ),
					'shortcodes' => array( 'date', 'name', 'username', 'lead_entries', 'estimate_id', 'estimate_edit_url', 'client_name', 'client_edit_url', 'estimate_total', 'estimate_subtotal' ),
					'default_title' => sprintf( __( '%s: Estimate Request Received', 'sprout-invoices' ), get_bloginfo( 'name' ) ),
					'default_content' => self::load_view_to_string( 'notifications/request-received', null ),
				),
				// Payments
				'deposit_payment' => array(
					'name' => __( 'Deposit Payment Received', 'sprout-invoices' ),
					'description' => __( 'Customize the payment email that is sent to the client recipients when a deposit is made.', 'sprout-invoices' ),
					'shortcodes' => array( 'date', 'name', 'username', 'payment_total', 'payment_id', 'line_item_table', 'line_item_list','invoice_subject', 'invoice_id', 'invoice_edit_url', 'invoice_url', 'invoice_issue_date', 'invoice_due_date', 'invoice_past_due_date', 'invoice_po_number', 'invoice_total', 'invoice_subtotal', 'invoice_calculated_total', 'invoice_total_due', 'invoice_deposit_amount', 'invoice_total_payments', 'client_name', 'client_edit_url' ),
					'default_title' => sprintf( __( '%s: Deposit Received', 'sprout-invoices' ), get_bloginfo( 'name' ) ),
					'default_content' => self::load_view_to_string( 'notifications/payment-deposit', null ),
				),
				'reminder_payment' => array(
					'name' => __( 'Payment Reminder', 'sprout-invoices' ),
					'description' => __( 'Customize the email that is sent to the client recipients in order to remind them that their payment is overdue.', 'sprout-invoices' ),
					'shortcodes' => array( 'date', 'name', 'username', 'payment_total', 'payment_id', 'line_item_table', 'line_item_list','invoice_subject', 'invoice_id', 'invoice_edit_url', 'invoice_url', 'invoice_issue_date', 'invoice_due_date', 'invoice_past_due_date', 'invoice_po_number', 'invoice_total', 'invoice_subtotal', 'invoice_calculated_total', 'invoice_total_due', 'invoice_deposit_amount', 'invoice_total_payments', 'client_name', 'client_edit_url' ),
					'default_title' => sprintf( __( '%s: Invoice Payment Overdue', 'sprout-invoices' ),  get_bloginfo( 'name' ) ),
					'default_content' => self::load_view_to_string( 'notifications/payment-reminder', null ),
					'default_disabled' => true,
				),
				// Admin Notifications
				'estimate_submitted' => array(
					'name' => __( 'Lead Submitted', 'sprout-invoices' ),
					'description' => __( 'Customize the email that is sent to the site admin after an lead is submitted.', 'sprout-invoices' ),
					'shortcodes' => array( 'date', 'name', 'username', 'lead_entries', 'line_item_table', 'line_item_list','estimate_subject', 'estimate_id', 'estimate_edit_url', 'estimate_url', 'estimate_issue_date', 'estimate_po_number', 'estimate_total', 'estimate_subtotal', 'client_name', 'client_edit_url' ),
					'default_title' => sprintf( __( '%s: Estimate Request Received', 'sprout-invoices' ), get_bloginfo( 'name' ) ),
					'default_content' => self::load_view_to_string( 'notifications/admin-request-submitted', null ),
				),
			);
		return array_merge( $notifications, $default_notifications );
	}

	/////////////////////////////
	// notification callbacks //
	/////////////////////////////

	public static function estimate_submitted_notification( SI_Estimate $estimate, $submission = array() ) {
		$client = $estimate->get_client();
		$client_users = self::get_document_recipients( $estimate );
		foreach ( array_unique( $client_users ) as $user_id ) {
			$to = self::get_user_email( $user_id );
			$data = array(
				'user_id' => $user_id,
				'estimate' => $estimate,
				'client' => $client,
				'submission_fields' => $submission,
				'to' => $to,
			);
			self::send_notification( 'estimate_received', $data, $to );
		}
	}

	public static function deposit_notification( SI_Payment $payment, $args = array() ) {
		$invoice_id = $payment->get_invoice_id();
		$invoice = SI_Invoice::get_instance( $invoice_id );
		if ( ! is_a( $invoice, 'SI_Invoice' ) ) {
			do_action( 'si_error', 'Deposit Notification Not Sent to Client; No Invoice Found: ' . $invoice_id, $payment->get_id() );
			return;
		}
		if ( $invoice->get_balance() >= 0.01 ) { // leave a bit of room for floating point arithmetic
			$client = $invoice->get_client();
			$client_users = self::get_document_recipients( $invoice );
			foreach ( array_unique( $client_users ) as $user_id ) {
				$to = self::get_user_email( $user_id );
				$data = array(
					'payment' => $payment,
					'invoice' => $invoice,
					'client' => $client,
					'to' => $to,
				);
				self::send_notification( 'deposit_payment', $data, $to );
			}
		}

	}

	/**
	 * Query for all invoices with past due date and marked partial or publish/pending.
	 *
	 * Add some post meta so notifications are not sent multiple times.
	 * @return
	 */
	public static function maybe_send_invoice_payment_reminder() {
		$option_key = 'last_overdue_invoices_notification_sent_timestamp';
		$last_check = get_option( $option_key, 0 );
		$delay = apply_filters( 'si_get_overdue_payment_reminder_delay', current_time( 'timestamp' ) - 60 * 60 * 24 );
		if ( $last_check > $delay ) {
			return;
		}
		$recently_overdue = SI_Invoice::get_overdue_invoices( $last_check, $delay );
		if ( empty( $recently_overdue ) ) { // no overdue invoices.
			update_option( $option_key, current_time( 'timestamp' ) );
			return;
		}

		foreach ( $recently_overdue as $invoice_id ) {
			$invoice = SI_Invoice::get_instance( $invoice_id );
			$client = $invoice->get_client();
			$client_users = self::get_document_recipients( $invoice );
			// send to user
			foreach ( array_unique( $client_users ) as $user_id ) {
				$to = self::get_user_email( $user_id );
				$data = array(
					'invoice' => $invoice,
					'client' => $client,
					'to' => $admin_to,
				);
				self::send_notification( 'reminder_payment', $data, $to );
			}
		}
		update_option( $option_key, current_time( 'timestamp' ) );
	}

	/**
	 * Estimate request received.
	 * @param  SI_Estimate $estimate
	 * @param  array       $submission
	 * @return
	 */
	public static function admin_lead_submitted( SI_Estimate $estimate, $submission = array() ) {
		// Admin email
		$data = array(
			'user_id' => $estimate->get_user_id(),
			'estimate' => $estimate,
			'client' => $estimate->get_client(),
			'submission_fields' => $submission,
		);
		$admin_to = self::admin_email( $data );
		self::send_notification( 'estimate_submitted', $data, $admin_to );
	}
}
