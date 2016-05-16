<?php

/**
 * Engagement Controller
 *
 *
 * @package Sprout_Engagement
 * @subpackage Engagement
 */
class Sprout_Engagements extends SC_Controller {
	const SUBMISSION_NONCE = 'sc_engagement_submission';

	public static function init() {

		if ( is_admin() ) {
			// Help Sections
			add_action( 'admin_menu', array( get_class(), 'help_sections' ) );
		}

		// Add default types and statuses
		add_action( 'admin_init', array( __CLASS__, 'add_defaults' ) );

	}

	/**
	 * Add default statuses and types.
	 */
	public static function add_defaults() {
		$loaded = get_option( 'sc_load_engagement_defaults', 0 );
		if ( $loaded ) { // not anything new
			return;
		}

		$default_types = array(
			'Web Form' => array(
					'slug' => 'web-form',
					'description' => 'A web form submission.',
				),
			'Conference' => array(
					'slug' => 'conference',
					'description' => 'Met them at a conference or meetup.',
				),
			'Meeting' => array(
					'slug' => 'meeting',
					'description' => 'Met at a meeting.',
				),
			'Note' => array(
					'slug' => 'note',
					'description' => 'Contacted via snail-mail or some other archaic  means.',
				),
			'Phone' => array(
					'slug' => 'phone',
					'description' => 'Talked over the phone or left a voicemail.',
				),
		);
		foreach ( $default_types as $name => $args ) {
			$term = wp_insert_term( $name, Sprout_Engagement::TYPE_TAXONOMY, $args );
		}

		$default_statuses = array(
			'complete' => 'Complete',
			'scheduled' => 'Scheduled',
			'cancelled' => 'Cancelled',
			'unknown' => 'Unknown',
			);
		foreach ( $default_statuses as $slug => $name ) {
			wp_insert_term( $name, Sprout_Client::STATUS_TAXONOMY, array( 'slug' => $slug ) );
		}

		update_option( 'sc_load_engagement_defaults', self::SC_VERSION );

	}

	////////////
	// Forms //
	////////////

	public static function form_fields( $required = true, $engagement_id = 0 ) {
		$fields = array();
		if ( $engagement_id ) {
			$engagement = Sprout_Engagement::get_instance( $engagement_id );
		}
		$args = array(
			'post_type' => Sprout_Client::POST_TYPE,
			'post_status' => 'publish',
			'posts_per_page' => -1,
			'fields' => 'ids',
		);
		$clients = get_posts( $args );
		$client_options = array( 0 => __( 'Select', 'sprout-invoices' ) );
		foreach ( $clients as $client_id ) {
			$title = get_the_title( $client_id );
			$title = ( $title == __( 'Auto Draft' ) ) ? __( 'Current Client', 'sprout-invoices' ) : $title ;
			$client_options[ $client_id ] = $title;
		}
		$fields['client_id'] = array(
			'weight' => 3,
			'label' => __( 'Client' , 'sprout-invoices' ),
			'type' => 'select',
			'required' => $required,
			'options' => $client_options,
			'attributes' => array( 'class' => 'sa_select2' ),
			'default' => ( $engagement_id && is_a( $engagement, 'Sprout_Engagement' ) ) ? $engagement->get_client_id() : 0,
			'description' => __( 'Client associated with this engagement.' , 'sprout-invoices' ),
		);

		$fields['start_date'] = array(
			'weight' => 110,
			'label' => __( 'Start Date', 'sprout-invoices' ),
			'type' => 'date',
			'default' => ( $engagement_id && $engagement->get_start_date() ) ? date( 'Y-m-d', $engagement->get_start_date() ) : date( 'Y-m-d', current_time( 'timestamp' ) ),
			'placeholder' => '',
		);

		$fields['end_date'] = array(
			'weight' => 120,
			'label' => __( 'End Date', 'sprout-invoices' ),
			'type' => 'date',
			'default' => ( $engagement_id && $engagement->get_end_date() ) ? date( 'Y-m-d', $engagement->get_end_date() ) : date( 'Y-m-d', current_time( 'timestamp' ) ),
			'placeholder' => '',
		);

		$fields['nonce'] = array(
			'type' => 'hidden',
			'value' => wp_create_nonce( self::SUBMISSION_NONCE ),
			'weight' => 10000,
		);

		$fields = apply_filters( 'si_engagement_form_fields', $fields );
		uasort( $fields, array( __CLASS__, 'sort_by_weight' ) );
		return $fields;
	}


	////////////////
	// Admin Help //
	////////////////

	public static function help_sections() {
		add_action( 'load-edit.php', array( __CLASS__, 'help_tabs' ) );
		add_action( 'load-post.php', array( __CLASS__, 'help_tabs' ) );
		add_action( 'load-post-new.php', array( get_class(), 'help_tabs' ) );
	}

	public static function help_tabs() {
		$post_type = '';

		$screen = get_current_screen();
		$screen_post_type = str_replace( 'edit-', '', $screen->id );
		if ( Sprout_Client::POST_TYPE === $screen_post_type ) {
			// get screen and add sections.
			$screen = get_current_screen();

			$screen->add_help_tab( array(
				'id' => 'edit-engagements',
				'title' => __( 'Manage Engagement' , 'sprout-invoices' ),
				'content' => sprintf( '<p>%s</p><p>%s</p>', __( 'The information here is used for estimates and invoices and includes settings to: Edit Company Name, Edit the company address, and Edit their website url.' , 'sprout-invoices' ), __( '<b>Important note:</b> when engagements are created new WordPress users are also created and given the “engagement” role. Creating users will allow for future functionality, i.e. engagement dashboards.' , 'sprout-invoices' ) ),
			) );
		}
	}
}
