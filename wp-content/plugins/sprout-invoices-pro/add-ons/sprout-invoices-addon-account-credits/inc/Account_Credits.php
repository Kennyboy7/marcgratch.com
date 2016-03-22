<?php

/**
 * Account_Credits Controller
 *
 * @package Sprout_Invoice
 * @subpackage Account_Credits
 */
class SI_Account_Credits extends SI_Controller {
	const SUBMISSION_NONCE = 'si_credit_submission';
	const IMPORT_QUERY_VAR = 'import-unbilled-credit';
	const LINE_ITEM_TYPE = 'credit';


	public static function init() {

		if ( is_admin() ) {

			// Enqueue
			add_action( 'admin_enqueue_scripts', array( __CLASS__, 'register_resources' ) );
			add_action( 'admin_enqueue_scripts', array( __CLASS__, 'admin_enqueue' ), 20 );

		}

		// Add Credit Type
		add_filter( 'si_line_item_types',  array( __CLASS__, 'add_credit_line_item_type' ) );
		add_filter( 'si_line_item_columns',  array( __CLASS__, 'add_credit_line_item_type_columns' ), -10, 2 );

	}

	///////////////
	// Line Item //
	///////////////

	public static function add_credit_line_item_type( $types = array() ) {
		$types = array_merge( $types, array( self::LINE_ITEM_TYPE => __( 'Credit', 'sprout-invoices' ) ) );
		return $types;
	}

	public static function add_credit_line_item_type_columns( $columns = array(), $type = '' ) {
		if ( self::LINE_ITEM_TYPE !== $type ) {
			return $columns;
		}
		$columns = array(
			'desc' => array(
					'label' => __( 'Credit', 'sprout-invoices' ),
					'type' => 'textarea',
					'calc' => false,
					'hide_if_parent' => false,
					'weight' => 1,
				),
			'rate' => array(
					'label' => __( 'Rate', 'sprout-invoices' ),
					'type' => 'small-input',
					'placeholder' => '-1',
					'calc' => false,
					'hide_if_parent' => true,
					'weight' => 10,
				),
			'qty' => array(
					'label' => __( 'Credits', 'sprout-invoices' ),
					'type' => 'small-input',
					'placeholder' => 1,
					'calc' => true,
					'hide_if_parent' => true,
					'weight' => 5,
				),
			'tax' => array(
					'type' => 'hidden',
					'placeholder' => 0,
					'calc' => false,
					'weight' => 15,
				),
			'total' => array(
					'label' => __( 'Amount', 'sprout-invoices' ),
					'type' => 'total',
					'placeholder' => sa_get_formatted_money( 0 ),
					'calc' => true,
					'hide_if_parent' => false,
					'weight' => 50,
				),
			'sku' => array(
					'type' => 'hidden',
					'placeholder' => '',
					'calc' => false,
					'weight' => 50,
				),
			'credit_id' => array(
					'type' => 'hidden',
					'placeholder' => '',
					'calc' => false,
					'weight' => 50,
				),
		);
		return $columns;
	}

	//////////////
	// Enqueue //
	//////////////

	public static function register_resources() {
		// admin js
		wp_register_script( 'si_account_credits', SA_ADDON_ACCOUNT_CREDITS_URL . '/resources/admin/js/account_credit.js', array( 'jquery' ), self::SI_VERSION );
	}

	public static function admin_enqueue() {
		wp_enqueue_script( 'si_account_credits' );
	}


	///////////
	// Form //
	///////////

	public static function credit_entry_fields() {
		$clients = array();
		$credit_types = array();
		$fields = array();

		if ( isset( $_GET['post'] ) && SI_Client::POST_TYPE === get_post_type( get_the_id() )  ) {

			$fields['client_id'] = array(
					'weight' => 1,
					'label' => __( 'Client', 'sprout-invoices' ),
					'type' => 'hidden',
					'value' => get_the_id(),
				);

		} else {
			$args = array(
				'post_type' => SI_Client::POST_TYPE,
				'post_status' => 'publish',
				'posts_per_page' => -1,
				'fields' => 'ids',
			);
			$clients = get_posts( $args );
			$client_options = array();
			foreach ( $clients as $client_id ) {
				$client_options[ $client_id ] = get_the_title( $client_id );
			}
			$fields['client_id'] = array(
					'weight' => 1,
					'label' => __( 'Client', 'sprout-invoices' ),
					'type' => 'select',
					'options' => $client_options,
					//'attributes' => array( 'class' => 'select2' ),
				);
		}

		$description = sprintf( __( 'Select a credit type, <a href="%s">create a new type</a> or <a class="thickbox" href="%s" title="Edit Types">manage existing credit types</a>.', 'sprout-invoices' ), 'javascript:void(0)" id="show_credit_type_creation_modal"', admin_url( 'admin-ajax.php?action=sa_manage_credit&width=750&height=450' ) );

		$credit_types_options = SI_Credit::get_credit_types();
		$fields['credit_type_id'] = array(
				'weight' => 10,
				'label' => __( 'Type', 'sprout-invoices' ),
				'type' => 'select',
				'description' => $description,
				'options' => $credit_types_options,
			);

		$fields['credit'] = array(
			'weight' => 20,
			'label' => __( 'Credit', 'sprout-invoices' ),
			'type' => 'number',
			'description' => __( 'Set to 1:1 to your set currency.', 'sprout-invoices' ),
		);

		$fields['note'] = array(
			'weight' => 30,
			'label' => __( 'Note', 'sprout-invoices' ),
			'type' => 'textarea',
			'default' => '',
		);

		$fields['date'] = array(
			'weight' => 100,
			'label' => __( 'Date', 'sprout-invoices' ),
			'type' => 'date',
			'default' => date( 'Y-m-d', current_time( 'timestamp' ) ),
			'placeholder' => '',
		);

		$fields['nonce'] = array(
			'type' => 'hidden',
			'value' => wp_create_nonce( self::SUBMISSION_NONCE ),
			'weight' => 10000,
		);

		$fields = apply_filters( 'si_credit_entry_form_fields', $fields );
		uasort( $fields, array( __CLASS__, 'sort_by_weight' ) );
		return $fields;
	}

	public static function credit_creation_fields( $id = 0 ) {

		$fields['name'] = array(
			'weight' => 0,
			'label' => __( 'Name', 'sprout-invoices' ),
			'type' => 'text',
		);

		$fields['nonce'] = array(
			'type' => 'hidden',
			'value' => wp_create_nonce( self::SUBMISSION_NONCE ),
			'weight' => 10000,
		);

		$fields = apply_filters( 'si_credit_creation_form_fields', $fields );
		uasort( $fields, array( __CLASS__, 'sort_by_weight' ) );
		return $fields;
	}

	//////////////
	// Utility //
	//////////////

	public static function load_addon_view( $view, $args, $allow_theme_override = true ) {
		add_filter( 'si_views_path', array( __CLASS__, 'addons_view_path' ) );
		$view = self::load_view( $view, $args, $allow_theme_override );
		remove_filter( 'si_views_path', array( __CLASS__, 'addons_view_path' ) );
		return $view;
	}

	public static function load_addon_view_to_string( $view, $args, $allow_theme_override = true ) {
		ob_start();
		self::load_addon_view( $view, $args, $allow_theme_override );
		return ob_get_clean();
	}

	public static function addons_view_path() {
		return SA_ADDON_ACCOUNT_CREDITS_PATH . '/views/';
	}

	public static function delete_credit_entry( $credit_id = 0 ) {
		// records are normally deleted if their parent is deleted
		remove_action( 'deleted_post', array( 'SI_Internal_Records', 'attempt_associated_record_deletion' ) );
		// by removing this action the credit entries will be newly associated
		// with the default credit_type instead via
		add_action( 'deleted_post', array( __CLASS__, 'attempt_reassign_entries_to_default' ) );

		wp_delete_post( $credit_id, true );
	}

	public static function attempt_reassign_entries_to_default( $post_id = 0 ) {
		// prevent looping and checking if a record has a record associated with it.
		if ( get_post_type( $post_id ) !== SI_Record::POST_TYPE ) {
			global $wpdb;
			$parent_update = array( 'post_parent' => SI_Credit::default_credit() );
			$parent_where = array( 'post_parent' => $post_id, 'post_type' => SI_Record::POST_TYPE );
			$wpdb->update( $wpdb->posts, $parent_update, $parent_where );
		}
	}
}
