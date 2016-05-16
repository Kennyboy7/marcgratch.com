<?php

/**
 * Engagements Controller
 *
 *
 * @package Sprout_Engagements
 * @subpackage Engagements
 */
class Sprout_Engagements_AJAX extends Sprout_Engagements {
	const SUBMISSION_NONCE = 'sc_engagement_submission';

	public static function init() {

		if ( is_admin() ) {

			// AJAX
			add_action( 'wp_ajax_sa_create_engagement',  array( __CLASS__, 'maybe_create_engagement' ), 5, 0 );

			add_action( 'wp_ajax_sc_change_engagement_type',  array( __CLASS__, 'maybe_change_engagement_type' ), 10, 0 );

			add_action( 'wp_ajax_sc_edit_engagement_status',  array( __CLASS__, 'maybe_edit_engagement_status' ), 10, 0 );

			add_action( 'wp_ajax_sc_create_engagement_private_note',  array( get_class(), 'maybe_create_private_note' ), 10, 0 );
		}

	}

	/**
	 * AJAX submission from admin.
	 * @return json response
	 */
	public static function maybe_create_engagement() {
		// form maybe be serialized
		if ( isset( $_REQUEST['serialized_fields'] ) ) {
			foreach ( $_REQUEST['serialized_fields'] as $key => $data ) {
				$_REQUEST[ $data['name'] ] = $data['value'];
			}
		}

		if ( ! isset( $_REQUEST['sa_engagement_nonce'] ) ) {
			self::ajax_fail( 'Forget something?' );
		}

		$nonce = $_REQUEST['sa_engagement_nonce'];
		if ( ! wp_verify_nonce( $nonce, self::SUBMISSION_NONCE ) ) {
			self::ajax_fail( 'Not going to fall for it!' );
		}

		if ( ! current_user_can( 'publish_posts' ) ) {
			self::ajax_fail( 'User cannot create new posts!' );
		}

		if ( ! isset( $_REQUEST['sa_engagement_title'] ) || '' === $_REQUEST['sa_engagement_title'] ) {
			self::ajax_fail( 'A title is required' );
		}

		$args = array(
			'name' => isset( $_REQUEST['sa_engagement_title'] ) ? esc_attr( $_REQUEST['sa_engagement_title'] ) : '',
			'start_date' => isset( $_REQUEST['sa_engagement_start_date'] ) ? strtotime( esc_attr( $_REQUEST['sa_engagement_start_date'] ) ) : '',
			'end_date' => isset( $_REQUEST['sa_engagement_end_date'] ) ? strtotime( esc_attr( $_REQUEST['sa_engagement_end_date'] ) ) : '',
			'assigned' => isset( $_REQUEST['sa_engagement_assigned'] ) ? esc_attr( $_REQUEST['sa_engagement_assigned'] ) : '',
			'client_id' => isset( $_REQUEST['sa_engagement_client_id'] ) ? esc_attr( $_REQUEST['sa_engagement_client_id'] ) : '',
			'type_id' => isset( $_REQUEST['sa_engagement_type'] ) ? esc_attr( $_REQUEST['sa_engagement_type'] ) : '',
			'status_id' => isset( $_REQUEST['sa_engagement_status'] ) ? esc_attr( $_REQUEST['sa_engagement_status'] ) : '',
		);

		$engagement_id = Sprout_Engagement::new_engagement( $args );

		if ( isset( $_REQUEST['sa_engagement_note'] ) && '' !== $_REQUEST['sa_engagement_note'] ) {
			$record_id = (int) SC_Internal_Records::new_record( $_REQUEST['sa_engagement_note'], SC_Controller::PRIVATE_NOTES_TYPE, $engagement_id, sprintf( __( 'Note from %s' , 'sprout-invoices' ), sc_get_users_full_name( get_current_user_id() ) ), 0, false );
		}

		ob_start();
		$client = Sprout_Client::get_instance( $args['client_id'] );
		global $post;
		setup_postdata( $client->get_post() );
		print Sprout_Engagements_Client::show_engagements_view( $client->get_post() );
		$view = ob_get_clean();

		$response = array(
				'id' => $engagement_id,
				'title' => get_the_title( $engagement_id ),
				'view' => $view,
			);

		wp_send_json_success( $response );
	}

	public static function maybe_change_engagement_type() {
		if ( ! current_user_can( 'edit_posts' ) ) {
			self::ajax_fail( 'User cannot create new posts!' );
		}

		$nonce = $_REQUEST['security'];
		if ( ! wp_verify_nonce( $nonce, self::NONCE ) ) {
			self::ajax_fail( 'Not going to fall for it!' );
		}

		if ( ! isset( $_REQUEST['engagement_id'] ) ) {
			self::ajax_fail( 'No engagement ID!' );
		}

		$engagement = Sprout_Engagement::get_instance( $_REQUEST['engagement_id'] );

		if ( ! is_a( $engagement, 'Sprout_Engagement' ) ) {
			self::ajax_fail( 'Engagement not found.' );
		}

		$engagement->set_type( $_REQUEST['type_id'] );
		print sc_get_engagement_type_select( $_REQUEST['engagement_id'] );
		exit();
	}

	public static function maybe_edit_engagement_status() {
		if ( ! current_user_can( 'edit_posts' ) ) {
			self::ajax_fail( 'User cannot create new posts!' );
		}

		$nonce = $_REQUEST['security'];
		if ( ! wp_verify_nonce( $nonce, self::NONCE ) ) {
			self::ajax_fail( 'Not going to fall for it!' );
		}

		if ( ! isset( $_REQUEST['engagement_id'] ) ) {
			self::ajax_fail( 'No engagement ID!' );
		}

		$engagement = Sprout_Engagement::get_instance( $_REQUEST['engagement_id'] );

		if ( ! is_a( $engagement, 'Sprout_Engagement' ) ) {
			self::ajax_fail( 'Engagement not found.' );
		}

		if ( 'add' === $_REQUEST['context'] ) {
			$return = $engagement->add_status( $_REQUEST['type_id'] );
		} else {
			$return = $engagement->remove_status( $_REQUEST['type_id'] );
		}

		wp_send_json( $return );
	}


	public static function maybe_create_private_note() {

		if ( ! isset( $_REQUEST['security'] ) ) {
			wp_send_json_error( array( 'message' => __( 'Forget something?' , 'sprout-invoices' ) ) );
		}

		$nonce = $_REQUEST['security'];
		if ( ! wp_verify_nonce( $nonce, self::NONCE ) ) {
			wp_send_json_error( array( 'message' => __( 'Not going to fall for it!' , 'sprout-invoices' ) ) );
		}

		if ( ! current_user_can( 'edit_sprout_clients' ) ) {
			wp_send_json_error( array( 'message' => __( 'Not going to fall for it!' , 'sprout-invoices' ) ) );
		}

		$record_id = (int) SC_Internal_Records::new_record( $_REQUEST['notes'], SC_Controller::PRIVATE_NOTES_TYPE, $_REQUEST['associated_id'], sprintf( __( 'Note from %s' , 'sprout-invoices' ), sc_get_users_full_name( get_current_user_id() ) ), 0, false );
		$error = ( $record_id ) ? '' : sc__( 'Private note failed to save, try again.' );
		$data = array(
			'id' => $record_id,
			'content' => esc_html( $_REQUEST['notes'] ),
			'type' => sc__( 'Private Note' ),
			'post_date' => sc__( 'Just now' ),
			'error' => $error,
		);

		if ( self::DEBUG ) { header( 'Access-Control-Allow-Origin: *' ); }
		wp_send_json_success( $data );

	}
}
