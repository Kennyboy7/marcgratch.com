<?php

class Sprout_Engagements_Admin_Table extends Sprout_Engagements {

	public static function init() {

		if ( is_admin() ) {
			add_filter( 'manage_edit-'.Sprout_Engagement::POST_TYPE.'_columns', array( __CLASS__, 'register_columns' ) );

			add_action( 'manage_'.Sprout_Engagement::POST_TYPE.'_posts_custom_column', array( __CLASS__, 'column_display' ), 20, 2 );
			add_action( 'post_row_actions', array( __CLASS__, 'modify_row_actions' ), 10, 2 );
		}

	}

	/**
	 * Overload the columns for the invoice post type admin
	 *
	 * @param array   $columns
	 * @return array
	 */
	public static function register_columns( $original_columns ) {
		$columns['cb'] = $original_columns['cb'];
		$columns['title'] = __( 'Engagement' , 'sprout-invoices' );
		$columns['sc_engagement_type'] = __( 'Type' , 'sprout-invoices' );
		$columns['sc_engagement_status'] = __( 'Status' , 'sprout-invoices' );
		$columns['dates'] = __( 'Dates' , 'sprout-invoices' );
		$columns['contacts'] = __( 'Assigned Users' , 'sprout-invoices' );
		return $columns;
	}

	/**
	 * Display the content for the column
	 *
	 * @param string  $column_name
	 * @param int     $id          post_id
	 * @return string
	 */
	public static function column_display( $column_name, $id ) {
		$engagement = Sprout_Engagement::get_instance( $id );
		if ( ! is_a( $engagement, 'Sprout_Engagement' ) ) {
			return; // return for that temp post
		}

		switch ( $column_name ) {

			case 'sc_engagement_type':
				printf( '<div id="sc_type_select">%s</div>', sc_get_engagement_type_select( $id ) );
				break;

			case 'sc_engagement_status':
				printf( '<div id="sc_status_selections" class="clearfix">%s</div>',sc_get_engagement_status_select( $id, false ) );
				break;

			case 'dates':
				$start_time = $engagement->get_start_date();
				if ( $start_time < current_time( 'timestamp' ) ) {
					printf( __( '<p><b>Started</b> %s ago</p>', 'sprout-invoices' ), human_time_diff( current_time( 'timestamp' ), $start_time ) );
				} elseif ( $start_time ) {
					printf( '<p><b>%s</b> %s</p>', __( 'Starts on', 'sprout-invoices' ), date( get_option( 'date_format' ), $start_time ) );
				}

				$end_time = $engagement->get_end_date();
				if ( $end_time > current_time( 'timestamp' ) ) {
					printf( '<p style="color:red;"><b>%s</b> %s</p>', __( 'Ends in', 'sprout-invoices' ), human_time_diff( current_time( 'timestamp' ), $end_time ) );
				} elseif ( $end_time ) {
					printf( __( '<p><b>Ended</b> on %s</p>', 'sprout-invoices' ), date( get_option( 'date_format' ), $end_time ) );
				}
				break;

			case 'contacts':
				$associated_users = $engagement->get_assigned_users();
				echo '<p>';
				printf( '<b>%s</b>: ', sc__( 'Assigned' ) );
				if ( ! empty( $associated_users ) ) {
					$users_print = array();
					foreach ( $associated_users as $user_id ) {
						$user = get_userdata( $user_id );
						if ( ! is_a( $user, 'WP_User' ) ) {
							$client->remove_associated_user( $user_id );
							continue;
						}
						$users_print[] = sprintf( '<span class="associated_user"><a href="%s">%s</a></span>', get_edit_user_link( $user_id ), sc_get_users_full_name( $user_id ) );
					}
				}
				if ( ! empty( $users_print ) ) {
					print implode( ', ', $users_print );
				} else {
					sc_e( 'No-one assigned.' );
				}
				echo '</p>';
				break;

			default:
				// code...
			break;
		}

	}

	/**
	 * Filter the array of row action links below the title.
	 *
	 * @param array   $actions An array of row action links.
	 * @param WP_Post $post    The post object.
	 */
	public static function modify_row_actions( $actions = array(), $post = array() ) {
		return $actions;
	}
}
