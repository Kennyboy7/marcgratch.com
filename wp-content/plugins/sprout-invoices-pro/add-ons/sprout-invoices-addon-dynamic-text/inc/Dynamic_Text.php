<?php

/**
 * SI_Dynamic_Text Controller
 *
 * @package Sprout_Invoice
 * @subpackage Dynamic_Text
 */
class SI_Dynamic_Text extends SI_Controller {
	const DYN_TEXT_SHORTCODE = 'si';
	const DYN_TEXT_SHORTCODE_MONTH = 'si_month';
	const DYN_TEXT_SHORTCODE_YEAR = 'si_year';
	const DYN_TEXT_SHORTCODE_QUARTER = 'si_quarter';

	public static function init() {

		// master shortcode
		do_action( 'sprout_shortcode', self::DYN_TEXT_SHORTCODE, array( __CLASS__, 'handle_short_code' ), 10 );

		// Individual shortcodes
		do_action( 'sprout_shortcode', self::DYN_TEXT_SHORTCODE_MONTH, array( __CLASS__, 'month_short_code' ), 10 );
		do_action( 'sprout_shortcode', self::DYN_TEXT_SHORTCODE_YEAR, array( __CLASS__, 'year_short_code' ), 10 );
		do_action( 'sprout_shortcode', self::DYN_TEXT_SHORTCODE_QUARTER, array( __CLASS__, 'quarter_short_code' ), 10 );

		// add_filter( 'si_line_item_content', array( __CLASS__, 'maybe_convert_dynamic_text' ) );
	}

	public static function handle_short_code( $atts = array(), $content = '' ) {
		if ( ! isset( $atts['type'] ) ) {
			return __( 'Missing shortcode type: e.g. [' .self::DYN_TEXT_SHORTCODE. ' type="month" offset="+1"]', 'sprout-invoices' );
		}

		$content = '';
		switch ( $atts['type'] ) {
			case 'month':
				$content = self::month_short_code( $atts );
				break;
			case 'year':
				$content = self::year_short_code( $atts );
				break;
			case 'quarter':
				$content = self::quarter_short_code( $atts );
				break;
			case 'due_date':
			case 'due':
			case 'expiration':
				$content = self::due_date( $atts );
				break;
			case 'invoice_id':
			case 'estimate_id':
			case 'id':
				$content = self::id( $atts );
				break;
			case 'discount':
				$content = self::discount( $atts );
				break;
			case 'client_name':
				$content = self::client_name( $atts );
				break;
			case 'client_username':
				$content = self::username( $atts );
				break;

			default:
				break;
		}
		return $content;
	}

	public static function month_short_code( $atts = array() ) {
		$time = time();
		if ( si_get_doc_context() == 'estimate' ) {
			$time = si_get_estimate_issue_date();
		} else {
			$time = si_get_invoice_issue_date();
		}
		if ( isset( $atts['offset'] ) ) {
			$offset = preg_replace( '/[^\d-+]/', '', $atts['offset'] );
			$time = strtotime( $offset.' month', $time );
		}
		return date_i18n( 'F', $time );
	}

	public static function year_short_code( $atts = array() ) {
		$time = time();
		if ( si_get_doc_context() == 'estimate' ) {
			$time = si_get_estimate_issue_date();
		} else {
			$time = si_get_invoice_issue_date();
		}
		if ( isset( $atts['offset'] ) ) {
			$offset = preg_replace( '/[^\d-+]/', '', $atts['offset'] );
			$time = strtotime( $offset.' year', $time );
		}
		return date_i18n( 'Y', $time );
	}

	public static function quarter_short_code( $atts = array() ) {
		$time = time();
		if ( si_get_doc_context() == 'estimate' ) {
			$time = si_get_estimate_issue_date();
		} else {
			$time = si_get_invoice_issue_date();
		}
		if ( isset( $atts['offset'] ) ) {
			$offset = ceil( preg_replace( '/[^\d-+]/', '', $atts['offset'] ) * 3 );
			$time = strtotime( $offset.' months', $time );
		}
		return 'Q' . ceil( date( 'm', $time ) / 3 );
	}

	public static function due_date( $atts = array() ) {
		$time = time() * 60 * 60 * 24 * 7;
		if ( si_get_doc_context() == 'estimate' ) {
			$time = si_get_estimate_expiration_date();
		} else {
			$time = si_get_invoice_due_date();
		}
		return date_i18n( 'Y', $time );
	}

	public static function id( $atts = array() ) {
		$id = 0;
		if ( si_get_doc_context() == 'estimate' ) {
			$id = si_get_estimate_id();
		} else {
			$id = si_get_invoice_id();
		}
		return $id;
	}

	public static function discount( $atts = array() ) {
		$discount = 0;
		if ( si_get_doc_context() == 'estimate' ) {
			$discount = si_get_estimate_subtotal() * ( si_get_estimate_discount() / 100 );
		} else {
			$discount = si_get_invoice_subtotal() * ( si_get_invoice_discount() / 100 );
		}
		return sa_get_formatted_money( $discount );
	}

	public static function client_name( $atts = array() ) {
		$id = 0;
		if ( si_get_doc_context() == 'estimate' ) {
			$id = si_get_estimate_id();
		} else {
			$id = si_get_invoice_id();
		}
		$doc = si_get_doc_object( $id );
		if ( ! $doc->get_client_id() ) {
			return '';
		}
		$client = SI_Client::get_instance( $client_id );
		return $client->get_title();
	}

	public static function username( $atts = array() ) {
		$id = 0;
		if ( si_get_doc_context() == 'estimate' ) {
			$id = si_get_estimate_id();
		} else {
			$id = si_get_invoice_id();
		}
		$doc = si_get_doc_object( $id );
		if ( ! $doc->get_client_id() ) {
			return '';
		}
		$user = si_default_client_user( $client_id );
		if ( ! $user->exists() ) {
			return '';
		}
		return $user->display_name;
	}


	public static function maybe_convert_dynamic_text( $description = '' ) {
		return $description;
	}

	//////////////
	// Utility //
	//////////////

	public static function load_addon_view( $view, $args, $allow_theme_override = true ) {
		add_filter( 'si_views_path', array( __CLASS__, 'addons_view_path' ) );
		$view = self::load_view( $view, $args, $allow_theme_override );
		remove_filter( 'si_views_path', array( __CLASS__, 'addons_view_path' ) );
		return $view;
	}

	protected static function load_addon_view_to_string( $view, $args, $allow_theme_override = true ) {
		ob_start();
		self::load_addon_view( $view, $args, $allow_theme_override );
		return ob_get_clean();
	}

	public static function addons_view_path() {
		return SA_ADDON_DYNAMIC_TEXT_PATH . '/views/';
	}
}
