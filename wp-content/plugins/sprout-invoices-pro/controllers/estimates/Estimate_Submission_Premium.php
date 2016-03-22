<?php

/**
 * Estimates Controller
 *
 *
 * @package Sprout_Invoice
 * @subpackage Estimates
 */
class SI_Estimates_Submission_Premium extends SI_Estimate_Submissions {

	public static function init() {
		// Store options
		self::register_settings();

		// Processing
		add_action( 'parse_request', array( __CLASS__, 'maybe_process_form' ) );
	}

	///////////////
	// Settings //
	///////////////

	/**
	 * Hooked on init add the settings page and options.
	 *
	 */
	public static function register_settings() {
		// Settings
		$settings = array(
			'estimate_submissions' => array(
				'title' => __( 'Lead Generation', 'sprout-invoices' ),
				'weight' => 5,
				'tab' => 'settings',
				'callback' => array( __CLASS__, 'submission_settings_description' ),
				'settings' => array(
					'default_submission_page' => array(
						'label' => __( 'Default Submission Form', 'sprout-invoices' ),
						'option' => array(
							'type' => 'bypass',
							'output' => '<code>['.self::SUBMISSION_SHORTCODE.']Thank you![/'.self::SUBMISSION_SHORTCODE.']</code>',
							'description' => sprintf( __( 'To get you started, Sprout Invoices provides a <a href="%s" target="_blank">fully customizable form</a> for estimate submissions. Simply add this shortcode to a page and an estimate submission form will be available to prospective clients. Notifications will be sent for each submission and a new estimate (and client) will be generated.', 'sprout-invoices' ), 'https://sproutapps.co/support/knowledgebase/sprout-invoices/advanced/customize-estimate-submission-form/' ),
							),
						),
					'advanced_submission_integration_addon' => array(
						'label' => __( 'Gravity Forms and Ninja Forms Integration', 'sprout-invoices' ),
						'option' => array(
							'type' => 'bypass',
							'output' => self::advanced_form_integration_view(),
							'description' => sprintf( __( 'Instead of creating our own advanced form builder we\'ve integrated with the top WordPress form plugins. Make sure to read the <a href="%s" target="_blank">integration guide</a> to make the best use of your custom forms.', 'sprout-invoices' ), self::PLUGIN_URL.'/support/knowledgebase/sprout-invoices/advanced/customize-estimate-submission-form/' ),
							),
						),
					),
				),
			);
		do_action( 'sprout_settings', $settings, self::SETTINGS_PAGE );

		do_action( 'sprout_shortcode', self::SUBMISSION_SHORTCODE, array( __CLASS__, 'submission_form' ) );

	}

	public static function submission_settings_description() {
		printf( __( 'Estimate submissions is the start of the <a href="%s">Sprout Invoices workflow</a>.', 'sprout-invoices' ), self::PLUGIN_URL.'/sprout-invoices/' );
	}

	public static function advanced_form_integration_view() {
		// FUTURE pull add-on dynamically
		ob_start();
		?>
			<div class="sa_addon">
				<div class="add_on_img_wrap">
					<img class="sa_addon_img" src="<?php echo SI_RESOURCES . 'admin/img/gravity-ninja.png' ?>" />
					<a class="purchase_button button button-primary button-large" href="<?php echo self::PLUGIN_URL.'/marketplace/advanced-form-integration-gravity-ninja-forms/' ?>"><?php _e( '$0', 'sprout-invoices' ) ?></a>
				</div>
				<h4><?php _e( 'Advanced Form Integration with Gravity and Ninja Forms', 'sprout-invoices' ) ?></h4>
			</div>
		<?php
		return ob_get_clean();
	}

	//////////////////////
	// Submission form //
	//////////////////////

	public static function submission_form( $atts, $content = '' ) {
		// Don't show the form if it was successfully submitted
		if ( isset( $_GET[ self::SUBMISSION_SUCCESS_QV ] ) && $_GET[ self::SUBMISSION_SUCCESS_QV ] ) {
			return $content;
		}

		// Show the form
		return self::get_form();
	}

	public static function submission_form_fields() {
		$fields = array();
		$fields['title'] = array(
			'weight' => 10,
			'label' => __( 'Subject', 'sprout-invoices' ),
			'type' => 'text',
			'required' => true,
			'default' => '',
			'description' => __( 'Very brief synopsis of the proposed project.', 'sprout-invoices' ),
		);

		$fields['requirements'] = array(
			'weight' => 20,
			'label' => __( 'Project Requirements', 'sprout-invoices' ),
			'type' => 'textarea',
			'required' => true,
			'default' => '',
			'description' => __( 'The more detail the better.', 'sprout-invoices' ),
		);

		$fields['file'] = array(
			'weight' => 30,
			'label' => __( 'File', 'sprout-invoices' ),
			'type' => 'file',
			'required' => false,
			'default' => '',
			'description' => __( 'Upload a PDF or screenshots to help with your estimate requirements.', 'sprout-invoices' ),
		);

		$fields['state'] = array(
			'weight' => 40,
			'label' => __( 'Project State', 'sprout-invoices' ),
			'type' => 'select',
			'options' => array(
					'new' => __( 'New Project (planning stage)', 'sprout-invoices' ),
					'old' => __( 'Old site new project', 'sprout-invoices' ),
					'existing' => __( 'Existing Project', 'sprout-invoices' ),
					'other' => __( 'Other', 'sprout-invoices' ),
				),
			'required' => true,
			'default' => '',
			'description' => __( "Help us understand where you're at.", 'sprout-invoices' ),
		);

		$fields['delivery'] = array(
			'weight' => 50,
			'label' => __( 'Expected Delivery', 'sprout-invoices' ),
			'type' => 'select',
			'options' => array(
					'flexible' => __( 'I\'m flexible', 'sprout-invoices' ),
					'later' => __( '2-4 Months', 'sprout-invoices' ),
					'soon' => __( '1-2 Months', 'sprout-invoices' ),
					'now' => __( 'Much sooner', 'sprout-invoices' ),
				),
			'required' => true,
			'default' => '',
			'description' => __( "Help us understand where you're at.", 'sprout-invoices' ),
		);

		$fields['budget'] = array(
			'weight' => 70,
			'label' => __( 'Budget', 'sprout-invoices' ),
			'type' => 'text',
			'required' => false,
			'default' => '',
			'description' => __( 'This has little influence on estimates; this helps us better understand what you consider the scope of the project is and allows us to set appropriate expectations/solutions in our estimate.', 'sprout-invoices' ),
		);

		$fields['examples'] = array(
			'weight' => 80,
			'label' => __( 'Examples', 'sprout-invoices' ),
			'type' => 'textarea',
			'required' => false,
			'default' => '',
			'description' => __( 'Any example sites or items that may help.', 'sprout-invoices' ),
		);

		$fields['name'] = array(
			'weight' => 90,
			'label' => __( 'Your Name', 'sprout-invoices' ),
			'type' => 'text',
			'required' => true,
			'default' => '',
			'description' => __( 'The first and last name of the project owner.', 'sprout-invoices' ),
		);

		$fields['client_name'] = array(
			'weight' => 100,
			'label' => __( 'Company Name', 'sprout-invoices' ),
			'type' => 'text',
			'required' => true,
			'default' => '',
		);

		$fields['email'] = array(
			'weight' => 110,
			'label' => __( 'Email', 'sprout-invoices' ),
			'type' => 'text',
			'required' => true,
			'default' => '',
		);

		$fields['website'] = array(
			'weight' => 120,
			'label' => __( 'Website', 'sprout-invoices' ),
			'type' => 'text',
			'required' => true,
			'placeholder' => 'http://',
		);

		$fields[ self::DEFAULT_NONCE ] = array(
			'type' => 'hidden',
			'value' => wp_create_nonce( self::DEFAULT_NONCE ),
		);

		$fields = apply_filters( 'si_submission_form_fields', $fields );
		uasort( $fields, array( __CLASS__, 'sort_by_weight' ) );
		return $fields;
	}

	public static function get_form() {
		return self::load_view_to_string( 'templates/estimate/estimate-submission-form', array( 'fields' => self::submission_form_fields() ) );
	}

	//////////////////////
	// Form processing //
	//////////////////////

	public static function maybe_process_form() {
		$nonce_value = ( isset( $_REQUEST[ 'sa_estimate_' . self::DEFAULT_NONCE ] ) ) ? $_REQUEST[ 'sa_estimate_' . self::DEFAULT_NONCE ] : false ;
		if ( ! $nonce_value ) {
			return;
		}

		if ( ! wp_verify_nonce( $nonce_value, self::DEFAULT_NONCE ) ) {
			return;
		}

		do_action( 'si_process_estimate_submission' );
		$success = self::process_form_submission();
		if ( $success ) {
			self::set_message( __( 'Estimate Submitted.', 'sprout-invoices' ), self::MESSAGE_STATUS_INFO );
			wp_redirect( add_query_arg( self::SUBMISSION_SUCCESS_QV, true ), apply_filters( 'si_estimate_submitted_redirect_url', __return_null() ) );
			exit();
		}
	}

	public static function process_form_submission() {
		// Validate
		$errors = self::validate_estimate_submission_fields( $_REQUEST );

		// Check for errors
		if ( ! empty( $errors ) ) {
			foreach ( $errors as $error ) {
				self::set_message( $error, self::MESSAGE_STATUS_ERROR );
			}
			return false;
		}
		do_action( 'si_estimate_submission' );
		self::maybe_create_estimate();
		return true;
	}

	public static function validate_estimate_submission_fields( $submitted ) {
		$errors = array();
		$fields = self::submission_form_fields();
		foreach ( $fields as $key => $data ) {
			if ( isset( $data['required'] ) && $data['required'] && ! ( isset( $submitted[ 'sa_estimate_'.$key ] ) && $submitted[ 'sa_estimate_'.$key ] != '' ) ) {
				$errors[] = sprintf( __( '"%s" field is required.', 'sprout-invoices' ), $data['label'] );
			}
		}
		return apply_filters( 'si_validate_estimate_submission', $errors );
	}

	//////////////////////
	// Create Estimate //
	//////////////////////

	public static function maybe_create_estimate( $args = array() ) {
		// create array of fields
		if ( ! isset( $args['fields'] ) ) {
			$fields = self::submission_form_fields();
			foreach ( $fields as $key => $data ) {
				if ( isset( $_REQUEST[ 'sa_estimate_'.$key ] ) && $_REQUEST[ 'sa_estimate_'.$key ] != '' ) {
					$args['fields'][ $key ] = array( 'data' => $data, 'value' => $_REQUEST[ 'sa_estimate_'.$key ] );
				}
			}
		}
		if ( isset( $_REQUEST['sa_estimate_requirements'] ) ) {
			$args['line_items'] = array(
				array(
					'rate' => 0,
					'qty' => 1,
					'tax' => 0,
					'total' => 0,
					'desc' => esc_textarea( $_REQUEST['sa_estimate_requirements'] ),
				),
			);
		}

		$defaults = array(
			'subject' => isset( $_REQUEST['sa_estimate_title'] ) ? $_REQUEST['sa_estimate_title'] : sprintf( __( 'New Estimate: %s', 'sprout-invoices' ), date_i18n( get_option( 'date_format' ).' @ '.get_option( 'time_format' ), current_time( 'timestamp' ) ) ),
			'requirements' => isset( $_REQUEST['sa_estimate_requirements'] ) ? $_REQUEST['sa_estimate_requirements'] : __( 'No requirements submitted. Check to make sure the "requirements" field is required.', 'sprout-invoices' ),
			'fields' => ! empty( $args['fields'] ) ? $args['fields'] : $_REQUEST,
		);

		$parsed_args = wp_parse_args( $args, $defaults );

		// Create estimate
		$estimate_id = SI_Estimate::create_estimate( $parsed_args );

		// handle image uploads
		$estimate = SI_Estimate::get_instance( $estimate_id );
		if ( ! empty( $_FILES['sa_estimate_file'] ) ) {
			// Set the uploaded field as an attachment
			$estimate->set_attachement( $_FILES );
		}

		// TODO Set the solution type

		// End
		do_action( 'estimate_submitted', $estimate, $parsed_args );
	}
}
