<?php

/**
 * Account_Credits Controller
 *
 * @package Sprout_Invoice
 * @subpackage Account_Credits
 */
class SI_Account_Credits_Invoices_Admin extends SI_Account_Credits {

	public static function init() {

		// Apply credit but before anything else, in case an payment is attempted
		add_action( 'sa_new_invoice', array( __CLASS__, 'maybe_apply_credit_to_invoice_balance' ), 0, 1 );
		add_action( 'si_recurring_invoice_created', array( __CLASS__, 'maybe_apply_credit_to_new_recurring_invoice' ), 0 );

		if ( is_admin() ) {
			add_action( 'si_payments_meta_box_pre', array( __CLASS__, 'import_credit' ) );
			add_action( 'si_save_line_items_meta_box', array( __CLASS__, 'mark_credit_invoiced' ), 10, 3 );
		}

	}

	public static function maybe_apply_credit_to_new_recurring_invoice( $invoice_id ) {
		$invoice = SI_Invoice::get_instance( $invoice_id );
		self::maybe_apply_credit_to_invoice_balance( $invoice );
	}

	public static function maybe_apply_credit_to_invoice_balance( SI_Invoice $invoice ) {
		$client_id = $invoice->get_client_id();
		if ( ! $client_id ) {
			return;
		}

		$credits = SI_Account_Credits_Clients::get_associated_credits( $client_id );
		if ( empty( $credits ) ) {
			return;
		}

		$invoice_total = $invoice->get_calculated_total();

		$credit_total_used = 0;
		$credit_array = array();
		foreach ( $credits as $credit_id ) {
			$credit = SI_Record::get_instance( $credit_id );
			if ( ! is_a( $credit, 'SI_Record' ) ) {
				continue;
			}
			$credit_type = SI_Credit::get_instance( $credit->get_associate_id() );
			$data = $credit->get_data();

			// Don't return the credit that has already been invoiced
			if ( isset( $data['invoice_id'] ) ) {
				continue;
			}

			$credit_total_used += si_get_number_format( $data['credit_val'] );

			if ( $credit_total_used > $invoice_total ) {
				$credit_total_used -= si_get_number_format( $data['credit_val'] );
				continue;
			}

			$description = ( is_a( $credit_type, 'SI_Credit' ) ) ? '<b>' . get_the_title( $credit_type->get_id() ) . "</b>\n" . $credit->get_title() . "\n<small>" . date_i18n( get_option( 'date_format' ), $data['date'] ) . '</small>' : $credit->get_title() . "\n<small>" . date_i18n( get_option( 'date_format' ), $data['date'] ) . '</small>';
			$description = apply_filters( 'the_content', $description );

			$credit_array[] = array(
				'_id' => $credit_id . mt_rand(),
				'type' => self::LINE_ITEM_TYPE,
				'rate' => '-1',
				'qty' => si_get_number_format( $data['credit_val'] ),
				'total' => sprintf( '-%s', si_get_number_format( $data['credit_val'] ) ),
				'desc' => apply_filters( 'si_invoice_credit_imported_description', $description ),
				);

			SI_Credit::add_invoice_id( $credit_id, $invoice->get_id() );
		}
		$line_items = array_merge( $invoice->get_line_items(), $credit_array );
		$invoice->set_line_items( $line_items );

		$invoice->set_calculated_total();
	}


	public static function import_credit() {
		$invoice = SI_Invoice::get_instance( get_the_ID() );
		if ( ! is_a( $invoice, 'SI_Invoice' ) ) {
			return;
		}
		if ( 0.01 > $invoice->get_balance() ) {
			return;
		}
		$client_id = $invoice->get_client_id();
		$invoice_id = $invoice->get_invoice_id();

		$client_options = array();
		$args = array(
			'post_type' => SI_Client::POST_TYPE,
			'post_status' => 'publish',
			'posts_per_page' => -1,
			'fields' => 'ids',
		);
		$clients = get_posts( $args );
		foreach ( $clients as $client_id ) {
			$client_options[ $client_id ] = get_the_title( $client_id );
		}

		self::load_addon_view( 'admin/meta-boxes/invoices/credit-invoicing.php', array(
				'invoice' => $invoice,
				'invoice_id' => $invoice_id,
				'client_id' => $client_id,
				'client_options' => $client_options,
		), true );
	}



	/**
	 * Save the credit id within the line item data array, then add the invoice id
	 * to the the credit so it will be marked as billed.
	 * @param  int $invoice_id
	 * @param  array $post
	 * @param  object $invoice
	 * @return
	 */
	public static function mark_credit_invoiced( $invoice_id, $post, $invoice ) {
		if ( ! is_a( $invoice, 'SI_Invoice' ) ) { // not estimates
			return;
		}

		// credit to mark as billed
		$mark_credit_invoiced = array();
		$line_items = $invoice->get_line_items();
		foreach ( $_POST['line_item_key'] as $key => $order ) {
			if ( isset( $_POST['line_item_desc'][ $key ] ) && $_POST['line_item_desc'][ $key ] != '' ) {
				if ( isset( $line_items[ $order ] ) ) {
					$line_items[ $order ]['credit_id'] = ( isset( $_POST['line_item_credit_id'][ $key ] ) && $_POST['line_item_credit_id'][ $key ] != '' ) ? $_POST['line_item_credit_id'][ $key ] : 0;
					if ( $line_items[ $order ]['credit_id'] ) {
						$mark_credit_invoiced[] = $line_items[ $order ]['credit_id'];
					}
				}
			}
		}

		// Add the invoice id to the credit's data
		foreach ( $mark_credit_invoiced as $credit_id ) {
			SI_Credit::add_invoice_id( $credit_id, $invoice_id );
		}
	}
}
