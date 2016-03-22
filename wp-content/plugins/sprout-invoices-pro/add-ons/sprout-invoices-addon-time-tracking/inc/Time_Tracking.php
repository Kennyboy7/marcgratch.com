<?php

/**
 * Time_Tracking Controller
 *
 * @package Sprout_Invoice
 * @subpackage Time_Tracking
 */
class SI_Time_Tracking_Premium extends SI_Controller {
	const SUBMISSION_NONCE = 'si_time_submission';
	const IMPORT_QUERY_VAR = 'import-unbilled-time';
	const LINE_ITEM_TYPE = 'time';


	public static function init() {

		if ( is_admin() ) {
			// Meta boxes
			add_action( 'admin_init', array( __CLASS__, 'register_meta_boxes' ) );

			add_action( 'project_quick_links', array( __CLASS__, 'quick_link' ) );

			// Invoicing
			add_action( 'si_post_add_line_item', array( __CLASS__, 'import_billed_time' ) );
			add_action( 'si_save_line_items_meta_box', array( __CLASS__, 'mark_time_billed' ), 10, 3 );

			// AJAX
			add_action( 'wp_ajax_sa_get_time_item',  array( __CLASS__, 'maybe_get_item' ), 10, 0 );

			// Admin bar
			add_action( 'admin_bar_menu', array( __CLASS__, 'add_time_button' ), 62 );

			// Enqueue
			add_action( 'admin_enqueue_scripts', array( __CLASS__, 'register_resources' ) );
			add_action( 'admin_enqueue_scripts', array( __CLASS__, 'admin_enqueue' ), 20 );

			// Dash widgets
			add_action( 'si_dashboard_setup', array( __CLASS__, 'add_dashboard_widgets' ) );
			add_action( 'wp_dashboard_setup', array( __CLASS__, 'add_dashboard_widgets' ), 10, 0 );
		}

		// Add Time Type
		add_filter( 'si_line_item_types',  array( __CLASS__, 'add_time_line_item_type' ) );
		add_filter( 'si_line_item_columns',  array( __CLASS__, 'add_time_line_item_type_columns' ), -10, 2 );

		// ajax actions
		add_action( 'wp_ajax_sa_create_activity',  array( __CLASS__, 'maybe_create_time_activity' ), 10, 0 );
		add_action( 'wp_ajax_sa_save_time',  array( __CLASS__, 'maybe_save_time' ), 10, 0 );
		add_action( 'wp_ajax_sa_remove_time_entry',  array( __CLASS__, 'maybe_delete_time_entry' ), 10, 0 );
		add_action( 'wp_ajax_sa_remove_time',  array( __CLASS__, 'maybe_delete_time' ), 10, 0 );
		add_action( 'wp_ajax_sa_projects_time',  array( __CLASS__, 'projects_time' ), 10, 0 );
		// ajax views
		add_action( 'wp_ajax_sa_manage_time',  array( __CLASS__, 'time_admin_view' ), 10, 0 );
		add_action( 'wp_ajax_sa_time_creation',  array( __CLASS__, 'time_creation_view' ), 10, 0 );
		add_action( 'wp_ajax_sa_time_tracker',  array( __CLASS__, 'time_tracker_view' ), 10, 0 );
		add_action( 'wp_ajax_sa_time_entries_table',  array( __CLASS__, 'time_entries_table_view' ), 10, 0 );

		add_filter( 'si_admin_scripts_localization',  array( __CLASS__, 'ajax_l10n' ) );

		// front-end
		add_action( 'wp_ajax_sa_time_tracker_view',  array( __CLASS__, 'front_end_time_tracker_view' ), 10, 0 );
		add_action( 'wp_ajax_nopriv_sa_time_tracker_view',  array( __CLASS__, 'front_end_time_tracker_view' ), 10, 0 );
	}

	///////////////
	// Line Item //
	///////////////

	public static function add_time_line_item_type( $types = array() ) {
		$types = array_merge( $types, array( self::LINE_ITEM_TYPE => __( 'Time', 'sprout-invoices' ) ) );
		return $types;
	}

	public static function add_time_line_item_type_columns( $columns = array(), $type = '' ) {
		if ( self::LINE_ITEM_TYPE !== $type ) {
			return $columns;
		}
		$columns = array(
			'desc' => array(
					'label' => __( 'Time', 'sprout-invoices' ),
					'type' => 'textarea',
					'calc' => false,
					'hide_if_parent' => false,
					'weight' => 1,
				),
			'rate' => array(
					'label' => __( 'Rate', 'sprout-invoices' ),
					'type' => 'small-input',
					'placeholder' => '120',
					'calc' => false,
					'hide_if_parent' => true,
					'weight' => 5,
				),
			'qty' => array(
					'label' => __( 'Hours', 'sprout-invoices' ),
					'type' => 'small-input',
					'placeholder' => 1,
					'calc' => true,
					'hide_if_parent' => true,
					'weight' => 10,
				),
			'tax' => array(
					'label' => sprintf( '&#37; <span class="helptip" title="%s"></span>', __( 'A percentage adjustment per line item, i.e. tax or discount', 'sprout-invoices' ) ),
					'type' => 'small-input',
					'placeholder' => 0,
					'calc' => false,
					'hide_if_parent' => true,
					'weight' => 15,
				),
			'total' => array(
					'label' => __( 'Amount', 'sprout-invoices' ),
					'type' => 'total',
					'placeholder' => sa_get_formatted_money( 0 ),
					'calc' => true,
					'hide_if_parent' => false,
					'weight' => 50,
				),
			'sku' => array(
					'type' => 'hidden',
					'placeholder' => '',
					'calc' => false,
					'weight' => 50,
				),
			'time_id' => array(
					'type' => 'hidden',
					'placeholder' => '',
					'calc' => false,
					'weight' => 50,
				),
		);
		return $columns;
	}

	//////////////
	// Enqueue //
	//////////////

	public static function register_resources() {
		// admin js
		wp_register_script( 'si_time_tracking', SA_ADDON_TIME_TRACKING_URL . '/resources/admin/js/time_entry.js', array( 'jquery' ), self::SI_VERSION );
	}

	public static function admin_enqueue() {
		add_thickbox();
		wp_enqueue_script( 'si_time_tracking' );
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
			'si_project_timetracking' => array(
				'title' => __( 'Time Tracking', 'sprout-invoices' ),
				'show_callback' => array( __CLASS__, 'show_timetracking_meta_box' ),
				'save_callback' => array( __CLASS__, 'save_meta_box_time_tracking' ),
				'context' => 'normal',
				'priority' => 'high',
				'save_priority' => 0
			)
		);
		do_action( 'sprout_meta_box', $args, SI_Project::POST_TYPE );
	}

	public static function quick_link() {

	}

	/**
	 * Show time tracking metabox.
	 * @param  WP_Post $post
	 * @param  array $metabox
	 * @return
	 */
	public static function show_timetracking_meta_box( $post, $metabox ) {
		$project = SI_Project::get_instance( $post->ID );
		$times = $project->get_associated_times();
		self::load_addon_view( 'admin/meta-boxes/projects/time-entries', array(
				'times' => $times,
				'project_id' => $project->get_id()
			), true );

		// Form
		$fields = self::time_entry_fields( $post->ID, true );
		self::load_addon_view( 'admin/sections/time-tracker', array(
				'fields' => $fields,
				'project_id' => $project->get_id()
			), true );
	}

	public static function save_meta_box_time_tracking() {
		// Time tracking is done via AJAX
	}

	////////////////
	// Invoicing //
	////////////////

	public static function import_billed_time() {
		$invoice = SI_Invoice::get_instance( get_the_ID() );
		if ( ! is_a( $invoice, 'SI_Invoice' ) ) {
			return;
		}
		$client_id = $invoice->get_client_id();
		$project_id = $invoice->get_project_id();

		self::load_addon_view( 'admin/meta-boxes/invoices/time-invoicing.php', array(
				'invoice' => $invoice,
				'project_id' => $project_id,
				'client_id' => $client_id,
			), true );
	}

	/**
	 * Save the time id within the line item data array, then add the invoice id
	 * to the the time so it will be marked as billed.
	 * @param  int $invoice_id
	 * @param  array $post
	 * @param  object $invoice
	 * @return
	 */
	public static function mark_time_billed( $invoice_id, $post, $invoice ) {
		if ( ! is_a( $invoice, 'SI_Invoice' ) ) { // not estimates
			return;
		}

		// time to mark as billed
		$mark_time_billed = array();
		$line_items = $invoice->get_line_items();
		foreach ( $_POST['line_item_key'] as $key => $order ) {
			if ( isset( $_POST['line_item_desc'][$key] ) && $_POST['line_item_desc'][$key] != '' ) {
				if ( isset( $line_items[$order] ) ) {
					$line_items[$order]['time_id'] = ( isset( $_POST['line_item_time_id'][$key] ) && $_POST['line_item_time_id'][$key] != '' ) ? $_POST['line_item_time_id'][$key] : 0;
					if ( $line_items[$order]['time_id'] ) {
						$mark_time_billed[] = $line_items[$order]['time_id'];
					}
				}
			}
		}

		// Add the invoice id to the time's data
		foreach ( $mark_time_billed as $time_id ) {
			SI_Time::add_invoice_id( $time_id, $invoice_id );
		}
	}

	public static function add_time_button( WP_Admin_Bar $wp_admin_bar ) {

		if ( ! current_user_can( 'edit_sprout_invoices' ) ) {
			return;
		}

		// do not show on project page, since the view is inserted already.
		if ( get_the_id() && get_post_type( get_the_id() ) === SI_Project::POST_TYPE ) {
			return;
		}

		$args = array(
			'id'    => 'si_time_tracker',
			'title' => '<span class="dashicons dashicons-clock ab-icon"></span>',
			'href'	=> '#time_tracker',
			'meta'  => array( 'class' => 'time_tracker_popup' )
		);
		$wp_admin_bar->add_node( $args );

	}

	public static function maybe_get_item() {
		if ( ! current_user_can( 'publish_sprout_invoices' ) ) {
			wp_send_json_error( array( 'message' => __( 'User cannot create an item!', 'sprout-invoices' ) ) );
		}

		$time = array();
		if ( isset( $_REQUEST['time'] ) && is_array( $_REQUEST['time'] ) ) {
			$time = $_REQUEST['time'];
		}

		if ( ! $time ) {
			wp_send_json_error( array( 'message' => __( 'No time given!', 'sprout-invoices' ) ) );
		}

		$item_data = array(
				'type' => self::LINE_ITEM_TYPE,
				'desc' => $time['description'],
				'rate' => $time['activity_rate'],
				'qty' => $time['qty'],
				'tax' => $time['activity_tax'],
				'sku' => $time['activity_id'],
				'time_id' => $time['id'],
			);

		ob_start();
		SI_Controller::load_view( 'admin/sections/line-item-options', array(
			'columns' => SI_Line_Items::line_item_columns( 'time' ),
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


	///////////
	// Form //
	///////////

	public static function time_entry_fields( $context = 0, $hide_project_select = false  ) {
		$projects = array();
		$time_types = array();
		if ( ! $context ) {
			$context = get_the_id();
		}
		// Get associated projects based on context
		switch ( get_post_type( $context ) ) {
			case SI_Project::POST_TYPE:
				$project_id = $context;
				// Projects
				$projects = array( $project_id );
				break;
			case SI_Client::POST_TYPE:
				$client_id = $context;
				// Projects
				$projects = SI_Project::get_projects_by_client( $client_id );
				break;
			case SI_Invoice::POST_TYPE:
				$invoice_id = $context;
				// Projects
				$invoice = SI_Invoice::get_instance( $invoice_id );
				$project_id = $invoice->get_project();
				if ( $project_id ) {
					$projects = array( $project_id );
				}
				break;

			default:
				// Projects
				$args = array(
					'post_type' => SI_Project::POST_TYPE,
					'post_status' => 'any',
					'posts_per_page' => -1,
					'fields' => 'ids'
				);
				$projects = get_posts( $args );
				break;
		}

		$fields = array();

		$project_options = array();
		foreach ( $projects as $project_id ) {
			$title = get_the_title( $project_id );
			$title = ( $title == __( 'Auto Draft' ) ) ? __( 'Current Project', 'sprout-invoices' ) : $title ;
			$project_options[ $project_id ] = $title;
		}
		$fields['project_id'] = array(
				'weight' => 1,
				'label' => __( 'Project', 'sprout-invoices' ),
				'type' => 'select',
				'options' => $project_options,
			);

		$description = sprintf( __( 'Select an activity, <a href="%s">create a new activity</a> or <a class="thickbox" href="%s" title="Edit Activities">manage existing activities</a>.', 'sprout-invoices' ), 'javascript:void(0)" id="show_time_creation_modal"', admin_url( 'admin-ajax.php?action=sa_manage_time&width=750&height=450' ) );

		$time_types_options = SI_Time::get_activities();
		$fields['activity_id'] = array(
				'weight' => 10,
				'label' => __( 'Activity', 'sprout-invoices' ),
				'type' => 'select',
				'description' => $description,
				'options' => $time_types_options,
			);

		$fields['time_inc'] = array(
			'weight' => 20,
			'label' => __( 'Time', 'sprout-invoices' ),
			'type' => 'text',
			'description' => __( 'In hours, e.g. 1.25 for 75 minutes.', 'sprout-invoices' )
		);

		$fields['note'] = array(
			'weight' => 30,
			'label' => __( 'Note', 'sprout-invoices' ),
			'type' => 'textarea',
			'default' => '',
		);

		$fields['date'] = array(
			'weight' => 100,
			'label' => __( 'Date', 'sprout-invoices' ),
			'type' => 'date',
			'default' => date( 'Y-m-d', current_time( 'timestamp' ) ),
			'placeholder' => '',
		);

		$fields['nonce'] = array(
			'type' => 'hidden',
			'value' => wp_create_nonce( self::SUBMISSION_NONCE ),
			'weight' => 10000,
		);

		$fields = apply_filters( 'si_time_entry_form_fields', $fields );
		uasort( $fields, array( __CLASS__, 'sort_by_weight' ) );
		return $fields;
	}

	public static function time_creation_fields( $id = 0 ) {

		$fields['name'] = array(
			'weight' => 0,
			'label' => __( 'Activity Name', 'sprout-invoices' ),
			'type' => 'text',
		);

		$fields['billable'] = array(
			'weight' => 50,
			'label' => __( 'Billable', 'sprout-invoices' ),
			'type' => 'checkbox',
		);

		$fields['rate'] = array(
			'weight' => 10,
			'label' => __( 'Default Rate', 'sprout-invoices' ),
			'type' => 'text',
		);

		$fields['percentage'] = array(
			'weight' => 20,
			'label' => __( 'Default Percentage', 'sprout-invoices' ),
			'type' => 'text',
		);

		$fields['nonce'] = array(
			'type' => 'hidden',
			'value' => wp_create_nonce( self::SUBMISSION_NONCE ),
			'weight' => 10000,
		);

		$fields = apply_filters( 'si_time_creation_form_fields', $fields );
		uasort( $fields, array( __CLASS__, 'sort_by_weight' ) );
		return $fields;
	}

	/////////////////////
	// AJAX Callbacks //
	/////////////////////

	public static function maybe_create_time_activity() {
		if ( ! current_user_can( 'edit_sprout_invoices' ) ) {
			self::ajax_fail( 'User cannot create SI time!' );
		}

		$nonce = $_REQUEST['nonce'];
		if ( ! wp_verify_nonce( $nonce, self::SUBMISSION_NONCE ) ) {
			self::ajax_fail( 'Not going to fall for it!' ); }

		if ( ! isset( $_REQUEST['name'] ) || $_REQUEST['name'] == '' ) {
			self::ajax_fail( 'No name given.' );
		}

		$args = array();
		$args['name'] = $_REQUEST['name'];
		$args['billable'] = ( isset( $_REQUEST['billable'] ) ) ? true : false ;
		if ( isset( $_REQUEST['rate'] ) ) {
			$args['rate'] = (float) $_REQUEST['rate'];
		}
		if ( isset( $_REQUEST['percentage'] ) ) {
			$args['percentage'] = (float) $_REQUEST['percentage'];
		}
		$id = SI_Time::new_activity( $args );
		$time = SI_Time::get_instance( $id );

		$response = array(
				'id' => $id,
				'title' => get_the_title( $id ),
				'option_name' => $time->get_title(),
			);

		header( 'Content-type: application/json' );
		if ( self::DEBUG ) { header( 'Access-Control-Allow-Origin: *' ); }
		echo wp_json_encode( $response );
		exit();
	}

	public static function maybe_save_time() {

		$nonce = $_REQUEST['nonce'];
		if ( ! wp_verify_nonce( $nonce, self::SUBMISSION_NONCE ) ) {
			self::ajax_fail( 'Not going to fall for it!' );
		}

		if ( isset( $_REQUEST['project_id'] ) ) {
			$project_id = $_REQUEST['project_id'];
		}
		if ( ! $project_id ) {
			self::ajax_fail( 'No project id' );
		}

		if ( isset( $_REQUEST['time_val'] ) ) {
			$time_val = floatval( $_REQUEST['time_val'] );
		}
		if ( ! $time_val ) {
			self::ajax_fail( 'A time value is required.' );
		}

		$project = SI_Project::get_instance( $project_id );
		if ( ! is_a( $project, 'SI_Project' ) ) {
			self::ajax_fail( 'Project not found.' );
		}

		$args = array();
		$args['project_id'] = (int) $project_id;
		$args['time_val'] = (float) si_get_number_format( (float) $time_val );

		if ( isset( $_REQUEST['activity_id'] ) ) {
			$args['activity_id'] = (int) $_REQUEST['activity_id'];
		}

		if ( isset( $_REQUEST['note'] ) ) {
			$args['note'] = esc_textarea( $_REQUEST['note'] );
		}

		if ( isset( $_REQUEST['date'] ) ) {
			$args['date'] = (int) strtotime( $_REQUEST['date'] );
		}

		$defaults = array(
			'project_id' => (int) $project->get_id(),
			'activity_id' => (int) 0,
			'time_val' => (float) si_get_number_format( (float) 0 ),
			'note' => '',
			'date' => (int) current_time( 'timestamp' ),
			'user_id' => get_current_user_id(),
		);
		$data = wp_parse_args( $args, $defaults );

		$new_time_id = $project->create_associated_time( $data );
		do_action( 'si_time_created', $new_time_id );

		header( 'Content-type: application/json' );
		if ( self::DEBUG ) { header( 'Access-Control-Allow-Origin: *' ); }
		echo wp_json_encode( $data );
		exit();
	}

	public static function maybe_delete_time() {
		$nonce = $_REQUEST['nonce'];
		if ( ! wp_verify_nonce( $nonce, self::SUBMISSION_NONCE ) ) {
			self::ajax_fail( 'Not going to fall for it!' ); }

		if ( isset( $_REQUEST['id'] ) ) {
			$time_id = $_REQUEST['id'];
		}
		if ( ! $time_id ) {
			self::ajax_fail( 'No id given.' );
		}
		if ( get_post_type( $time_id ) != SI_Time::POST_TYPE ) {
			self::ajax_fail( 'Not an activity.' );
		}

		self::delete_time_entry( $time_id );

		echo true;
		exit();
	}

	public static function maybe_delete_time_entry() {
		$nonce = $_REQUEST['nonce'];
		if ( ! wp_verify_nonce( $nonce, self::SUBMISSION_NONCE ) ) {
			self::ajax_fail( 'Not going to fall for it!' ); }

		if ( isset( $_REQUEST['project_id'] ) ) {
			$project_id = $_REQUEST['project_id'];
		}
		if ( ! $project_id ) {
			self::ajax_fail( 'No project id' );
		}
		$project = SI_Project::get_instance( $project_id );

		if ( isset( $_REQUEST['id'] ) ) {
			$time_id = $_REQUEST['id'];
		}
		if ( ! $time_id ) {
			self::ajax_fail( 'No id given.' );
		}
		$time = SI_Record::get_instance( $time_id );

		$project->remove_time_associated( $time_id );

		do_action( 'si_time_deleted', $time_id );

		echo true;
		exit();
	}

	public static function projects_time() {
		$nonce = $_REQUEST['nonce'];
		if ( ! wp_verify_nonce( $nonce, self::SUBMISSION_NONCE ) ) {
			self::ajax_fail( 'Not going to fall for it!' ); }

		if ( isset( $_REQUEST['project_id'] ) ) {
			$project_id = $_REQUEST['project_id'];
		}
		if ( ! $project_id ) {
			self::ajax_fail( 'No project id' );
		}
		$project = SI_Project::get_instance( $project_id );
		if ( ! is_a( $project, 'SI_Project' ) ) {
			self::ajax_fail( 'Project not found' );
		}
		$times = $project->get_associated_times();

		$time_data = array();
		if ( ! empty( $times ) ) {
			foreach ( $times as $time_id ) {
				$time = SI_Record::get_instance( $time_id );
				if ( ! is_a( $time, 'SI_Record' ) ) {
					continue;
				}
				$activity = SI_Time::get_instance( $time->get_associate_id() );
				$data = $time->get_data();

				// If time is unbillable don't import
				// This includes not returning time that has already been invoiced.
				if ( isset( $_REQUEST['billable'] ) ) {
					if ( is_a( $activity, 'SI_Time' ) && ! $activity->is_billable() ) {
						continue;
					}
					// Don't return the time that has already been invoiced
					if ( isset( $data['invoice_id'] ) ) {
						continue;
					}
				}

				$description = ( is_a( $activity, 'SI_Time' ) ) ? '<b>' . get_the_title( $activity->get_id() ) . "</b>\n" . $time->get_title() . "\n<small>" . date_i18n( get_option( 'date_format' ), $data['date'] ) . '</small>' : $time->get_title() . "\n<small>" . date_i18n( get_option( 'date_format' ), $data['date'] ) . '</small>';
				$description = apply_filters( 'the_content', $description );

				$time_data[] = array(
					'id' => $time_id,
					'date' => date_i18n( get_option( 'date_format' ), $data['date'] ),
					'note' => apply_filters( 'the_content', $time->get_title() ),
					'qty' => si_get_number_format( (float) $data['time_val'] ),
					'description' => apply_filters( 'si_project_time_imported_description', $description ),
					'activity_id' => ( is_a( $activity, 'SI_Time' ) ) ? $activity->get_id() : false ,
					'activity' => ( is_a( $activity, 'SI_Time' ) ) ? $activity->get_title() : '' ,
					'activity_rate' => ( is_a( $activity, 'SI_Time' ) ) ? $activity->get_default_rate() : 0 ,
					'activity_tax' => ( is_a( $activity, 'SI_Time' ) ) ? $activity->get_default_percentage() : 0 ,
					);
			}
		}
		if ( empty( $time_data ) ) {
			self::ajax_fail( 'Nothing to import' );
		}
		header( 'Content-type: application/json' );
		if ( self::DEBUG ) { header( 'Access-Control-Allow-Origin: *' ); }
		echo wp_json_encode( $time_data );
		exit();

	}

	////////////////
	// AJAX View //
	////////////////

	public static function ajax_l10n( $js_object = array() ) {
		$js_object['time_creation_modal_title'] = __( 'Create Activity', 'sprout-invoices' );
		$js_object['time_creation_modal_url'] = admin_url( 'admin-ajax.php?action=sa_time_creation&height=300' );
		$js_object['time_tracker_modal_title'] = __( 'Track Time', 'sprout-invoices' );
		$js_object['time_tracker_modal_url'] = admin_url( 'admin-ajax.php?action=sa_time_tracker&height=450&width=600' );
		$js_object['time_tracker_success_message'] = __( 'Time saved!', 'sprout-invoices' );
		return $js_object;
	}

	public static function time_admin_view() {
		if ( ! current_user_can( 'edit_sprout_invoices' ) ) {
			self::ajax_fail( 'User cannot create new posts!' );
		}

		$args = array(
			'post_type' => SI_Time::POST_TYPE,
			'post_status' => 'any',
			'posts_per_page' => -1,
			'fields' => 'ids',
		);
		$time_types = get_posts( $args );
		self::load_addon_view( 'admin/sections/time-admin', array(
				'time' => $time_types,
			), true );
		exit();
	}

	public static function time_creation_view() {
		if ( ! current_user_can( 'edit_sprout_invoices' ) ) {
			self::ajax_fail( 'User cannot create new posts!' );
		}

		// Time creation
		$fields = self::time_creation_fields();
		self::load_addon_view( 'admin/sections/time-creation-form', array(
				'fields' => $fields,
			), true );
		exit();
	}

	/**
	 * Meta box view
	 * Abstracted to be called via AJAX
	 * @param int $project_id
	 *
	 */
	public static function time_tracker_view( $project_id = 0 ) {
		if ( ! current_user_can( 'edit_sprout_invoices' ) ) {
			self::ajax_fail( 'User cannot create new posts!' ); }

		if ( ! $project_id && isset( $_REQUEST['project_id'] ) ) {
			$project_id = $_REQUEST['project_id'];
		}

		$fields = self::time_entry_fields( $project_id );
		self::load_addon_view( 'admin/sections/time-tracker', array(
				'fields' => $fields,
			), true );
		exit();
	}

	/**
	 * Meta box view
	 * Abstracted to be called via AJAX
	 * @param int $project_id
	 *
	 */
	public static function front_end_time_tracker_view( $project_id = 0 ) {
		if ( ! current_user_can( 'edit_sprout_invoices' ) ) {
			wp_login_form();
			exit();
		}

		if ( ! $project_id && isset( $_REQUEST['project_id'] ) ) {
			$project_id = $_REQUEST['project_id'];
		}
		$fields = self::time_entry_fields( $project_id );
		unset( $fields['activity_id']['description'] );
		unset( $fields['time_inc']['description'] );
		$fields['date']['weight'] = 25;
		uasort( $fields, array( __CLASS__, 'sort_by_weight' ) );

		// enqueue
		wp_register_script( 'si_time_tracking', SA_ADDON_TIME_TRACKING_URL . '/resources/admin/js/time_entry.js', array( 'jquery' ), self::SI_VERSION );
		wp_enqueue_script( 'si_time_tracking' );
		add_filter( 'si_sprout_doc_scripts_localization',  array( 'SI_Time_Tracking_Premium', 'ajax_l10n' ) );
		wp_register_style( 'sprout_doc_style', SI_URL . '/resources/front-end/css/sprout-invoices.style.css', array( 'open-sans' ), self::SI_VERSION );
		wp_enqueue_style( 'sprout_doc_style' );

		self::load_addon_view( 'public/time-tracker', array(
				'fields' => $fields,
			), true );
		exit();
	}

	/**
	 * Meta box view
	 * Abstracted to be called via AJAX
	 * @param int $project_id
	 *
	 */
	public static function time_entries_table_view( $project_id = 0 ) {
		if ( ! current_user_can( 'edit_sprout_invoices' ) ) {
			self::ajax_fail( 'User cannot create new posts!' ); }

		if ( isset( $_REQUEST['project_id'] ) ) {
			$project_id = $_REQUEST['project_id'];
		}

		if ( ! $project_id ) {
			self::ajax_fail( 'No project id' );
		}

		$project = SI_Project::get_instance( $project_id );
		$times = $project->get_associated_times();
		self::load_addon_view( 'admin/meta-boxes/projects/time-entries', array(
				'times' => $times,
				'project_id' => $project->get_id()
			), true );
		exit();
	}

	//////////////
	// Widgets //
	//////////////

	public static function add_dashboard_widgets( $context = 'dashboard' ) {

		add_meta_box(
			'time_tracking',
			__( 'Time Tracking Dashboard', 'sprout-invoices' ),
			array( __CLASS__, 'time_tracking_dashboard' ),
			$context,
			'normal',
			'high'
		);

		if ( current_user_can( 'edit_sprout_invoices' ) ) {
			add_meta_box(
				'unbilled_time_tracking',
				__( 'Unbilled Time Dashboard', 'sprout-invoices' ),
				array( __CLASS__, 'unbilled_time_tracking_dashboard' ),
				$context,
				'normal',
				'high'
			);
		}

		add_meta_box(
			'time_tracker',
			__( 'Time Tracker', 'sprout-invoices' ),
			array( __CLASS__, 'time_tracker_dashboard' ),
			$context,
			'normal',
			'high'
		);

	}

	public static function time_tracking_dashboard() {
		$args = array(
			'post_type' => SI_Project::POST_TYPE,
			'post_status' => 'any',
			'posts_per_page' => 5,
			'fields' => 'ids',
			'orderby' => 'modified',
		);
		$projects = get_posts( $args );
		if ( empty( $projects ) ) {
			_e( 'No new projects.', 'sprout-invoices' );
			return;
		}
		$something_shown = false;
		foreach ( $projects as $project_id ) {
			$project = SI_Project::get_instance( $project_id );
			$times = $project->get_associated_times();
			if ( empty( $times ) ) {
				continue;
			}
			self::load_addon_view( 'admin/dashboards/project-time-tracking', array(
				'project' => $project,
				'times' => $times,
			), true );
			$something_shown = true;
		}
		if ( ! $something_shown ) {
			_e( 'No time tracked from your most recent projects.', 'sprout-invoices' );
		}
	}

	public static function unbilled_time_tracking_dashboard() {
		$args = array(
			'post_type' => SI_Project::POST_TYPE,
			'post_status' => 'any',
			'posts_per_page' => 3,
			'fields' => 'ids',
			'orderby' => 'modified',
		);
		$projects = get_posts( $args );
		if ( empty( $projects ) ) {
			_e( 'No new projects.', 'sprout-invoices' );
			return;
		}
		$something_shown = false;
		foreach ( $projects as $project_id ) {
			$project = SI_Project::get_instance( $project_id );
			$times = $project->get_associated_times();
			if ( empty( $times ) ) {
				continue;
			}
			self::load_addon_view( 'admin/dashboards/project-time-tracking-unbilled', array(
				'project' => $project,
				'times' => $times,
			), true );
			$something_shown = true;
		}
		if ( ! $something_shown ) {
			_e( 'No unbilled time from your most recent projects.', 'sprout-invoices' );
		}
	}

	public static function time_tracker_dashboard() {
		$fields = self::time_entry_fields( 0 );
		if ( ! current_user_can( 'edit_sprout_invoices' ) ) {
			unset( $fields['activity_id']['description'] );
		}
		self::load_addon_view( 'admin/dashboards/time-tracker', array(
				'fields' => $fields,
			), true );
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

	public static function addons_view_path() {
		return SA_ADDON_TIME_TRACKING_PATH . '/views/';
	}

	public static function delete_time_entry( $time_id = 0 ) {
		// records are normally deleted if their parent is deleted
		remove_action( 'deleted_post', array( 'SI_Internal_Records', 'attempt_associated_record_deletion' ) );
		// by removing this action the time entries will be newly associated
		// with the default activity instead via
		add_action( 'deleted_post', array( __CLASS__, 'attempt_reassign_entries_to_default' ) );

		wp_delete_post( $time_id, true );
	}

	public static function attempt_reassign_entries_to_default( $post_id = 0 ) {
		// prevent looping and checking if a record has a record associated with it.
		if ( get_post_type( $post_id ) !== SI_Record::POST_TYPE ) {
			global $wpdb;
			$parent_update = array( 'post_parent' => SI_Time::default_time() );
			$parent_where = array( 'post_parent' => $post_id, 'post_type' => SI_Record::POST_TYPE );
			$wpdb->update( $wpdb->posts, $parent_update, $parent_where );
		}
	}

}