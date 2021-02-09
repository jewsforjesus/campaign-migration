<?php

error_reporting(E_ALL);
require 'vendor/j4mie/idiorm/idiorm.php';
require 'vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Reader\Xls;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

$live_list_id = '4e7469f84cca66107bfc0c39542b6a11';
$test_list_id = 'f382c03b099b30557e36175e4256f5ff';

$env = 'prod';
$select_limit = 10000;

$db_host = '127.0.0.1';
$db_name = 'ems_bbec';
$db_user = 'root';
$db_pass = '';
ORM::configure('id_column', 'ID');
ORM::configure('mysql:host='.$db_host.';dbname='.$db_name);
ORM::configure('username', $db_user);
ORM::configure('password', '');

$results = ORM::for_table('subscribers_onl')->where(array('CMUpdated'=>'No'))->limit($select_limit)->find_many();

/*
* Get a limited number of email addreses from table
* Retrieve details from Campaign Monitor
* Iterate through the Opt-in – Believer  key
*/
if($results){
	foreach($results as $result){
		$EmailAddress = $result->EmailAddress;
		if(update_cm_details($EmailAddress) === true){
			print $EmailAddress . ' updated ' . PHP_EOL;
			$contact = ORM::for_table('subscribers_onl')->where("EmailAddress", $EmailAddress)->find_one();
			//$person = ORM::for_table('person')->where('name', 'Fred Bloggs')->find_one();
			$contact->set('CMUpdated','Yes');
			$contact->save();
		}
	}
}

function get_cm_details($EmailAddress){
$curl = curl_init();
	
	curl_setopt_array($curl, array(
	  CURLOPT_URL => 'https://api.createsend.com/api/v3.2/subscribers/f382c03b099b30557e36175e4256f5ff.json?email='.$EmailAddress,
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



			
