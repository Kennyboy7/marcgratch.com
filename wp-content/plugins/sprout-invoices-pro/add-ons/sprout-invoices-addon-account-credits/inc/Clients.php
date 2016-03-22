<?php

/**
 * Account_Credits Controller
 *
 * @package Sprout_Invoice
 * @subpackage Account_Credits
 */
class SI_Account_Credits_Clients extends SI_Account_Credits {

	private static $meta_keys = array(
		'associated_credit' => '_assoc_credit_records',
	); // A list of meta keys this class cares about. Try to keep them in alphabetical order.

	/**
	 * Get the associated credits with this client
	 * @return array
	 */
	public static function get_associated_credits( $client_id = 0 ) {
		$client = SI_Client::get_instance( $client_id );
		$credits = $client->get_post_meta( self::$meta_keys['associated_credit'] );
		if ( ! is_array( $credits ) ) {
			$credits = array();
		}
		return array_filter( $credits );
	}

	public static function get_credit_balance( $client_id = 0, $paid_out = false ) {
		$total_credits = 0;
		$paid_out_total_credits = 0;
		$credits = self::get_associated_credits( $client_id );

		$credit_records = array();
		foreach ( $credits as $credit_id ) {
			$credit = SI_Credit::get_credit_entry( $credit_id );
			if ( ! is_a( $credit, 'SI_Record' ) ) {
				continue;
			}
			$credit_records[ $credit_id ] = $credit->get_data();
		}

		foreach ( $credit_records as $credit_id => $data ) {
			$credit_val = (float) $data['credit_val'];
			if ( isset( $data['invoice_id'] ) ) {
				$paid_out_total_credits += $credit_val;
			} else {
				$total_credits += $credit_val;
			}
		}
		if ( $paid_out ) {
			return (float) $paid_out_total_credits;
		}

		return (float) $total_credits;
	}

	/**
	 * Save the associated credits with this client
	 * @param array $credits
	 */
	public static function set_associated_credits( $client_id = 0, $credits = array() ) {
		$client = SI_Client::get_instance( $client_id );
		$client->save_post_meta( array(
			self::$meta_keys['associated_credit'] => $credits,
		) );
		return $credits;
	}

	/**
	 * Clear out the associated credits
	 * @param array $credits
	 */
	public static function clear_associated_credits( $client_id = 0 ) {
		$client = SI_Client::get_instance( $client_id );
		$client->delete_post_meta( array(
			self::$meta_keys['associated_credit'] => '',
		) );
	}

	/**
	 * Add single credit to associated array
	 * @param array $credit_data
	 */
	public static function create_associated_credit( $client_id = 0, $credit_data = array() ) {
		$client = SI_Client::get_instance( $client_id );
		$credit = false;
		if ( isset( $credit_data['type_id'] ) ) {
			$credit = SI_Credit::get_instance( $credit_data['type_id'] );
		}
		if ( ! $credit || ! is_a( $credit, 'SI_Credit' ) ) {
			// get default credit to clock credit to.
			$type_id = SI_Credit::default_credit();
			$credit = SI_Credit::get_instance( $type_id );
		}
		// Create credit entry record
		$new_credit_id = $credit->new_credit( $credit_data );
		// Add to the associated array on this client
		self::add_associated_credit( $client_id, $new_credit_id );
		//$client->save_post(); // update modified credit.
		return $new_credit_id;
	}

	/**
	 * Add single credit to associated array
	 * @param int $credit_id
	 */
	public static function add_associated_credit( $client_id = 0, $credit_id = 0 ) {
		$credits = self::get_associated_credits( $client_id );
		$credits[] = $credit_id;
		self::set_associated_credits( $client_id, $credits );
	}

	/**
	 * Remove single credit to associated array
	 * @param int $credit_id
	 */
	public static function remove_credit_associated( $client_id = 0, $credit_id = 0 ) {
		// Delete credit record
		$credit = SI_Record::get_instance( $credit_id );
		if ( is_a( $credit, 'SI_Record' ) ) {
			$type_id = $credit->get_associate_id();
			$type = SI_Credit::get_instance( $type_id );
			$type->delete_credit( $credit_id );
		}
		// Remove from associated array
		$credits = self::get_associated_credits( $client_id );
		if ( ( $key = array_search( $credit_id, $credits ) ) !== false ) {
			unset( $credits[ $key ] );
		}
		self::set_associated_credits( $client_id, $credits );
	}
}
