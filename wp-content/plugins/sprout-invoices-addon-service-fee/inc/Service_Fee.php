<?php

/**
 * Time_Tracking Controller
 *
 * @package Sprout_Invoice
 * @subpackage Time_Tracking
 */
class SI_Service_Fee extends SI_Controller {
	const OPTION_PRE = 'si_service_fee_';

	public static function init() {

		// settings
		if ( is_admin() ) {
			add_action( 'init', array( __CLASS__, 'register_options' ) );
		}

		// remove tax2 option from doc admin
		add_action( 'doc_information_meta_box_last', array( __CLASS__, 'remove_tax_2_option' ) );

		// filter the line item totals
		add_filter( 'invoice_line_item_totals', array( __CLASS__, 'modify_line_item_totals' ), 10, 2 );

		// process payment but adjust fee/tax
		add_action( 'si_checkout_action_'.SI_Checkouts::PAYMENT_PAGE, array( __CLASS__, 'adjust_tax2' ), -100, 1 );

		// Stripe compatible
		add_filter( 'si_stripe_js_data_attributes', array( __CLASS__, 'adjust_stripe_total' ), 10, 2 );
	}

	public static function adjust_stripe_total( $data_attributes = array() ) {
		$invoice_id = get_the_id();
		$invoice = SI_Invoice::get_instance( $invoice_id );
		if ( si_has_invoice_deposit( $invoice->get_id() ) ) {
			return $data_attributes;
		}

		$service_fee = self::get_service_fee( 'SA_Stripe' );
		$calculated_total = floatval( $invoice->get_balance() * ( $service_fee / 100 ) );
		$payment_amount = (float) round( $invoice->get_balance() + $calculated_total, 2 );
		$payment_in_cents = ( round( $payment_amount, 2 ) * 100 );

		$data_attributes['amount'] = $payment_in_cents;
		return $data_attributes;
	}

	/**
	 * Hooked on init add the settings page and options.
	 *
	 */
	public static function register_options() {
		// Settings
		$settings = array(
			'si_service_fee_settings' => array(
				'title' => __( 'Service Fee Options', 'sprout-invoices' ),
				'weight' => 300,
				'tab' => SI_Payment_Processors::get_settings_page( false ),
				'settings' => array(),
				),
			);

		$payment_gateways = SI_Payment_Processors::get_registered_processors();
		foreach ( $payment_gateways as $class => $label ) {
			if ( 'SI_Checks' === $class ) {
				continue;
			}
			$settings['si_service_fee_settings']['settings'][ self::OPTION_PRE . $class ] = array(
					'label' => sprintf( __( '%s Service Fee', 'sprout-invoices' ), $label ),
						'option' => array(
							'type' => 'text',
							'default' => self::get_service_fee( $class ),
							'attributes' => array( 'class' => 'small-text' ),
							'description' => __( 'Percentage based on subtotal (before tax & discounts).' ),
						),
					);
		}
		do_action( 'sprout_settings', $settings, SI_Payment_Processors::SETTINGS_PAGE );
	}

	public static function get_service_fee( $class = '' ) {
		if ( is_object( $class ) ) {
			$class = get_class( $class );
		}
		$option = get_option( self::OPTION_PRE . $class, 0 );
		return $option;
	}

	public static function remove_tax_2_option() {
		?>
			<style type="text/css">
				[data-edit-id="tax2"] {
					visibility: hidden;
				}
			</style>
			<script type="text/javascript">
				jQuery(document).ready(function() {
					jQuery("body").find("[data-edit-id=tax2]").remove();
				});
			</script>
		<?php
	}

	public static function modify_line_item_totals( $totals = array(), $doc_id = 0 ) {
		$invoice = SI_Invoice::get_instance( $doc_id );

		$totals['taxes']['label'] = __( 'Tax', 'sprout-invoices' );
		$totals['taxes']['formatted'] = sa_get_formatted_money( $invoice->get_tax_total(), $doc_id, '<span class="money_amount">%s</span>' );

		$service_fees = array();
		$service_fee_description = '';
		$payment_gateways = SI_Payment_Processors::enabled_processors();
		foreach ( $payment_gateways as $class ) {
			if ( ! class_exists( $class ) ) {
				continue;
			}
			$service_fee = self::get_service_fee( $class );
			if ( $service_fee > 0.00 ) {
				$processor = call_user_func( array( $class, 'get_instance' ) );
				$service_fee_description .= sprintf( '<b>%s</b> - %s%%<br/>', $processor->public_name(), $service_fee );
				$service_fees[ $class ] = $service_fee;
			}
		}

		if ( empty( $service_fees ) ) {
			return $totals;
		}

		$invoice_service_fee = $invoice->get_tax2_total();

		$totals['service_fee'] = array(
				'label' => __( 'Service Fee', 'sprout-invoices' ),
				'value' => $invoice_service_fee,
				'formatted' => ( $invoice_service_fee > 0.00 ) ? sa_get_formatted_money( $invoice_service_fee, $doc_id, '<span class="money_amount">%s</span>' ) : __( '&nbsp;&nbsp;&nbsp;&nbsp;N/A', 'sprout-invoices' ),
				'helptip' => $service_fee_description,
				'admin_hide' => ( 0.01 > (float) $invoice_service_fee ),
				'weight' => 20,
			);

		if ( $invoice_service_fee > 0.00 && $invoice->get_payments_total() > 0.00 ) {
			unset( $totals['service_fee']['helptip'] );
		}

		return $totals;
	}

	public static function adjust_tax2( SI_Checkouts $checkout ) {
		$service_fee = self::get_service_fee( $checkout->get_processor() );
		$invoice = $checkout->get_invoice();
		$invoice->set_tax2( $service_fee );
	}
}
