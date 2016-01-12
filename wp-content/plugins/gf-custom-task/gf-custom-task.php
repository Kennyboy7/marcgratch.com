<?php

/**
 * Plugin Name: Connect Gravity Forms to Custom Tasks
 * Description: Connect Gravity Forms to the mg_task Post Type for form id 3 "Task Creator"
 * Author: Marc Gratch
 * Author URI: https://marcgratch.com
 */
define( 'GF_CPTASK_PATH', plugin_dir_path(__FILE__) );
define('GF_CPTASK_URL', plugin_dir_url(__FILE__));

function populate_project_dropdown( $form ) {

	if ( $form['id'] != 4 ) {
		return $form;
	}

	if (isset($_GET['project']) && check_for_value($_GET['project'])){
		if (is_numeric($_GET['project'])){
			$project_id = $_GET['project'];
		}
		else {
			$project_id = maybe_get_pod_id(get_page_by_path($_GET['project'],OBJECT,'sa_project'));
		}
		$args = array(
			'post_type' => 'sa_project',
			'post__in ' => $project_id,
			'posts_per_page' => -1,
			'status' => 'published'
		);
	}
	else {
		$args = array(
			'post_type' => 'sa_project',
			'posts_per_page' => -1,
			'status' => 'published'
		);
	}

	//Getting all the projects;

	$posts = get_posts( $args );

	//Creating drop down item array.
	$items = array();

	//Adding initial blank value.
	$items[] = array( 'text' => '', 'value' => '' );

	//Adding post titles to the items array
	foreach ( $posts as $post ) {
		$items[] = array( 'value' => $post->post_title, 'text' => $post->post_title );
	}

	//Adding items to field id 8. Replace 8 with your actual field id. You can get the field id by looking at the input name in the markup.
	foreach ( $form['fields'] as &$field ) {
		if ( $field->id == 2 ) {
			$field->choices = $items;
		}
	}

	return $form;
}
add_filter( 'gform_pre_render', 'populate_pods_dropdown' );

//Note: when changing drop down values, we also need to use the gform_pre_validation so that the new values are available when validating the field.
add_filter( 'gform_pre_validation', 'populate_pods_dropdown' );

//Note: when changing drop down values, we also need to use the gform_admin_pre_render so that the right values are displayed when editing the entry.
add_filter( 'gform_admin_pre_render', 'populate_pods_dropdown' );

//Note: this will allow for the labels to be used during the submission process in case values are enabled
add_filter( 'gform_pre_submission_filter', 'populate_pods_dropdown' );
function populate_pods_dropdown( $form ) {

	//only populating drop down for form id 5
	if ( $form['id'] != 5 ) {
		return $form;
	}

	if (isset($_GET['project']) && check_for_value($_GET['project'])){
		if (is_numeric($_GET['project'])){
			$project_id = $_GET['project'];
		}
		else {
			$project_id = maybe_get_pod_id(get_page_by_path($_GET['project'],OBJECT,'sa_project'));
		}
		$SI_Project = SI_Project::get_instance( $project_id );
		$invoice_ids = $SI_Project->get_invoices();
		$estimate_ids = $SI_Project->get_estimates();
		$client_ids = $SI_Project->get_associated_clients();
		$users = array();
		$estimates = array();
		$invoices = array();
		$asscoiativeTasks = array();
	}



	//Getting all the projects;
	$pod = pods('mg_task',$z_id = null, true);

	//Adding items to field id 8. Replace 8 with your actual field id. You can get the field id by looking at the input name in the markup.
	foreach ( $form['fields'] as &$field ) {
		if ( $field->id == 3 ) {
			$field_options = $pod->fields['issue_type']['options']['pick_custom'];

			$field_options = explode("\n",$field_options);

			//Creating drop down item array.
			$items = array();

			//Adding initial blank value.
			$items[] = array( 'text' => '', 'value' => '' );

			//Adding post titles to the items array
			foreach ( $field_options as $option ) {

				$slug = utf8_encode($option);
				$slug = str_replace(' ', '-', $slug);
				$slug = preg_replace('/[^\da-z]/i', '', $slug);
				$slug = sprintf('%s', $slug);
				$slug = urlencode($slug);

				$items[] = array( 'value' => $slug, 'text' => $option );
			}

			$field->choices = $items;
		}
		elseif ($field->id == 4){
			$field_options = $pod->fields['priority']['options']['pick_custom'];

			$field_options = explode("\n",$field_options);

			//Creating drop down item array.
			$items = array();

			//Adding initial blank value.
			$items[] = array( 'text' => '', 'value' => '' );

			//Adding post titles to the items array
			foreach ( $field_options as $option ) {

				$slug = utf8_encode($option);
				$slug = str_replace(' ', '-', $slug);
				$slug = preg_replace('/[^\da-z]/i', '', $slug);
				$slug = sprintf('%s', $slug);
				$slug = urlencode($slug);

				$items[] = array( 'value' => $slug, 'text' => $option );
			}

			$field->choices = $items;
		}
		elseif ($field->id == 8 || $field->id == 9){

			if (isset($client_ids) && check_for_value($client_ids) === true){

				foreach ($client_ids as $clients_id){
					$SI_Client = SI_Client::get_instance($clients_id);
					$user_ids = $SI_Client->get_associated_users();

					foreach ($user_ids as $user_id){

						if (user_can($user_id, 'update_core')){
							$has_admin[] = $user_id;
						}
						$users[] = get_user_by('ID',$user_id);
					}
				}

				if (empty($has_admin) && current_user_can('update_core')){
					$args = array(
						'role' => 'administrator'
					);
					$users = array_merge($users,get_users( $args ));
				}
			}
			else {
				$users = get_users();
			}

			//Creating drop down item array.
			$items = array();

			//Adding initial blank value.
			$items[] = array( 'text' => '', 'value' => '' );

			//Adding post titles to the items array
			foreach ( $users as $user ) {

				$items[] = array( 'value' => $user->ID, 'text' => $user->data->display_name );
			}

			$field->choices = $items;
		}
		elseif ($field->id == 11){

			if (isset($project_id) && check_for_value($project_id) === true ){
				$args = array(
					'post_type' => 'mg_task',
					'posts_per_page' => -1,
					'post_status' => array('complete','in-progress','not-started','testing','publish','published'),
					'meta_key'	=> 'project',
					'meta_value'	=> $project_id
				);
			}
			else {
				$args = array(
					'post_type' => 'mg_task',
					'posts_per_page' => -1,
					'post_status' => array('complete','in-progress','not-started','testing','publish','published'),
				);
			}

			//Creating drop down item array.
			$items = array();

			//Adding initial blank value.
			$items[] = array( 'text' => '', 'value' => '' );


			$posts = get_posts( $args );

			foreach( $posts as $post ) {

				$items[] = array( 'text' => $post->post_title, 'value' => $post->ID );

			}

			$field->choices = $items;
		}
		elseif ($field->id == 18){

			//Creating drop down item array.
			$items = array();

			//Adding initial blank value.
			$items[] = array( 'text' => '-- No Primary Task --', 'value' => '' );

			if (isset($project_id) && check_for_value($project_id) === true ){
				$args = array(
					'post_type' => 'mg_task',
					'posts_per_page' => -1,
					'post_status' => array('complete','in-progress','not-started','testing','publish','published'),
					'meta_key'	=> 'project',
					'meta_value'	=> $project_id
				);
			}
			else {
				$args = array(
					'post_type' => 'mg_task',
					'posts_per_page' => -1,
					'post_status' => array('complete','in-progress','not-started','testing','publish','published'),
				);
			}

			$posts = get_posts( $args );

			foreach( $posts as $post ) {

				$items[] = array( 'text' => $post->post_title, 'value' => $post->ID );

			}

			$field->choices = $items;
		}
		elseif ($field->id == 13){

			if (isset($invoice_ids) && check_for_value($invoice_ids) === true){
				foreach ($invoice_ids as $invoice_id){

					$invoices[] = (int)$invoice_id;

				}
				$args = array(
					'post_type' => 'sa_invoice',
					'posts_per_page' => -1,
					'public' => false,
					'post_status' => array('temp','request','Pending','Scheduled','publish'),
					'post__in'	=> $invoices
					);
			}
			elseif (isset($invoice_ids) && check_for_value($invoice_ids) === false){
				$args = array(
					'post_type' => 'sa_invoice',
					'posts_per_page' => 0
				);
			}
			else {
				$args = array(
					'post_type' => 'sa_invoice',
					'posts_per_page' => -1,
					'public' => false,
					'post_status' => array('temp','request','Pending','Scheduled','publish')
				);
			}

			//Creating drop down item array.
			$items = array();

			//Adding initial blank value.
			$items[] = array( 'text' => '', 'value' => '' );

			$posts = get_posts( $args );

			foreach( $posts as $post ) {

				$items[] = array( 'text' => $post->post_title, 'value' => $post->ID );

			}

			$field->choices = $items;
		}
		elseif ($field->id == 14){

			if (isset($estimate_ids) && check_for_value($estimate_ids) === true){
				foreach ($estimate_ids as $estimate_id){

					$estimates[] = (int)$estimate_id;

				}
				$args = array(
					'post_type' => 'sa_estimate',
					'posts_per_page' => -1,
					'public' => false,
					'post_status' => array('temp','request','Pending','Scheduled','publish'),
					'post__in'	=> $estimates
				);
			}
			elseif (isset($estimate_ids) && check_for_value($estimate_ids) === false){
				$args = array(
					'post_type' => 'sa_estimate',
					'posts_per_page' => 0
				);
			}
			else {
				$args = array(
					'post_type' => 'sa_estimate',
					'posts_per_page' => -1,
					'public' => false,
					'post_status' => array('temp','request','Pending','Scheduled','publish')
				);
			}

			//Creating drop down item array.
			$items = array();

			//Adding initial blank value.
			$items[] = array( 'text' => '', 'value' => '' );

			$posts = get_posts( $args );

			foreach( $posts as $post ) {

				$items[] = array( 'text' => $post->post_title, 'value' => $post->ID );

			}

			$field->choices = $items;
		}
	}

	return $form;
}

function pods_modify_data_b4_submit($pieces, $is_new_item = null){
	unset($_SESSION['prev_inv']);
	unset($_SESSION['prev_est']);
	if (isset($pieces) && check_for_value($pieces) !== false){
		$current_field = $pieces['fields_active'];
		if (isset($pieces['params']) && check_for_value($pieces['params'])){
			$post_id = intval(maybe_get_pod_id($pieces['params']));
			$_SESSION['prev_inv'] = maybe_get_pod_id(get_post_meta($post_id,'add_line_item_to_invoice',false));
			$_SESSION['prev_est'] = maybe_get_pod_id(get_post_meta($post_id,'add_line_item_to_estimate',false));
		}
		if ( $pieces['pod']['name'] == 'mg_task' && in_array('estimated_time',$current_field) ) {
			$current_val = $pieces['fields']['estimated_time']['value'];
			$new_value = convert_estimated_time_to_minutes($current_val);
			$pieces['fields']['estimated_time']['value'] = $new_value;
		}
	}

	return $pieces;
}
add_filter( 'pods_api_pre_save_pod_item', 'pods_modify_data_b4_submit', 10, 2);

function convert_estimated_time_to_minutes( $current_val )
{
	if (is_numeric($current_val)){
		return intval($current_val);
	}
	$spanisor = 1;
	$new_value = 0;
	$all_units = explode(' ', $current_val);
	foreach ($all_units as $unit) {
		$unit_of_measurement = substr($unit, -1);
		$current_val = str_replace($unit_of_measurement, '', $unit);
		if ($unit_of_measurement === 'h') {
			$spanisor = 60;
		} elseif ($unit_of_measurement === 'd') {
			$spanisor = 8 * 60;
		} elseif ($unit_of_measurement === 'w') {
			$spanisor = 8 * 60 * 5;
		} elseif ($unit_of_measurement === 'M') {
			$spanisor = 8 * 60 * 5 * 4;
		} elseif ($unit_of_measurement === 'm') {
			$spanisor = 1;
		}
		$new_value += floatval($current_val) * $spanisor;
	}
	return $new_value;
}
function add_pods_to_gfcpt( $args, $form_id ){
	$args = array(
			'public'   => false
	);
	return $args;
}
add_filter('gfcpt_post_type_args', 'add_pods_to_gfcpt',9999, 2);

function add_pods_to_gfcpt_tax( $args, $form_id ){
	$args = array(
			'public'   => false
	);
	return $args;
}
add_filter('gfcpt_tax_args', 'add_pods_to_gfcpt_tax',9999, 2);

function set_post_content( $post_id, $entry, $form ) {

	$attachments = $entry[7];
	$attachment_array = array();
	$attachments = json_decode($attachments);
	$fix_version = $entry[6];
	if (count($attachments) > 0){
		foreach ($attachments as $attachment){
			$attachment_id = pods_attachment_import($attachment, $post_id, false);
			$attachment_array[] = $attachment_id;
		}
	}

	$fix_version = explode(',',$fix_version);

	$status = wp_set_object_terms( $post_id, $fix_version, 'fixversion', true );

	$data = array(
			'project' => $entry[2],
			'issue_type' => $entry[3],
			'priority' => $entry[4],
			'description' => $entry[5],
			'fix_version' => $status,
			'attachments' => $attachment_array,
			'assignee' => $entry[8],
			'cc_users' => $entry[9],
			'estimated_time' => $entry[10],
			'associated_tasks' => $entry[11],
			'description_of_associated_tasks' => $entry[12],
			'add_line_item_to_invoice' => $entry[13],
			'add_line_item_to_estimate' => $entry[14],
			'rate' => $entry[15],
			'quantity' => $entry[16],
			'percent_adjustment' => $entry[17]
	);
	if (isset($entry[13]) && !empty($entry[13])){
		$rate = $entry[15];
		$description = "<span id='task-$post_id' class='".sanitize_title($entry[4])." ".sanitize_title($entry[3])."'><strong class='title'>".$entry[1]."</strong><br/>".$entry[5]."</span>";
		$quantity = $entry[16];
		$percent_adjustment = floatval($entry[17]);
		$new_rate = ((float)convert_estimated_time_to_minutes( $entry[10] ) / 60) * $rate;
		$total = (float)round(($new_rate * $quantity) - ($new_rate * $quantity) * ( $percent_adjustment / 100 ),2);
		$invoices = explode(',',$entry[13]);

		foreach ($invoices as $invoice){
			$SI_Invoice = SI_Invoice::get_instance( $invoice );
			$line_items = $SI_Invoice->get_line_items();
			$line_items[] = array(
					'rate' => $new_rate,
					'qty' => $quantity,
					'desc' => $description,
					'type' => 'task',
					'total' => $total,
					'tax' => $percent_adjustment,
			);
			$SI_Invoice->set_line_items( $line_items );
			$SI_Invoice->set_calculated_total();
		}
	}
	if (isset($entry[14]) && !empty($entry[14])){

		$rate = $entry[15];
		$description = "<span id='task-$post_id' class='".sanitize_title($entry[4])." ".sanitize_title($entry[3])."'><strong class='title'>".$entry[1]."</strong><br/>".$entry[5]."</span>";
		$quantity = $entry[16];
		$percent_adjustment = floatval($entry[17]);
		$new_rate = ((float)convert_estimated_time_to_minutes( $entry[10] ) / 60) * $rate;
		$total = (float)round(($new_rate * $quantity) - ($new_rate * $quantity) * ( $percent_adjustment / 100 ),2);
		$estimates = explode(',',$entry[14]);

		foreach ($estimates as $estimate){
			$SI_Estimate = SI_Estimate::get_instance( $estimate );
			$line_items = $SI_Estimate->get_line_items();
			$line_items[] = array(
					'rate' => $new_rate,
					'qty' => $quantity,
					'desc' => $description,
					'type' => 'task',
					'total' => $total,
					'tax' => $percent_adjustment,
			);
			$SI_Estimate->set_line_items( $line_items );
			$SI_Estimate->set_calculated_total();

		}
	}

	$pod = pods('mg_task',$post_id);
	apply_filters('pods_api_pre_save_pod_item', $pod->save($data));
	if (isset($entry[18]) && check_for_value($entry[18]) === true){
		wp_update_post(array('ID' => $post_id, 'post_parent'=> $entry[18]));
	}


}
add_action( 'gform_after_create_post', 'set_post_content', 99999, 3 );

function process_ajax_on_project_change(){
	$has_admin = array();
	//@TODO ADD NONCE CHECK
	$post_id = $_POST['post_id'];
	if ($post_id <= 0){
		return;
	}
	$SI_Project = SI_Project::get_instance( $post_id );
	$invoice_ids = $SI_Project->get_invoices();
	$estimate_ids = $SI_Project->get_estimates();
	$client_ids = $SI_Project->get_associated_clients();
	$clients = array();
	$estimates = array();
	$invoices = array();
	$asscoiativeTasks = array();

	foreach ($client_ids as $clients_id){
		$SI_Client = SI_Client::get_instance($clients_id);
		$user_ids = $SI_Client->get_associated_users();

		foreach ($user_ids as $user_id){

			if (user_can($user_id, 'update_core')){
				$has_admin[] = $user_id;
			}

			$user = get_user_by('ID',$user_id);
			$display_name = $user->data->display_name;
			$clients[] = array(
					'user_id' 	=> (int)$user_id,
					'display_name'	=> $display_name
			);
		}
	}

	if (empty($has_admin) && current_user_can('update_core')){
		$args = array(
				'role' => 'administrator'
		);
		$users = get_users( $args );
		foreach ($users as $user){
			$clients[] = array(
					'user_id' => (int)$user->ID,
					'display_name' => $user->data->display_name
			);
		}
	}

	$blank_option = array(
			'user_id' 	=> '',
			'display_name'	=> ''
	);

	array_unshift($clients, $blank_option);

	foreach ($estimate_ids as $estimate_id){
		$title = get_the_title($estimate_id);
		$estimates[] = array(
			'est_id' => (int)$estimate_id,
			'est_title' => $title
		);
	}

	foreach ($invoice_ids as $invoice_id){
		$title = get_the_title($invoice_id);
		$invoices[] = array(
				'inv_id' => (int)$invoice_id,
				'inv_title' => $title
		);
	}

	$args = array(
		'post_type'		=> 'mg_task',
		'meta_key'   	=> 'project',
		'meta_value'	=> $post_id,
		'post_status' => array('complete','in-progress','not-started','testing','publish','published'),
		'posts_per_page'	=> -1
	);

	$the_query = new WP_Query( $args );
	while ( $the_query->have_posts() ) {
		$the_query->the_post();
		$asscoiativeTasks[] = array(
			'task_title' 	=> get_the_title(),
			'task_id'	 	=> get_the_ID(),
		);
	}

	$data = array(
		'clients' 	=> $clients,
		'estimates' => $estimates,
		'invoices'	=> $invoices,
		'tasks'		=> $asscoiativeTasks
	);


	$output = json_encode($data);

	exit($output);
}
add_action('wp_ajax_mg_process_ajax_on_project_change', 'process_ajax_on_project_change');

function gf_cpt_frontend_scripts(){
	wp_register_script('gf_cpt_frontend_scripts',GF_CPTASK_URL . '/assets/js/gf-cpt-front-end-ajax.js',array('gform_chosen'),null,true);
	wp_enqueue_script('gf_cpt_frontend_scripts');
	wp_localize_script( 'gf_cpt_frontend_scripts', 'gfCPTask', array(
			'nonce' => wp_create_nonce( 'gf_cpt_frontend_scripts'),
			'ajaxurl' => admin_url( 'admin-ajax.php' )
	) );
}
add_action( 'gform_enqueue_scripts_5', 'gf_cpt_frontend_scripts', 10, 2 );


// define the pods_api_get_table_info_default_post_status callback
function filter_pods_api_get_table_info_default_post_status( $array, $post_type, $info, $object_type, $object, $name, $pod, $field ) {
	global $pagenow;
	global $typenow;

	if ($typenow !== 'mg_task' && $pagenow !== 'edit-mg_task'){
		return $array;
	}
	else {
		if(isset($field['name']) && ($field['name'] == 'add_line_item_to_invoice' || $field['name'] == 'add_line_item_to_estimate' || $field['name'] == 'associated_tasks')) {
			$array[] = 'temp';
			$array[] = 'request';
			$array[] = 'Pending';
			$array[] = 'Scheduled';
			$array[] = 'publish';
			$array[] = 'not-started';
			$array[] = 'in-progress';
			$array[] = 'testing';
			$array[] = 'complete';
		}
	}

	if (strpos($_SERVER["REQUEST_URI"],'/wp-admin/') !== false){
		if (isset($_SERVER['HTTP_REFERER'])){
			if(strpos($_SERVER["HTTP_REFERER"],'post.php')){
				if(isset($field['name']) && ($field['name'] == 'add_line_item_to_invoice' || $field['name'] == 'add_line_item_to_estimate')) {
					$array[] = 'temp';
					$array[] = 'request';
					$array[] = 'Pending';
					$array[] = 'Scheduled';
					$array[] = 'publish';
				}
			}
		} else {
			if(isset($field['name']) && ($field['name'] == 'add_line_item_to_invoice' || $field['name'] == 'add_line_item_to_estimate')) {
				$array[] = 'temp';
				$array[] = 'request';
				$array[] = 'Pending';
				$array[] = 'Scheduled';
				$array[] = 'publish';
			}
		}
	}
	// make filter magic happen here...
	return $array;
};

// add the filter
add_filter( 'pods_api_get_table_info_default_post_status', 'filter_pods_api_get_table_info_default_post_status', 10, 8 );

// define the pods_form_ui_field_<type>_value callback
function filter_pods_form_ui_field_type_value( $value, $name, $options, $pod, $id ) {
	global $pagenow;
	if ($name === 'pods_meta_estimated_time'){
		$value = (float)round($value / 60 , 2) .'h';
	}
	// make filter magic happen here...
	return $value;
};

// add the filter
add_filter( "pods_form_ui_field_text_value", 'filter_pods_form_ui_field_type_value', 10, 5 );

function add_line_items_on_task_save($pieces, $is_new_item, $id ) {
	global $pagenow;
	if ( $pagenow == 'post.php' || $pagenow == 'admin-ajax.php' || $pagenow == 'edit.php' ){
		if (!empty($pieces['changed_fields']) || $pieces['fields_active'] === 'estimated_time' || isset($_SESSION["inv_to_remove"]) || isset($_SESSION["est_to_remove"]) || isset($_SESSION["inv_to_add"]) || isset($_SESSION["est_to_add"])) {
			$changed_fields = $pieces['changed_fields'];

			if (isset($_SESSION["inv_to_remove"])){
				$inv_to_remove = $_SESSION["inv_to_remove"];
			} else {
				$inv_to_remove = false;
			}
			if (isset($_SESSION["est_to_remove"])){
				$est_to_remove = $_SESSION["est_to_remove"];
			} else {
				$est_to_remove = false;
			}
			if (isset($_SESSION["inv_to_add"])){
				$inv_to_add = $_SESSION["inv_to_add"];
			} else {
				$inv_to_add = false;
			}
			if (isset($_SESSION["est_to_add"])){
				$est_to_add = $_SESSION["est_to_add"];
			} else {
				$est_to_add = false;
			}

			$line_item_chnages = invoice_or_estimate_update( $est_to_add, $est_to_remove, $inv_to_add, $inv_to_remove, $changed_fields, $id );

			if (($line_item_chnages === false && !isset($_POST['estimates_to_remove']) && !isset($_POST['invoices_to_remove'])) || ($line_item_chnages === false && isset($_POST['estimates_to_remove']) && $_POST['estimates_to_remove'] == "" && isset($_POST['invoices_to_remove']) && $_POST['invoices_to_remove'] == "")){
				unset($_SESSION["est_to_remove"]);
				unset($_SESSION["inv_to_remove"]);
				unset($_SESSION["inv_to_add"]);
				unset($_SESSION["est_to_add"]);
				unset($_SESSION["update_inv"]);
				unset($_SESSION["update_est"]);
				return;
			}

			if (isset($line_item_chnages['estimates']['estimates_to_update'])){
				update_estimate_line_item( $pieces, $line_item_chnages['estimates']['estimates_to_update'], $id );
			}

			if (isset($line_item_chnages['invoices']['invoices_to_update'])){
				update_invoice_line_item( $pieces, $line_item_chnages['invoices']['invoices_to_update'], $id );
			}

			if (isset($line_item_chnages['estimates']['estimates_to_add'])){
				add_estimate_line_item( $pieces, $line_item_chnages['estimates']['estimates_to_add'], $id );
			}

			if (isset($line_item_chnages['invoices']['invoices_to_add'])){
				add_invoice_line_item( $pieces, $line_item_chnages['invoices']['invoices_to_add'], $id );
			}

			if (isset($line_item_chnages['estimates']['estimates_to_remove'])){
				remove_estimate_line_items($line_item_chnages['estimates']['estimates_to_remove'], $id);
			}

			if (isset($line_item_chnages['invoices']['invoices_to_remove'])){
				remove_invoice_line_items($line_item_chnages['invoices']['invoices_to_remove'], $id);
			}
			if ($pagenow === 'post.php'){
				$items = array(
						"invoices" => array(),
						"estimates" => array()
				);
				$object = null;
				foreach ($line_item_chnages as $key => $val){
					if ($key === 'invoices'){
						foreach ($val as $k => $v){
							if (is_array($v) && check_for_value($v) !== false){
								$items['invoices'] = array_merge($items['invoices'],$v);
							}
						}
					}
					if ($key === 'estimates'){
						foreach ($val as $k => $v){
							if (is_array($v) && check_for_value($v) !== false ){
								$items['estimates'] = array_merge($items['estimates'],$v);
							}
						}
					}
				}
				foreach ($items as $key => $value){
					foreach ($value as $item){
						if ($key === 'estimates'){
							$object = SI_Estimate::get_instance($item);
						}
						elseif ($key === 'invoices'){
							$object = SI_Estimate::get_instance($item);
						}
						if ($object !== null){
							$object->set_calculated_total();
						}
					}
				}
			}
		}
	}
	unset($_SESSION["est_to_remove"]);
	unset($_SESSION["inv_to_remove"]);
	unset($_SESSION["inv_to_add"]);
	unset($_SESSION["est_to_add"]);
	unset($_SESSION["update_inv"]);
	unset($_SESSION["update_est"]);
	unset($_SESSION["prev_est"]);
	unset($_SESSION["prev_inv"]);

}

/**
 * Looks at the items being passed in and determines if its an array or a string
 * @param $maybe_object_array_string
 * @return array
 */
function maybe_get_pod_id( $maybe_object_array_string ){

	$count = count($maybe_object_array_string);
	$output = array();

	if ($count > 1){
		if (is_array($maybe_object_array_string)){
			if (isset($maybe_object_array_string['ID']) && check_for_value($maybe_object_array_string['ID']) === true ){
				$output[] = $maybe_object_array_string['ID'];
				return $output;
			}
		}
		foreach ($maybe_object_array_string as $item){
			if (is_array($item)){
				$output[] = $item['ID'];
			}
			elseif (is_object($item)){
				$output[] = $item->ID;
			}
			else {
				$output[] = $item;
			}
		}
	} else {
		if (is_array($maybe_object_array_string)){
			foreach ($maybe_object_array_string as $item){
				if (is_array($item) && isset($item['ID'])){
					$output = $item['ID'];
				}
				elseif (is_object($item)){
					if (isset($item->id)){
						$output = $item->id;
					}
				}
				else {
					$output = $item;
				}
			}
		}
		elseif (is_object($maybe_object_array_string)){
			if (isset($maybe_object_array_string->id)){
				$output = $maybe_object_array_string->id;
			}
		}
		else {
			$output = $maybe_object_array_string;
		}
	}
	return $output;
}

function update_invoice_line_item( $pieces, $invoices, $id ){

	$priority = sanitize_title(get_post_meta($id,'priority',true));
	if (check_for_value($priority) !== true){
		$priority = 'no-priority';
	}
	$issue_type = sanitize_title(get_post_meta($id,'issue_type',true));
	if (check_for_value($issue_type) !== true){
		$issue_type = 'no-issue';
	}
	if (!isset($invoices) || check_for_value($invoices) !== true){
		return; //this is not the function you are looking for
	}

	$fields = array('rate','description','quantity','percent_adjustment','estimated_time');
	$populated_fields = array();

	foreach ($fields as $field){
		if (!isset($pieces['fields'][$field]['value']) || isset($pieces['fields'][$field]['value']) && $pieces['fields'][$field]['value'] == null) {
			$val = get_post_meta($id, $field, true);
			if ($field === "percent_adjustment"){
				if ($val === ''){
					$val = 0;
				}
			}
			$populated_fields[$field] = $val;
			if ($field === 'estimated_time'){
				$populated_fields[$field] = convert_estimated_time_to_minutes($populated_fields[$field]);
			}
		} else {
			$populated_fields[$field] = $pieces['fields'][$field]['value'];
		}
	}

	$convert_min_to_hr = intval($populated_fields['estimated_time']) / 60;
	$rate = floatval($populated_fields['rate']);
	$quantity = floatval($populated_fields['quantity']);
	$percent_adjustment = floatval($populated_fields['percent_adjustment']);

	$new_rate = $convert_min_to_hr * $rate;

	$total = round( ( $new_rate * $quantity ) - ( $new_rate * $quantity  * (  $percent_adjustment / 100 )),2);

	if (!is_array($invoices)){
		$invoices = explode(",",$invoices);
	}

	foreach ($invoices as $invoice) {
		$SI_Invoice = SI_Invoice::get_instance($invoice);
		$line_items = $SI_Invoice->get_line_items();
		$invoice_total = 0;

		$new_line_items_array = array();
			foreach ($line_items as $li){
				if( strpos($li['desc'], "task-$id") !== false || strpos($li['desc'], 'task: '.$id) !== false){
					$new_line_items_array[] = array(
							'rate' => $new_rate,
							'qty' => $populated_fields['quantity'],
							'desc' => "<span id='task-$id' class='$priority $issue_type'><strong class='title'>" . get_the_title($id) . "</strong><br/>".$populated_fields['description']."</span>",
							'type' => 'task',
							'total' => $total,
							'tax' => $populated_fields['percent_adjustment'],
					);
					$invoice_total += $total;
				} else {
					$new_line_items_array[] = $li;
					$invoice_total += $li['total'];
				}
			}
			if (isset($line_items) && empty($line_items)){
				$new_line_items_array[] = array(
						'rate' => $new_rate,
						'qty' => $populated_fields['quantity'],
						'desc' => "<span id='task-$id' class='$priority $issue_type'><strong class='title'>" . get_the_title($id) . "</strong><br/>".$populated_fields['description']."</span>",
						'type' => 'task',
						'total' => $total,
						'tax' => $populated_fields['percent_adjustment'],
				);
				$invoice_total += $total;
			}
		$SI_Invoice->set_line_items( $new_line_items_array );
	}
	if (isset($_SESSION['changed_docs']['invoices'])){
		$_SESSION['changed_docs']['invoices'] = array_merge($invoices,$_SESSION['changed_docs']['invoices']);
	}
}

function add_invoice_line_item( $pieces, $invoices, $id ){
	$priority = sanitize_title(get_post_meta($id,'priority',true));
	if (check_for_value($priority) !== true){
		$priority = 'no-priority';
	}
	$issue_type = sanitize_title(get_post_meta($id,'issue_type',true));
	if (check_for_value($issue_type) !== true){
		$issue_type = 'no-issue';
	}
	if (!isset($invoices) && check_for_value($invoices) === false){
		return; //this is not the function you are looking for
	}

	$fields = array('rate','description','quantity','percent_adjustment','estimated_time');
	$populated_fields = array();

	foreach ($fields as $field){
		if (!isset($pieces['fields'][$field]['value']) || isset($pieces['fields'][$field]['value']) && $pieces['fields'][$field]['value'] == null) {
			$val = get_post_meta($id, $field, true);
			if ($field === "percent_adjustment"){
				if ($val === ''){
					$val = 0;
				}
			}
			$populated_fields[$field] = $val;
			if ($field === 'estimated_time'){
				$populated_fields[$field] = convert_estimated_time_to_minutes($populated_fields[$field]);
			}
		} else {
			$populated_fields[$field] = $pieces['fields'][$field]['value'];
		}
	}

	$convert_min_to_hr = intval($populated_fields['estimated_time']) / 60;
	$rate = floatval($populated_fields['rate']);
	$quantity = floatval($populated_fields['quantity']);
	$percent_adjustment = floatval($populated_fields['percent_adjustment']);

	$new_rate = $convert_min_to_hr * $rate;

	$total = round( ( $new_rate * $quantity ) - ( $new_rate * $quantity  * (  $percent_adjustment / 100 )),2);

	if (!is_array($invoices)){
		$invoices = explode(",",$invoices);
	}

	foreach ($invoices as $invoice) {
		$SI_Invoice = SI_Invoice::get_instance($invoice);
		$line_items = $SI_Invoice->get_line_items();
		$invoice_total = 0;

		/**
		 * Even though we are adding a new line items, sometimes meta gets broken
		 * Lets make sure that we dnn't ACTUALLY need to just update a line item.
		 */
		$new_line_items_array = array();
		foreach ($line_items as $li){
			if( strpos($li['desc'], "task-$id") !== false || strpos($li['desc'], 'task: '.$id) !== false){
				$new_line_items_array[] = array(
						'rate' => $new_rate,
						'qty' => $populated_fields['quantity'],
						'desc' => "<span id='task-$id' class='$priority $issue_type'><strong class='title'>" . get_the_title($id) . "</strong><br/>".$populated_fields['description']."</span>",
						'type' => 'task',
						'total' => $total,
						'tax' => $populated_fields['percent_adjustment'],
				);
				$invoice_total += $total;
				$fired = true;
			} else {
				$new_line_items_array[] = $li;
				$invoice_total += $li['total'];
			}
		}

		/**
		 * If there wasn't an update then let's actually add one
		 * Is the ADD function uneccessary?  Since we are checking for an update
		 * anyhow.
		 */
		if (!isset($fired) || isset($fired) && $fired !== true){
			$new_line_items_array[] = array(
					'rate' => $new_rate,
					'qty' => $populated_fields['quantity'],
					'desc' => "<span id='task-$id' class='$priority $issue_type'><strong class='title'>" . get_the_title($id) . "</strong><br/>".$populated_fields['description']."</span>",
					'type' => 'task',
					'total' => $total,
					'tax' => $populated_fields['percent_adjustment'],
			);
			$invoice_total += $total;
		}
		$SI_Invoice->set_line_items($new_line_items_array);
	}
	if (isset($_SESSION['changed_docs']['invoices'])){
		$_SESSION['changed_docs']['invoices'] = array_merge($invoices,$_SESSION['changed_docs']['invoices']);
	}
}

function remove_invoice_line_items( $invoices, $id ){

	if (!isset($invoices) && check_for_value($invoices) === false){
		return; //this is not the function you are looking for
	}

	if (!is_array($invoices)){
		$invoices = explode(",",$invoices);
	}

	foreach ($invoices as $invoice) {
		$SI_Invoice = SI_Invoice::get_instance($invoice);
		$line_items = $SI_Invoice->get_line_items();
		$invoice_total = $SI_Invoice->get_calculated_total( false );

		$fired = null;
		$new_line_items_array = array();
		foreach ($line_items as $li){
			if( strpos($li['desc'], "task-$id") === false && strpos($li['desc'], 'task: '.$id) === false){
				$new_line_items_array[] = $li;
			} elseif ( $fired !== null ) {
				$fired = true;
				$invoice_total -= $li['total'];
			}
		}

		$SI_Invoice->set_line_items( $new_line_items_array );
	}
	if (isset($_SESSION['changed_docs']['invoices'])){
		$_SESSION['changed_docs']['invoices'] = array_merge($invoices,$_SESSION['changed_docs']['invoices']);
	}
}

function update_estimate_line_item( $pieces, $estimates, $id ){
	$priority = sanitize_title(get_post_meta($id,'priority',true));
	if (check_for_value($priority) !== true){
		$priority = 'no-priority';
	}
	$issue_type = sanitize_title(get_post_meta($id,'issue_type',true));
	if (check_for_value($issue_type) !== true){
		$issue_type = 'no-issue';
	}
	if (!isset($estimates) || check_for_value($estimates) !== true){
		return; //this is not the function you are looking for
	}

	$fields = array('rate','description','quantity','percent_adjustment','estimated_time');
	$populated_fields = array();

	foreach ($fields as $field){
		if (!isset($pieces['fields'][$field]['value']) || isset($pieces['fields'][$field]['value']) && $pieces['fields'][$field]['value'] == null) {
			$val = get_post_meta($id, $field, true);
			if ($field === "percent_adjustment"){
				if ($val === ''){
					$val = 0;
				}
			}
			$populated_fields[$field] = $val;
			if ($field === 'estimated_time'){
				$populated_fields[$field] = convert_estimated_time_to_minutes($populated_fields[$field]);
			}
		} else {
			$populated_fields[$field] = $pieces['fields'][$field]['value'];
		}
	}

	$convert_min_to_hr = intval($populated_fields['estimated_time']) / 60;
	$rate = floatval($populated_fields['rate']);
	$quantity = floatval($populated_fields['quantity']);
	$percent_adjustment = floatval($populated_fields['percent_adjustment']);

	$new_rate = $convert_min_to_hr * $rate;

	$total = round( ( $new_rate * $quantity ) - ( $new_rate * $quantity  * (  $percent_adjustment / 100 )),2);

	if (!is_array($estimates)){
		$estimates = explode(",",$estimates);
	}

	foreach ($estimates as $estimate) {
		$SI_Estimate = SI_Estimate::get_instance($estimate);
		$line_items = $SI_Estimate->get_line_items();
		$estimate_status = $SI_Estimate->get_status();

		if ($estimate_status === 'approved'){
			return;
		}

			$new_line_items_array = array();
			foreach ($line_items as $li){
				if( strpos($li['desc'], "task-$id") !== false || strpos($li['desc'], 'task: '.$id) !== false){
					$new_line_items_array[] = array(
							'rate' => $new_rate,
							'qty' => $populated_fields['quantity'],
							'desc' => "<span id='task-$id' class='$priority $issue_type'><strong class='title'>" . get_the_title($id) . "</strong><br/>".$populated_fields['description']."</span>",
							'type' => 'task',
							'total' => $total,
							'tax' => $populated_fields['percent_adjustment'],
					);
				} else {
					$new_line_items_array[] = $li;
				}
			}
			if (isset($line_items) && empty($line_items)){
				$new_line_items_array[] = array(
						'rate' => $new_rate,
						'qty' => $populated_fields['quantity'],
						'desc' => "<span id='task-$id' class='$priority $issue_type'><strong class='title'>" . get_the_title($id) . "</strong><br/>".$populated_fields['description']."</span>",
						'type' => 'task',
						'total' => $total,
						'tax' => $populated_fields['percent_adjustment'],
				);
			}
		$SI_Estimate->set_line_items( $new_line_items_array );
	}
	if (isset($_SESSION['changed_docs']['estimates'])){
		$_SESSION['changed_docs']['estimates'] = array_merge($estimates,$_SESSION['changed_docs']['estimates']);
	}
}

function add_estimate_line_item( $pieces, $estimates, $id ){
	$priority = sanitize_title(get_post_meta($id,'priority',true));
	if (check_for_value($priority) !== true){
		$priority = 'no-priority';
	}
	$issue_type = sanitize_title(get_post_meta($id,'issue_type',true));
	if (check_for_value($issue_type) !== true){
		$issue_type = 'no-issue';
	}
	if (!isset($estimates) || check_for_value($estimates) !== true){
		return; //this is not the function you are looking for
	}

	$fields = array('rate','description','quantity','percent_adjustment','estimated_time');
	$populated_fields = array();

	foreach ($fields as $field){
		if (!isset($pieces['fields'][$field]['value']) || isset($pieces['fields'][$field]['value']) && $pieces['fields'][$field]['value'] == null) {
			$val = get_post_meta($id, $field, true);
			if ($field === "percent_adjustment"){
				if ($val === ''){
					$val = 0;
				}
			}
			$populated_fields[$field] = $val;
			if ($field === 'estimated_time'){
				$populated_fields[$field] = convert_estimated_time_to_minutes($populated_fields[$field]);
			}
		} else {
			$populated_fields[$field] = $pieces['fields'][$field]['value'];
		}
	}

	$convert_min_to_hr = intval($populated_fields['estimated_time']) / 60;
	$rate = floatval($populated_fields['rate']);
	$quantity = floatval($populated_fields['quantity']);
	$percent_adjustment = floatval($populated_fields['percent_adjustment']);

	$new_rate = $convert_min_to_hr * $rate;

	$total = round( ( $new_rate * $quantity ) - ( $new_rate * $quantity  * (  $percent_adjustment / 100 )),2);

	if (!is_array($estimates)){
		$estimates = explode(",",$estimates);
	}

	foreach ($estimates as $estimate) {
		unset($fired);
		$SI_Estimate = SI_Estimate::get_instance($estimate);
		$line_items = $SI_Estimate->get_line_items();

		/**
		 * Even though we are adding a new line items, sometimes meta gets broken
		 * Lets make sure that we dnn't ACTUALLY need to just update a line item.
		 */
		$new_line_items_array = array();
		foreach ($line_items as $li){
			if( strpos($li['desc'], "task-$id") !== false || strpos($li['desc'], 'task: '.$id) !== false){
				$new_line_items_array[] = array(
						'rate' => $new_rate,
						'qty' => $populated_fields['quantity'],
						'desc' => "<span id='task-$id' class='$priority $issue_type'><strong class='title'>" . get_the_title($id) . "</strong><br/>".$populated_fields['description']."</span>",
						'type' => 'task',
						'total' => $total,
						'tax' => $populated_fields['percent_adjustment'],
				);
				$fired = true;
			} else {
				$new_line_items_array[] = $li;
			}
		}

		/**
		 * If there wasn't an update then let's actually add one
		 * Is the ADD function uneccessary?  Since we are checking for an update
		 * anyhow.
		 */
		if (!isset($fired) || isset($fired) && $fired !== true){
			$new_line_items_array[] = array(
					'rate' => $new_rate,
					'qty' => $populated_fields['quantity'],
					'desc' => "<span id='task-$id' class='$priority $issue_type'><strong class='title'>" . get_the_title($id) . "</strong><br/>".$populated_fields['description']."</span>",
					'type' => 'task',
					'total' => $total,
					'tax' => $populated_fields['percent_adjustment'],
			);
		}

		/**
		 * Line Items array is now updated, lets save them
		 */
		$SI_Estimate->set_line_items($new_line_items_array);
	}
	if (isset($_SESSION['changed_docs']['estimates'])){
		$_SESSION['changed_docs']['estimates'] = array_merge($estimates,$_SESSION['changed_docs']['estimates']);
	}
}

function remove_estimate_line_items( $estimates, $id ){

	if (!isset($estimates) && check_for_value($estimates) === false){
		return; //this is not the function you are looking for
	}

	if (!is_array($estimates)){
		$estimates = explode(",",$estimates);
	}

	foreach ($estimates as $estimate) {
		$SI_Estimate = SI_Estimate::get_instance($estimate);
		$line_items = $SI_Estimate->get_line_items();

		$fired = null;
		$new_line_items_array = array();

		foreach ($line_items as $li){
			if( strpos($li['desc'], "task-$id") === false && strpos($li['desc'], 'task: '.$id) === false){
				$new_line_items_array[] = $li;
			} elseif ( $fired !== true ) {
				$fired = true;
			}
		}

		$SI_Estimate->set_line_items( $new_line_items_array );
	}
	if (isset($_SESSION['changed_docs']['estimates'])){
		$_SESSION['changed_docs']['estimates'] = array_merge($estimates,$_SESSION['changed_docs']['estimates']);
	}
}

function invoice_or_estimate_update( $est_to_add, $est_to_remove, $inv_to_add, $inv_to_remove, $changed_fields, $id )
{

	if (isset($changed_fields['add_line_item_to_estimate'])){
		if ($changed_fields['add_line_item_to_estimate'] === '' || isset($_SESSION['prev_est']) && check_for_value($_SESSION['prev_est']) === true){
			if (isset($_SESSION['prev_est']) && check_for_value($_SESSION['prev_est']) === true){
				$current_estimates = maybe_get_pod_id($_SESSION['prev_est']);
				if ($est_to_remove === false){
					if(check_for_value($changed_fields['add_line_item_to_estimate']) === false){
						$est_to_remove = false;
					}
					else {
						if (!is_array($current_estimates)){
							$current_estimates = explode(",",$current_estimates);
						}
						if (!is_array($changed_fields['add_line_item_to_estimate'])){
							$changed_fields['add_line_item_to_estimate'] = explode(",",$changed_fields['add_line_item_to_estimate']);
						}
						$est_to_remove = array_diff($current_estimates,$changed_fields['add_line_item_to_estimate']);
					}
				}
			}
		}
		if (is_array($changed_fields['add_line_item_to_estimate']) && check_for_value($changed_fields['add_line_item_to_estimate']) === true && (!isset($est_to_add) || isset($est_to_add) && check_for_value($est_to_add) === false)){
			$est_to_add = $changed_fields['add_line_item_to_estimate'];
		}
	}

	if (isset($changed_fields['add_line_item_to_invoice'])){
		if ($changed_fields['add_line_item_to_invoice'] === '' || isset($_SESSION['prev_inv']) && check_for_value($_SESSION['prev_inv']) === true){
			if (isset($_SESSION['prev_inv']) && check_for_value($_SESSION['prev_inv']) === true){
				$current_invoices = maybe_get_pod_id($_SESSION['prev_inv']);
				if ($inv_to_remove === false){
					if(check_for_value($changed_fields['add_line_item_to_invoice']) === false){
						$inv_to_remove = false;
					}
					else {
						if (!is_array($current_invoices)){
							$current_invoices = explode(",",$current_invoices);
						}
						if (!is_array($changed_fields['add_line_item_to_invoice'])){
							$changed_fields['add_line_item_to_invoice'] = explode(",",$changed_fields['add_line_item_to_invoice']);
						}
						$inv_to_remove = array_diff($current_invoices,$changed_fields['add_line_item_to_invoice']);
					}
				}
			}
		}
		if (is_array($changed_fields['add_line_item_to_invoice']) && check_for_value($changed_fields['add_line_item_to_invoice']) === true && (!isset($inv_to_add) || isset($inv_to_add) && check_for_value($inv_to_add) === false)){
			$inv_to_add = $changed_fields['add_line_item_to_invoice'];
		}
	}

	$invoice_changes = array();
	$estimate_changes = array();

	if ($inv_to_add !== false) {
		$invoice_add = array(
				'invoices_to_add' => $inv_to_add,
		);
		$invoice_changes = array_merge($invoice_changes,$invoice_add);
	}
	if ($inv_to_remove !== false) {
		$inv_remove = array(
				'invoices_to_remove' => $inv_to_remove,
		);
		$invoice_changes = array_merge($invoice_changes,$inv_remove);
	}
	if ($est_to_add !== false) {
		$est_add = array(
				'estimates_to_add' => $est_to_add,
		);
		$estimate_changes = array_merge($estimate_changes,$est_add);
	}
	if ($est_to_remove !== false) {
		$est_remove = array(
				'estimates_to_remove' => $est_to_remove,
		);
		$estimate_changes = array_merge($estimate_changes,$est_remove);
	}

	if (isset($changed_fields['percent_adjustment']) || isset($changed_fields['rate']) || isset($changed_fields['quantity']) || isset($changed_fields['estimated_time'])) {
		if (
				(
						(isset($inv_to_add) && $inv_to_add !== false && !empty($inv_to_add) &&!is_null($inv_to_add)) &&
						(isset($inv_to_add[0]) && $inv_to_add[0] !== false && !empty($inv_to_add[0]) && !is_null($inv_to_add[0]))
				) ||
				(
						(isset($_SESSION["update_inv"]) && $_SESSION["update_inv"] !== false && !empty($_SESSION["update_inv"]) &&!is_null($_SESSION["update_inv"])) &&
						(isset($_SESSION["update_inv"][0]) && $_SESSION["update_inv"][0] !== false && !empty($_SESSION["update_inv"][0]) && !is_null($_SESSION["update_inv"][0]))
				) ||
				(
						(isset($_SESSION["prev_inv"]) && $_SESSION["prev_inv"] !== false && !empty($_SESSION["prev_inv"]) &&!is_null($_SESSION["prev_inv"])) &&
						(isset($_SESSION["prev_inv"][0]) && $_SESSION["prev_inv"][0] !== false && !empty($_SESSION["prev_inv"][0]) && !is_null($_SESSION["prev_inv"][0]))
				)
		)
		{
			if (
					(isset($_SESSION["update_inv"]) && $_SESSION["update_inv"] !== false && !empty($_SESSION["update_inv"]) &&!is_null($_SESSION["update_inv"])) &&
					(isset($_SESSION["update_inv"][0]) && $_SESSION["update_inv"][0] !== false && !empty($_SESSION["update_inv"][0]) && !is_null($_SESSION["update_inv"][0]))
			){
				$inv_to_add = maybe_get_pod_id($_SESSION["update_inv"]);
			}
			elseif (
					(isset($_SESSION["prev_inv"]) && $_SESSION["prev_inv"] !== false && !empty($_SESSION["prev_inv"]) &&!is_null($_SESSION["prev_inv"])) &&
					(isset($_SESSION["prev_inv"][0]) && $_SESSION["prev_inv"][0] !== false && !empty($_SESSION["prev_inv"][0]) && !is_null($_SESSION["prev_inv"][0]))
			){
				$inv_to_add = maybe_get_pod_id($_SESSION["prev_inv"]);
			}
			$inv_update = array(
					'invoices_to_update' => $inv_to_add
			);
			$invoice_changes = array_merge($invoice_changes,$inv_update);
		}
	}

	if (isset($changed_fields['percent_adjustment']) || isset($changed_fields['rate']) || isset($changed_fields['quantity']) || isset($changed_fields['estimated_time'])) {
		if (
				(
						(isset($est_to_add) && $est_to_add !== false && !empty($est_to_add) &&!is_null($est_to_add)) &&
						(isset($est_to_add[0]) && $est_to_add[0] !== false && !empty($est_to_add[0]) && !is_null($est_to_add[0]))
				) ||
				(
						(isset($_SESSION["update_est"]) && $_SESSION["update_est"] !== false && !empty($_SESSION["update_est"]) &&!is_null($_SESSION["update_est"])) &&
						(isset($_SESSION["update_est"][0]) && $_SESSION["update_est"][0] !== false && !empty($_SESSION["update_est"][0]) && !is_null($_SESSION["update_est"][0]))
				) ||
				(
						(isset($_SESSION["prev_est"]) && $_SESSION["prev_est"] !== false && !empty($_SESSION["prev_est"]) &&!is_null($_SESSION["prev_est"])) &&
						(isset($_SESSION["prev_est"][0]) && $_SESSION["prev_est"][0] !== false && !empty($_SESSION["prev_est"][0]) && !is_null($_SESSION["prev_est"][0]))
				)
		)
		{
			if (
					(isset($_SESSION["update_est"]) && $_SESSION["update_est"] !== false && !empty($_SESSION["update_est"]) &&!is_null($_SESSION["update_est"])) &&
					(isset($_SESSION["update_est"][0]) && $_SESSION["update_est"][0] !== false && !empty($_SESSION["update_est"][0]) && !is_null($_SESSION["update_est"][0]))
			){
				$est_to_add = maybe_get_pod_id($_SESSION["update_est"]);
			}
			elseif (
					(isset($_SESSION["prev_est"]) && $_SESSION["prev_est"] !== false && !empty($_SESSION["prev_est"]) &&!is_null($_SESSION["prev_est"])) &&
					(isset($_SESSION["prev_est"][0]) && $_SESSION["prev_est"][0] !== false && !empty($_SESSION["prev_est"][0]) && !is_null($_SESSION["prev_est"][0]))
			){
				$est_to_add = maybe_get_pod_id($_SESSION["prev_est"]);
			}
			$est_update = array(
					'estimates_to_update' => $est_to_add
			);
			$estimate_changes = array_merge($estimate_changes,$est_update);
		}
	}

	$output = ['estimates' => $estimate_changes, 'invoices' => $invoice_changes];
	return $output;
}

add_action('pods_api_post_save_pod_item', 'add_line_items_on_task_save', 999, 3);


// define the pods_api_save_pod_item_track_changed_fields_<pod> callback
add_filter( 'pods_api_save_pod_item_track_changed_fields_mg_task', '__return_true' );

function auto_send_invoice_after_creation( $invoice ) {

	$args = array(
		'post_type' => 'mg_task',
		'meta_key'	=> 'add_line_item_to_estimate',
		'meat_value'=> $_POST['id']
	);

	$tasks = get_posts($args);

	foreach ($tasks as $task){
		update_post_meta($task->ID, 'add_line_item_to_invoice', $invoice->get_id());
	}
}
add_action( 'si_create_invoice_on_est_acceptance', 'auto_send_invoice_after_creation' );

function si_format_values($value, $column_slug, $item_data){
	return $value;
}

add_filter( 'si_format_front_end_line_item_value', 'si_format_values', 10, 3 );

function mg_register_post_statuses() {
	$statuses = array(
		'not-started' => __( 'Pending', 'sprout-invoices' ),
		'in-progress' => __( 'In Progress', 'sprout-invoices' ),
		'testing' => __( 'Testing', 'sprout-invoices' ),
		'complete' => __( 'Complete', 'sprout-invoices' )
	);
	foreach ( $statuses as $status => $label ) {
		register_post_status( $status, array(
			'label' => $label,
			'public' => true,
			'exclude_from_search' => false,
			'show_in_admin_all_list' => true,
			'show_in_admin_status_list' => true,
			'label_count' => _n_noop( $label . ' <span class="count">(%s)</span>', $label . ' <span class="count">(%s)</span>' )
		));
	}
}
add_action( 'init', 'mg_register_post_statuses' );

function mg_display_status_label( $statuses ) {
	global $post; // we need it to check current post status
	$custom_status = array('not-started','in-progress','testing','complete');
	if( !in_array(get_query_var( 'post_status' ),$custom_status)){ // not for pages with all posts of this status
		if( $post->post_status == 'not-started' ){ // если статус поста - Архив
			return array('Pending'); // returning our status label
		}
		elseif( $post->post_status == 'in-progress' ){ // если статус поста - Архив
			return array('In Progress'); // returning our status label
		}
		elseif( $post->post_status == 'testing' ){ // если статус поста - Архив
			return array('Testing'); // returning our status label
		}
		elseif( $post->post_status == 'complete' ){ // если статус поста - Архив
			return array('Complete'); // returning our status label
		}
	}
	return $statuses; // returning the array with default statuses
}

add_filter( 'display_post_states', 'mg_display_status_label' );

add_filter( 'gform_post_status_options', 'mg_add_custom_post_status' );
function mg_add_custom_post_status( $post_status_options ) {
	$post_status_options['not-started'] = 'Pending';
	$post_status_options['in-progress'] = 'In Progress';
	$post_status_options['testing'] = 'Testing';
	$post_status_options['complete'] = 'Complete';
	return $post_status_options;
}

