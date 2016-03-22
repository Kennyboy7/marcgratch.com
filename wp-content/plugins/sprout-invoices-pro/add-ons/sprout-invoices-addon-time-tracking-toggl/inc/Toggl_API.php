<?php

/**
 * Time_Tracking_Toggl Controller
 *
 * @package Sprout_Invoice
 * @subpackage Time_Tracking_Toggl
 */
class Toggl_API extends Toggl_Controller {
	const API_URL = 'https://www.toggl.com/api/v8/';
	const WORKSPACES = 'si_toggl_workspaces';

	public static function init() {
		// nada
	}

	public static function get_default_workspace() {
		$workspace_id = Toggl_Settings::get_workspace_id();
		// if non is selected than check the API.
		if ( ! $workspace_id ) {
			$me = self::api_request( 'workspaces' );
			if ( empty( $me->data->workspaces ) ) {
				return 0;
			}
			// default workspace is the first
			$workspace_id = $me->data->workspaces[0]->id;
		}
		return $workspace_id;

	}

	public static function get_projects() {
		$projects = self::api_request( 'workspaces/' .self::get_default_workspace(). '/projects' );
		if ( ! is_array( $projects ) ) {
			$projects = array();
		}
		return $projects;
	}

	public static function get_workspaces() {
		$workspaces = self::api_request( 'workspaces' );
		return $workspaces;
	}

	public static function get_workspace_time( $pid = 0 ) {
		$args = array(
				'workspace_id' => self::get_default_workspace(),
				'project_ids' => ( $pid ) ? $pid : '',
			);
		$time = self::detail_api_request( $args );
		return $time;
	}

	public static function api_request( $endpoint = 'projects', $post_data = array() ) {
		// Make sure there's a workspace id added.
		if ( is_array( $post_data ) && ! empty( $post_data ) ) { // assumes that an array key exists
			$default_post_data[ key( $post_data ) ] = array(
				'wid' => self::get_default_workspace(),
			);
			$post_data[ key( $post_data ) ] = wp_parse_args( $post_data[ key( $post_data ) ], $default_post_data[ key( $post_data ) ] );
		}
		$method = 'GET';
		if ( 'delete' === $post_data ) {
			$method = 'DELETE';
		} elseif ( ! empty( $post_data ) ) {
			$method = 'POST';
		}
		$params = array(
			'method' => $method,
			'headers' => array(
					'Content-type' => 'application/json',
					'Authorization' => 'Basic ' . base64_encode( Toggl_Settings::get_api_key() . ':api_token' ),
				),
			'httpversion' => '1.1',
			'body' => wp_json_encode( $post_data ),
			'timeout' => apply_filters( 'http_request_timeout', 15 ),
			'sslverify' => false,
		);
		$request_response = wp_remote_request( self::API_URL . $endpoint, $params );
		$api_response = json_decode( wp_remote_retrieve_body( $request_response ) );
		return $api_response;
	}

	/**
	 * Uses old toggl api
	 * @param  array  $args query args
	 * @return
	 */
	public static function detail_api_request( $args = array(), $page = 1 ) {
		$args = array_merge( $args, array( 'user_agent' => self::APP_DOMAIN, 'page' => $page, 'since' => date( 'c', strtotime( '-1 year' ) ), 'until' => date( 'c', time() ) ) );
		$args = apply_filters( 'si_toggle_api_request_args', $args, $page );
		$endpoint = add_query_arg( $args, 'https://toggl.com/reports/api/v2/details' );
		$params = array(
			'method' => 'GET',
			'headers' => array(
					'Content-type' => 'application/json',
					'Authorization' => 'Basic ' . base64_encode( Toggl_Settings::get_api_key() . ':api_token' ),
				),
			'httpversion' => '1.1',
			'timeout' => apply_filters( 'http_request_timeout', 15 ),
			'sslverify' => false,
		);
		$request_response = wp_remote_request( $endpoint, $params );
		$api_response = json_decode( wp_remote_retrieve_body( $request_response ) );
		if ( $api_response->total_count > ( $page * 50 ) ) {
			// Loop through until we get them all.
			$paged_response = self::detail_api_request( $args, $page + 1 );
			$api_response->data = array_merge( $api_response->data, $paged_response->data );
		}
		return $api_response;
	}
}
