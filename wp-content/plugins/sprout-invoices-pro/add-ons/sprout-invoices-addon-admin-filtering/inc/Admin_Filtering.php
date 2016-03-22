<?php

/**
 * @package Sprout_Invoices
 * @subpackage Admin_Filtering
 */
class SI_Admin_Filtering extends SI_Controller {

	public static function init() {

		if ( is_admin() ) {
			// Invoice admin table
			add_action( 'restrict_manage_posts', array( __CLASS__, 'show_invoices_due' ) );
			add_action( 'query_vars', array( __CLASS__, 'filter_admin_table_register_qvs' ) );

			add_action( 'pre_get_posts', array( __CLASS__, 'filter_admin_table_results' ) );

			add_action( 'admin_footer-edit.php', array( __CLASS__, 'custom_bulk_admin_footer' ) );
			add_action( 'load-edit.php', array( __CLASS__, 'custom_bulk_action' ) );
			add_action( 'admin_notices', array( __CLASS__, 'custom_bulk_admin_notices' ) );
		}
	}

	////////////////////
	// Invoices Table //
	////////////////////

	public static function show_invoices_due() {
		global $typenow;
		if ( ! in_array( $typenow, array( SI_Invoice::POST_TYPE, SI_Estimate::POST_TYPE ) ) ) {
			return;
		}
		$post_type = $typenow;

		global $wpdb, $wp_locale;

		$meta_key = ( SI_Invoice::POST_TYPE === $post_type ) ? '_due_date' : '_expiration_date' ;
		$months = $wpdb->get_results( $wpdb->prepare( "
			SELECT DISTINCT YEAR( FROM_UNIXTIME( $wpdb->postmeta.meta_value ) ) AS year, MONTH( FROM_UNIXTIME( $wpdb->postmeta.meta_value ) ) AS month
			FROM $wpdb->posts 
			INNER JOIN $wpdb->postmeta ON ( $wpdb->posts.ID = $wpdb->postmeta.post_id ) WHERE 1=1 
			AND ( ( $wpdb->postmeta.meta_key = '%s' AND CAST( $wpdb->postmeta.meta_value AS CHAR ) BETWEEN '1' AND '%s' ) )
			AND $wpdb->posts.post_type = %s
			ORDER BY $wpdb->postmeta.meta_value DESC
		", $meta_key, current_time( 'timestamp' ), $post_type ) );

		// Due Dates filter
		$m = isset( $_GET['dm'] ) ? (int) $_GET['dm'] : 0;
		printf( '<label for="filter-by-duedate" class="screen-reader-text">%s</label>', __( 'Filter due date', 'sprout-invoices' ) );
		echo '<select name="dm" id="filter-by-duedate">';
		printf( '<option %s value="0">%s</option>', selected( $m, 0, false ), __( 'All due dates', 'sprout-invoices' ) );
		foreach ( $months as $arc_row ) {
			if ( 0 == $arc_row->year ) {
				continue; }

			$month = zeroise( $arc_row->month, 2 );
			$year = $arc_row->year;

			printf( "<option %s value='%s'>%s</option>\n",
				selected( $m, $year . $month, false ),
				esc_attr( $arc_row->year . $month ),
				/* translators: 1: month name, 2: 4-digit year */
				sprintf( __( '%1$s %2$d' ), $wp_locale->get_month( $month ), $year )
			);
		}
		echo '</select>';

		// Clients filter
		$args = array(
			'post_type' => SI_Client::POST_TYPE,
			'post_status' => 'publish',
			'posts_per_page' => -1,
			'fields' => 'ids',
		);
		$client_ids = get_posts( $args );
		$current_client_id = isset( $_GET['client_id'] ) ? (int) $_GET['client_id'] : 0;
		printf( '<label for="filter-by-client" class="screen-reader-text">%s</label>', __( 'Filter by client', 'sprout-invoices' ) );
		echo '<select name="client_id" id="filter-by-client">';
		printf( '<option %s value="0">%s</option>', selected( $current_client_id, 0, false ), __( 'All clients', 'sprout-invoices' ) );
		foreach ( $client_ids as $client_id ) {
			printf( "<option %s value='%s'>%s</option>\n",
				selected( $client_id, $current_client_id, false ),
				esc_attr( $client_id ),
				get_the_title( $client_id )
			);
		}
		echo '</select>';

	}

	public static function filter_admin_table_register_qvs( $query_vars ) {
		$query_vars[] = 'dm';
		$query_vars[] = 'client_id';
		return $query_vars;
	}

	public static function filter_admin_table_results( $query ) {
		if ( ! isset( $query->query['post_type'] ) ) {
			return;
		}
		if ( ! in_array( $query->query['post_type'], array( SI_Invoice::POST_TYPE, SI_Estimate::POST_TYPE ) ) ) {
			return;
		}
		$post_type = $query->query['post_type'];

		$due_date = $query->get( 'dm' );
		// only if this query is for those with a due date filter.
		if ( ! empty( $due_date ) ) {
			$month = substr( $due_date, 4, 2 );
			$year = substr( $due_date, 0, 4 );
			$last_day_of_month = date( 't', strtotime( $month . '/01/' . $year ) );

			global $wpdb;
			// Make sure to accommodate the other post__in queries along with.
			$posts_in = $query->get( 'post__in' );
			$and_posts_in = '';
			if ( ! empty( $posts_in ) ) {
				$and_posts_in = sprintf( "AND $wpdb->posts.ID IN ( %s )", implode( ',', array_map( 'absint', $posts_in ) ) );
			}
			// Get all post_ids between the due dates
			$meta_key = ( SI_Invoice::POST_TYPE === $post_type ) ? '_due_date' : '_expiration_date' ;
			$ids = $wpdb->get_col( $wpdb->prepare( "
				SELECT ID
				FROM $wpdb->posts 
				INNER JOIN $wpdb->postmeta ON ( $wpdb->posts.ID = $wpdb->postmeta.post_id ) WHERE 1=1 
				AND ( ( $wpdb->postmeta.meta_key = '%s' AND CAST( $wpdb->postmeta.meta_value AS CHAR ) BETWEEN '%s' AND '%s' ) )
				AND $wpdb->posts.post_type = %s
				ORDER BY $wpdb->postmeta.meta_value DESC
			", $meta_key, strtotime( $month . '/01/' . $year ), strtotime( $month . '/' . $last_day_of_month . '/' . $year ), $post_type ) );

			// If there are no results don't pass an empty array, otherwise WP will return all.
			if ( empty( $ids ) ) {
				$ids = array( 0 );
			}
			// Set to certain posts
			$query->set( 'post__in', $ids );
		}

		$client_id = $query->get( 'client_id' );
		// only if this query is for those with a due date filter.
		if ( ! empty( $client_id ) ) {
			global $wpdb;
			// Make sure to accommodate the other post__in queries along with.
			$posts_in = $query->get( 'post__in' );
			$and_posts_in = '';
			if ( ! empty( $posts_in ) ) {
				$and_posts_in = sprintf( "AND $wpdb->posts.ID IN ( %s )", implode( ',', array_map( 'absint', $posts_in ) ) );
			}
			// get all the post ids that are auto billable.
			$ids = $wpdb->get_col( $wpdb->prepare( "
				SELECT ID
				FROM $wpdb->posts
				INNER JOIN $wpdb->postmeta ON ( $wpdb->posts.ID = $wpdb->postmeta.post_id ) WHERE 1=1 
				AND ( ( $wpdb->postmeta.meta_key = '%s' AND CAST( $wpdb->postmeta.meta_value AS CHAR ) = %s ) )
				AND $wpdb->posts.post_type = %s
				$and_posts_in
				ORDER BY $wpdb->postmeta.meta_value DESC
			", '_client_id', $client_id, $post_type ) );

			// If there are no results don't pass an empty array, otherwise WP will return all.
			if ( empty( $ids ) ) {
				$ids = array( 0 );
			}
			// Set to certain posts
			$query->set( 'post__in', $ids );
		}
	}

	//////////////////
	// Bulk Actions //
	//////////////////

	public static function custom_bulk_admin_footer() {
		global $post_type;

		if ( in_array( $post_type, array( SI_Invoice::POST_TYPE, SI_Estimate::POST_TYPE ) ) ) {
			?>
				<script type="text/javascript">
					jQuery(document).ready(function() {
						jQuery('<option>').val('send').text('<?php _e( 'Send invoice', 'sprout-invoices' ) ?>').appendTo('select[name="action"]');
						jQuery('<option>').val('send').text('<?php _e( 'Send invoice', 'sprout-invoices' ) ?>').appendTo('select[name="action2"]');
					});
				</script>
			<?php
		}
	}

	public static function custom_bulk_action() {
		global $typenow;
		if ( ! in_array( $typenow, array( SI_Invoice::POST_TYPE, SI_Estimate::POST_TYPE ) ) ) {
			return;
		}
		$post_type = $typenow;

		// get the action
		$wp_list_table = _get_list_table( 'WP_Posts_List_Table' );
		$action = $wp_list_table->current_action();

		// allow only defined actions
		$allowed_actions = array( 'send' );
		if ( ! in_array( $action, $allowed_actions ) ) {
			return;
		}

		// security check
		check_admin_referer( 'bulk-posts' );

		// make sure ids are submitted.  depending on the resource type, this may be 'media' or 'ids'
		if ( isset( $_REQUEST['post'] ) ) {
			$post_ids = array_map( 'intval', $_REQUEST['post'] );
		}

		if ( empty( $post_ids ) ) {
			 return;
		}

		// this is based on wp-admin/edit.php
		$sendback = remove_query_arg( array( 'exported', 'untrashed', 'deleted', 'ids' ), wp_get_referer() );
		if ( ! $sendback ) {
			$sendback = admin_url( "edit.php?post_type=$post_type" );
		}

		$pagenum = $wp_list_table->get_pagenum();
		$sendback = add_query_arg( 'paged', $pagenum, $sendback );

		$result = self::bulk_send_notifications( $post_ids );

		$sendback = add_query_arg( array( 'success_action' => $action, 'ids' => join( ',', $post_ids ) ), $sendback );

		$sendback = remove_query_arg( array( 'action', 'paged', 'mode', 'action2', 'tags_input', 'post_author', 'comment_status', 'ping_status', '_status', 'post', 'bulk_edit', 'post_view' ), $sendback );

		wp_redirect( $sendback );
		exit();
	}

	public static function custom_bulk_admin_notices() {
		global $post_type, $pagenow;
		if ( 'edit.php' !== $pagenow || ! in_array( $post_type, array( SI_Invoice::POST_TYPE, SI_Estimate::POST_TYPE ) ) ) {
			return;
		}

		if ( isset( $_REQUEST['success_action'] ) ) {
			printf( '<div class="updated notice is-dismissible"><p>%s</p></div>', __( 'Notifications Sent', 'sprout-invoices' ) );
		}
	}

	public static function bulk_send_notifications( $doc_ids = array() ) {
		foreach ( $doc_ids as $doc_id ) {
			$doc = si_get_doc_object( $doc_id );
			$client = $doc->get_client();
			if ( ! is_a( $client, 'SI_Client' ) ) {
				continue;
			}
			$recipients = $client->get_associated_users();
			if ( empty( $recipients ) ) {
				continue;
			}
			if ( is_a( $doc, 'SI_Estimate' ) ) {
				do_action( 'send_estimate', $doc, $recipients );
			} elseif ( is_a( $doc, 'SI_Invoice' ) ) {
				do_action( 'send_invoice', $doc, $recipients );
				// If status is temp than change to pending.
				if ( $doc->get_status() === SI_Invoice::STATUS_TEMP ) {
					$doc->set_pending();
				}
			}
		}
	}
}
