<?php

/**
 * Engagements Controller
 *
 *
 * @package Sprout_Engagements
 * @subpackage Engagements
 */
class Sprout_Engagements_Client extends Sprout_Engagements {

	public static function init() {

		if ( is_admin() ) {
			// Meta boxes
			add_action( 'admin_init', array( __CLASS__, 'register_meta_boxes' ), 5 );
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
			'si_show_engagements' => array(
				'title' => sc__( 'Engagements' ),
				'show_callback' => array( __CLASS__, 'show_engagements_view' ),
				'save_callback' => array( __CLASS__, '_save_null' ),
				'context' => 'normal',
				'priority' => 'high',
				'weight' => 10,
			),
		);
		do_action( 'sprout_meta_box', $args, Sprout_Client::POST_TYPE );
	}


	/**
	 * Show the notes
	 *
	 * @param WP_Post $post
	 * @param array   $metabox
	 * @return
	 */
	public static function show_engagements_view( $post, $metabox = '' ) {
		if ( 'auto-draft' === $post->post_status ) {
			printf( '<p>%s</p>', sc__( 'Save before creating any engagements.' ) );
			return;
		}
		$engagements = Sprout_Engagement::get_engagements_by_client( $post->ID );
		self::load_view( 'admin/meta-boxes/engagements/client-engagements', array(
				'client_id' => $post->ID,
				'engagements' => $engagements,
		), false );

		add_thickbox();

		// add the engagement creation modal
		$fields = self::form_fields();

		$fields['client_id']['weight'] = PHP_INT_MAX;
		$fields['client_id']['default'] = $post->ID;
		$fields['client_id']['value'] = $post->ID;
		$fields['client_id']['type'] = 'hidden';
		$fields['client_id']['attributes'] = array();
		$fields['client_id']['description'] = '';
		$fields['title'] = array(
			'weight' => 1,
			'label' => __( 'Title' , 'sprout-invoices' ),
			'type' => 'small-input',
		);

		$fields['type'] = array(
			'weight' => 500,
			'label' => __( 'Type' , 'sprout-invoices' ),
			'type' => 'select',
			'options' => sc_get_engagement_types(),
		);
		$fields['status'] = array(
			'weight' => 500,
			'label' => __( 'Status', 'sprout-invoices' ),
			'type' => 'select',
			'options' => sc_get_engagement_statuses(),
		);
		$fields['note'] = array(
			'weight' => 100,
			'label' => __( 'Note' , 'sprout-invoices' ),
			'type' => 'textarea',
			'placeholder' => __( 'A private note about this enagement.', 'sprout-invoices' ),
		);
		$fields['nonce'] = array(
			'type' => 'hidden',
			'value' => wp_create_nonce( Sprout_Engagements_AJAX::SUBMISSION_NONCE ),
			'weight' => PHP_INT_MAX,
		);

		$fields = apply_filters( 'si_engagement_create_modal_form_fields', $fields );
		uasort( $fields, array( __CLASS__, 'sort_by_weight' ) );
		self::load_view( 'admin/meta-boxes/engagements/create-modal', array( 'fields' => $fields ) );

	}
}
