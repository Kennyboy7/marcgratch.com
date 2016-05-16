<?php

/**
 * Send messages, apply shortcodes and create management screen.
 *
 * @package Sprout_Client
 * @subpackage Notification
 */
class Sprout_Clients_Messages_Admin extends SC_Controller {
	const SUBMISSION_NONCE = 'sc_message_submission';

	public static function init() {

		if ( is_admin() ) {
			// Meta boxes
			add_action( 'admin_init', array( __CLASS__, 'register_meta_boxes' ), 5 );

			// AJAX
			add_action( 'wp_ajax_sc_create_message',  array( __CLASS__, 'maybe_create_message' ), 5, 0 );

			add_action( 'wp_ajax_sc_delete_message',  array( __CLASS__, 'maybe_delete_message' ), 5, 0 );

			add_action( 'wp_ajax_sc_edit_message',  array( get_class(), 'maybe_update_message' ), 10, 0 );

			// ajax views
			add_action( 'wp_ajax_sc_create_message_form',  array( __CLASS__, 'create_message_form' ), 5, 0 );
			add_action( 'wp_ajax_sc_edit_message_form',  array( __CLASS__, 'edit_message_form' ), 10, 0 );
			add_action( 'wp_ajax_sc_preview_message',  array( __CLASS__, 'preview_message' ), 10, 0 );
		}

	}

	/**
	 * AJAX submission from admin.
	 * @return json response
	 */
	public static function maybe_create_message() {

		// form maybe be serialized
		if ( isset( $_REQUEST['serialized_fields'] ) ) {
			foreach ( $_REQUEST['serialized_fields'] as $key => $data ) {
				$_REQUEST[ $data['name'] ] = $data['value'];
			}
		}

		if ( ! isset( $_REQUEST['sa_message_nonce'] ) ) {
			wp_send_json_error( array( 'error_message' => __( 'Forget something?', 'sprout-invoices' ) ) );
		}

		$nonce = $_REQUEST['sa_message_nonce'];
		if ( ! wp_verify_nonce( $nonce, self::SUBMISSION_NONCE ) ) {
			wp_send_json_error( array( 'error_message' => __( 'Not going to fall for it!', 'sprout-invoices' ) ) );
		}

		if ( ! current_user_can( 'publish_posts' ) ) {
			wp_send_json_error( array( 'error_message' => __( 'User cannot create new posts!', 'sprout-invoices' ) ) );
		}

		if ( ! isset( $_REQUEST['sa_message_send_time'] ) || '' === $_REQUEST['sa_message_send_time'] ) {
			wp_send_json_error( array( 'error_message' => __( 'A send date is required!', 'sprout-invoices' ) ) );
		}

		if ( ! isset( $_REQUEST['sa_message_subject'] ) || '' === $_REQUEST['sa_message_subject'] ) {
			wp_send_json_error( array( 'error_message' => __( 'A subject is required!', 'sprout-invoices' ) ) );
		}

		if ( ! isset( $_REQUEST['sa_message_message'] ) || '' === $_REQUEST['sa_message_message'] ) {
			wp_send_json_error( array( 'error_message' => __( 'A message is required', 'sprout-invoices' ) ) );
		}

		$args = array(
			'subject' => isset( $_REQUEST['sa_message_subject'] ) ? esc_attr( $_REQUEST['sa_message_subject'] ) : '',
			'message' => isset( $_REQUEST['sa_message_message'] ) ? $_REQUEST['sa_message_message'] : '',
			'send_time' => isset( $_REQUEST['sa_message_send_time'] ) ? strtotime( esc_attr( $_REQUEST['sa_message_send_time'] ) ) : '',
			'recipients' => isset( $_REQUEST['sa_message_recipients'] ) ? esc_attr( $_REQUEST['sa_message_recipients'] ) : '',
			'html' => isset( $_REQUEST['sa_message_format'] ) && $_REQUEST['sa_message_format'] ? 1 : '',
			'client_id' => isset( $_REQUEST['sa_message_client_id'] ) ? esc_attr( $_REQUEST['sa_message_client_id'] ) : '',
		);

		$message_id = SC_Message::new_message( $args );

		ob_start();
		$client = Sprout_Client::get_instance( $args['client_id'] );
		global $post;
		setup_postdata( $client->get_post() );
		print self::pagination_view( $args['client_id'] );
		$view = ob_get_clean();

		$response = array(
				'client_id' => $args['client_id'],
				'id' => $message_id,
				'title' => get_the_title( $message_id ),
				'view' => $view,
			);

		wp_send_json_success( $response );
	}

	/**
	 * AJAX submission from admin.
	 * @return json response
	 */
	public static function maybe_update_message() {
		// form maybe be serialized
		if ( isset( $_REQUEST['serialized_fields'] ) ) {
			foreach ( $_REQUEST['serialized_fields'] as $key => $data ) {
				$_REQUEST[ $data['name'] ] = $data['value'];
			}
		}

		if ( ! isset( $_REQUEST['sa_message_nonce'] ) ) {
			wp_send_json_error( array( 'error_message' => __( 'Forget something?', 'sprout-invoices' ) ) );
		}

		$nonce = $_REQUEST['sa_message_nonce'];
		if ( ! wp_verify_nonce( $nonce, self::SUBMISSION_NONCE ) ) {
			wp_send_json_error( array( 'error_message' => __( 'Not going to fall for it!', 'sprout-invoices' ) ) );
		}

		if ( ! current_user_can( 'publish_posts' ) ) {
			wp_send_json_error( array( 'error_message' => __( 'User cannot create new posts!', 'sprout-invoices' ) ) );
		}

		if ( ! isset( $_REQUEST['sa_message_message_id'] ) || '' === $_REQUEST['sa_message_message_id'] ) {
			wp_send_json_error( array( 'error_message' => __( 'A message id is required', 'sprout-invoices' ) ) );
		}

		$message = SC_Message::get_instance( $_REQUEST['sa_message_message_id'] );
		$client_id = $message->get_client_id();

		if ( isset( $_REQUEST['sa_message_send_time'] ) && '' !== $_REQUEST['sa_message_send_time'] ) {
			$message->set_send_time( strtotime( esc_attr( $_REQUEST['sa_message_send_time'] ) ) );
		}

		if ( isset( $_REQUEST['sa_message_subject'] ) && '' !== $_REQUEST['sa_message_subject'] ) {
			$message->set_title( esc_attr( $_REQUEST['sa_message_subject'] ) );
		}

		if ( isset( $_REQUEST['sa_message_message'] ) && '' !== $_REQUEST['sa_message_message'] ) {
			$message->set_content( $_REQUEST['sa_message_message'] );
		}

		if ( isset( $_REQUEST['sa_message_recipients'] ) && '' !== $_REQUEST['sa_message_recipients'] ) {
			$message->set_recipients( array( $_REQUEST['sa_message_recipients'] ) );
		}

		$message->set_plain();
		if ( isset( $_REQUEST['sa_message_format'] ) && $_REQUEST['sa_message_format'] ) {
			$message->set_html();
		}

		ob_start();
		$client = Sprout_Client::get_instance( $client_id );
		global $post;
		setup_postdata( $client->get_post() );
		print self::pagination_view( $client_id );
		$view = ob_get_clean();

		$response = array(
				'client_id' => $client_id,
				'id' => $message->get_id(),
				'title' => $message->get_subject(),
				'view' => $view,
			);

		wp_send_json_success( $response );
	}


	/**
	 * AJAX message preview
	 * @return json response
	 */
	public static function preview_message() {

		if ( ! current_user_can( 'publish_posts' ) ) {
			wp_send_json_error( array( 'error_message' => __( 'User cannot view this message!', 'sprout-invoices' ) ) );
		}

		if ( ! isset( $_REQUEST['message_id'] ) || '' === $_REQUEST['message_id'] ) {
			wp_die( __( 'No message id provided.', 'sprout-invoices' ) );
		}
		$message = SC_Message::get_instance( $_REQUEST['message_id'] );
		if ( ! is_a( $message, 'SC_Message' ) ) {
			wp_die( __( 'Valid message id not provided.', 'sprout-invoices' ) );
		}
		$full_message = Sprout_Clients_Messages_Template::wrap_message_with_body_template( $message );
		if ( ! $message->is_html() ) {
			$full_message = wpautop( $full_message );
		}
		print $full_message;
		exit();
	}

	/**
	 * AJAX submission from admin.
	 * @return json response
	 */
	public static function edit_message_form() {

		if ( ! current_user_can( 'publish_posts' ) ) {
			wp_send_json_error( array( 'error_message' => __( 'User cannot create new posts!', 'sprout-invoices' ) ) );
		}

		if ( ! isset( $_REQUEST['message_id'] ) || '' === $_REQUEST['message_id'] ) {
			wp_send_json_error( array( 'error_message' => __( 'A message id is required!', 'sprout-invoices' ) ) );
		}
		ob_start();
		$fields = self::messages_edit_form( 0, $_REQUEST['message_id'] );
		self::edit_form( $fields );
		$view = ob_get_clean();

		$response = array(
				'view' => $view,
			);

		wp_send_json_success( $response );
	}

	/**
	 * AJAX submission from admin.
	 * @return json response
	 */
	public static function create_message_form() {

		if ( ! current_user_can( 'publish_posts' ) ) {
			wp_send_json_error( array( 'error_message' => __( 'User cannot create new posts!', 'sprout-invoices' ) ) );
		}

		if ( ! isset( $_REQUEST['client_id'] ) || '' === $_REQUEST['client_id'] ) {
			wp_send_json_error( array( 'error_message' => __( 'A client id is required!', 'sprout-invoices' ) ) );
		}
		ob_start();
		$fields = self::messages_edit_form( $_REQUEST['client_id'] );
		self::create_form( $fields );
		$view = ob_get_clean();

		$response = array(
				'view' => $view,
			);

		wp_send_json_success( $response );
	}

	/**
	 * Delete the message
	 * @return json response
	 */
	public static function maybe_delete_message() {

		if ( ! current_user_can( 'publish_posts' ) ) {
			wp_send_json_error( array( 'error_message' => __( 'User cannot delete messages!', 'sprout-invoices' ) ) );
		}
		if ( ! isset( $_REQUEST['message_id'] ) || '' === $_REQUEST['message_id'] ) {
			wp_send_json_error( array( 'error_message' => __( 'A message id is required!', 'sprout-invoices' ) ) );
		}

		wp_delete_post( $_REQUEST['message_id'], true );

		wp_send_json_success();
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
			'si_show_messages' => array(
				'title' => sc__( 'Messages' ),
				'show_callback' => array( __CLASS__, 'show_messages_view' ),
				'save_callback' => array( __CLASS__, '_save_null' ),
				'context' => 'normal',
				'priority' => 'high',
				'weight' => 5,
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
	public static function show_messages_view( $post, $metabox = '' ) {
		if ( 'auto-draft' === $post->post_status ) {
			printf( '<p>%s</p>', sc__( 'Save before creating any messages.' ) );
			return;
		}

		print self::pagination_view( $post->ID );
		add_thickbox();
	}

	public static function pagination_view( $client_id = 0 ) {
		$messages = SC_Message::get_messages_by_client_id( $client_id );
		// pagination
		$show_per_page = apply_filters( 'sc_message_history_records', 10 );
		$total_pages = ceil( count( $messages ) / $show_per_page );
		$current_page = ( isset( $_REQUEST['messages_page'] ) ) ? (int) $_REQUEST['messages_page'] : 1;
		$start = ( $current_page > 1 ) ? ( $current_page - 1 ) * $show_per_page : 0 ;
		$messages = array_slice( $messages, $start, $show_per_page, true );

		return self::load_view_to_string( 'admin/meta-boxes/messages/messages', array(
				'client_id' => $client_id,
				'messages' => $messages,
				'pagination' => self::get_pagination( $total_pages, $current_page ),
		), false );
	}

	public static function get_pagination( $total_pages, $current_page ) {
		$page_links = paginate_links( array(
			'base' => add_query_arg( 'messages_page', '%#%' ),
			'format' => '',
			'prev_text' => __( '&laquo;' , 'sprout-invoices' ),
			'next_text' => __( '&raquo;' , 'sprout-invoices' ),
			'total' => $total_pages,
			'current' => $current_page,
		) );
		return  '<div class="sa_tablenav">' . $page_links . '</div>';
	}


	////////////////
	// AJAX Views //
	////////////////

	public static function create_form( $fields = array() ) {
		self::load_view( 'admin/meta-boxes/messages/message-creation', array( 'fields' => $fields ) );
	}

	public static function edit_form( $fields = array() ) {
		self::load_view( 'admin/meta-boxes/messages/message-edit', array( 'fields' => $fields ) );
	}

	public static function messages_edit_form( $client_id = 0, $message_id = 0 ) {

		$message = null;
		if ( $message_id ) {
			$message = SC_Message::get_instance( $message_id );
		}

		if ( is_a( $message, 'SC_Message' ) && ! $client_id ) {
			$client_id = $message->get_client_id();
		}

		$client = Sprout_Client::get_instance( $client_id );
		$associtated_users = $client->get_associated_users();

		$args = apply_filters( 'si_get_users_for_association_args', array( 'fields' => array( 'ID', 'user_email', 'display_name' ) ) );
		$users = get_users( $args );
		$users_select_options = array();

		if ( ! empty( $associtated_users ) ) {
			foreach ( $associtated_users as $user_id ) {
				$users_select_options[ $user_id ] = sc_get_users_full_name( $user_id );
			}
			$users_select_options['disabled'] = '&mdash;&mdash;&mdash;&mdash;&mdash;&mdash;&mdash;&mdash;&mdash;';
		}

		foreach ( $users as $user ) {
			if ( ! in_array( $user->ID, $users_select_options ) ) {
				$users_select_options[ $user->ID ] = sc_get_users_full_name( $user->ID );
			}
		}

		// add the message creation modal
		$fields = array();

		$fields['client_id'] = array(
			'weight' => PHP_INT_MAX,
			'type' => 'hidden',
			'default' => $client_id,
			'value' => $client_id,
		);

		if ( $message_id ) {
			$fields['message_id'] = array(
				'weight' => PHP_INT_MAX - 1,
				'type' => 'hidden',
				'default' => $message_id,
				'value' => $message_id,
			);
		}

		$fields['nonce'] = array(
			'type' => 'hidden',
			'value' => wp_create_nonce( self::SUBMISSION_NONCE ),
			'weight' => 10000,
		);

		$fields['send_time'] = array(
			'weight' => 5,
			'label' => __( 'Send Date' , 'sprout-invoices' ),
			'type' => 'date',
		);

		$fields['recipients'] = array(
			'weight' => 10,
			'label' => __( 'Recipient' , 'sprout-invoices' ),
			'type' => 'select',
			'attributes' => array( 'class' => 'sa_select2' ),
			'options' => $users_select_options,
		);

		$fields['subject'] = array(
			'weight' => 30,
			'label' => __( 'Subject' , 'sprout-invoices' ),
			'type' => 'small-input',
		);

		$fields['message'] = array(
			'weight' => 50,
			'label' => __( 'Message' , 'sprout-invoices' ),
			'type' => 'textarea',
			'attributes' => array( 'class' => 'si_redactorize' ),
			'placeholder' => 'This is the message that will replace the [message] shortcode within the messages template you have configured in settings.',
		);

		$fields['format'] = array(
			'weight' => 70,
			'label' => __( 'HTML Format' , 'sprout-invoices' ),
			'type' => 'checkbox',
			'value' => 1,
			'default' => get_option( Sprout_Clients_Messages_Template::FORMAT_DEFAULT_OPTION, 1 ),
		);

		if ( is_a( $message, 'SC_Message' ) ) {
			$fields['send_time']['default'] = date( 'Y-m-d', $message->get_send_time() );
			$recipients = $message->get_recipients();
			if ( is_array( $recipients ) ) {
				$fields['recipients']['default'] = $recipients[0];
			}
			$fields['subject']['default'] = $message->get_subject();
			$fields['message']['default'] = $message->get_message_content();
			if ( $message->is_html() ) {
				$fields['format']['default'] = 1;
			}
		}

		$fields = apply_filters( 'si_message_create_form_fields', $fields );
		uasort( $fields, array( __CLASS__, 'sort_by_weight' ) );
		return $fields;
	}
}
