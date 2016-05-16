<?php

/**
 * Predefined_Items Controller
 *
 * @package Sprout_Invoice
 * @subpackage Predefined_Items
 */
class Predefined_Items extends SI_Controller {
	public static function init() {

		if ( is_admin() ) {
			// Invoicing
			add_action( 'si_post_add_line_item', array( __CLASS__, 'add_predefined_items' ) );

			// Enqueue
			add_action( 'admin_enqueue_scripts', array( __CLASS__, 'register_resources' ) );
			add_action( 'admin_enqueue_scripts', array( __CLASS__, 'admin_enqueue' ), 20 );
		}

		// ajax actions
		add_action( 'wp_ajax_sa_create_pd_item',  array( __CLASS__, 'maybe_create_item' ), 10, 0 );
		add_action( 'wp_ajax_sa_edit_pd_item',  array( __CLASS__, 'maybe_edit_item' ), 10, 0 );
		add_action( 'wp_ajax_sa_delete_pd_item',  array( __CLASS__, 'maybe_delete_item' ), 10, 0 );
		add_action( 'wp_ajax_sa_get_pd_item',  array( __CLASS__, 'maybe_get_item' ), 10, 0 );

		// ajax views
		add_action( 'wp_ajax_sa_manage_pd_items_view',  array( __CLASS__, 'items_admin_view' ), 10, 0 );
		add_action( 'wp_ajax_sa_pd_item_creation_view',  array( __CLASS__, 'item_creation_view' ), 10, 0 );
		add_action( 'wp_ajax_sa_pd_item_edit_view',  array( __CLASS__, 'item_edit_view' ), 10, 0 );
		add_action( 'wp_ajax_sa_pd_items_options_view',  array( __CLASS__, 'add_predefined_items_view' ), 10, 0 );

		add_filter( 'si_admin_scripts_localization',  array( __CLASS__, 'ajax_l10n' ) );
	}


	//////////////
	// Enqueue //
	//////////////

	public static function register_resources() {
		// admin js
		wp_register_script( 'si_predefined_items', SA_ADDON_PREDEFINED_ITEMS_URL . '/resources/admin/js/predefined_items.js', array( 'jquery', 'si_line_items' ), self::SI_VERSION );
	}

	public static function admin_enqueue() {

		// doc admin templates
		$screen = get_current_screen();
		$screen_post_type = str_replace( 'edit-', '', $screen->id );
		if ( in_array( $screen_post_type, array( SI_Estimate::POST_TYPE, SI_Invoice::POST_TYPE ) ) ) {
			add_thickbox();
			wp_enqueue_script( 'si_predefined_items' );
		}
	}

	////////////////
	// Invoicing //
	////////////////

	public static function add_predefined_items() {
		$items = self::get_items_and_products();
		$types = SI_Line_Items::line_item_types();
		self::load_addon_view( 'admin/meta-boxes/invoices/add-task-or-product.php', array(
				'types' => $types,
				'items' => $items,
		), false );
	}


	///////////
	// Form //
	///////////

	public static function item_creation_fields( $id = 0 ) {

		$fields['name'] = array(
			'weight' => 0,
			'label' => __( 'Item Label', 'sprout-invoices' ),
			'type' => 'text',
		);

		$fields['description'] = array(
			'weight' => 10,
			'label' => __( 'Default Description', 'sprout-invoices' ),
			'type' => 'textarea',
			'attributes' => array( 'class' => 'si_redactorize' ),
			'placeholder' => '',
		);

		$fields['type'] = array(
			'weight' => 50,
			'label' => __( 'Type', 'sprout-invoices' ),
			'type' => 'select',
			'options' => SI_Line_Items::line_item_types(),
		);

		$fields['rate'] = array(
			'weight' => 15,
			'label' => __( 'Default Rate', 'sprout-invoices' ),
			'type' => 'text',
			'placeholder' => 100,
		);

		$fields['qty'] = array(
			'weight' => 20,
			'label' => __( 'Default Qty', 'sprout-invoices' ),
			'type' => 'text',
			'placeholder' => 1,
		);

		$fields['percentage'] = array(
			'weight' => 25,
			'label' => __( 'Default %', 'sprout-invoices' ),
			'type' => 'text',
			'placeholder' => 0,
		);

		$fields['sku'] = array(
			'weight' => 25,
			'label' => __( 'Default SKU (if product)', 'sprout-invoices' ),
			'type' => 'text',
			'placeholder' => '',
		);

		$fields['id'] = array(
			'type' => 'hidden',
			'weight' => 10000,
		);

		$fields['nonce'] = array(
			'type' => 'hidden',
			'value' => wp_create_nonce( self::NONCE ),
			'weight' => 10000,
		);

		$fields = apply_filters( 'si_item_creation_form_fields', $fields );
		uasort( $fields, array( __CLASS__, 'sort_by_weight' ) );
		return $fields;
	}

	/////////////////////
	// AJAX Callbacks //
	/////////////////////
	///
	public static function maybe_edit_item() {
		if ( ! current_user_can( 'edit_sprout_invoices' ) ) {
			wp_send_json_error( array( 'message' => __( 'User cannot create an item!', 'sprout-invoices' ) ) );
		}

		$nonce = $_REQUEST['nonce'];
		if ( ! wp_verify_nonce( $nonce, self::NONCE ) ) {
			wp_send_json_error( array( 'message' => __( 'Not secure!', 'sprout-invoices' ) ) );
		}

		if ( ! isset( $_REQUEST['id'] ) || ! is_numeric( $_REQUEST['id'] ) ) {
			wp_send_json_error( array( 'message' => __( 'No id given!', 'sprout-invoices' ) ) );
		}
		$item = SI_Item::get_instance( $_REQUEST['id'] );
		if ( ! is_a( $item, 'SI_Item' ) ) {
			wp_send_json_error( array( 'message' => __( 'Something is wrong!', 'sprout-invoices' ) ) );
		}
		if ( isset( $_REQUEST['name'] ) ) {
			$item->set_title( stripslashes( $_REQUEST['name'] ) );
		}
		if ( isset( $_REQUEST['type'] ) ) {
			$item->set_type( $_REQUEST['type'] );
		}
		if ( isset( $_REQUEST['description'] ) ) {
			$item->set_content( stripslashes( $_REQUEST['description'] ) );
		}
		if ( isset( $_REQUEST['qty'] ) ) {
			$item->set_default_qty( $_REQUEST['qty'] );
		}
		if ( isset( $_REQUEST['percentage'] ) ) {
			$item->set_default_percentage( $_REQUEST['percentage'] );
		}
		if ( isset( $_REQUEST['rate'] ) ) {
			$item->set_default_rate( $_REQUEST['rate'] );
		}
		if ( isset( $_REQUEST['sku'] ) ) {
			$item->set_default_sku( $_REQUEST['sku'] );
		}
	}

	public static function maybe_create_item() {
		if ( ! current_user_can( 'edit_sprout_invoices' ) ) {
			self::ajax_fail( 'User cannot create an item!' );
		}

		$nonce = $_REQUEST['nonce'];
		if ( ! wp_verify_nonce( $nonce, self::NONCE ) ) {
			self::ajax_fail( 'Not going to fall for it!' );
		}

		if ( ! isset( $_REQUEST['name'] ) || $_REQUEST['name'] == '' ) {
			self::ajax_fail( 'No name given.' );
		}

		$args = array();
		$args['name'] = $_REQUEST['name'];
		$args['product'] = ( isset( $_REQUEST['product'] ) ) ? true : false ;
		if ( isset( $_REQUEST['type'] ) ) {
			$args['type'] = $_REQUEST['type'];
		}
		if ( isset( $_REQUEST['description'] ) ) {
			$args['description'] = $_REQUEST['description'];
		}
		if ( isset( $_REQUEST['qty'] ) ) {
			$args['qty'] = (float) $_REQUEST['qty'];
		}
		if ( isset( $_REQUEST['percentage'] ) ) {
			$args['percentage'] = (float) $_REQUEST['percentage'];
		}
		if ( isset( $_REQUEST['rate'] ) ) {
			$args['rate'] = (float) $_REQUEST['rate'];
		}
		if ( isset( $_REQUEST['sku'] ) ) {
			$args['sku'] = $_REQUEST['sku'];
		}
		$id = SI_Item::new_item( $args );
		$item = SI_Item::get_instance( $id );
		$response = array(
				'id' => $id,
				'type' => $item->get_type(),
				'desc' => stripslashes( $item->get_content() ),
				'rate' => $item->get_default_rate(),
				'qty' => $item->get_default_qty(),
				'tax' => $item->get_default_percentage(),
				'sku' => $item->get_default_sku(),
				'title' => stripslashes( $item->get_title() ),
			);
		wp_send_json_success( $response );
	}

	public static function maybe_delete_item() {
		$nonce = $_REQUEST['nonce'];
		if ( ! wp_verify_nonce( $nonce, self::NONCE ) ) {
			self::ajax_fail( 'Not going to fall for it!' ); }

		if ( isset( $_REQUEST['item_id'] ) ) {
			$item_id = $_REQUEST['item_id'];
		}
		if ( ! $item_id ) {
			self::ajax_fail( 'No id given.' );
		}
		if ( get_post_type( $item_id ) != SI_Item::POST_TYPE ) {
			self::ajax_fail( 'Not an item.' );
		}

		wp_delete_post( $item_id, true );

		echo true;
		exit();
	}

	public static function maybe_get_item() {
		if ( ! current_user_can( 'publish_sprout_invoices' ) ) {
			wp_send_json_error( array( 'message' => __( 'User cannot create an item!', 'sprout-invoices' ) ) );
		}

		$item_id = 0;
		if ( isset( $_REQUEST['item_id'] ) ) {
			$item_id = (int) $_REQUEST['item_id'];
		}

		if ( ! $item_id ) {
			wp_send_json_error( array( 'message' => __( 'No item given!', 'sprout-invoices' ) ) );
		}

		$item = SI_Item::get_instance( $item_id );
		$item_data = array(
				'type' => $item->get_type(),
				'desc' => $item->get_content(),
				'rate' => $item->get_default_rate(),
				'qty' => ( $item->get_default_qty() ) ? $item->get_default_qty() : 1,
				'tax' => $item->get_default_percentage(),
				'sku' => $item->get_default_sku(),
				'title' => $item->get_title(),
			);

		ob_start();
		SI_Controller::load_view( 'admin/sections/line-item-options', array(
			'columns' => SI_Line_Items::line_item_columns( $item_data['type'] ),
			'item_data' => $item_data,
			'has_children' => false,
			'items' => array(),
			'position' => 0,
			'children' => array(),
		), false );
		$option = ob_get_clean();

		$view = sprintf( '<li id="line_item_loaded_%1$s" class="item line_item_type_%1$s" data-id="0">%2$s</li>', $item_data['type'], $option );

		$response = array(
				'option' => $view,
				'type' => $item_data['type'],
			);
		wp_send_json_success( $response );
	}

	////////////////
	// AJAX View //
	////////////////

	public static function ajax_l10n( $js_object = array() ) {
		$js_object['item_creation_modal_title'] = __( 'Create Pre-defined Item', 'sprout-invoices' );
		$js_object['item_creation_modal_url'] = admin_url( 'admin-ajax.php?action=sa_pd_item_creation_view&height=600&width=800' );
		$js_object['item_mngt_modal_title'] = __( 'Manage Pre-defined Items', 'sprout-invoices' );
		$js_object['item_mngt_modal_url'] = admin_url( 'admin-ajax.php?action=sa_manage_pd_items_view&height=520&width=800' );
		$js_object['item_edit_modal_title'] = __( 'Edit a Pre-defined Item', 'sprout-invoices' );
		$js_object['item_edit_modal_url'] = admin_url( 'admin-ajax.php?action=sa_pd_item_edit_view&height=600&width=800' );
		return $js_object;
	}

	public static function items_admin_view() {
		if ( ! current_user_can( 'edit_sprout_invoices' ) ) {
			self::ajax_fail( 'User cannot create new posts!' );
		}

		$items = self::get_items_and_products();
		self::load_addon_view( 'admin/sections/items-admin', array(
				'items' => $items,
		), false );
		exit();
	}

	public static function item_creation_view() {
		if ( ! current_user_can( 'edit_sprout_invoices' ) ) {
			self::ajax_fail( 'User cannot create new posts!' );
		}

		// Item creation
		$fields = self::item_creation_fields();
		self::load_addon_view( 'admin/sections/items-creation-form', array(
				'fields' => $fields,
		), false );
		exit();
	}

	public static function item_edit_view() {
		if ( ! current_user_can( 'publish_sprout_invoices' ) ) {
			wp_send_json_error( array( 'message' => __( 'User cannot create an item!', 'sprout-invoices' ) ) );
		}

		$item_id = 0;
		if ( isset( $_REQUEST['item_id'] ) ) {
			$item_id = (int) $_REQUEST['item_id'];
		}

		if ( ! $item_id ) {
			wp_send_json_error( array( 'message' => __( 'No item given!', 'sprout-invoices' ) ) );
		}

		$item = SI_Item::get_instance( $item_id );
		$item_data = array(
				'id' => $item_id,
				'type' => $item->get_type(),
				'description' => $item->get_content(),
				'rate' => $item->get_default_rate(),
				'qty' => $item->get_default_qty(),
				'percentage' => $item->get_default_percentage(),
				'sku' => $item->get_default_sku(),
				'name' => $item->get_title(),
			);

		// Item creation
		$fields = self::item_creation_fields();
		foreach ( $fields as $key => $option ) {
			if ( isset( $item_data[ $key ] ) ) {
				$fields[ $key ]['default'] = $item_data[ $key ];
			}
		}
		$fields['id']['value'] = $item_id;
		self::load_addon_view( 'admin/sections/items-edit-form', array(
				'fields' => $fields,
		), false );
		exit();
	}

	public static function add_predefined_items_view() {
		if ( ! current_user_can( 'edit_sprout_invoices' ) ) {
			self::ajax_fail( 'User cannot create new posts!' ); }

		$items = self::get_items_and_products();
		$types = SI_Line_Items::line_item_types();
		self::load_addon_view( 'admin/meta-boxes/invoices/add-task-or-product.php', array(
				'types' => $types,
				'items' => $items,
		), false );
		exit();
	}

	//////////////
	// Utility //
	//////////////

	public static function get_items_and_products() {
		$items = array(); // multi-dimensional array of products and tasks

		$args = array(
			'orderby' => 'modified',
			'post_type' => SI_Item::POST_TYPE,
			'posts_per_page' => -1,
			'fields' => 'ids',
			);
		$item_ids = get_posts( $args );
		foreach ( $item_ids as $item_id ) {
			$item = SI_Item::get_instance( $item_id );
			$items[ $item->get_type() ][ $item_id ] = array(
				'id' => $item_id,
				'type' => $item->get_type(),
				'qty' => $item->get_default_qty(),
				'rate' => $item->get_default_rate(),
				'percentage' => $item->get_default_percentage(),
				'sku' => $item->get_default_sku(),
				'title' => $item->get_title(),
				'content' => $item->get_content(),
				// 'item' => $item,
			);
		}
		return apply_filters( 'si_items_and_products', $items );
	}

	public static function load_addon_view( $view, $args, $allow_theme_override = true ) {
		add_filter( 'si_views_path', array( __CLASS__, 'addons_view_path' ) );
		$view = self::load_view( $view, $args, $allow_theme_override );
		remove_filter( 'si_views_path', array( __CLASS__, 'addons_view_path' ) );
		return $view;
	}

	public static function addons_view_path() {
		return SA_ADDON_PREDEFINED_ITEMS_PATH . '/views/';
	}
}
