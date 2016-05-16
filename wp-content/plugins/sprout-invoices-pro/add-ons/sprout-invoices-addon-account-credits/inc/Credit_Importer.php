<?php

/**
 *
 * @package SI_Importer
 * @subpackage Credits_Importer
 */
class SI_Account_Credits_Importer extends SI_Importer {
	const SETTINGS_PAGE = 'import';
	const PROCESS_ACTION = 'start_import';
	const DELETE_PROGRESS = 'remove_progress_option';
	const FILE_OPTION = 'si_credit_csv_upload';

	public static function init() {
		self::register_settings();
		self::save_options();

		// Maybe process import
		self::maybe_process_import();
	}

	public static function register() {
		self::add_importer( __CLASS__, __( 'CSV (Credits)', 'sprout-invoices' ) );
	}


	/**
	 * Register the credit settings
	 * @return
	 */
	public static function register_settings() {
		// Settings
		$settings = array(
			'si_csv_credit_importer_settings' => array(
				'title' => 'CSV Import Settings',
				'weight' => 0,
				'tab' => self::get_settings_page( false ),
				'settings' => array(
					self::FILE_OPTION => array(
						'label' => __( 'Credits', 'sprout-invoices' ),
						'option' => array(
							'type' => 'file',
							'description' => sprintf( __( 'Example CSV <a href="%s" target="_blank">here</a>. To be safe import no more than 100 credits at a time and import all of your invoices and clients and will be associated with these credits first.', 'sprout-invoices' ), SA_ADDON_ACCOUNT_CREDITS_URL . '/importers/credits-csv-example.csv' ),
						),
					),
					self::PROCESS_ACTION => array(
						'option' => array(
							'type' => 'hidden',
							'value' => wp_create_nonce( self::PROCESS_ACTION ),
						),
					),
				),
			),
		);
		do_action( 'sprout_settings', $settings, self::SETTINGS_PAGE );
	}

	public static function save_options() {

		if ( ! function_exists( 'wp_handle_upload' ) ) {
			require_once( ABSPATH . 'wp-admin/includes/file.php' );
		}

		$upload_overrides = array( 'test_form' => false, 'mimes' => array( 'csv' => 'text/csv' ) );
		if ( isset( $_FILES[ self::FILE_OPTION ] ) ) {
			$client_csv_file = $_FILES[ self::FILE_OPTION ];
			$client_csv = wp_handle_upload( $client_csv_file, $upload_overrides );
			if ( isset( $client_csv['file'] ) && $client_csv['file'] != '' ) {
				update_option( self::FILE_OPTION, $client_csv['file'] );
			}
		}

	}

	/**
	 * Check to see if it's time to start the import process.
	 * @return
	 */
	public static function maybe_process_import() {
		if ( isset( $_POST[ self::PROCESS_ACTION ] ) && wp_verify_nonce( $_POST[ self::PROCESS_ACTION ], self::PROCESS_ACTION ) ) {
			add_filter( 'si_show_importer_settings', '__return_false' );
		}
	}

	/**
	 * Utility to return a JSON error
	 * @param  string $message
	 * @return json
	 */
	public static function return_error( $message ) {
		header( 'Content-type: application/json' );
		if ( self::DEBUG ) { header( 'Access-Control-Allow-Origin: *' ); }
		echo wp_json_encode(
			array( 'error' => true, 'message' => $message )
		);
		exit();
	}

	/**
	 * Return the progress array
	 * @param  array  $array associated array with method and status message
	 * @return json
	 */
	public static function return_progress( $array = array() ) {
		header( 'Content-type: application/json' );
		if ( self::DEBUG ) { header( 'Access-Control-Allow-Origin: *' ); }
		echo wp_json_encode( $array );
		exit();
	}

	/**
	 * First step in the import progress
	 * @return
	 */
	public static function import_authentication() {
		$csv_file = get_option( self::FILE_OPTION );

		if ( ! $csv_file ) {
			// Completed previously
			self::return_progress( array(
				'authentication' => array(
				'message' => __( 'Skipping credit import without a CSV to process...', 'sprout-invoices' ),
				'progress' => 100,
				'next_step' => 'complete',
				),
			) );
			return;
		}

		$credits = self::csv_to_array( $csv_file );
		$total_records = count( $credits );

		// Suppress notifications
		add_filter( 'suppress_notifications', '__return_true' );

		foreach ( $credits as $key => $credit ) {
			$credit_id = self::create_credit( $credit );
			if ( ! $credit_id ) {
				$total_records--;
			}
		}

		// Complete
		self::return_progress( array(
			'authentication' => array(
			'message' => sprintf( __( 'Successfully imported %s credits...', 'sprout-invoices' ), $total_records ),
			'progress' => 100,
			'next_step' => 'complete',
			),
		) );

	}

	public static function create_credit( $credit_args = array() ) {
		$client_id = 0;
		if ( isset( $credit_args['client_id'] ) ) {
			$client_id = $credit_args['client_id'];
		}

		$client = SI_Client::get_instance( $client_id );
		if ( ! is_a( $client, 'SI_Client' ) ) {
			return false;
		}

		if ( isset( $credit_args['credit'] ) ) {
			$credit_val = floatval( $credit_args['credit'] );
		}

		$args = array();
		$args['client_id'] = (int) $client_id;
		$args['credit_val'] = (float) si_get_number_format( (float) $credit_val );

		if ( isset( $credit_args['credit_type_id'] ) ) {
			$args['credit_type_id'] = (int) $credit_args['credit_type_id'];
		}

		if ( isset( $credit_args['note'] ) ) {
			$args['note'] = esc_textarea( $credit_args['note'] );
		}

		if ( isset( $credit_args['date'] ) ) {
			$args['date'] = (int) strtotime( $credit_args['date'] );
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

		return $new_credit_id;
	}


	/*
	 * Singleton Design Pattern
	 * ------------------------------------------------------------- */
	protected function __clone() {
		// cannot be cloned
		trigger_error( __CLASS__.' may not be cloned', E_USER_ERROR );
	}

	protected function __sleep() {
		// cannot be serialized
		trigger_error( __CLASS__.' may not be serialized', E_USER_ERROR );
	}

	protected function __construct() {
		//
	}



	protected static function csv_to_array( $filename = '', $delimiter = ',', $fieldnames = '' ) {
		if ( ! file_exists( $filename ) || ! is_readable( $filename ) ) {
			return false;
		}
		if ( strlen( $fieldnames ) > 0 ) {
			$header = explode( ',', $fieldnames );
		} else {
			$header = null;
		}
		$data = array();
		if ( ( $handle = fopen( $filename, 'r' ) ) !== false ) {
			while ( ( $row = fgetcsv( $handle, 1000, $delimiter ) ) !== false ) {
				if ( ! $header ) {
					$header = $row; } else {
					$data[] = array_combine( $header, $row ); }
			}
			fclose( $handle );
		}
		return $data;
	}
}
