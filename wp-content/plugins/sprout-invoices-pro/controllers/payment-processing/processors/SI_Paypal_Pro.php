<?php

/**
 * Paypal offsite payment processor.
 *
 * These actions are fired for each checkout page.
 *
 * Payment page - 'si_checkout_action_'.SI_Checkouts::PAYMENT_PAGE
 * Review page - 'si_checkout_action_'.SI_Checkouts::REVIEW_PAGE
 * Confirmation page - 'si_checkout_action_'.SI_Checkouts::CONFIRMATION_PAGE
 *
 * Necessary methods:
 * get_instance -- duh
 * get_slug -- slug for the payment process
 * get_options -- used on the invoice payment dropdown
 * process_payment -- called when the checkout is complete before the confirmation page is shown. If a
 * payment fails than the user will be redirected back to the invoice.
 *
 * CC processors can override the credit card form with a credit_card_payment_form method
 *
 * @package SI
 * @subpackage Payment Processing_Processor
 */
class SI_Paypal_Pro extends SI_Credit_Card_Processors {
	const API_ENDPOINT_SANDBOX = 'https://api-3t.sandbox.paypal.com/nvp';
	const API_ENDPOINT_LIVE = 'https://api-3t.paypal.com/nvp';
	const MODE_TEST = 'sandbox';
	const MODE_LIVE = 'live';
	const API_USERNAME_OPTION = 'si_paypal_username';
	const API_SIGNATURE_OPTION = 'si_paypal_signature';
	const API_PASSWORD_OPTION = 'si_paypal_password';
	const API_MODE_OPTION = 'si_paypal_mode';
	const CANCEL_URL_OPTION = 'si_paypal_cancel_url';
	const RETURN_URL_OPTION = 'si_paypal_return_url';
	const CURRENCY_CODE_OPTION = 'si_paypal_currency';
	const PAYMENT_METHOD = 'Credit (PayPal WPP)';
	const PAYMENT_SLUG = 'paypal_pro';

	protected static $instance;
	protected static $api_mode = self::MODE_TEST;
	private static $api_username;
	private static $api_password;
	private static $api_signature;
	private static $cancel_url = '';
	private static $currency_code = 'USD';
	private static $version = '64';

	public static function get_instance() {
		if ( ! ( isset( self::$instance ) && is_a( self::$instance, __CLASS__ ) ) ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function get_api_url() {
		if ( self::$api_mode == self::MODE_LIVE ) {
			return self::API_ENDPOINT_LIVE;
		} else {
			return self::API_ENDPOINT_SANDBOX;
		}
	}

	private function get_redirect_url() {
		if ( self::$api_mode == self::MODE_LIVE ) {
			return self::API_REDIRECT_LIVE;
		} else {
			return self::API_REDIRECT_SANDBOX;
		}
	}

	public function get_payment_method() {
		return self::PAYMENT_METHOD;
	}

	public function get_slug() {
		return self::PAYMENT_SLUG;
	}

	public static function register() {
		self::add_payment_processor( __CLASS__, __( 'PayPal Payments Pro', 'sprout-invoices' ) );
	}

	public static function public_name() {
		return __( 'Credit Card', 'sprout-invoices' );
	}

	public static function checkout_options() {
		$option = array(
			'icons' => array(
				SI_URL . '/resources/front-end/img/visa.png',
				SI_URL . '/resources/front-end/img/mastercard.png',
				SI_URL . '/resources/front-end/img/amex.png',
				SI_URL . '/resources/front-end/img/discover.png',
			),
			'label' => __( 'Credit Card', 'sprout-invoices' ),
			'accepted_cards' => array(
				'visa',
				'mastercard',
				'amex',
				// 'diners',
				'discover',
				// 'jcb',
				// 'maestro'
				),
			);
		return apply_filters( 'si_paypal_pro_checkout_options', $option );
	}

	protected function __construct() {
		parent::__construct();
		self::$api_username = get_option( self::API_USERNAME_OPTION, '' );
		self::$api_password = get_option( self::API_PASSWORD_OPTION, '' );
		self::$api_signature = get_option( self::API_SIGNATURE_OPTION, '' );
		self::$api_mode = get_option( self::API_MODE_OPTION, self::MODE_TEST );
		self::$currency_code = get_option( self::CURRENCY_CODE_OPTION, 'USD' );

		if ( is_admin() ) {
			add_action( 'init', array( get_class(), 'register_options' ) );
		}

		// Remove pages
		add_filter( 'si_checkout_pages', array( $this, 'remove_checkout_pages' ) );

		add_action( 'processed_payment', array( $this, 'capture_payment_after_auth' ), 10 );
		add_action( 'si_manually_capture_purchase', array( $this, 'manually_capture_purchase' ), 10 );

		// Add Recurring button
		add_action( 'recurring_payments_profile_info', array( __CLASS__, 'paypal_profile_link' ) );
	}

	/**
	 * The review page is unnecessary
	 *
	 * @param array   $pages
	 * @return array
	 */
	public function remove_checkout_pages( $pages ) {
		unset( $pages[ SI_Checkouts::REVIEW_PAGE ] );
		return $pages;
	}

	/**
	 * Hooked on init add the settings page and options.
	 *
	 */
	public static function register_options() {
		// Settings
		$settings = array(
			'si_paypal_settings' => array(
				'title' => __( 'PayPal Payments Pro', 'sprout-invoices' ),
				'weight' => 200,
				'tab' => self::get_settings_page( false ),
				'settings' => array(
					self::API_MODE_OPTION => array(
						'label' => __( 'Mode', 'sprout-invoices' ),
						'option' => array(
							'type' => 'radios',
							'options' => array(
								self::MODE_LIVE => __( 'Live', 'sprout-invoices' ),
								self::MODE_TEST => __( 'Sandbox', 'sprout-invoices' ),
								),
							'default' => self::$api_mode,
							),
						),
					self::API_USERNAME_OPTION => array(
						'label' => __( 'API Username', 'sprout-invoices' ),
						'option' => array(
							'type' => 'text',
							'default' => self::$api_username,
							),
						),
					self::API_PASSWORD_OPTION => array(
						'label' => __( 'API Password', 'sprout-invoices' ),
						'option' => array(
							'type' => 'text',
							'default' => self::$api_password,
							),
						),
					self::API_SIGNATURE_OPTION => array(
						'label' => __( 'API Signature', 'sprout-invoices' ),
						'option' => array(
							'type' => 'text',
							'default' => self::$api_signature,
							),
						),
					self::CURRENCY_CODE_OPTION => array(
						'label' => __( 'Currency Code', 'sprout-invoices' ),
						'option' => array(
							'type' => 'text',
							'default' => self::$currency_code,
							'attributes' => array( 'class' => 'small-text' ),
							),
						),
					),
				),
			);
		do_action( 'sprout_settings', $settings, self::SETTINGS_PAGE );
	}

	/**
	 * Process a payment
	 *
	 * @param SI_Checkouts $checkout
	 * @param SI_Invoice $invoice
	 * @return SI_Payment|bool false if the payment failed, otherwise a Payment object
	 */
	public function process_payment( SI_Checkouts $checkout, SI_Invoice $invoice ) {
		$post_data = $this->nvp_data( $checkout, $invoice );
		do_action( 'si_log', __CLASS__ . '::' . __FUNCTION__ . ' - PayPal WPP post_data', $post_data );
		$response = wp_safe_remote_post( $this->get_api_url(), array(
				'httpversion' => '1.1',
				'body' => $post_data,
				'timeout' => apply_filters( 'http_request_timeout', 30 ),
				'sslverify' => false,
		) );
		do_action( 'si_log', __CLASS__ . '::' . __FUNCTION__ . ' - PayPal WPP $response', $response );

		if ( is_wp_error( $response ) ) {
			return false;
		}
		if ( $response['response']['code'] != '200' ) {
			return false;
		}
		$response = wp_parse_args( wp_remote_retrieve_body( $response ) );
		do_action( 'si_log', __CLASS__ . '::' . __FUNCTION__ . ' - PayPal EC Authorization Response (Parsed)', $response );

		if ( strpos( $response['ACK'], 'Success' ) !== 0 ) {
			$this->set_error_messages( $response );
			return false;
		}
		if ( strpos( $response['ACK'], 'SuccessWithWarning' ) === 0 ) {
			$this->set_error_messages( $response );
		}

		$payment_id = SI_Payment::new_payment( array(
			'payment_method' => $this->get_payment_method(),
			'invoice' => $invoice->get_id(),
			'amount' => $response['AMT'],
			'data' => array(
			'live' => ( self::$api_mode == self::MODE_LIVE ),
			'api_response' => $response,
			),
		), SI_Payment::STATUS_AUTHORIZED );
		if ( ! $payment_id ) {
			return false;
		}
		$payment = SI_Payment::get_instance( $payment_id );
		do_action( 'payment_authorized', $payment );

		$this->maybe_create_recurring_payment_profiles( $checkout, $invoice, $payment );

		return $payment;
	}

	/**
	 * Facility to capture a purchase manually.
	 *
	 * @param SI_Payment $payment
	 * @return void
	 */
	public function manually_capture_purchase( SI_Payment $payment ) {
		$this->capture_payment( $payment );
	}

	public function capture_payment_after_auth( SI_Payment $payment ) {
		$this->capture_payment( $payment );
	}

	public  function capture_payment( SI_Payment $payment ) {
		// is this the right payment processor? does the payment still need processing?
		if ( $payment->get_payment_method() == $this->get_payment_method() && $payment->get_status() != SI_Payment::STATUS_COMPLETE ) {
			$data = $payment->get_data();
			// Do we have a transaction ID to use for the capture?
			if ( isset( $data['api_response']['TRANSACTIONID'] ) && $data['api_response']['TRANSACTIONID'] ) {
				$transaction_id = $data['api_response']['TRANSACTIONID'];
				$post_data = $this->capture_nvp_data( $transaction_id, $payment->get_amount(), $payment->get_invoice_id() );

				do_action( 'si_log', __CLASS__ . '::' . __FUNCTION__ . ' - PayPal EC DoCapture Request', $post_data );

				$response = wp_safe_remote_post( $this->get_api_url(), array(
						'httpversion' => '1.1',
						'body' => $post_data,
						'timeout' => apply_filters( 'http_request_timeout', 15 ),
						'sslverify' => false,
				) );

				if ( ! is_wp_error( $response ) && $response['response']['code'] == '200' ) {
					$response = wp_parse_args( wp_remote_retrieve_body( $response ) );
					do_action( 'si_log', __CLASS__ . '::' . __FUNCTION__ . ' - PayPal WPP DoCapture Response', $response );

					if ( strpos( $response['ACK'], 'Success' ) === 0 ) {
						$payment->set_status( SI_Payment::STATUS_COMPLETE );
						do_action( 'payment_complete', $payment );
					} else {
						$error = array(
								'payment_id' => $payment->get_id(),
								'response' => $response,
							);
						do_action( 'si_error', __CLASS__ . '::' . __FUNCTION__ . ' - capture response error', $error );
						if ( $response['L_ERRORCODE0'] == 10601 || 10602 ) { // authorization expired or authorization complete
							$payment->set_status( SI_Payment::STATUS_VOID );
						}
					}
				}
			}
		}
	}

	/**
	 * Build the NVP data array for submitting the current checkout to PayPal as an Authorization request
	 *
	 * @param SI_Checkouts $checkout
	 * @return array
	 */
	private function nvp_data( SI_Checkouts $checkout, SI_Invoice $invoice ) {
		$client = $invoice->get_client();

		$user = si_who_is_paying( $invoice );
		// User email or none
		$user_email = ( $user ) ? $user->user_email : '' ;

		$nvpData['USER'] = self::$api_username;
		$nvpData['PWD'] = self::$api_password;
		$nvpData['SIGNATURE'] = self::$api_signature;
		$nvpData['VERSION'] = '56.0';

		$nvpData['METHOD'] = 'DoDirectPayment';
		$nvpData['PAYMENTACTION'] = 'Authorization';
		$nvpData['IPADDRESS'] = $_SERVER['REMOTE_ADDR'];

		$nvpData['CREDITCARDTYPE'] = self::get_card_type( $this->cc_cache['cc_number'] );
		$nvpData['ACCT'] = $this->cc_cache['cc_number'];
		$nvpData['EXPDATE'] = self::expiration_date( $this->cc_cache['cc_expiration_month'], $this->cc_cache['cc_expiration_year'] );
		$nvpData['CVV2'] = $this->cc_cache['cc_cvv'];

		$nvpData['FIRSTNAME'] = $checkout->cache['billing']['first_name'];
		$nvpData['LASTNAME'] = $checkout->cache['billing']['last_name'];
		$nvpData['EMAIL'] = $user->user_email;

		$nvpData['STREET'] = $checkout->cache['billing']['street'];
		$nvpData['CITY'] = $checkout->cache['billing']['city'];
		$nvpData['STATE'] = $checkout->cache['billing']['zone'];
		$nvpData['COUNTRYCODE'] = self::country_code( $checkout->cache['billing']['country'] );
		$nvpData['ZIP'] = $checkout->cache['billing']['postal_code'];

		$payment_amount = ( si_has_invoice_deposit( $invoice->get_id() ) ) ? $invoice->get_deposit() : $invoice->get_balance();
		$nvpData['AMT'] = si_get_number_format( $payment_amount );
		$nvpData['CURRENCYCODE'] = $this->get_currency_code( $invoice->get_id() );

		$item_amount = ( si_has_invoice_deposit( $invoice->get_id() ) ) ? $invoice->get_deposit() : $invoice->get_balance() - ( $invoice->get_tax_total() + $invoice->get_tax2_total() );
		$nvpData['ITEMAMT'] = si_get_number_format( $item_amount );

		//$nvpData['SHIPPINGAMT'] = si_get_number_format( $purchase->get_shipping_total( $this->get_payment_method() ) );

		$tax_amount = ( si_has_invoice_deposit( $invoice->get_id() ) ) ? 0 : $invoice->get_tax_total() + $invoice->get_tax2_total();
		$nvpData['TAXAMT'] = si_get_number_format( $tax_amount );

		$nvpData['INVNUM'] = $invoice->get_id().'#'.time();
		$nvpData['BUTTONSOURCE'] = self::PLUGIN_NAME;

		$nvpData += self::payment_request_line_items( $invoice );

		// Recurring billing agreement
		if ( si_is_invoice_recurring( $invoice ) ) {
			$nvpData['L_BILLINGTYPE0'] = 'RecurringPayments';
			$nvpData['L_BILLINGAGREEMENTDESCRIPTION0'] = $this->recurring_desc( $invoice );
		}

		$nvpData = apply_filters( 'si_paypal_wpp_nvp_data', $nvpData, $checkout, $i, $invoice );

		return $nvpData;
	}

	public static function payment_request_line_items( SI_Invoice $invoice ) {
		$i = 0;
		$total = 0;
		$line_items = $invoice->get_line_items();
		// we can add individual item info if there's actually an item cost
		foreach ( $line_items as $position => $data ) {
			$children = si_line_item_get_children( $data['key'], $line_items );
			$is_parent_line_item = ( ! empty( $children ) ) ? true : false ;
			if ( $is_parent_line_item ) {
				continue;
			}
			if ( $data['total'] ) {
				$nvpData[ 'L_NAME' . $i ] = html_entity_decode( strip_tags( $data['desc'] ), ENT_QUOTES, 'UTF-8' );
				$nvpData[ 'L_AMT' . $i ] = si_get_number_format( $data['rate'] * $data['qty'] );
				$nvpData[ 'L_NUMBER' . $i ] = $position;
				$nvpData[ 'L_QTY' . $i ] = 1;
				$total += si_get_number_format( $data['rate'] * $data['qty'] );
				$i++;
			}
			if ( floatval( $data['total'] ) !== floatval( $data['rate'] * $data['qty'] ) ) {
				$nvpData[ 'L_NAME' . $i ] = __( 'Adjustment: ', 'sprout-invoices' ) . ' ' . html_entity_decode( strip_tags( $data['desc'] ), ENT_QUOTES, 'UTF-8' );
				$nvpData[ 'L_AMT' . $i ] = si_get_number_format( $data['total'] - $data['rate'] * $data['qty'] );
				$nvpData[ 'L_NUMBER' . $i ] = $position.'.1';
				$nvpData[ 'L_QTY' . $i ] = 1;
				$total += si_get_number_format( $data['total'] - $data['rate'] * $data['qty'] );
				$i++;
			}
		}
		if ( $invoice->get_deposit() && $invoice->get_deposit() < $invoice->get_balance() ) {
			$nvpData[ 'L_NAME' . $i ] = __( 'Deposit Adjustment', 'sprout-invoices' );
			$nvpData[ 'L_AMT' . $i ] = si_get_number_format( $invoice->get_deposit() - $total );
			$nvpData[ 'L_NUMBER' . $i ] = time().'_dep';
			$nvpData[ 'L_QTY' . $i ] = '1';
			$i++;
		}

		// payment adjustments and discounts are overlooked for deposits.
		if ( $invoice->get_deposit() ) {
			return $nvpData;
		}

		$payments_total = $invoice->get_payments_total();
		if ( $payments_total > 0 ) {
			$nvpData[ 'L_NAME' . $i ] = __( 'Payment Adjustment', 'sprout-invoices' );
			$nvpData[ 'L_AMT' . $i ] = -si_get_number_format( $payments_total );
				$nvpData[ 'L_NUMBER' . $i ] = time().'_pay';
			$nvpData[ 'L_QTY' . $i ] = '1';
			$i++;
		}

		$discount = $invoice->get_discount_total();
		if ( $discount > 0.00 ) {
			$nvpData[ 'L_NAME' . $i ] = __( 'Invoice Discount', 'sprout-invoices' );

			$nvpData[ 'L_AMT' . $i ] = -si_get_number_format( $discount );
			$nvpData[ 'L_NUMBER' . $i ] = time().'_discount';
			$nvpData[ 'L_QTY' . $i ] = '1';
			$i++;
		}

		return $nvpData;
	}

	/**
	 * The the NVP data for submitting a DoCapture request
	 *
	 * @param string  $transaction_id
	 * @param array   $items
	 * @param string  $status
	 * @return array
	 */
	private function capture_nvp_data( $transaction_id, $amount, $invoice_id ) {
		$nvpData = array();

		$nvpData['USER'] = self::$api_username;
		$nvpData['PWD'] = self::$api_password;
		$nvpData['SIGNATURE'] = self::$api_signature;
		$nvpData['VERSION'] = self::$version;

		$nvpData['METHOD'] = 'DoCapture';
		$nvpData['AUTHORIZATIONID'] = $transaction_id;
		$nvpData['AMT'] = si_get_number_format( $amount );
		$nvpData['CURRENCYCODE'] = self::get_currency_code( $invoice_id );
		$nvpData['COMPLETETYPE'] = 'Complete';

		$nvpData = apply_filters( 'si_paypal_wpp_capture_nvp_data', $nvpData );
		return $nvpData;
	}

	////////////////
	// Recurring //
	////////////////


	/**
	 * Create recurring payment profiles for any recurring invoices in the purchase
	 * @param  SI_Invoice $invoice
	 * @param  SI_Payment $payment
	 * @return void
	 */
	private function maybe_create_recurring_payment_profiles( SI_Checkouts $checkout, SI_Invoice $invoice, SI_Payment $payment ) {
		if ( $payment->get_payment_method() != $this->get_payment_method() ) {
			return false;
		}
		if ( si_is_invoice_recurring( $invoice ) ) {
			$this->create_recurring_payment_profile( $checkout, $invoice, $payment );
		}
	}

	/**
	 * Create the recurring payment profile.
	 */
	private function create_recurring_payment_profile( SI_Checkouts $checkout, SI_Invoice $invoice, SI_Payment $payment ) {

		$post_data = $this->create_recurring_payment_nvp_data( $checkout, $invoice, $payment );

		if ( ! $post_data ) {
			return false; // paying for it some other way
		}

		do_action( 'si_log', __CLASS__ . '::' . __FUNCTION__ . ' - PayPal EC Recurring Payment Request', $post_data );

		$response = wp_safe_remote_post( $this->get_api_url(), array(
				'httpversion' => '1.1',
				'body' => $post_data,
				'timeout' => apply_filters( 'http_request_timeout', 15 ),
				'sslverify' => false,
		) );

		do_action( 'si_log', __CLASS__ . '::' . __FUNCTION__ . ' - PayPal EC Recurring Payment Response (Raw)', $response );

		if ( is_wp_error( $response ) ) {
			return false;
		}
		if ( $response['response']['code'] != '200' ) {
			return false;
		}

		$response = wp_parse_args( wp_remote_retrieve_body( $response ) );

		do_action( 'si_log', __CLASS__ . '::' . __FUNCTION__ . ' - PayPal EC Recurring Payment Response (Parsed)', $response );

		if ( empty( $response['PROFILEID'] ) ) {
			do_action( 'si_paypal_recurring_payment_profile_failed', $response );
			return false;
		}

		$payment_id = SI_Payment::new_payment( array(
			'payment_method' => self::get_payment_method(),
			'invoice' => $invoice->get_id(),
			'amount' => $post_data['AMT'],
			'data' => array(
			'live' => ( self::$api_mode == self::MODE_LIVE ),
			'api_response' => $response,
			),
		), SI_Payment::STATUS_RECURRING );

		// let the world know
		do_action( 'si_paypal_recurring_payment_profile_created', $payment_id );
		return true;
	}

	private function create_recurring_payment_nvp_data( SI_Checkouts $checkout, SI_Invoice $invoice, SI_Payment $payment ) {

		$invoice_id = $invoice->get_id();
		$payment_data = $payment->get_data();

		$term = SI_Subscription_Payments::get_term( $invoice_id ); // day, week, month, or year
		$duration = (int) SI_Subscription_Payments::get_duration( $invoice_id );
		$price = SI_Subscription_Payments::get_renew_price( $invoice_id );

		$terms = array(
			'day' => 'Day',
			'week' => 'Week',
			//'bymonth' => 'SemiMonth',
			'month' => 'Month',
			'year' => 'Year',
		);
		if ( ! isset( $terms[ $term ] ) ) {
			$term = 'day';
		}

		// The first payment was just now, so
		// the subscription starts based on the term
		$starts = strtotime( date( 'Y-m-d' ).' +'.$duration.' '.$term );

		$user = si_who_is_paying( $invoice );
		// User email or none
		$user_email = ( $user ) ? $user->user_email : '' ;

		$nvp = array(
			'USER' => self::$api_username,
			'PWD' => self::$api_password,
			'SIGNATURE' => self::$api_signature,
			'VERSION' => self::$version,
			'METHOD' => 'CreateRecurringPaymentsProfile',

			'PROFILESTARTDATE' => date( 'Y-m-d', $starts ).'T00:00:00Z',
			'PROFILEREFERENCE' => $payment->get_id(),
			'DESC' => $this->recurring_desc( $invoice ),
			'MAXFAILEDPAYMENTS' => 2,
			'AUTOBILLOUTAMT' => 'AddToNextBilling',
			'BILLINGPERIOD' => $terms[ $term ],
			'BILLINGFREQUENCY' => $duration,
			'TOTALBILLINGCYCLES' => 0,

			'AMT' => si_get_number_format( $price ),
			'CURRENCYCODE' => self::get_currency_code( $invoice_id ),
			'EMAIL' => $user_email,

			'L_PAYMENTREQUEST_0_ITEMCATEGORY0' => 'Digital',
			'L_PAYMENTREQUEST_0_NAME0' => $invoice->get_title(),
			'L_PAYMENTREQUEST_0_AMT0' => si_get_number_format( $price ),
			'L_PAYMENTREQUEST_0_NUMBER0' => $invoice_id,
			'L_PAYMENTREQUEST_0_QTY0' => 1,

			'CREDITCARDTYPE' => self::get_card_type( $this->cc_cache['cc_number'] ),
			'ACCT' => $this->cc_cache['cc_number'],
			'EXPDATE' => self::expiration_date( $this->cc_cache['cc_expiration_month'], $this->cc_cache['cc_expiration_year'] ),
			'CVV2' => $this->cc_cache['cc_cvv'],
			'FIRSTNAME' => $checkout->cache['billing']['first_name'],
			'LASTNAME' => $checkout->cache['billing']['last_name'],
			'STREET' => $checkout->cache['billing']['street'],
			'CITY' => $checkout->cache['billing']['city'],
			'STATE' => $checkout->cache['billing']['zone'],
			'COUNTRYCODE' => self::country_code( $checkout->cache['billing']['country'] ),
			'ZIP' => $checkout->cache['billing']['postal_code'],
		);
		return $nvp;
	}

	public function verify_recurring_payment( SI_Invoice $invoice ) {
		// Get the profile status
		//  - see https://www.x.com/developers/paypal/documentation-tools/api/getrecurringpaymentsprofiledetails-api-operation-nvp
		$payment = self::get_recurring_payment( $invoice );
		if ( ! $payment ) {
			return;
		}
		$status = self::get_recurring_payment_status( $invoice );
		do_action( 'si_verify_recurring_payment_status', $status, $payment );
		if ( $status != 'Active' ) {
			$payment->set_status( SI_Payment::STATUS_CANCELLED );
		}
		return $status;
	}

	private function get_recurring_payment_status( SI_Invoice $invoice ) {
		$profile_id = self::get_payment_profile_id( $invoice );
		if ( ! $profile_id ) {
			return false;
		}
		$nvp = array(
			'USER' => self::$api_username,
			'PWD' => self::$api_password,
			'SIGNATURE' => self::$api_signature,
			'VERSION' => self::$version,
			'METHOD' => 'GetRecurringPaymentsProfileDetails',
			'PROFILEID' => $profile_id,
		);

		do_action( 'si_log', __CLASS__ . '::' . __FUNCTION__ . ' - PayPal EC Recurring Payment Details Request', $nvp );

		$response = wp_safe_remote_post( self::get_api_url(), array(
				'httpversion' => '1.1',
				'method' => 'POST',
				'body' => $nvp,
				'timeout' => apply_filters( 'http_request_timeout', 15 ),
				'sslverify' => false,
		) );

		do_action( 'si_log', __CLASS__ . '::' . __FUNCTION__ . ' - PayPal EC Recurring Payment Details Response (Raw)', $response );

		if ( is_wp_error( $response ) ) {
			return false;
		}
		if ( $response['response']['code'] != '200' ) {
			return false;
		}

		$response = wp_parse_args( wp_remote_retrieve_body( $response ) );
		do_action( 'si_verify_recurring_payment', $response, $invoice );

		do_action( 'si_log', __CLASS__ . '::' . __FUNCTION__ . ' - PayPal EC Recurring Payment Details Response (Parsed)', $response );

		if ( empty( $response['STATUS'] ) ) {
			return false;
		}

		return $response['STATUS'];
	}

	public function cancel_recurring_payment( SI_Invoice $invoice ) {
		$profile_id = self::get_payment_profile_id( $invoice );
		if ( ! $profile_id ) {
			return false;
		}

		// Cancel the profile
		//  - see https://cms.paypal.com/us/cgi-bin/?cmd=_render-content&content_ID=developer/e_howto_api_nvp_r_ManageRecurringPaymentsProfileStatus

		$nvp = array(
			'USER' => self::$api_username,
			'PWD' => self::$api_password,
			'SIGNATURE' => self::$api_signature,
			'VERSION' => self::$version,
			'METHOD' => 'ManageRecurringPaymentsProfileStatus',
			'PROFILEID' => $profile_id,
			'ACTION' => 'Cancel',
			'NOTE' => apply_filters( 'si_paypal_recurring_payment_cancelled_note', '' ),
		);

		do_action( 'si_log', __CLASS__ . '::' . __FUNCTION__ . ' - PayPal EC Cancel Recurring Payment Request', $nvp );

		$response = wp_safe_remote_post( self::get_api_url(), array(
				'httpversion' => '1.1',
				'method' => 'POST',
				'body' => $nvp,
				'timeout' => apply_filters( 'http_request_timeout', 15 ),
				'sslverify' => false,
		) );

		do_action( 'si_log', __CLASS__ . '::' . __FUNCTION__ . ' - PayPal EC Cancel Recurring Payment Response (Raw)', $response );

		do_action( 'si_cancelled_recurring_payment', $response, $invoice );
	}

	public static function get_payment_profile_id( $invoice ) {
		$payment = self::get_recurring_payment( $invoice );
		if ( ! $payment ) {
			return;
		}
		$data = $payment->get_data();
		if ( empty( $data['api_response']['PROFILEID'] ) ) {
			return;
		}
		$profile_id = $data['api_response']['PROFILEID'];
		return $profile_id;
	}

	public function recurring_desc( $invoice ) {
		$length = 127;
		$title = html_entity_decode( strip_tags( 'Billing Agreement: ' . $invoice->get_title() ), ENT_QUOTES, 'UTF-8' );
		return ( strlen( $title ) > $length ) ? substr( $title, 0, $length - strlen( '...' ) ) . '...' : $title;
	}

	public static function paypal_profile_link( $payment ) {
		if ( $payment->get_payment_method() != self::get_payment_method() ) {
			return;
		}
		$data = $payment->get_data();
		if ( isset( $data['api_response']['PROFILEID'] ) ) {

			$invoice_id = $payment->get_invoice_id();
			$invoice = SI_Invoice::get_instance( $invoice_id );

			printf( __( '<b>Current Payment Status:</b> <code>%s</code>', 'sprout-invoices' ), self::verify_recurring_payment( $invoice ) );
			echo ' &mdash; ';
			_e( 'Paypal Profile ID: ', 'sprout-invoices' );
			if ( isset( $data['live'] ) && ! $data['live'] ) {
				printf( '<a class="payment_profile_link" href="https://www.sandbox.paypal.com/us/cgi-bin/webscr?cmd=_profile-recurring-payments&encrypted_profile_id=%s" target="_blank">%s</a>', $data['api_response']['PROFILEID'], $data['api_response']['PROFILEID'] );
			} else {
				printf( '<a class="payment_profile_link" href="https://www.paypal.com/us/cgi-bin/webscr?cmd=_profile-recurring-payments&encrypted_profile_id=%s" target="_blank">%s</a>', $data['api_response']['PROFILEID'], $data['api_response']['PROFILEID'] );
			}
		}
	}


	//////////////
	// Utility //
	//////////////
	/**
	 * Format the month and year as an expiration date
	 *
	 * @static
	 * @param int     $month
	 * @param int     $year
	 * @return string
	 */
	private static function expiration_date( $month, $year ) {
		return sprintf( '%02d%04d', $month, $year );
	}

	private static function country_code( $country = null ) {
		if ( null != $country ) {
			return $country;
		}
		return 'US';
	}

	private function get_currency_code( $invoice_id ) {
		return apply_filters( 'si_currency_code', self::$currency_code, $invoice_id, self::PAYMENT_METHOD );
	}

	/**
	 * Grabs error messages from a PayPal response and displays them to the user
	 *
	 * @param array   $response
	 * @param bool    $display
	 * @return void
	 */
	private function set_error_messages( $response, $display = true ) {
		foreach ( $response as $key => $value ) {
			if ( preg_match( '/^L_SHORTMESSAGE(\d+)$/', $key, $matches ) ) {
				$message_id = $matches[1];
				$message = $value;
				if ( isset( $response[ 'L_LONGMESSAGE'.$message_id ] ) ) {
					$message .= sprintf( ': %s', $response[ 'L_LONGMESSAGE'.$message_id ] );
				}
				if ( isset( $response[ 'L_ERRORCODE'.$message_id ] ) ) {
					$message .= sprintf( __( ' (Error Code: %s)', 'sprout-invoices' ), $response[ 'L_ERRORCODE'.$message_id ] );
				}
				if ( $display ) {
					self::set_message( $message, self::MESSAGE_STATUS_ERROR );
				} else {
					do_action( 'si_error', __CLASS__ . '::' . __FUNCTION__ . ' - Paypal Pro Error Response', $response );
				}
			}
		}
	}
}
SI_Paypal_Pro::register();
