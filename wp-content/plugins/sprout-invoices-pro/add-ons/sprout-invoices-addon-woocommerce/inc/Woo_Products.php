<?php

/**
 * Woo_Products Controller
 *
 * @package Sprout_Invoice
 * @subpackage Woo_Products
 */
class SI_Woo_Products extends SI_Controller {
	public static function init() {

		if ( is_admin() ) {
			// Invoicing
			add_action( 'si_post_add_line_item', array( __CLASS__, 'add_woocommerce_products' ) );

			// Enqueue
			add_action( 'admin_enqueue_scripts', array( __CLASS__, 'register_resources' ) );
			add_action( 'admin_enqueue_scripts', array( __CLASS__, 'admin_enqueue' ), 20 );
		}

		// ajax actions
		add_action( 'wp_ajax_sa_get_woo_product',  array( __CLASS__, 'maybe_get_product' ), 10, 0 );

	}

	//////////////
	// Enqueue //
	//////////////

	public static function register_resources() {
		// admin js
		wp_register_script( 'si_woocommerce_products', SA_ADDON_WOOCOMMERCE_URL . '/resources/admin/js/si_woo_products.js', array( 'jquery', 'si_line_items' ), self::SI_VERSION );
	}

	public static function admin_enqueue() {

		// doc admin templates
		$screen = get_current_screen();
		$screen_post_type = str_replace( 'edit-', '', $screen->id );
		if ( in_array( $screen_post_type, array( SI_Estimate::POST_TYPE, SI_Invoice::POST_TYPE ) ) ) {
			add_thickbox();
			wp_enqueue_script( 'si_woocommerce_products' );
		}
	}

	////////////////
	// Invoicing //
	////////////////

	public static function add_woocommerce_products() {
		$products = self::get_woo_products();
		$categories = get_terms( 'product_cat', array(
			'hide_empty' => true,
		) );
		error_log( 'products: ' . print_r( $products, true ) );
		error_log( 'categories: ' . print_r( $categories, true ) );
		self::load_addon_view( 'admin/meta-boxes/invoices/add-product.php', array(
				'products' => $products,
				'categories' => $categories,
		), false );
	}

	///////////////////
	// AJAX CALLBACK //
	///////////////////

	public static function maybe_get_product() {
		if ( ! current_user_can( 'publish_sprout_invoices' ) ) {
			wp_send_json_error( array( 'message' => __( 'User cannot create invoices!', 'sprout-invoices' ) ) );
		}

		$product_id = 0;
		if ( isset( $_REQUEST['product_id'] ) ) {
			$product_id = (int) $_REQUEST['product_id'];
		}

		if ( ! $product_id ) {
			wp_send_json_error( array( 'message' => __( 'No product given!', 'sprout-invoices' ) ) );
		}

		$product = new WC_Product( $product_id );
		$product_data = array(
				'type' => 'product',
				'desc' => sprintf( '<b>%s</b><br/>%s', $product->get_title(), apply_filters( 'si_woocommerce_short_description', $product->get_post_data()->post_excerpt, $product )
				),
				'rate' => wc_format_decimal( $product->get_price(), 2 ),
				'qty' => 1,
				'tax' => 0,
				'total' => wc_format_decimal( $product->get_price(), 2 ),
				'sku' => $product->get_sku(),
				'title' => $product->get_title(),
			);

		ob_start();
		SI_Controller::load_view( 'admin/sections/line-item-options', array(
			'columns' => SI_Line_Items::line_item_columns( $product_data['type'] ),
			'item_data' => $product_data,
			'has_children' => false,
			'items' => array(),
			'position' => 0,
			'children' => array(),
		), false );
		$option = ob_get_clean();

		$view = sprintf( '<li id="line_item_loaded_%1$s" class="product line_item_type_%1$s" data-id="0">%2$s</li>', $product_data['type'], $option );

		$response = array(
				'option' => $view,
				'type' => $product_data['type'],
			);
		wp_send_json_success( $response );
	}


	/////////////
	// Utility //
	/////////////

	public static function get_woo_products() {
		$products = array(); // multi-dimensional array of products and tasks
		$args = array(
			'post_type' => 'product',
			'posts_per_page' => -1,
			'fields' => 'ids',
			'orderby' => 'modified',
			);
		$product_ids = get_posts( $args );
		foreach ( $product_ids as $product_id ) {
			$product = new WC_Product( $product_id );
			$categories = wp_get_post_terms( $product_id, 'product_cat' );
			if ( ! empty( $categories ) ) {
				// create a product within each category assigned.
				foreach ( $categories as $category ) {
					$products[ $category->slug ][ $product_id ] = array(
						'id' => $product_id,
						'cat_name' => $term->name,
						'qty' => 1,
						'rate' => wc_format_decimal( $product->get_price(), 2 ),
						'percentage' => 0,
						'sku' => $product->get_sku(),
						'title' => $product->get_title(),
						'content' => apply_filters( 'woocommerce_short_description', $product->get_post_data()->post_excerpt ),
						// 'product' => $product,
					);
				}
			} else {
				$products['none'][ $product_id ] = array(
					'id' => $product_id,
					'cat_name' => $term->name,
					'qty' => 1,
					'rate' => wc_format_decimal( $product->get_price(), 2 ),
					'percentage' => 0,
					'sku' => $product->get_sku(),
					'title' => $product->get_title(),
					'content' => apply_filters( 'si_woocommerce_short_description', $product->get_post_data()->post_excerpt, $product ),
					// 'product' => $product,
				);
			}
		}
		return apply_filters( 'si_woocommerce_products', $products );
	}

	public static function load_addon_view( $view, $args, $allow_theme_override = true ) {
		add_filter( 'si_views_path', array( __CLASS__, 'addons_view_path' ) );
		$view = self::load_view( $view, $args, $allow_theme_override );
		remove_filter( 'si_views_path', array( __CLASS__, 'addons_view_path' ) );
		return $view;
	}

	public static function addons_view_path() {
		return SA_ADDON_WOOCOMMERCE_PATH . '/views/';
	}
}
