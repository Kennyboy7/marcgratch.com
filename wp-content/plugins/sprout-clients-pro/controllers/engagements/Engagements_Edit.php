<?php

/**
 * Engagements Controller
 *
 *
 * @package Sprout_Engagements
 * @subpackage Engagements
 */
class Sprout_Engagements_Edit extends Sprout_Engagements {

	public static function init() {

		if ( is_admin() ) {
			// Meta boxes
			add_action( 'admin_init', array( __CLASS__, 'register_meta_boxes' ), 5 );

			add_action( 'do_meta_boxes', array( __CLASS__, 'modify_meta_boxes' ) );
			add_action( 'edit_form_top', array( __CLASS__, 'name_box' ) );
		}

	}

	/////////////////
	// Meta boxes //
	/////////////////

	/**
	 * Regsiter meta boxes for estimate editing.
	 *
	 * @return
	 */
	public static function register_meta_boxes() {
		// estimate specific
		$args = array(
			'si_engagement_information' => array(
				'title' => sc__( 'Information' ),
				'show_callback' => array( __CLASS__, 'show_information_meta_box' ),
				'save_callback' => array( __CLASS__, 'save_meta_box_engagement_information' ),
				'context' => 'normal',
				'priority' => 'high',
				'save_priority' => 0,
				'weight' => 15,
			),
			'si_engagement_submit' => array(
				'title' => 'Update',
				'show_callback' => array( __CLASS__, 'show_submit_meta_box' ),
				'save_callback' => array( __CLASS__, 'save_submit_meta_box' ),
				'context' => 'side',
				'priority' => 'high',
			),
			'si_engagement_notes' => array(
				'title' => sc__( 'Notes' ),
				'show_callback' => array( __CLASS__, 'show_engagement_notes_view' ),
				'save_callback' => array( __CLASS__, '_save_null' ),
				'context' => 'normal',
				'priority' => 'low',
				'weight' => 50,
			),
		);
		do_action( 'sprout_meta_box', $args, Sprout_Engagement::POST_TYPE );
	}

	/**
	 * Remove publish box and add something custom for estimates
	 *
	 * @param string  $post_type
	 * @return
	 */
	public static function modify_meta_boxes( $post_type ) {
		if ( Sprout_Engagement::POST_TYPE === $post_type ) {
			remove_meta_box( 'submitdiv', null, 'side' );
		}
	}

	/**
	 * Add quick links
	 * @param  object $post
	 * @return
	 */
	public static function name_box( $post ) {
		if ( get_post_type( $post ) === Sprout_Engagement::POST_TYPE ) {
			$engagement = Sprout_Engagement::get_instance( $post->ID );
			self::load_view( 'admin/meta-boxes/engagements/name', array(
					'engagement' => $engagement,
					'id' => $post->ID,
					'type' => $engagement->get_type(),
					'statuses' => $engagement->get_statuses(),
					'all_statuses' => sc_get_engagement_statuses(),
					'post_status' => $post->post_status,
			) );
		}
	}

	/**
	 * Show custom submit box.
	 * @param  WP_Post $post
	 * @param  array $metabox
	 * @return
	 */
	public static function show_submit_meta_box( $post, $metabox ) {
		$engagement = Sprout_Engagement::get_instance( $post->ID );

		$args = apply_filters( 'si_get_users_for_association_args', array( 'fields' => array( 'ID', 'user_email', 'display_name' ) ) );
		$users = get_users( $args );
		self::load_view( 'admin/meta-boxes/engagements/submit', array(
				'id' => $post->ID,
				'engagement' => $engagement,
				'post' => $post,
				'client_id' => $engagement->get_client_id(),
				'assigned_users' => $engagement->get_assigned_users(),
				'users' => $users,
		), false );

		add_thickbox();

		// add the user creation modal
		$fields = SC_Clients::user_form_fields( $post->ID );
		self::load_view( 'admin/meta-boxes/clients/create-user-modal', array( 'fields' => $fields ) );
	}

	/**
	 * Information
	 * @param  object $post
	 * @return
	 */
	public static function show_information_meta_box( $post ) {
		if ( get_post_type( $post ) === Sprout_Engagement::POST_TYPE ) {
			$engagement = Sprout_Engagement::get_instance( $post->ID );
			self::load_view( 'admin/meta-boxes/engagements/info', array(
					'engagement' => $engagement,
					'id' => $post->ID,
					'associated_users' => $engagement->get_assigned_users(),
					'fields' => self::form_fields( false, $post->ID ),
			) );
		}
	}

	/**
	 * Saving info meta
	 * @param  int $post_id
	 * @param  object $post
	 * @param  array $callback_args
	 * @return
	 */
	public static function save_meta_box_engagement_information( $post_id, $post, $callback_args ) {
		// name is updated in the title div
		$start_date = ( isset( $_POST['sa_metabox_start_date'] ) && '' !== $_POST['sa_metabox_start_date'] ) ? $_POST['sa_metabox_start_date'] : '' ;
		$end_date = ( isset( $_POST['sa_metabox_end_date'] ) && '' !== $_POST['sa_metabox_end_date'] ) ? $_POST['sa_metabox_end_date'] : '' ;
		$client_id = ( isset( $_POST['sa_metabox_client_id'] ) && '' !== $_POST['sa_metabox_client_id'] ) ? $_POST['sa_metabox_client_id'] : '' ;
		$user_id = ( isset( $_POST['sa_metabox_user_id'] ) && '' !== $_POST['sa_metabox_user_id'] ) ? $_POST['sa_metabox_user_id'] : '' ;

		$engagement = Sprout_Engagement::get_instance( $post_id );
		$engagement->set_end_date( strtotime( $end_date ) );
		$engagement->set_start_date( strtotime( $start_date ) );
		$engagement->set_client_id( $client_id );

		if ( $user_id ) {
			$engagement->add_assigned_user( $user_id );
		}
	}

	/**
	 * Saving submit meta
	 * @param  int $post_id
	 * @param  object $post
	 * @param  array $callback_args
	 * @return
	 */
	public static function save_submit_meta_box( $post_id, $post, $callback_args ) {
		$engagement = Sprout_Engagement::get_instance( $post_id );
		$engagement->clear_assigned_users();
		if ( ! isset( $_POST['assigned_users'] ) ) {
			return;
		}

		foreach ( $_POST['assigned_users'] as $user_id ) {
			$engagement->add_assigned_user( $user_id );
		}
	}


	/**
	 * Show the history
	 *
	 * @param WP_Post $post
	 * @param array   $metabox
	 * @return
	 */
	public static function show_engagement_history_view( $post, $metabox ) {
		if ( 'auto-draft' === $post->post_status ) {
			printf( '<p>%s</p>', sc__( 'No history available.' ) );
			return;
		}
		$engagement = Sprout_Engagement::get_instance( $post->ID );
		self::load_view( 'admin/meta-boxes/engagements/history', array(
				'id' => $post->ID,
				'post' => $post,
				'engagement' => $engagement,
				'history' => $engagement->get_history(),
		), false );
	}


	/**
	 * Show the notes
	 *
	 * @param WP_Post $post
	 * @param array   $metabox
	 * @return
	 */
	public static function show_engagement_notes_view( $post, $metabox ) {
		if ( 'auto-draft' === $post->post_status ) {
			printf( '<p>%s</p>', sc__( 'Save before creating any notes.' ) );
			return;
		}
		$engagement = Sprout_Engagement::get_instance( $post->ID );
		self::load_view( 'admin/meta-boxes/engagements/notes', array(
				'id' => $post->ID,
				'post' => $post,
				'engagement' => $engagement,
		), false );
	}
}
