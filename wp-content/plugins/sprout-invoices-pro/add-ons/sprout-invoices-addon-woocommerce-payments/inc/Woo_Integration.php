<?php

/**
 * Woo_Integration Controller
 *
 * @package Woo_Integration
 */
class Woo_Integration {
	const ORDER_ID_META = '_si_woocommerce_order';

	public static function init() {
		add_filter( 'woocommerce_payment_gateways',  array( __CLASS__, 'add_sprout_invoice_gateway' ) );

		add_action( 'si_invoice_status_updated', array( __CLASS__, 'maybe_change_order_status' ), 10, 2 );
	}

	public static function add_sprout_invoice_gateway( $methods = array() ) {
		$methods[] = 'WC_Gateway_Sprout_Invoices';
		return $methods;
	}

	public static function maybe_change_order_status( SI_Invoice $invoice, $status = '' ) {
		$order_id = get_post_meta( $invoice->get_id(), self::ORDER_ID_META, true );
		if ( ! $order_id ) {
			return;
		}
		$order = wc_get_order( $order_id );
		switch ( $status ) {
			case SI_Invoice::STATUS_PAID:
				$order->update_status( 'completed', __( 'Invoice payment complete', 'sprout-invoices' ) );
				break;
			case SI_Invoice::STATUS_WO:
				$order->update_status( 'cancelled', __( 'Invoice written-off', 'sprout-invoices' ) );
				break;
			default:
				break;
		}
	}
}
