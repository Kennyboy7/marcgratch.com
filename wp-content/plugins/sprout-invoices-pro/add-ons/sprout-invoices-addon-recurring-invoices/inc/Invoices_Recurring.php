<?php

/**
 * Time_Tracking Controller
 *
 * @package Sprout_Invoice
 * @subpackage Time_Tracking
 */
class SI_Invoices_Recurring extends SI_Controller {

	private static $meta_keys = array(
		'is_recurring' => '_is_recurring_invoice', // bool
		'start_time' => '_recurring_start_time', // int
		'frequency' => '_recurring_option', // string
		'frequency_custom' => '_recurring_custom_days', // int
		'clone_time' => '_next_clone_time', // int
		'cloned_from' => '_cloned_parent_invoice_id', // int
		'duration' => '_time_before_stop', // int
	);

	public static function init() {

		if ( is_admin() ) {
			// Meta boxes
			add_action( 'admin_init', array( __CLASS__, 'register_meta_boxes' ) );

			// Enqueue
			add_action( 'admin_enqueue_scripts', array( __CLASS__, 'register_resources' ) );
			add_action( 'admin_enqueue_scripts', array( __CLASS__, 'admin_enqueue' ), 20 );

			add_action( 'si_estimate_acceptance_fields', array( __CLASS__, 'add_estimate_acceptance_meta_fields' ) );
			add_action( 'save_estimate_acceptance_meta', array( __CLASS__, 'save_estimate_acceptance_meta' ) );
		}

		add_action( self::CRON_HOOK, array( __CLASS__, 'maybe_create_new_invoices' ) );

		add_action( 'si_client_dashboard_invoice_info_row', array( __CLASS__, 'add_recurring_invoice_info' ) );

	}

	public static function add_recurring_invoice_info( $invoice ) {
		$invoice_id = $invoice->get_id();
		$start_date = self::get_start_time( $invoice_id );
		$cloned_id = self::get_cloned( $invoice_id );
		if ( ! self::is_recurring( $invoice_id ) && ! $cloned_id ) {
			return;
		}
		if ( ! $cloned_id ) {
			printf( __( '<small>The billing agreement started on <em>%s</em>.</small>', 'sprout-invoices' ), date_i18n( get_option( 'date_format' ), $start_date ) );
		} else {
			$start_date = self::get_start_time( $cloned_id );
			printf( __( '<small>Invoice generated from a billing agreement started on <em>%s</em>.</small>', 'sprout-invoices' ), date_i18n( get_option( 'date_format' ), $start_date ) );
		}
		echo '<style type="text/css">.estimate_info_row_wrap { display: none; }</style>';
	}

	//////////////
	// Enqueue //
	//////////////

	public static function register_resources() {
		// admin js
		wp_register_script( 'si_recurring_invoices', SA_ADDON_RECURRING_INVOICES_URL . '/resources/admin/js/recurring_invoices.js', array( 'jquery' ), self::SI_VERSION );
	}

	public static function admin_enqueue() {
		wp_enqueue_script( 'si_recurring_invoices' );
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
			'si_recurring_invoices' => array(
				'title' => __( 'Recurring Invoice Creation', 'sprout-invoices' ),
				'show_callback' => array( __CLASS__, 'show_recurring_invoices_meta_box' ),
				'save_callback' => array( __CLASS__, 'save_meta_box_recurring_invoices' ),
				'context' => 'normal',
				'priority' => 'low',
				'weight' => 0,
				'save_priority' => 0,
			),
		);
		do_action( 'sprout_meta_box', $args, SI_Invoice::POST_TYPE );
	}

	/**
	 * Show time tracking metabox.
	 * @param  WP_Post $post
	 * @param  array $metabox
	 * @return
	 */
	public static function show_recurring_invoices_meta_box( $post, $metabox ) {
		$invoice = SI_Invoice::get_instance( $post->ID );

		$cloned_id = self::get_cloned( $post->ID );
		if ( $cloned_id ) {
			printf( __( 'This invoice was created from a recurring invoice: <a href="%s">%s</a>', 'sprout-invoices' ), get_edit_post_link( $cloned_id ), get_the_title( $cloned_id ) );
			return;
		}

		self::load_addon_view( 'admin/meta-boxes/invoices/recurring', array(
				'fields' => self::recurring_options( $invoice ),
				'next_time' => date_i18n( 'Y-m-d', self::get_clone_time( $post->ID ) ),
				'invoice_id' => $invoice->get_id(),
				'duration' => self::get_duration( $post->ID ),
				'children' => self::get_child_clones( $post->ID ),
		), false );

	}

	public static function save_meta_box_recurring_invoices( $post_id, $post, $callback_args, $invoice_id = null ) {

		self::set_as_not_recurring( $post_id );

		$start_time = ( isset( $_POST['sa_recurring_invoice_start_time'] ) ) ? strtotime( $_POST['sa_recurring_invoice_start_time'] ) : current_time( 'time_stamp' );
		self::set_start_time( $post_id, $start_time );

		$frequency = ( isset( $_POST['sa_recurring_invoice_frequency'] ) ) ? $_POST['sa_recurring_invoice_frequency'] : '' ;
		self::set_frequency( $post_id, $frequency );

		$frequency_days = ( isset( $_POST['sa_recurring_invoice_custom_freq'] ) ) ? $_POST['sa_recurring_invoice_custom_freq'] : '' ;
		self::set_frequency_custom( $post_id, $frequency_days );

		$duration = ( isset( $_POST['sa_recurring_invoice_duration'] ) ) ? $_POST['sa_recurring_invoice_duration'] : 0 ;
		self::set_duration( $post_id, $duration );

		if ( isset( $_POST['sa_recurring_invoice_is_recurring'] ) && $_POST['sa_recurring_invoice_is_recurring'] ) {
			self::set_recurring( $post_id );

			// This must come last since it uses saved meta
			self::set_clone_time( $post_id );
		}
	}

	public static function recurring_options( $doc ) {
		$fields = array();

		$is_recurring = self::is_recurring( $doc->get_id() );

		$start_meta = self::get_start_time( $doc->get_id() );
		$start = ( $start_meta ) ? $start_meta : current_time( 'timestamp' );

		$frequency_meta = self::get_frequency( $doc->get_id() );
		$frequency = ( $frequency_meta ) ? $frequency_meta : 'monthly' ;

		$custom_freq_meta = self::get_frequency_custom( $doc->get_id() );
		$custom_freq = ( $custom_freq_meta ) ? $custom_freq_meta : 15 ;

		$duration_meta = self::get_duration( $doc->get_id() );
		$duration = ( $duration_meta ) ? $duration_meta : 0 ;

		$is_recurring_desc = __( 'Check if this invoice should be recurring and be cloned on the frequency below.', 'sprout-invoices' );
		$clone_time = self::get_clone_time( $doc->get_id() );
		if ( $clone_time ) {
			$is_recurring_desc = sprintf( 'The next invoice will be generated on <code>%s</code>.', date_i18n( get_option( 'date_format' ), $clone_time ) );
		}
		$fields['is_recurring'] = array(
			'weight' => 100,
			'label' => __( 'Recurring Invoice', 'sprout-invoices' ),
			'type' => 'checkbox',
			'default' => $is_recurring,
			'value' => '1',
			'description' => $is_recurring_desc,
		);

		$fields['duration'] = array(
			'weight' => 102,
			'label' => __( 'Duration', 'sprout-invoices' ),
			'type' => 'number',
			'default' => $duration,
			'description' => __( 'Times a new invoice should be created. 0 is unlimited.', 'sprout-invoices' ),
		);

		$fields['start_time'] = array(
			'weight' => 105,
			'label' => __( 'Start Date', 'sprout-invoices' ),
			'type' => 'date',
			'default' => date_i18n( 'Y-m-d', $start ),
		);

		$fields['frequency'] = array(
			'weight' => 110,
			'label' => __( 'Frequency', 'sprout-invoices' ),
			'type' => 'select',
			'options' => array(
					'weekly' => __( 'Weekly', 'sprout-invoices' ),
					'monthly' => __( 'Monthly', 'sprout-invoices' ),
					'quarterly' => __( 'Quarterly', 'sprout-invoices' ),
					'yearly' => __( 'Yearly', 'sprout-invoices' ),
					'custom' => __( 'Custom', 'sprout-invoices' ),
				),
			'default' => $frequency,
		);

		$day_option_input = '<input type="number" name="sa_recurring_invoice_custom_freq" id="sa_recurring_invoice_custom_freq" class="small-input" placeholder="10" max="364" size="4" value="'.$custom_freq.'">';

		$fields['custom_freq'] = array(
			'weight' => 110.1,
			'label' => '',
			'type' => 'bypass',
			'output' => sprintf( __( 'Created Every %s Days', 'sprout-invoices' ), $day_option_input ),
		);

		$fields = apply_filters( 'si_recurring_invoice_submission_fields', $fields );
		uasort( $fields, array( __CLASS__, 'sort_by_weight' ) );
		return $fields;
	}


	///////////
	// Meta //
	///////////

	/**
	 * Is Recurring
	 */
	public static function is_recurring( $doc_id = 0 ) {
		$doc = si_get_doc_object( $doc_id );
		if ( ! is_object( $doc ) ) {
			return 0;
		}
		$bool = $doc->get_post_meta( self::$meta_keys['is_recurring'] );
		if ( $bool != 1 ) {
			$bool = false;
		}
		return $bool;
	}

	public static function set_recurring( $doc_id = 0 ) {
		$doc = si_get_doc_object( $doc_id );
		if ( ! is_object( $doc ) ) {
			return 0;
		}
		$doc->save_post_meta( array(
			self::$meta_keys['is_recurring'] => 1,
		) );
		return 1;
	}

	public static function set_as_not_recurring( $doc_id = 0 ) {
		$doc = si_get_doc_object( $doc_id );
		if ( ! is_object( $doc ) ) {
			return 0;
		}
		$doc->save_post_meta( array(
			self::$meta_keys['is_recurring'] => 0,
		) );
		return 1;
	}

	/**
	 * Issue date
	 */
	public static function get_start_time( $doc_id = 0 ) {
		$doc = si_get_doc_object( $doc_id );
		if ( ! is_object( $doc ) ) {
			return 0;
		}
		$time = (int) $doc->get_post_meta( self::$meta_keys['start_time'] );
		return $time;
	}

	public static function set_start_time( $doc_id = 0, $start_time = 0 ) {
		$doc = si_get_doc_object( $doc_id );
		if ( ! is_object( $doc ) ) {
			return 0;
		}
		$doc->save_post_meta( array(
			self::$meta_keys['start_time'] => $start_time,
		) );
		return $start_time;
	}

	/**
	 * Start Time
	 */
	public static function get_frequency( $doc_id = 0 ) {
		$doc = si_get_doc_object( $doc_id );
		if ( ! is_object( $doc ) ) {
			return 0;
		}
		$freq = $doc->get_post_meta( self::$meta_keys['frequency'] );
		return $freq;
	}

	public static function set_frequency( $doc_id = 0, $frequency = '' ) {
		$doc = si_get_doc_object( $doc_id );
		if ( ! is_object( $doc ) ) {
			return 0;
		}
		$doc->save_post_meta( array(
			self::$meta_keys['frequency'] => $frequency,
		) );
		return $frequency;
	}

	/**
	 * Frequency in Days
	 */
	public static function get_frequency_custom( $doc_id = 0 ) {
		$doc = si_get_doc_object( $doc_id );
		if ( ! is_object( $doc ) ) {
			return 0;
		}
		$days = (int) $doc->get_post_meta( self::$meta_keys['frequency_custom'] );
		return $days;
	}

	public static function set_frequency_custom( $doc_id = 0, $days = 0 ) {
		$doc = si_get_doc_object( $doc_id );
		if ( ! is_object( $doc ) ) {
			return 0;
		}
		$doc->save_post_meta( array(
			self::$meta_keys['frequency_custom'] => $days,
		) );
		return $days;
	}

	/**
	 * Total times
	 */
	public static function get_duration( $doc_id = 0 ) {
		$doc = si_get_doc_object( $doc_id );
		if ( ! is_object( $doc ) ) {
			return 0;
		}
		$duration = (int) $doc->get_post_meta( self::$meta_keys['duration'] );
		return $duration;
	}

	public static function set_duration( $doc_id = 0, $duration = 0 ) {
		$doc = si_get_doc_object( $doc_id );
		if ( ! is_object( $doc ) ) {
			return 0;
		}
		$doc->save_post_meta( array(
			self::$meta_keys['duration'] => (int) $duration,
		) );
		return $duration;
	}


	/**
	 * Issue date
	 */
	public static function was_cloned( $invoice_id = 0 ) {
		return self::get_cloned( $invoice_id );
	}

	public static function get_cloned( $invoice_id = 0 ) {
		$parent = 0;
		$invoice = SI_Invoice::get_instance( $invoice_id );
		if ( ! is_a( $invoice, 'SI_Invoice' ) ) {
			return 0;
		}
		$cloned_from = $invoice->get_post_meta( self::$meta_keys['cloned_from'] );
		if ( get_post_type( $cloned_from ) === SI_Invoice::POST_TYPE ) {
			$parent = $cloned_from;
		}
		return $parent;
	}

	public static function set_parent( $invoice_id = 0, $parent = 0 ) {
		$invoice = SI_Invoice::get_instance( $invoice_id );
		if ( ! is_a( $invoice, 'SI_Invoice' ) ) {
			return 0;
		}
		$invoice->save_post_meta( array(
			self::$meta_keys['cloned_from'] => $parent,
		) );
		return $parent;
	}

	/**
	 * The next time this invoice will be cloned
	 */
	public static function get_clone_time( $invoice_id = 0 ) {
		$invoice = SI_Invoice::get_instance( $invoice_id );
		if ( ! is_a( $invoice, 'SI_Invoice' ) ) {
			return 0;
		}
		if ( ! self::is_recurring( $invoice_id ) ) {
			return 0;
		}
		$time = (int) $invoice->get_post_meta( self::$meta_keys['clone_time'] );
		return $time;
	}

	public static function set_clone_time( $invoice_id = 0, $start_time = 0 ) {
		$invoice = SI_Invoice::get_instance( $invoice_id );
		if ( ! is_a( $invoice, 'SI_Invoice' ) ) {
			return 0;
		}
		if ( ! self::is_recurring( $invoice_id ) ) {
			$clone_time = 0;
		}

		if ( ! $start_time ) {
			$start_time = self::get_start_time( $invoice_id );
		}

		$frequency = self::get_frequency( $invoice_id );

		switch ( $frequency ) {
			case 'weekly':
				$clone_time = strtotime( '+1 Week', $start_time );
				break;
			case 'monthly':
				$clone_time = strtotime( '+1 Month', $start_time );
				break;
			case 'quarterly':
				$clone_time = strtotime( '+3 Month', $start_time );
				break;
			case 'yearly':
				$clone_time = strtotime( '+1 Year', $start_time );
				break;
			case 'custom':
				$days = self::get_frequency_custom( $invoice_id );
				$clone_time = strtotime( '+' . $days . 'days', $start_time );
				break;

			default:
				$clone_time = 0;
				break;
		}

		$invoice->save_post_meta( array(
			self::$meta_keys['clone_time'] => $clone_time,
		) );
		return $clone_time;
	}

	/////////////////////
	// Scheduled Task //
	/////////////////////

	public static function maybe_create_new_invoices() {
		$args = array(
			'post_type' => SI_Invoice::POST_TYPE,
			'post_status' => array_keys( SI_Invoice::get_statuses() ),
			'posts_per_page' => -1,
			'fields' => 'ids',
			'meta_query' => array(
				'relation' => 'AND',
				array(
					'key' => self::$meta_keys['is_recurring'],
					'value' => 1,
					'compare' => '=',
					),
				array(
					'key' => self::$meta_keys['clone_time'],
					'value' => array(
							strtotime( 'Last year' ),
							current_time( 'timestamp' ),
							),
					'compare' => 'BETWEEN',
					),
				array(
					'key' => self::$meta_keys['cloned_from'],
					'compare' => 'NOT EXISTS',
					)
				),
		);

		$invoice_ids = get_posts( $args );

		foreach ( $invoice_ids as $invoice_id ) {
			$duration = self::get_duration( $invoice_id );
			if ( 0 !== $duration ) {
				$duration_clones = count( self::get_child_clones( $invoice_id ) );
				if ( $duration_clones >= $duration ) {
					self::set_as_not_recurring( $invoice_id );
					continue;
				}
			}

			$cloned_post_id = self::clone_post( $invoice_id, SI_Invoice::STATUS_PENDING, SI_Invoice::POST_TYPE );

			// Issue date is today.
			$cloned_invoice = SI_Invoice::get_instance( $cloned_post_id );
			$cloned_invoice->set_issue_date( time() );

			// Due date is in the future
			$due_date = apply_filters( 'si_new_recurring_invoice_due_date_in_days', 14 );
			$cloned_invoice->set_due_date( time() + (60 * 60 * 24 * $due_date) );

			// adjust the clone time after the next invoice
			self::set_clone_time( $invoice_id, current_time( 'timestamp' ) );

			self::set_parent( $cloned_post_id, $invoice_id );
			do_action( 'si_recurring_invoice_created', $invoice_id, $cloned_post_id );
		}

	}

	/////////////////////////
	// Estimate Acceptance //
	/////////////////////////

	public static function add_estimate_acceptance_meta_fields( $fields = array() ) {
		$estimate = SI_Estimate::get_instance( get_the_id() );
		if ( ! is_a( $estimate, 'SI_Estimate' ) ) {
			return $fields;
		}
		$recurring_fields = self::recurring_options( $estimate );
		$recurring_fields['recurring_heading'] = array(
			'weight' => 99,
			'label' => __( 'Recurring Invoice Settings', 'sprout-invoices' ),
			'type' => 'heading',
		);
		$fields = array_merge( $recurring_fields, $fields );
		uasort( $fields, array( __CLASS__, 'sort_by_weight' ) );
		return $fields;
	}

	public static function save_estimate_acceptance_meta( $post_id = 0 ) {
		self::set_as_not_recurring( $post_id );

		$start_time = ( isset( $_POST['sa_estimate_acceptance_start_time'] ) ) ? strtotime( $_POST['sa_estimate_acceptance_start_time'] ) : current_time( 'time_stamp' );
		self::set_start_time( $post_id, $start_time );

		$frequency = ( isset( $_POST['sa_estimate_acceptance_frequency'] ) ) ? $_POST['sa_estimate_acceptance_frequency'] : '' ;
		self::set_frequency( $post_id, $frequency );

		$frequency_days = ( isset( $_POST['sa_estimate_acceptance_custom_freq'] ) ) ? $_POST['sa_estimate_acceptance_custom_freq'] : '' ;
		self::set_frequency_custom( $post_id, $frequency_days );

		if ( isset( $_POST['sa_estimate_acceptance_is_recurring'] ) && $_POST['sa_estimate_acceptance_is_recurring'] ) {
			self::set_recurring( $post_id );
		}
	}



	//////////////
	// Utility //
	//////////////

	public static function get_child_clones( $invoice_id = 0 ) {
		$invoice_ids = SI_Post_Type::find_by_meta( SI_Invoice::POST_TYPE, array( self::$meta_keys['cloned_from'] => $invoice_id ) );
		return $invoice_ids;
	}

	public static function load_addon_view( $view, $args, $allow_theme_override = true ) {
		add_filter( 'si_views_path', array( __CLASS__, 'addons_view_path' ) );
		$view = self::load_view( $view, $args, $allow_theme_override );
		remove_filter( 'si_views_path', array( __CLASS__, 'addons_view_path' ) );
		return $view;
	}

	public static function addons_view_path() {
		return SA_ADDON_RECURRING_INVOICES_PATH . '/views/';
	}
}
