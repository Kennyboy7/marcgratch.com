<?php

/**
 * Sprout_Invoices Payment Gateway
 *
 * Provides a Sprout_Invoices Payment Gateway, mainly for testing purposes.
 *
 * @class 		WC_Gateway_Sprout_Invoices
 * @extends		WC_Payment_Gateway
 * @version		2.1.0
 * @package		WooCommerce/Classes/Payment
 * @author 		WooThemes
 */
class WC_Gateway_Sprout_Invoices extends WC_Payment_Gateway {

	/**
	 * Constructor for the gateway.
	 */
	public function __construct() {
		$this->id                 = 'sprout_invoices';
		$this->icon               = apply_filters( 'woocommerce_sprout_invoices_icon', '' );
		$this->has_fields         = false;
		$this->method_title       = __( 'Sprout Invoices', 'sprout-invoices' );
		$this->method_description = __( 'Allows an invoice to be created for Sprout Invoices.', 'sprout-invoices' );

		// Load the settings.
		$this->init_form_fields();
		$this->init_settings();

		// Define user set variables
		$this->title        = $this->get_option( 'title' );
		$this->description  = $this->get_option( 'description' );
		$this->instructions = $this->get_option( 'instructions', $this->description );

		// Actions
		add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );
		add_action( 'woocommerce_thankyou_sprout_invoices', array( $this, 'thankyou_page' ) );

		// Customer Emails
		add_action( 'woocommerce_email_before_order_table', array( $this, 'email_instructions' ), 10, 3 );
	}

	/**
	 * Initialise Gateway Settings Form Fields
	 */
	public function init_form_fields() {

		$this->form_fields = array(
			'enabled' => array(
				'title'   => __( 'Enable/Disable', 'sprout-invoices' ),
				'type'    => 'checkbox',
				'label'   => __( 'Enable Sprout Invoices Payment', 'sprout-invoices' ),
				'default' => 'yes',
			),
			'title' => array(
				'title'       => __( 'Title', 'sprout-invoices' ),
				'type'        => 'text',
				'description' => __( 'This controls the title which the user sees during checkout.', 'sprout-invoices' ),
				'default'     => __( 'Sprout Invoices Payment', 'sprout-invoices' ),
				'desc_tip'    => true,
			),
			'description' => array(
				'title'       => __( 'Description', 'sprout-invoices' ),
				'type'        => 'textarea',
				'description' => __( 'Payment method description that the customer will see on your checkout.', 'sprout-invoices' ),
				'default'     => __( 'An invoice will be created for later payment after checkout.', 'sprout-invoices' ),
				'desc_tip'    => true,
			),
			'instructions' => array(
				'title'       => __( 'Instructions', 'sprout-invoices' ),
				'type'        => 'textarea',
				'description' => __( 'Instructions that will be added to the thank you page and emails.', 'sprout-invoices' ),
				'default'     => __( 'Your invoice can be found at {invoice_url}.', 'sprout-invoices' ),
				'desc_tip'    => true,
			),
		);
	}

	/**
	 * Output for the order received page.
	 */
	public function thankyou_page( $order_id ) {
		if ( $this->instructions ) {
			echo wpautop( wptexturize( $this->get_instructions( $order_id ) ) );
		}
	}

	/**
	 * Add content to the WC emails.
	 *
	 * @access public
	 * @param WC_Order $order
	 * @param bool $sent_to_admin
	 * @param bool $plain_text
	 */
	public function email_instructions( $order, $sent_to_admin, $plain_text = false ) {
		if ( $this->instructions && ! $sent_to_admin && 'sprout_invoices' === $order->payment_method && $order->has_status( 'on-hold' ) ) {
			echo wpautop( wptexturize( $this->get_instructions( $order->id ) ) ) . PHP_EOL;
		}
	}

	/**
	 * Process the payment and return the result
	 *
	 * @param int $order_id
	 * @return array
	 */
	public function process_payment( $order_id ) {

		$order = wc_get_order( $order_id );

		///////////////////////////
		// Create Sprout Invoice //
		///////////////////////////

		/**
		 * Build line item array
		 * @var array
		 */
		$line_items = array();
		$tax = 0;
		foreach ( $order->get_items() as $key => $item ) {
			$_product  = apply_filters( 'woocommerce_order_item_product', $order->get_product_from_item( $item ), $item );
			if ( $_product && ! $_product->is_visible() ) {
				$desc = apply_filters( 'woocommerce_order_item_name', $item['name'], $item );
			} else {
				$desc = apply_filters( 'woocommerce_order_item_name', sprintf( '<a href="%s">%s</a>', get_permalink( $item['product_id'] ), $item['name'] ), $item );
			}
			$line_items[] = array(
				'rate' => $item['line_subtotal'] / $item['qty'],
				'qty' => (int) $item['qty'],
				'desc' => $desc,
				'total' => $item['line_subtotal'],
				'tax' => -$tax,
				);
		}
		if ( $order->get_total_shipping() > 0.00  ) {
			$line_items[] = array(
				'rate' => $order->get_total_shipping(),
				'qty' => 1,
				'desc' => __( 'Shipping', 'sprout-invoices' ),
				'total' => $order->get_total_shipping(),
				'tax' => 0,
				);
		}
		foreach ( $order->get_tax_totals() as $code => $tax ) {
			$line_items[] = array(
				'rate' => $tax->amount - $order->get_total_tax_refunded_by_rate_id( $tax->rate_id ),
				'qty' => 1,
				'desc' => $tax->label,
				'total' => $tax->amount - $order->get_total_tax_refunded_by_rate_id( $tax->rate_id ),
				'tax' => 0,
				);
		}
		if ( '' !== $order->customer_note  ) {
			$line_items[] = array(
				'rate' => 0,
				'qty' => 0,
				'desc' => $order->customer_note,
				'total' => 0,
				'tax' => 0,
				);
		}
		$invoice_args = array(
			'status' => SI_Invoice::STATUS_PENDING,
			'subject' => sprintf( __( 'Order #%s', 'sprout-invoices' ), $order_id ),
			'line_items' => $line_items,
		);

		$invoice_id = SI_Invoice::create_invoice( $invoice_args );
		$invoice = SI_Invoice::get_instance( $invoice_id );
		$invoice->set_discount( $order->get_total_discount() );
		$invoice->set_total( $order->get_total() );

		if ( method_exists( $invoice, 'set_email' ) ) {
			$invoice->set_email( $order->billing_email );
		}

		if ( $order->get_user_id() ) {

			$client_ids = SI_Client::get_clients_by_user( $order->get_user_id() );
			if ( ! empty( $client_ids ) ) {
				$client_id = array_pop( $client_ids );
			}

			if ( ! $client_id ) {
				$address = array(
					'street' => $order->billing_address_1 . ' ' . $order->billing_address_2,
					'city' => $order->billing_city,
					'zone' => $order->billing_state,
					'postal_code' => $order->billing_postcode,
					'country' => $order->billing_country,
				);

				$args = array(
					'company_name' => ( '' !== $order->billing_company ) ? $order->billing_company : $order->billing_first_name . ' ' . $order->billing_last_name,
					'website' => '',
					'address' => $address,
					'user_id' => $order->get_user_id(),
				);
				$client_id = SI_Client::new_client( $args );
			}

			$invoice->set_client_id( $client_id );
		}

		// History
		do_action( 'si_new_record',
			sprintf( '<a href="%s">#%s</a>', add_query_arg( array( 'post' => $order_id, 'action' => 'edit' ), admin_url( 'post.php' ) ), $order_id ),
			'woocommerce_order',
			$invoice_id,
			sprintf( '<a href="%s">#%s</a>', add_query_arg( array( 'post' => $order_id, 'action' => 'edit' ), admin_url( 'post.php' ) ), $order_id ),
			0,
		false );

		// store the order id within the invoice
		update_post_meta( $invoice_id, Woo_Integration::ORDER_ID_META, $order_id );

		do_action( 'si_woocommerce_payment', $order_id, $invoice_id );

		////////////
		// Return //
		////////////

		// Mark as on-hold (we're awaiting the sprout_invoices)
		$order->update_status( 'on-hold', __( 'Awaiting invoice payment', 'sprout-invoices' ) );

		// Reduce stock levels
		$order->reduce_order_stock();

		// Remove cart
		WC()->cart->empty_cart();

		// Return thankyou redirect
		return array(
			'result' 	=> 'success',
			'redirect'	=> $this->get_return_url( $order ),
		);
	}

	public function get_instructions( $order_id ) {
		$invoice_id = $this->get_invoice_id( $order_id );
		$invoice_link = sprintf( '<a href="%1$s">%2$s</a>', get_permalink( $invoice_id ), __( 'invoice', 'sprout-invoices' ) );
		$instructions = str_replace( '{invoice_url}', $invoice_link, $this->instructions );
		return $instructions;
	}

	public function get_invoice_id( $order_id = 0 ) {
		$invoices = SI_Post_Type::find_by_meta( SI_Invoice::POST_TYPE, array( Woo_Integration::ORDER_ID_META => $order_id ) );
		if ( empty( $invoices ) ) {
			return 0;
		}
		$invoice_id = $invoices[0];
		return $invoice_id;
	}
}
