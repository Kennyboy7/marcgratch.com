<?php

/**
 * Account_Credits Controller
 *
 * @package Sprout_Invoice
 * @subpackage Account_Credits
 */
class SI_Account_Credits_AJAX extends SI_Account_Credits {

	public static function init() {

		if ( is_admin() ) {
			// AJAX
			add_action( 'wp_ajax_sa_get_credit_item',  array( __CLASS__, 'maybe_get_item' ), 10, 0 );
		}

		// ajax actions
		add_action( 'wp_ajax_sa_create_credit_type',  array( __CLASS__, 'maybe_create_credit_type' ), 10, 0 );
		add_action( 'wp_ajax_sa_remove_credit_entry',  array( __CLASS__, 'maybe_delete_credit_entry' ), 10, 0 );
		add_action( 'wp_ajax_sa_save_credit',  array( __CLASS__, 'maybe_save_credit_type' ), 10, 0 );

		add_action( 'wp_ajax_sa_invoices_credit',  array( __CLASS__, 'invoices_credit' ), 10, 0 );
		add_action( 'wp_ajax_sa_remove_credit',  array( __CLASS__, 'maybe_delete_credit_type' ), 10, 0 );

		// Payment
		add_action( 'wp_ajax_sa_invoices_credit_payment',  array( __CLASS__, 'invoices_credit_payment' ), 10, 0 );

		// ajax views
		add_action( 'wp_ajax_sa_manage_credit',  array( __CLASS__, 'credit_admin_view' ), 10, 0 );
		add_action( 'wp_ajax_sa_credit_creation',  array( __CLASS__, 'credit_creation_view' ), 10, 0 );
		add_action( 'wp_ajax_sa_credit_tracker',  array( __CLASS__, 'credit_tracker_view' ), 10, 0 );
		add_action( 'wp_ajax_sa_credit_entries_table',  array( __CLASS__, 'credit_entries_table_view' ), 10, 0 );

		add_filter( 'si_admin_scripts_localization',  array( __CLASS__, 'ajax_l10n' ) );

		// front-end
		add_action( 'wp_ajax_sa_credit_tracker_view',  array( __CLASS__, 'front_end_credit_tracker_view' ), 10, 0 );
		add_action( 'wp_ajax_nopriv_sa_credit_tracker_view',  array( __CLASS__, 'front_end_credit_tracker_view' ), 10, 0 );

	}

	///////////////////
	// AJAX Response //
	///////////////////



	public static function maybe_get_item() {
		if ( ! current_user_can( 'publish_sprout_invoices' ) ) {
			wp_send_json_error( array( 'message' => __( 'User cannot create an item!', 'sprout-invoices' ) ) );
		}

		$credit = array();
		if ( isset( $_REQUEST['credit'] ) && is_array( $_REQUEST['credit'] ) ) {
			$credit = $_REQUEST['credit'];
		}

		if ( ! $credit ) {
			wp_send_json_error( array( 'message' => __( 'No credit given!', 'sprout-invoices' ) ) );
		}

		$item_data = array(
				'type' => self::LINE_ITEM_TYPE,
				'desc' => $credit['description'],
				'rate' => 0 - (int) $credit['rate'],
				'qty' => (float) $credit['qty'],
				'tax' => 0,
				'sku' => '',
				'title' => $credit['description'],
				'note' => $credit['note'],
				'credit_id' => $credit['id'],
			);

		ob_start();
		SI_Controller::load_view( 'admin/sections/line-item-options', array(
			'columns' => SI_Line_Items::line_item_columns( self::LINE_ITEM_TYPE ),
			'item_data' => $item_data,
			'has_children' => false,
			'items' => array(),
			'position' => 0,
			'children' => array(),
		), false );
		$option = ob_get_clean();

		$view = sprintf( '<li id="line_item_loaded_%1$s" class="item line_item_type_%1$s" data-id="0">%2$s</li>', $item_data['type'], $option );

		$response = array(
				'option' => $view,
				'type' => $item_data['type'],
			);
		wp_send_json_success( $response );
	}

	/////////////////////
	// AJAX Callbacks //
	/////////////////////

	public static function maybe_create_credit_type() {
		if ( ! current_user_can( 'edit_sprout_invoices' ) ) {
			self::ajax_fail( 'User cannot create SI credit!' );
		}

		$nonce = $_REQUEST['nonce'];
		if ( ! wp_verify_nonce( $nonce, self::SUBMISSION_NONCE ) ) {
			self::ajax_fail( 'Not going to fall for it!' ); }

		if ( ! isset( $_REQUEST['name'] ) || '' === $_REQUEST['name'] ) {
			self::ajax_fail( 'No name given.' );
		}

		$args = array();
		$args['name'] = $_REQUEST['name'];
		$id = SI_Credit::new_credit_type( $args );
		$credit = SI_Credit::get_instance( $id );

		$response = array(
				'id' => $id,
				'title' => get_the_title( $id ),
				'option_name' => $credit->get_title(),
			);

		wp_send_json_success( $response );
	}

	public static function maybe_delete_credit_type() {
		$nonce = $_REQUEST['nonce'];
		if ( ! wp_verify_nonce( $nonce, self::SUBMISSION_NONCE ) ) {
			wp_send_json_error( array( 'message' => 'Not going to fall for it!' ) );
		}

		if ( isset( $_REQUEST['id'] ) ) {
			$credit_id = $_REQUEST['id'];
		}
		if ( ! $credit_id ) {
			wp_send_json_error( array( 'message' => 'No id given.' ) );
		}
		if ( SI_Credit::POST_TYPE !== get_post_type( $credit_id ) ) {
			wp_send_json_error( array( 'message' => 'Not an credit type.' ) );
		}

		self::delete_credit_entry( $credit_id );

		wp_send_json_success();
	}

	public static function maybe_save_credit_type() {

		$nonce = $_REQUEST['nonce'];
		if ( ! wp_verify_nonce( $nonce, self::SUBMISSION_NONCE ) ) {
			wp_send_json_error( array( 'message' => 'Not going to fall for it!' ) );
		}

		$client_id = 0;
		if ( isset( $_REQUEST['client_id'] ) ) {
			$client_id = $_REQUEST['client_id'];
		}
		if ( ! $client_id ) {
			wp_send_json_error( array( 'message' => 'No client id' ) );
		}

		if ( isset( $_REQUEST['credit'] ) ) {
			$credit = floatval( $_REQUEST['credit'] );
		}
		if ( ! $credit ) {
			wp_send_json_error( array( 'message' => 'A credit value is required.' ) );
		}

		$client = SI_Client::get_instance( $client_id );
		if ( ! is_a( $client, 'SI_Client' ) ) {
			wp_send_json_error( array( 'message' => 'Client not found.' ) );
		}

		$args = array();
		$args['client_id'] = (int) $client_id;
		$args['credit_val'] = (float) si_get_number_format( (float) $credit );

		if ( isset( $_REQUEST['credit_type_id'] ) ) {
			$args['credit_type_id'] = (int) $_REQUEST['credit_type_id'];
		}

		if ( isset( $_REQUEST['note'] ) ) {
			$args['note'] = esc_textarea( $_REQUEST['note'] );
		}

		if ( isset( $_REQUEST['date'] ) ) {
			$args['date'] = (int) strtotime( $_REQUEST['date'] );
		}

		$defaults = array(
			'client_id' => (int) $client->get_id(),
			'credit_type_id' => (int) 0,
			'credit_val' => (float) si_get_number_format( (float) 0 ),
			'note' => '',
			'date' => (int) current_time( 'timestamp' ),
			'user_id' => get_current_user_id(),
		);
		$data = wp_parse_args( $args, $defaults );

		$new_credit_id = SI_Account_Credits_Clients::create_associated_credit( $client_id, $data );
		do_action( 'si_credit_created', $new_credit_id );

		wp_send_json_success( $data );
	}

	public static function maybe_delete_credit_entry() {
		$nonce = $_REQUEST['nonce'];
		if ( ! wp_verify_nonce( $nonce, self::SUBMISSION_NONCE ) ) {
			wp_send_json_error( array( 'message' => 'Not going to fall for it!' ) );
		}

		if ( isset( $_REQUEST['client_id'] ) ) {
			$client_id = $_REQUEST['client_id'];
		}
		if ( ! $client_id ) {
			wp_send_json_error( array( 'message' => 'No invoice id' ) );
		}
		$invoice = SI_Invoice::get_instance( $client_id );

		if ( isset( $_REQUEST['id'] ) ) {
			$credit_id = $_REQUEST['id'];
		}
		if ( ! $credit_id ) {
			wp_send_json_error( array( 'message' => 'No id given.' ) );
		}
		$credit = SI_Record::get_instance( $credit_id );

		SI_Account_Credits_Clients::remove_credit_associated( $client_id, $credit_id );

		do_action( 'si_credit_deleted', $credit_id );

		wp_send_json_success();
	}

	public static function invoices_credit_payment() {
		$nonce = $_REQUEST['nonce'];
		if ( ! wp_verify_nonce( $nonce, self::SUBMISSION_NONCE ) ) {
			wp_send_json_error( array( 'message' => 'Not going to fall for it!' ) );
		}

		if ( isset( $_REQUEST['client_id'] ) ) {
			$client_id = $_REQUEST['client_id'];
		}
		if ( ! $client_id ) {
			wp_send_json_error( array( 'message' => 'No client id' ) );
		}
		$client = SI_Client::get_instance( $client_id );
		if ( ! is_a( $client, 'SI_Client' ) ) {
			wp_send_json_error( array( 'message' => 'Client not found' ) );
		}

		if ( isset( $_REQUEST['invoice_id'] ) ) {
			$invoice_id = $_REQUEST['invoice_id'];
		}
		if ( ! $invoice_id ) {
			wp_send_json_error( array( 'message' => 'No invoice id' ) );
		}
		$invoice = SI_Invoice::get_instance( $invoice_id );
		if ( ! is_a( $invoice, 'SI_Invoice' ) ) {
			wp_send_json_error( array( 'message' => 'Invoice not found' ) );
		}

		$total_credits = SI_Account_Credits_Clients::get_credit_balance( $client_id );

		if ( $total_credits < 0.01 ) {
			wp_send_json_error( array( 'message' => __( 'Client has no credits', 'sprout-invoices' ) ) );
		}

		$balance = $invoice->get_balance();
		$amount = ( $total_credits <= $balance ) ? $total_credits : $balance ;

		// Create negative balance credit
		$credit_data = array(
			'client_id' => (int) $client_id,
			'type_id' => (int) SI_Credit::get_payment_credit_type(),
			'credit_val' => (float) -$amount,
			'note' => sprintf(
				__( 'Credit Payment Applied to Invoice: %s', 'sprout-invoices' ),
				sprintf( '<a href="%s">%s</a>', get_edit_post_link( $invoice_id ), get_the_title( $invoice_id )
				)
			),
			'date' => (int) current_time( 'timestamp' ),
			'user_id' => get_current_user_id(),
		);
		$new_credit_id = SI_Account_Credits_Clients::create_associated_credit( $client_id, $credit_data );

		// Make a payment
		$payment_id = Credits_Payment::create_admin_payment( $invoice_id, $amount, $new_credit_id, date( get_option( 'date_format' ), time() ), sprintf( __( 'Credit Applied from Account Balance. Payment Credit ID #%s', 'sprout-invoices' ), $new_credit_id ) );

		if ( ! $payment_id ) {
			wp_send_json_error( array( 'message' => 'Payment not created.' ) );
		}

		do_action( 'si_credit_payment', $new_credit_id, $payment_id );

		$data = array(
			'payment_credit_id' => $new_credit_id,
			'payment_id' => $payment_id,
			);

		$credit = SI_Credit::get_credit_entry( $new_credit_id );
		$data = array_merge( $credit->get_data(), $data );
		$credit->set_data( $data );

		wp_send_json_success( $data );

	}

	public static function invoices_credit() {
		$nonce = $_REQUEST['nonce'];
		if ( ! wp_verify_nonce( $nonce, self::SUBMISSION_NONCE ) ) {
			wp_send_json_error( array( 'message' => 'Not going to fall for it!' ) );
		}

		if ( isset( $_REQUEST['client_id'] ) ) {
			$client_id = $_REQUEST['client_id'];
		}
		if ( ! $client_id ) {
			wp_send_json_error( array( 'message' => 'No client id' ) );
		}
		$client = SI_Client::get_instance( $client_id );
		if ( ! is_a( $client, 'SI_Client' ) ) {
			wp_send_json_error( array( 'message' => 'Client not found' ) );
		}
		$credits = SI_Account_Credits_Clients::get_associated_credits( $client_id );

		$credit_data = array();
		if ( ! empty( $credits ) ) {
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

				$description = ( is_a( $credit_type, 'SI_Credit' ) ) ? '<b>' . get_the_title( $credit_type->get_id() ) . "</b>\n" . $credit->get_title() . "\n<small>" . date_i18n( get_option( 'date_format' ), $data['date'] ) . '</small>' : $credit->get_title() . "\n<small>" . date_i18n( get_option( 'date_format' ), $data['date'] ) . '</small>';
				$description = apply_filters( 'the_content', $description );

				$credit_data[] = array(
					'id' => $credit_id,
					'date' => date_i18n( get_option( 'date_format' ), $data['date'] ),
					'note' => apply_filters( 'the_content', $credit->get_title() ),
					'qty' => si_get_number_format( $data['credit_val'] ),
					'rate' => 1,
					'description' => apply_filters( 'si_invoice_credit_imported_description', $description ),
					'credit_type_id' => ( is_a( $credit_type, 'SI_Credit' ) ) ? $credit_type->get_id() : false,
					'credit_type' => ( is_a( $credit_type, 'SI_Credit' ) ) ? $credit_type->get_title() : '',
					);
			}
		}
		if ( empty( $credit_data ) ) {
			wp_send_json_error( array( 'message' => 'Nothing to import' ) );
		}
		wp_send_json_success( $credit_data );

	}

	////////////////
	// AJAX View //
	////////////////

	public static function ajax_l10n( $js_object = array() ) {
		$js_object['credit_creation_modal_title'] = __( 'Create Credit Type', 'sprout-invoices' );
		$js_object['credit_creation_modal_url'] = admin_url( 'admin-ajax.php?action=sa_credit_creation&height=200&width=300' );
		$js_object['credit_tracker_modal_title'] = __( 'Save Credit', 'sprout-invoices' );
		$js_object['credit_tracker_modal_url'] = admin_url( 'admin-ajax.php?action=sa_credit_tracker&height=450&width=300' );
		$js_object['credit_tracker_success_message'] = __( 'Credit saved!', 'sprout-invoices' );
		return $js_object;
	}

	/**
	 * Meta box view
	 * Abstracted to be called via AJAX
	 * @param int $client_id
	 *
	 */
	public static function credit_entries_table_view( $client_id = 0 ) {
		if ( ! current_user_can( 'edit_sprout_invoices' ) ) {
			wp_send_json_error( array( 'message' => 'User cannot create new posts!' ) );
		}

		if ( isset( $_REQUEST['client_id'] ) ) {
			$client_id = $_REQUEST['client_id'];
		}

		if ( ! $client_id ) {
			wp_send_json_error( array( 'message' => 'No invoice id' ) );
		}

		$client = SI_Client::get_instance( $client_id );
		$credits = SI_Account_Credits_Clients::get_associated_credits( $client_id );
		self::load_addon_view( 'admin/meta-boxes/accounts/credit-entries', array(
				'credits' => $credits,
				'client_id' => $client->get_id(),
		), true );
		exit();
	}

	public static function credit_admin_view() {
		if ( ! current_user_can( 'edit_sprout_invoices' ) ) {
			wp_send_json_error( array( 'message' => 'User cannot create new posts!' ) );
		}

		$args = array(
			'post_type' => SI_Credit::POST_TYPE,
			'post_status' => 'any',
			'posts_per_page' => -1,
			'fields' => 'ids',
		);
		$credit_types = get_posts( $args );
		self::load_addon_view( 'admin/sections/credit-admin', array(
				'credit' => $credit_types,
		), true );
		exit();
	}

	public static function credit_creation_view() {
		if ( ! current_user_can( 'edit_sprout_invoices' ) ) {
			wp_send_json_error( array( 'message' => 'User cannot create new posts!' ) );
		}

		// Credit creation
		$fields = self::credit_creation_fields();
		self::load_addon_view( 'admin/sections/credit-creation-form', array(
				'fields' => $fields,
		), true );
		exit();
	}

	/**
	 * Meta box view
	 * Abstracted to be called via AJAX
	 * @param int $invoice_id
	 *
	 */
	public static function credit_tracker_view( $invoice_id = 0 ) {
		if ( ! current_user_can( 'edit_sprout_invoices' ) ) {
			wp_send_json_error( array( 'message' => 'User cannot create new posts!' ) );
		}

		if ( ! $invoice_id && isset( $_REQUEST['invoice_id'] ) ) {
			$invoice_id = $_REQUEST['invoice_id'];
		}

		$fields = self::credit_entry_fields( $invoice_id );
		self::load_addon_view( 'admin/sections/credit-tracker', array(
				'fields' => $fields,
		), true );
		exit();
	}

	/**
	 * Meta box view
	 * Abstracted to be called via AJAX
	 * @param int $invoice_id
	 *
	 */
	public static function front_end_credit_tracker_view( $invoice_id = 0 ) {
		if ( ! current_user_can( 'edit_sprout_invoices' ) ) {
			wp_login_form();
			exit();
		}

		if ( ! $invoice_id && isset( $_REQUEST['invoice_id'] ) ) {
			$invoice_id = $_REQUEST['invoice_id'];
		}
		$fields = self::credit_entry_fields( $invoice_id );
		unset( $fields['credit_type_id']['description'] );
		unset( $fields['credit_inc']['description'] );
		$fields['date']['weight'] = 25;
		uasort( $fields, array( __CLASS__, 'sort_by_weight' ) );

		// enqueue
		wp_register_script( 'si_account_credits', SA_ADDON_ACCOUNT_CREDITS_URL . '/resources/admin/js/credit_entry.js', array( 'jquery' ), self::SI_VERSION );
		wp_enqueue_script( 'si_account_credits' );
		add_filter( 'si_sprout_doc_scripts_localization',  array( 'SI_Account_Credits', 'ajax_l10n' ) );
		wp_register_style( 'sprout_doc_style', SI_URL . '/resources/front-end/css/sprout-invoices.style.css', array( 'open-sans' ), self::SI_VERSION );
		wp_enqueue_style( 'sprout_doc_style' );

		self::load_addon_view( 'public/credit-tracker', array(
				'fields' => $fields,
		), true );
		exit();
	}
}
