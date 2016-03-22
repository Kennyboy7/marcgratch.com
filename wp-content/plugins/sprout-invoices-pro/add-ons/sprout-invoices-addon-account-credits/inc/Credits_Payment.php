<?php

/**
 * Account_Credits Controller
 *
 * @package Sprout_Invoice
 * @subpackage Account_Credits
 */
class Credits_Payment extends SI_Controller {
	const PAYMENT_METHOD = 'Credit';
	const PAYMENT_SLUG = 'credit_payment';
	const NONCE = 'si_payments_nonce';

	public static function get_payment_method() {
		return self::PAYMENT_METHOD;
	}

	public static function get_slug() {
		return self::PAYMENT_SLUG;
	}

	public static function init() {
		// NADA
	}

	public static function create_admin_payment( $invoice_id = 0, $amount = '0.00', $number = '', $date = '', $notes = '' ) {
		if ( did_action( 'si_new_payment' ) > 0 ) { // make sure this
			return;
		}
		$invoice = SI_Invoice::get_instance( $invoice_id );
		// create new payment
		$payment_id = SI_Payment::new_payment( array(
			'payment_method' => self::get_payment_method(),
			'invoice' => $invoice_id,
			'amount' => $amount,
			'transaction_id' => $number,
			'data' => array(
			'amount' => $amount,
			'check_number' => $number,
			'date' => strtotime( $date ),
			'notes' => $notes,
			),
		), SI_Payment::STATUS_COMPLETE );
		if ( ! $payment_id ) {
			return false;
		}
		$payment = SI_Payment::get_instance( $payment_id );
		if ( $date != '' ) {
			$payment->set_post_date( date( 'Y-m-d H:i:s', strtotime( $date ) ) );
		}
		do_action( 'admin_payment', $payment_id, $invoice );
		do_action( 'payment_complete', $payment );

		return $payment_id;
	}
}

