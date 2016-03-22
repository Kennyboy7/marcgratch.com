<?php

/**
 * Controller
 * Adds meta boxes to client admin.
 */
class SI_Client_PP_Meta extends SI_Controller {
	const META_KEY = '_si_enabled_payment_processors';

	public static function init() {
		add_filter( 'si_client_adv_form_fields', array( __CLASS__, 'add_pp_option' ), 10, 2 );
		add_action( 'SI_Clients::save_meta_box_client_adv_information', array( __CLASS__, 'save_client_options' ) );
	}


	public static function add_pp_option( $fields = array(), $client = 0 ) {
		$processor_options = array();
		$enabled_processors = SI_Payment_Processors::enabled_processors();
		if ( empty( $enabled_processors ) ) {
			return $fields;
		}
		$client_enabled = self::get_client_processors( $client->get_id() );
		foreach ( $enabled_processors as $class ) {
			if ( method_exists( $class, 'get_instance' ) ) {
				$payment_processor = call_user_func( array( $class, 'get_instance' ) );
				$processor_options[ esc_attr( $class ) ] = esc_html( $payment_processor->get_payment_method() );
			}
		}
		$fields[ self::META_KEY ] = array(
			'weight' => 1000,
			'label' => __( 'Enabled Payment Options' , 'sprout-invoices' ),
			'type' => 'multiselect',
			'options' => $processor_options,
			'default' => self::get_client_processors( $client->get_id() ),
			'description' => __( 'Limit client to specific payment processors.' ),
			'attributes' => array( 'class' => 'select2' ),
			);

		return $fields;

	}

	public static function get_client_processors( $client_id = 0 ) {
		$enabled_processors = get_post_meta( $client_id, self::META_KEY, true );
		if ( '' === $enabled_processors || empty( $enabled_processors ) ) {
			$enabled_processors = SI_Payment_Processors::enabled_processors();
		}
		return $enabled_processors;
	}

	public static function set_client_processors( $client_id = 0, $enabled_processors = array() ) {
		update_post_meta( $client_id, self::META_KEY, $enabled_processors );
	}

	/**
	 * Save client options on advanced meta box save action
	 * @param  integer $post_id
	 * @return
	 */
	public static function save_client_options( $post_id = 0 ) {
		if ( ! isset( $_POST[ 'sa_metabox_' . self::META_KEY ] ) ) {
			return;
		}
		self::set_client_processors( $post_id, $_POST[ 'sa_metabox_' . self::META_KEY ] );
	}
}
SI_Client_PP_Meta::init();
