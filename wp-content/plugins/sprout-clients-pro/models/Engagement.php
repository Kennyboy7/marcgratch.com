<?php

/**
 * Engagement Model
 *
 *
 * @package Sprout_Engagements
 * @subpackage Engagement
 */
class Sprout_Engagement extends SC_Post_Type {
	const POST_TYPE = 'sa_engagement';
	const REWRITE_SLUG = 'sprout-engagement';

	const TYPE_TAXONOMY = 'sc_engagement_type';
	const STATUS_TAXONOMY = 'sc_engagement_status';

	private static $instances = array();

	private static $meta_keys = array(
		'assigned_users' => '_assigned_user_ids', // int
		'end_date' => '_end_date', // int
		'start_date' => '_start_date', // int
		'client_id' => '_client_id', // int
	); // A list of meta keys this class cares about. Try to keep them in alphabetical order.


	public static function init() {
		// register Engagement post type
		$post_type_args = array(
			'public' => false,
			'exclude_from_search' => true,
			'has_archive' => false,
			'show_in_nav_menus' => false,
			'show_in_menu' => 'edit.php?post_type='.Sprout_Client::POST_TYPE,
			'supports' => array( '' ),
		);
		self::register_post_type( self::POST_TYPE, 'Engagement', 'Engagements', $post_type_args );

		$singular = 'Type';
		$plural = 'Types';
		$taxonomy_args = array(
			'meta_box_cb' => false,
			'hierarchical' => false,
		);
		self::register_taxonomy( self::TYPE_TAXONOMY, array( self::POST_TYPE ), $singular, $plural, $taxonomy_args );

		$singular = 'Status';
		$plural = 'Statuses';
		$taxonomy_args = array(
			'meta_box_cb' => false,
			'hierarchical' => false,
		);
		self::register_taxonomy( self::STATUS_TAXONOMY, array( self::POST_TYPE ), $singular, $plural, $taxonomy_args );
	}

	protected function __construct( $id ) {
		parent::__construct( $id );
	}

	/**
	 *
	 *
	 * @static
	 * @param int     $id
	 * @return Sprout_Engagements_Engagement
	 */
	public static function get_instance( $id = 0 ) {
		if ( ! $id ) {
			return null;
		}

		if ( ! isset( self::$instances[ $id ] ) || ! self::$instances[ $id ] instanceof self ) {
			self::$instances[ $id ] = new self( $id );
		}

		if ( ! isset( self::$instances[ $id ]->post->post_type ) ) {
			return null;
		}

		if ( self::$instances[ $id ]->post->post_type !== self::POST_TYPE ) {
			return null;
		}

		return self::$instances[ $id ];
	}

	/**
	 * Create a engagement
	 * @param  array $args
	 * @return int
	 */
	public static function new_engagement( $passed_args ) {
		$defaults = array(
			'name' => sprintf( __( 'New Engagement: %s' , 'sprout-invoices' ), date_i18n( get_option( 'date_format' ).' @ '.get_option( 'time_format' ), current_time( 'timestamp' ) ) ),
			'start_date' => time(),
			'end_date' => time() + (60 * 60 * 24 * 7),
			'assigned' => 0,
			'client_id' => 0,
			'type_id' => 0,
			'status_id' => 0,
		);
		$args = wp_parse_args( $passed_args, $defaults );

		$id = wp_insert_post( array(
			'post_status' => 'publish',
			'post_type' => self::POST_TYPE,
			'post_title' => $args['name'],
		) );
		if ( is_wp_error( $id ) ) {
			return 0;
		}

		$engagement = self::get_instance( $id );
		$engagement->set_start_date( $args['start_date'] );
		$engagement->set_end_date( $args['end_date'] );
		$engagement->set_client_id( $args['client_id'] );

		if ( $args['assigned'] ) {
			$engagement->add_assigned_user( $args['assigned'] );
		}

		if ( $args['type_id'] ) {
			$engagement->set_type( $args['type_id'] );
		}

		if ( $args['status_id'] ) {
			$engagement->add_status( $args['status_id'] );
		}

		do_action( 'sa_new_engagement', $engagement, $args );
		return $id;
	}

	///////////
	// Meta //
	///////////

	public function get_client_id() {
		return $this->get_post_meta( self::$meta_keys['client_id'] );
	}

	public function set_client_id( $client_id ) {
		return $this->save_post_meta( array( self::$meta_keys['client_id'] => $client_id ) );
	}

	public function get_start_date() {
		return $this->get_post_meta( self::$meta_keys['start_date'] );
	}

	public function set_start_date( $start_date ) {
		return $this->save_post_meta( array( self::$meta_keys['start_date'] => $start_date ) );
	}


	public function get_end_date() {
		return $this->get_post_meta( self::$meta_keys['end_date'] );
	}

	public function set_end_date( $end_date ) {
		return $this->save_post_meta( array( self::$meta_keys['end_date'] => $end_date ) );
	}

	/**
	 * Get the assigned users with this engagement
	 * @return array
	 */
	public function get_assigned_users() {
		$users = $this->get_post_meta( self::$meta_keys['assigned_users'], false );
		if ( ! is_array( $users ) ) {
			$users = array();
		}
		return array_filter( $users );
	}

	/**
	 * Save the assigned users with this engagement
	 * @param array $users
	 */
	public function set_assigned_users( $users = array() ) {
		$this->clear_assigned_users();
		$this->save_post_meta( array(
			self::$meta_keys['assigned_users'] => $users,
		) );
		return $users;
	}

	/**
	 * Clear out the assigned users
	 * @param array $users
	 */
	public function clear_assigned_users() {
		$this->delete_post_meta( array(
			self::$meta_keys['assigned_users'] => '',
		) );
	}

	/**
	 * Add single user to assigned array
	 * @param integer $user_id
	 */
	public function add_assigned_user( $user_id = 0 ) {
		if ( is_numeric( $user_id ) && ! $this->is_user_assigned( $user_id ) ) {
			$this->add_post_meta( array(
				self::$meta_keys['assigned_users'] => $user_id,
			) );
		}
	}

	/**
	 * Remove single user to assigned array
	 * @param integer $user_id
	 */
	public function remove_assigned_user( $user_id = 0 ) {
		if ( $this->is_user_assigned( $user_id ) ) {
			$this->delete_post_meta( array(
				self::$meta_keys['assigned_users'] => $user_id,
			) );
		}
	}

	public function is_user_assigned( $user_id ) {
		$assigned_users = $this->get_assigned_users();
		if ( empty( $assigned_users ) ) { return; }
		return in_array( $user_id, $assigned_users );
	}

	////////////////
	// Taxonomies //
	////////////////

	public function get_type( $single = true ) {
		$types = get_the_terms( $this->ID, self::TYPE_TAXONOMY );
		if ( empty( $types ) ) {
			$types = new stdClass();
			$types->term_id = 0;
			$types->name = __( 'Meeting' , 'sprout-invoices' );
			$types->slug = 'engagement';
			$types->description = __( 'Temporary engagement created.' , 'sprout-invoices' );
		} elseif ( $single ) {
			$types = reset( $types );
		}
		return $types;
	}

	public function set_type( $type_id = 0 ) {
		$update = wp_set_object_terms( $this->ID, (int) $type_id, self::TYPE_TAXONOMY );
		return $update;
	}

	public function get_statuses() {
		$types = get_the_terms( $this->ID, self::STATUS_TAXONOMY );
		return $types;
	}

	public function add_status( $status_id = 0 ) {
		$update = wp_set_object_terms( $this->ID, (int) $status_id, self::STATUS_TAXONOMY, true );
		return $update;
	}

	public function remove_status( $status_id = 0 ) {
		$update = wp_remove_object_terms( $this->ID, (int) $status_id, self::STATUS_TAXONOMY );
		return $update;
	}

	//////////////
	// Utility //
	//////////////

	/**
	 * Get the engagements that are associated by client id
	 * @param  integer $client_id
	 * @return array
	 */
	public static function get_engagements_by_client( $client_id = 0 ) {
		$engagements = self::find_by_meta( self::POST_TYPE, array( self::$meta_keys['client_id'] => $client_id ) );
		return $engagements;
	}

	/**
	 * Get the engagements that are assigned with the user
	 * @param  integer $user_id
	 * @return array
	 */
	public static function get_engagements_by_user( $user_id = 0 ) {
		$engagements = self::find_by_meta( self::POST_TYPE, array( self::$meta_keys['assigned_users'] => $user_id ) );
		return $engagements;
	}

	public static function get_all_engagements() {
		// TODO CACHE
		$engagements = self::find_by_meta( self::POST_TYPE );
		$aa = array();
		foreach ( $engagements as $engagement_id ) {
			$aa[ $engagement_id ] = get_the_title( $engagement_id );
		}
		return $aa;
	}

	///////////////////////////////////
	// Sprout Invoice Compatibility //
	///////////////////////////////////

	public function get_history( $type = '' ) {
		$history = apply_filters( 'engagement_history', array(), $this, $type );
		return $history;
	}
}
