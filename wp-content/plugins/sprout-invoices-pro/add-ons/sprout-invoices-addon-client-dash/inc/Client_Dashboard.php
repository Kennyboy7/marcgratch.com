<?php

/**
 * SI_Client_Dashboard Controller
 *
 * @package Sprout_Invoice
 * @subpackage SI_Client_Dashboard
 */
class SI_Client_Dashboard extends SI_Controller {
	const SHORTCODE = 'sprout_invoices_dashboard';
	const CLIENT_TOKEN_META = 'token';
	const CLIENT_TOKEN_QUERY_ARG = 'token';
	const USER_QUERY_ARG = 'uid';
	const DOC_QUERY_ARG = 'id';

	public static function init() {

		do_action( 'sprout_shortcode', self::SHORTCODE, array( __CLASS__, 'dashboard' ) );

		if ( ! is_admin() ) {
			// Enqueue
			add_action( 'wp_enqueue_scripts', array( __CLASS__, 'register_resources' ) );
			add_action( 'wp_enqueue_scripts', array( __CLASS__, 'frontend_enqueue' ), 20 );
		}

		// client meta
		add_action( 'client_associated_user_list', array( __CLASS__, 'add_dashboard_link' ) );

		add_action( 'sc_client_user_listed', array( __CLASS__, 'add_dashboard_link_for_sprout_clients' ), 10, 2 );

	}


	/////////////////
	// Shortcodes //
	/////////////////

	public static function dashboard( $atts = array() ) {
		do_action( 'sprout_invoices_dashboard' );

		// User not logged in yet.
		if ( self::show_login_page() ) {
			do_action( 'sprout_invoices_dashboard_not_logged_in' );
			return self::login_form();
		}

		$user_id = 0;
		$valid_client_ids = self::validate_token();
		if ( isset( $_GET[ self::USER_QUERY_ARG ] ) && $valid_client_ids ) {
			$user_id = (int) $_GET[ self::USER_QUERY_ARG ];
			$client_ids = $valid_client_ids;
		} elseif ( is_user_logged_in() ) {
			$user_id = get_current_user_id();
		}

		if ( $user_id ) {
			if ( empty( $client_ids ) ) {
				$client_ids = SI_Client::get_clients_by_user( $user_id );
			}
			if ( ! empty( $client_ids ) ) {
				$view = '';
				// show a dashboard for each client associated.
				foreach ( $client_ids as $client_id ) {
					$view .= self::dashboard_view( $client_id, $atts );
				}
				return $view;
			}
		}
		// no client associated
		do_action( 'sprout_invoices_dashboard_not_client' );
		return self::blank_dashboard_view();
	}

	public static function login_form() {
		return self::load_addon_view_to_string( 'section/login-form', array(), true );
	}

	public static function dashboard_view( $client_id, $atts = array() ) {
		$client = SI_Client::get_instance( $client_id );
		if ( ! is_a( $client, 'SI_Client' ) ) {
			return;
		}

		$show_estimates = true;
		if ( isset( $atts['estimates'] ) && 'false' === $atts['estimates'] ) {
			$show_estimates = false;
		}

		$show_invoices = true;
		if ( isset( $atts['invoices'] ) && 'false' === $atts['invoices'] ) {
			$show_invoices = false;
		}

		wp_enqueue_script( 'si_client_dash' );
		wp_enqueue_style( 'si_client_dash' );

		return self::load_addon_view_to_string( 'shortcodes/dashboard', array(
			'client_id' => $client_id,
			'invoices' => $client->get_invoices(),
			'estimates' => $client->get_estimates(),
			'show_estimates' => $show_estimates,
			'show_invoices' => $show_invoices,
		), true );
	}

	public static function blank_dashboard_view() {
		return self::load_addon_view_to_string( 'shortcodes/dashboard-blank', array(), true );
	}

	////////////
	// Login //
	////////////

	public static function show_login_page() {
		return ( ! is_user_logged_in() && ! isset( $_GET[ self::CLIENT_TOKEN_QUERY_ARG ] ) );
	}

	public static function private_dashboard_url( $client_id = 0, $user_id = 0, $doc_id = 0 ) {
		$dashboard_page_id = self::get_dashboard_page_id();
		if ( ! $dashboard_page_id ) {
			return __( 'Error: No Dashboard Found (code: 002).', 'sprout-invoices' );
		}

		$url = get_the_permalink( $dashboard_page_id );

		if ( $client_id ) {
			$url = add_query_arg( array( self::CLIENT_TOKEN_QUERY_ARG => self::get_client_token( $client_id ) ), $url );
		}

		if ( $doc_id ) {
			$url = add_query_arg( array( self::DOC_QUERY_ARG => $doc_id ), $url );
		}

		if ( $user_id ) {
			$url = add_query_arg( array( self::USER_QUERY_ARG => $user_id ), $url );
		}

		return esc_url_raw( $url );
	}

	public static function get_client_token( $client_id = 0 ) {
		$client = SI_Client::get_instance( $client_id );
		$token = $client->get_post_meta( self::CLIENT_TOKEN_META );
		if ( ! $token ) {
			$token = self::set_client_token( $client_id );
		}
		return $token;
	}

	public static function set_client_token( $client_id = 0 ) {
		$client = SI_Client::get_instance( $client_id );
		$now = time();
		$token = md5( wp_json_encode( $client ).$now );
		$client->save_post_meta( array(
			self::CLIENT_TOKEN_META => $token,
			self::CLIENT_TOKEN_META.'_time' => $now, // future use for timeout
		) );
		return $token;
	}

	public static function validate_token() {
		$user_id = 0;
		if ( ! isset( $_REQUEST[ self::USER_QUERY_ARG ] ) || $_REQUEST[ self::USER_QUERY_ARG ] == '' ) {
			return false;
		}
		if ( ! isset( $_REQUEST[ self::CLIENT_TOKEN_QUERY_ARG ] ) || $_REQUEST[ self::CLIENT_TOKEN_QUERY_ARG ] == '' ) {
			return false;
		}

		$user_id = $_REQUEST[ self::USER_QUERY_ARG ];
		$token = $_REQUEST[ self::CLIENT_TOKEN_QUERY_ARG ];

		// Search for client with token.
		$clients = SI_Post_Type::find_by_meta( SI_Client::POST_TYPE, array( self::CLIENT_TOKEN_META => $token ) );

		if ( empty( $clients ) ) {
			return false;
		}

		foreach ( $clients as $client_id ) {
			$client = SI_Client::get_instance( $client_id );
			// confirm user id is associated still.
			if ( ! in_array( $user_id, $client->get_associated_users() ) ) {
				return false;
			}
		}
		return $clients;
	}

	///////////
	// Meta //
	///////////

	public static function add_dashboard_link( $user_id ) {
		$client_id = get_the_ID();
		echo '<a href="'.self::private_dashboard_url( $client_id, $user_id ).'" class="dash_link" target="_blank"><span class="dashicons dashicons-dashboard"></span></a>';
	}

	public static function add_dashboard_link_for_sprout_clients( $user_id = 0, $client_id = 0 ) {
		$url = self::private_dashboard_url( $client_id, $user_id );
		printf( '<span class="user_meta users_dashboardlink"><span class="dashicons dashicons-dashboard"></span> <a href="%1$s">%2$s...</a></span>', $url, substr( $url, 0, 60 ) );
	}

	//////////////
	// Enqueue //
	//////////////

	public static function register_resources() {
		wp_register_style( 'si_client_dash', SA_ADDON_CLIENT_DASH_URL . '/resources/front-end/css/si-dashboard.css', array(), self::SI_VERSION );
		wp_register_script( 'si_client_dash', SA_ADDON_CLIENT_DASH_URL . '/resources/front-end/js/si-dashboard.js', array( 'jquery' ), self::SI_VERSION );
	}

	public static function frontend_enqueue() {
		wp_enqueue_style( 'si_client_dash' );
		wp_enqueue_script( 'si_client_dash' );
	}


	//////////////
	// Utility //
	//////////////

	public static function get_dashboard_page_id() {
		// filter to prevent a search.
		$get_dashboard_page_id = apply_filters( 'si_client_dashboard_page_id', 0 );
		if ( $get_dashboard_page_id ) {
			return $get_dashboard_page_id;
		}
		$cache_key = 'dashboard_page_id';
		$cached_dashboard_page_id = get_transient( $cache_key );
		if ( $cached_dashboard_page_id ) {
			if ( get_post_type( $cached_dashboard_page_id ) == 'page' ) {
				return $cached_dashboard_page_id;
			}
		}
		$args = array(
			'post_type' => 'page',
			'fields' => 'ids',
			's' => '['.self::SHORTCODE.']',
			);
		$pages = get_posts( $args );
		if ( ! empty( $pages ) ) {
			$page_id = $pages[0];
		} else {
			$page_id = wp_insert_post( array(
				'post_status' => 'publish',
				'post_type' => 'page',
				'post_title' => __( 'Client Dashboard (auto generated)', 'sprout-invoices' ),
				'post_name' => 'client-dashboard',
				'post_content' => '['.self::SHORTCODE.']',
			) );
		}
		set_transient( $cache_key, $page_id );
		return $page_id;
	}

	public static function load_addon_view( $view, $args, $allow_theme_override = true ) {
		add_filter( 'si_views_path', array( __CLASS__, 'addons_view_path' ) );
		$view = self::load_view( $view, $args, $allow_theme_override );
		remove_filter( 'si_views_path', array( __CLASS__, 'addons_view_path' ) );
		return $view;
	}

	public static function load_addon_view_to_string( $view, $args, $allow_theme_override = true ) {
		ob_start();
		self::load_addon_view( $view, $args, $allow_theme_override );
		return ob_get_clean();
	}

	public static function addons_view_path() {
		return SA_ADDON_CLIENT_DASH_PATH . '/views/';
	}
}
