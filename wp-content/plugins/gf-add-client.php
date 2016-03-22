<?php

/**
 * Plugin Name: Connect Gravity Forms to Create Clients
 * Description: Connect Gravity Forms to the sprout apps to generate leads
 * Author: Marc Gratch
 * Author URI: https://marcgratch.com
 */

add_action( 'plugins_loaded', 'load_gf_add_client', 120 ); // load up after Sprout Clients

function load_gf_add_client(){
	add_action( 'gform_after_create_post_7', 'set_leads', 10, 3 );

	class SC_Local_Data extends SI_Countries_States {
		public static function getCountries() {
			return self::$countries;
		}

		public static function getGroupedStates() {
			return self::$grouped_states;
		}
	}

	//add_filter( 'gform_address_types', 'sa_international_address', 10, 2 );
	add_filter( 'gform_predefined_choices', 'add_predefined_choice' );
}

function set_leads( $post_id, $entry, $form ) {

	$user_nicename = apply_filters( 'pre_user_nicename', $entry['1.2'].' '.$entry['1.3'].' '.$entry['1.6'].' '.$entry['1.8'] );

	$user_id = username_exists( $entry[3] );
	if ( !$user_id and email_exists($entry[3]) == false ) {
		$userdata = array(
			'user_login'  =>  $entry[3],
			'user_nicename'  =>  $user_nicename,
			'display_name'  =>  $entry['1.2'].' '.$entry['1.3'].' '.$entry['1.6'].' '.$entry['1.8'],
			'first_name'  =>  $entry['1.3'],
			'last_name'  =>  $entry['1.6'],
			'user_email'  =>  $entry[3],
			'sc_dob'  =>  $entry[5],
			'sc_phone'  =>  $entry[4],
			'role'  =>  'sa_client',
			'user_pass'   =>  NULL  // When creating an user, `user_pass` is expected.
		);
		$user_id = wp_insert_user( $userdata ) ;
	}

	$serialized_address = array(
		'street'    => $entry['8.1'],
		'city'    => $entry['8.3'],
		'zone'    => $entry['17'],
		'postal_code'    => $entry['8.5'],
		'country'    => $entry['16']
	);

	$data = array(
			'_associated_users' => $user_id,
			'_phone' => $entry[10],
			'_twitter' => $entry[12],
			'_skype' => $entry[11],
			'_facebook' => $entry[13],
			'_linkedin' => $entry[14],
			'_website' => $entry[9],
			'_address' => $serialized_address,
	);

	foreach ( $data as $key => $value ){
		update_post_meta( $post_id, $key, $value );
	}

}

function add_predefined_choice( $choices ) {

	$SC_Local_Data = new SC_Local_Data;
	$countries = $SC_Local_Data->getCountries();
	$states = $SC_Local_Data->getGroupedStates();
	$countries_array = array();
	$states_array = array();

	foreach ( $countries as $key => $val ){
		$countries_array[] = $val."|".$key;
	}

	foreach ( $states as $key => $val ){
		$states_array[] = $key."|optgroup";
		foreach ( $val as $state => $code ){
			$states_array[] = $code."|".$state;
		}
	}

	$choices['Sprout Apps Countries'] = $countries_array;
	$choices['Sprout Apps States'] = $states_array;

	return $choices;
}


function sa_international_address( $address_types, $form_id ) {
	$SC_Local_Data = new SC_Local_Data;
	$countries = $SC_Local_Data->getCountries();
	$states = $SC_Local_Data->getGroupedStates();
	$countries_array = array();
	$states_array = array();
	GF_Fields::get( 'address' )->get_us_states();

	foreach ( $countries as $key => $val ){
		$countries_array[] = $key."|".$val;
	}

	foreach ( $states as $key => $val ){
		$states_array[] = $key."|optgroup";
		foreach ( $val as $code => $state ){
			$states_array[] = $code."|".$state;
		}
	}

	$address_types['sa_international'] = array(
		'label'       => 'Sprout Apps International',
		'country'     => $countries_array,
		'zip_label'   => 'Zip / Postal Code',
		'state_label' => 'State/Province/Region',
		'states'      => $states_array
	);

	return $address_types;
}

/**
 * Filter Gravity Forms select field display to wrap optgroups where defined
 * USE:
 * set the value of the select option to `optgroup` within the form editor. The
 * filter will then automagically wrap the options following until the start of
 * the next option group
 */

add_filter( 'gform_field_content', 'filter_gf_select_optgroup', 10, 2 );
function filter_gf_select_optgroup( $input, $field ) {
	if ( $field->type == 'select' ) {
		$opt_placeholder_regex = strpos($input,'gf_placeholder') === false ? '' : "<\s*?option.*?class='gf_placeholder'>[^<>]+<\/option\b[^>]*>";
		$opt_regex = "/<\s*?select\b[^>]*>" . $opt_placeholder_regex . "(.*?)<\/select\b[^>]*>/i";
		$opt_group_regex = "/<\s*?option\s*?value='optgroup\b[^>]*>([^<>]+)<\/option\b[^>]*>/i";

		preg_match($opt_regex, $input, $opt_values);
		$split_options = preg_split($opt_group_regex, $opt_values[1]);
		$optgroup_found = count($split_options) > 1;

		// sometimes first item in the split is blank
		if( strlen($split_options[0]) < 1 ){
			unset($split_options[0]);
			$split_options = array_values( $split_options );
		}

		if( $optgroup_found ){
			$fixed_options = '';
			preg_match_all($opt_group_regex, $opt_values[1], $opt_group_match);
			if( count($opt_group_match) > 1 ){
				foreach( $split_options as $index => $option ){
					$fixed_options .= "<optgroup label='" . $opt_group_match[1][$index] . "'>" . $option . '</optgroup>';
				}
			}
			$input = str_replace($opt_values[1], $fixed_options, $input);
		}
	}

	return $input;
}
