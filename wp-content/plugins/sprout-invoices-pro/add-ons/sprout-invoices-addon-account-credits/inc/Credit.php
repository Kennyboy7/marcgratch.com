<?php

/**
 * Credit Model
 * A SI_Credit is referred to an credit_type in many places.
 * A credit (entry/clocked) is an SI_Record associated with an SI_Credit,
 * this will allow for reports and more with the separation.
 *
 *
 * @package Sprout_Invoices
 * @subpackage Credit
 */
class SI_Credit extends SI_Post_Type {
	const POST_TYPE = 'sa_credit_type';
	const REWRITE_SLUG = 'sprout-credit-type'; // unused
	const CREDIT_RECORD = 'si_credit_record';
	private static $instances = array();

	private static $meta_keys = array(
		'client_id' => '_client_id',
		'credit' => '_credit',
		'notes' => '_notes',
		'user_id' => '_user_id',
	); // A list of meta keys this class cares about. Try to keep them in alphabetical order.


	public static function init() {
		// register Credit post type
		$post_type_args = array(
			'public' => false,
			'has_archive' => false,
			'show_ui' => false,
			'supports' => array( '' ),
		);
		self::register_post_type( self::POST_TYPE, 'Credit', 'Credit Type', $post_type_args );

		add_action( 'si_plugin_activation_hook', array( __CLASS__, 'default_credit' ) );
	}

	protected function __construct( $id ) {
		parent::__construct( $id );
	}

	/**
	 *
	 *
	 * @static
	 * @param int     $id
	 * @return Sprout_Invoices_Credit
	 */
	public static function get_instance( $id = 0 ) {
		if ( ! $id ) {
			return null; }

		if ( get_post_type( $id ) != self::POST_TYPE ) {
			$id = self::default_credit();
		}
		if ( ! isset( self::$instances[ $id ] ) || ! self::$instances[ $id ] instanceof self ) {
			self::$instances[ $id ] = new self( $id ); }

		if ( ! isset( self::$instances[ $id ]->post->post_type ) ) {
			return null; }

		if ( self::$instances[ $id ]->post->post_type != self::POST_TYPE ) {
			return null; }

		return self::$instances[ $id ];
	}

	/**
	 * Create a credit credit_type
	 * @param  array $args
	 * @return int
	 */
	public static function new_credit_type( $args = array() ) {
		$defaults = array(
			'name' => __( 'Standard Credit', 'sprout-invoices' ),
		);
		$parsed_args = wp_parse_args( $args, $defaults );

		$id = wp_insert_post( array(
			'post_status' => 'publish',
			'post_type' => self::POST_TYPE,
			'post_title' => $parsed_args['name'],
		) );

		if ( is_wp_error( $id ) ) {
			return 0;
		}

		$credit = self::get_instance( $id );

		do_action( 'si_new_credit_type', $credit, $parsed_args );
		return $id;
	}

	public static function default_credit() {
		$default_id = get_option( 'si_default_credit' );
		if ( $default_id && self::POST_TYPE !== get_post_type( $default_id ) ) {
			$default_id = 0;
		}
		if ( ! $default_id ) {
			$credit_types = self::get_credit_types();
			if ( ! empty( $credit_types ) ) {
				reset( $credit_types );
				$default_id = key( $credit_types );
			}
			if ( self::POST_TYPE !== get_post_type( $default_id ) ) {
				$default_id = self::new_credit_type();
			}
			update_option( 'si_default_credit', $default_id );
		}
		return $default_id;
	}

	public static function get_payment_credit_type() {
		$payment_type_id = get_option( 'si_payment_credit_type' );
		if ( $payment_type_id && self::POST_TYPE !== get_post_type( $payment_type_id ) ) {
			$payment_type_id = 0;
		}
		if ( ! $payment_type_id ) {
			$defaults = array(
				'name' => __( 'Payment', 'sprout-invoices' ),
			);
			$payment_type_id = self::new_credit_type( $defaults );
			update_option( 'si_payment_credit_type', $payment_type_id );
		}
		return $payment_type_id;
	}

	///////////
	// Meta //
	///////////

	/**
	 * Create a credit entry
	 * @param  array $args
	 * @return int
	 */
	public function new_credit( $data ) {
		$id = SI_Internal_Records::new_record(
			$data,
			self::CREDIT_RECORD,
			$this->get_id(),
			$data['note'],
		0 );
		return $id;
	}

	public function delete_credit( $credit_id = 0 ) {
		$credit = SI_Record::get_instance( $credit_id );
		if ( is_a( $credit, 'SI_Record' ) && $credit->get_associate_id() === $this->get_id() ) {
			wp_delete_post( $credit_id, true );
		}
	}

	public function get_credit_entries( $args = array() ) {
		return SI_Record::get_records_by_association( $this->ID );
	}

	public static function get_credit_entry( $credit_id = 0 ) {
		$record = SI_Record::get_instance( $credit_id );
		if ( ! is_a( $record, 'SI_Record' ) ) {
			$record = 0;
		}
		return $record;
	}

	public static function add_invoice_id( $credit_id = 0, $invoice_id = 0 ) {
		$record = SI_Record::get_instance( $credit_id );
		if ( ! is_a( $record, 'SI_Record' ) ) {
			return 0;
		}
		$data = $record->get_data();
		$data['invoice_id'] = $invoice_id;
		$record->set_data( $data );
		return $record;
	}

	public function get_client_id() {
		return $this->get_post_meta( self::$meta_keys['client_id'] );
	}

	public function set_client_id( $client_id ) {
		return $this->save_post_meta( array( self::$meta_keys['client_id'] => $client_id ) );
	}

	public function get_note() {
		return $this->get_post_meta( self::$meta_keys['note'] );
	}

	public function set_note( $note ) {
		return $this->save_post_meta( array( self::$meta_keys['note'] => $note ) );
	}

	public function get_credit() {
		return $this->get_post_meta( self::$meta_keys['credit'] );
	}

	public function set_credit( $credit ) {
		return $this->save_post_meta( array( self::$meta_keys['credit'] => $credit ) );
	}

	public function get_user_id() {
		return $this->get_post_meta( self::$meta_keys['user_id'] );
	}

	public function set_user_id( $user_id ) {
		return $this->save_post_meta( array( self::$meta_keys['user_id'] => $user_id ) );
	}

	//////////////
	// Utility //
	//////////////

	public static function get_credit_types( $credit_types = array() ) {
		if ( empty( $credit_types ) ) {
			$args = array(
				'post_type' => self::POST_TYPE,
				'post_status' => 'any',
				'posts_per_page' => -1,
				'fields' => 'ids',
			);
			$credit_types = get_posts( $args );
		}
		$credit_types_options = array();
		foreach ( $credit_types as $credit_id ) {
			$credit = SI_Credit::get_instance( $credit_id );
			if ( is_a( $credit, 'SI_Credit' ) ) {
				$credit_types_options[ $credit_id ] = sprintf( '%s (%s)', $credit->get_title(), $credit->get_id() );
			}
		}
		return $credit_types_options;
	}
}
