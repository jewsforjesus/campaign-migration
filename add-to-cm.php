<?php

error_reporting(E_ERROR);
require 'vendor/j4mie/idiorm/idiorm.php';
require 'vendor/autoload.php';

use Monolog\Logger;
use Monolog\Handler\StreamHandler;

$log = new Logger('CM-Import');
$log_filename = 'logs/cm-import-'.time().'.log';
$log->pushHandler(new StreamHandler(__DIR__.'/'.$log_filename, Logger::INFO));
$log->info('Import Into Campaign Monitor', array('Start Time' => date('Y-m-d H:i:s')));

$table_to_use = 'cm_import_events_2104';
$subscrtions_to_add = array("Special event invitations","Partnering opportunities");
$live_list_id = '4e7469f84cca66107bfc0c39542b6a11';
$test_list_id = 'f382c03b099b30557e36175e4256f5ff';

$env = 'dev';
$select_limit = 1;

$db_host = '127.0.0.1';
$db_name = 'ems_bbec';
$db_user = 'root';
$db_pass = '';
ORM::configure('id_column', 'ID');
ORM::configure('mysql:host='.$db_host.';dbname='.$db_name);
ORM::configure('username', $db_user);
ORM::configure('password', '');

$results = ORM::for_table($table_to_use)->where(array('ExistsInMC'=>'No','CMUpdated'=>'No'))->limit($select_limit)->find_array();
/*
* Get a limited number of records from table
* Retrieve details from Campaign Monitor
*/
if($results){
	foreach($results as $result){
		unset($believer_subscriptions);
		$EmailAddress = $result['EmailAddress'];
		
		// check to see if email address/contact exists in Campaign Monitor
		$exist_check = get_cm_details($EmailAddress);
		print 'Processing Email Address: '.$EmailAddress . PHP_EOL;

		if(isset($exist_check['Code'])){
			if($exist_check['Code'] == 203){

				$add_result = add_to_cm(refine_data($result));
				if($add_result == true){
					print 'Contact Added: ' . $EmailAddress . PHP_EOL;

					$contact_info = ORM::for_table($table_to_use)->find_one($result['ID']);
					$contact_info->CMUpdated = "Yes";
					$contact_info->ExistsInMC = "Yes";
					$contact_info->save();
				}else{
					$contact_info = ORM::for_table($table_to_use)->find_one($result['ID']);
					$contact_info->CMUpdated = "No";
					$contact_info->save();					
				}
			}else{ 
				$log->info('Error received from Campaign Monitor', $exist_check);
			}
		}else{ 
			// At this point the contact exists
			// We need to retain any existing subscriptions
			print 'Email address already in Campaign Monitor : ' . $EmailAddress .  PHP_EOL; 
			
			$existing_subs = array();
			$update_post_data = array();

			if(isset($exist_check['CustomFields'])){
				// Gather all the existing items user is currentlys subscribed to
				foreach ($exist_check['CustomFields'] as $key => $custom_field) {
					if($custom_field['Key'] == 'Opt-in – Believer'){
						$existing_subs[] = $custom_field['Value'];
					}
				}

				// Add required subscriptions
				if(!in_array("Special event invitations",$existing_subs)){
					$existing_subs[] = "Special event invitations";
				}

				// Add all subsriptions to the user's record
				foreach($existing_subs as $sub){
					$update_post_data['CustomFields'][] = array("Key"=>'Opt-in – Believer',"Value"=>$sub);
				}

				$update_post_data['ConsentToTrack'] = "Yes";
				$update_post_data['Contact Code'] = $result['ContactCode'];

				$json_post = json_encode($update_post_data, JSON_PRETTY_PRINT);

				unset($believer_subscriptions);
				// Update contact
				if($env == 'dev'){
					$update_endpoint = 'https://api.createsend.com/api/v3.2/subscribers/'.$test_list_id.'.json?email=' . $EmailAddress;
				}else{
					$update_endpoint = 'https://api.createsend.com/api/v3.2/subscribers/'.$live_list_id.'.json?email=' . $EmailAddress;
				}
				print $update_endpoint . PHP_EOL;

			$curl = curl_init();

			curl_setopt_array($curl, array(
			  CURLOPT_URL => $update_endpoint,
			  CURLOPT_RETURNTRANSFER => true,
			  CURLOPT_ENCODING => '',
			  CURLOPT_MAXREDIRS => 10,
			  CURLOPT_TIMEOUT => 0,
			  CURLOPT_FOLLOWLOCATION => true,
			  CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
			  CURLOPT_CUSTOMREQUEST => 'PUT',
			  CURLOPT_POSTFIELDS =>$json_post,
			  CURLOPT_HTTPHEADER => array(
			    'Authorization: Basic eGFNN1U2dkNYeUpHcm1EdkozQmVLeFZDaVFiRGZiei9CSzFLWmRkaC92UVNNeXRVUTcxMjY2bU53ZjFtOGhYVXlXdnljL09meVd3dHhoZ0cwVGkvaWZGSm5qcTVxQmQyeFVJOFNJV2h0RXBuTURudXc5WHpldGF2Qzl1K0VmL09hUmNNU3MxeXNWNWNqUUExRXVXdVFRPT06',
			    'Content-Type: application/json'
			  ),
			));

			$response = curl_exec($curl);

			curl_close($curl);
			echo $response;

			$contact_info = ORM::for_table($table_to_use)->find_one($result['ID']);
			$contact_info->CMUpdated = "Yes";
			$contact_info->ExistsInMC = "Yes";
			$contact_info->save();	

			}


			unset($believer_subscriptions);

		}
	}
}

/*
* add_to_cm
* Add a new contact to list via Campaign Monitor API
* @param array $data
*/
function add_to_cm($data){

	global $env;
	global $live_list_id;
	global $test_list_id;
	global $log;

	if($env == 'dev' ){
		$list_id = $test_list_id;
	}else{
		$list_id = $live_list_id;
	}
	$post_array['EmailAddress'] = $data['Email Address']; 
	$post_array['ConsentToTrack'] = "Yes";
	unset($data['ID']);
	unset($data['Email Address']);
	unset($data['']);

	$post_array['CustomFields'] = $data;

	$json_post = json_encode($post_array, JSON_PRETTY_PRINT);


	//return $json_post;

	$curl = curl_init();

	curl_setopt_array($curl, array(
	  CURLOPT_URL => 'https://api.createsend.com/api/v3.2/subscribers/' . $list_id . '.json',
	  CURLOPT_RETURNTRANSFER => true,
	  CURLOPT_ENCODING => '',
	  CURLOPT_MAXREDIRS => 10,
	  CURLOPT_TIMEOUT => 0,
	  CURLOPT_FOLLOWLOCATION => true,
	  CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
	  CURLOPT_CUSTOMREQUEST => 'POST',
	  CURLOPT_POSTFIELDS =>$json_post,
	  CURLOPT_HTTPHEADER => array(
	    'Content-Type: application/json',
	    'Authorization: Basic eGFNN1U2dkNYeUpHcm1EdkozQmVLeFZDaVFiRGZiei9CSzFLWmRkaC92UVNNeXRVUTcxMjY2bU53ZjFtOGhYVXlXdnljL09meVd3dHhoZ0cwVGkvaWZGSm5qcTVxQmQyeFVJOFNJV2h0RXBuTURudXc5WHpldGF2Qzl1K0VmL09hUmNNU3MxeXNWNWNqUUExRXVXdVFRPT06'
	  ),
	));

	$response = curl_exec($curl);
	curl_close($curl);

	$filtered_response = str_replace('"', '', $response);
	if($filtered_response == $post_array['EmailAddress']){
		return true;
	}else{
		$captured_response[] = $response;
		$log->info('Campaign Monitor Add Response: ', $captured_response);
		return false;
	}

}

function get_cm_details($EmailAddress){
	
	$curl = curl_init();
	
	global $env;
	global $live_list_id;
	global $test_list_id;
	if($env == 'dev'){
		$list_id = $test_list_id;
	}else{
		$list_id = $live_list_id;
	}
	$endpoint = 'https://api.createsend.com/api/v3.2/subscribers/' . $list_id. '.json?email='.$EmailAddress;

	curl_setopt_array($curl, array(
	  CURLOPT_URL => $endpoint,
	  CURLOPT_RETURNTRANSFER => true,
	  CURLOPT_ENCODING => '',
	  CURLOPT_MAXREDIRS => 10,
	  CURLOPT_TIMEOUT => 0,
	  CURLOPT_FOLLOWLOCATION => true,
	  CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
	  CURLOPT_CUSTOMREQUEST => 'GET',
	  CURLOPT_HTTPHEADER => array(
	    'includetrackingpreference: true',
	    'Authorization: Basic eGFNN1U2dkNYeUpHcm1EdkozQmVLeFZDaVFiRGZiei9CSzFLWmRkaC92UVNNeXRVUTcxMjY2bU53ZjFtOGhYVXlXdnljL09meVd3dHhoZ0cwVGkvaWZGSm5qcTVxQmQyeFVJOFNJV2h0RXBuTURudXc5WHpldGF2Qzl1K0VmL09hUmNNU3MxeXNWNWNqUUExRXVXdVFRPT06'
	  ),
	));

	$response = curl_exec($curl);

	curl_close($curl);
	return json_decode($response,true);

}

/*
* refine_data
* 
* @param array $data
*/
function refine_data($data){

	ksort($data);

	unset($data['City']);
	unset($data['State']);
	unset($data['Country']);
	unset($data['CreatedDatetime']);
	unset($data['Tags']);
	unset($data['ONL']);
	unset($data['EMSSaved']);
	unset($data['BBECSaved']);
	unset($data['AreYouJewish']);
	unset($data['Subscription']);
	unset($data['DataSource']);
	unset($data['ExistsInMC']);
	unset($data['CMUpdated']);


	foreach($data as $key=>$value){

		if($key != 'ID'){
			// Get the proper CM version of the field name
			$new_key = get_field_mapping($key);
			$data[$new_key] = $data[$key];
			unset($data[$key]);
		}
		
	}

	$refined_data_array = array();
	foreach($data as $key=>$value){
		if(!empty($key)){
			if($key !== 'Email Address' && $key !== 'ID'){
				$refined_data_array[] = array("Key"=>$key,"Value"=>$value);
			}else{
				$refined_data_array[$key] = $value;
			}
		}
	}

	
	if(isset($data['believer_subscriptions'])){
		foreach($data['believer_subscriptions'] as $value){
			$refined_data_array[] = array("Key"=>'Opt-in – Believer',"Value"=>$value);
		}
	}
 	//$refined_data_array[] = array("Key"=>'Opt-in – Believer',"Value"=>'Monthly Issues articles');
	return($refined_data_array);
}

/* 
* field_mapping
* Returns the field name that Campaign Monitor recognizes for given $key
* @param string $key
*
*/
function get_field_mapping($key){
	//print $key .PHP_EOL;
	$fields = array(
		"EmailAddress"=>"Email Address",
		"FirstName"=>"First Name",
		"LastName"=>"Last Name",
		"Language"=>"Language",
		"JFJBranch"=>"JFJ Branch",
		"JFJStaffStatus"=>"JFJ Staff Status",
		"MissionaryCode"=>"Missionary Code",
		"MissionaryAssignment"=>"Missionary Assignment",
		"FirstSourceAppealCode"=>"First Source Appeal Code",
		"BBECSystemID"=>"BBEC System ID",
		"BBECLookupID"=>"BBEC Lookup ID",
		"HouseholdFirstRecognitionAmount"=>"Household First Recognition Amount",
		"JFJStaffRole"=>"JFJ Staff Role",
		"HouseholdLastRecognitionAmount"=>"Household Last Recognition Amount",
		"HouseholdLargestRecognitionAmount"=>"Household Largest Recognition Amount",
		"LastDeputationDate"=>"Last Deputation Date",
		"Address"=>"Address",
		"LifetimeHouseholdRecognitionAmount"=>"Lifetime Household Recognition Amount",
		"DaysSinceHouseholdFirstRecognition"=>"Days Since Household First Recognition",
		"DaysSinceLastInteraction"=>"Days Since Last Interaction",
		"DaysSinceAddedtoBBEC"=>"Days Since Added to BBEC",
		"InteractionSummary"=>"Interaction Summary",
		"AddressLine1"=>"Address Line 1",
		"AddressLine2"=>"Address Line 2",
		"ZIP"=>"ZIP",
		"DaysSinceHouseholdLastRecognition"=>"Days Since Household Last Recognition",
		"InteractionDate"=>"Interaction Date",
		"Salutation"=>"Salutation",
		"DaysSinceHouseholdLargestRecognition"=>"Days Since Household Largest Recognition",
		"UnbouncePageID"=>"Unbounce Page ID",
		"UnbouncePageVariant"=>"Unbounce Page Variant",
		"UnbounceSubmissionDate"=>"Unbounce Submission Date",
		"LifetimeHouseholdRecognitionCount"=>"Lifetime Household Recognition Count",
		"Age"=>"Age",
		"Gender"=>"Gender",
		"PhoneNumber"=>"Phone Number",
		"IsChurchContact"=>"Is Church Contact",
		"DoyoubelieveinJesus"=>"Do you believe in Jesus?",
		"ContactCode"=>"Contact Code"
		);

	return $fields[$key];
}

function update_cm_details($EmailAddress){

	global $env;
	global $live_list_id;
	global $test_list_id;
	if($env == 'dev' ){
		$list_id = $test_list_id;
	}else{
		$list_id = $live_list_id;
	}
	$curl = curl_init();

	curl_setopt_array($curl, array(
	  CURLOPT_URL => 'https://api.createsend.com/api/v3.2/subscribers/'.$list_id . '.json?email='.$EmailAddress,
	  CURLOPT_RETURNTRANSFER => true,
	  CURLOPT_ENCODING => '',
	  CURLOPT_MAXREDIRS => 10,
	  CURLOPT_TIMEOUT => 0,
	  CURLOPT_FOLLOWLOCATION => true,
	  CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
	  CURLOPT_CUSTOMREQUEST => 'PUT',
	  CURLOPT_POSTFIELDS =>'{
	  "ConsentToTrack": "Yes",
	  "CustomFields": [
	{
	            "Key": "Opt-in – Believer",
	            "Value": "Partnering opportunities"
	        },{
	            "Key": "Opt-in – Believer",
	            "Value": "Monthly ministry updates"
	        },
	        {
	            "Key": "Opt-in – Believer",
	            "Value": "Special event invitations"
	        }
	        ]
	}',
	  CURLOPT_HTTPHEADER => array(
	    'Authorization: Basic eGFNN1U2dkNYeUpHcm1EdkozQmVLeFZDaVFiRGZiei9CSzFLWmRkaC92UVNNeXRVUTcxMjY2bU53ZjFtOGhYVXlXdnljL09meVd3dHhoZ0cwVGkvaWZGSm5qcTVxQmQyeFVJOFNJV2h0RXBuTURudXc5WHpldGF2Qzl1K0VmL09hUmNNU3MxeXNWNWNqUUExRXVXdVFRPT06',
	    'Content-Type: application/json'
	  ),
	));

	$response = curl_exec($curl);
	$info = curl_getinfo($curl);

	curl_close($curl);
	if(isset($info)){
		if($info['http_code'] == 200){
			return true;
		}
	}else{ return false; }

}



			
