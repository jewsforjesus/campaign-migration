<?php
/**
* Reads a Mailchimp export ( Excel file ) and stores it into the given
* database table
* Uses PhpSpreadsheet library
*/
error_reporting(E_ALL);
require 'vendor/j4mie/idiorm/idiorm.php';
require 'vendor/autoload.php';

use \PhpOffice\PhpSpreadsheet\Spreadsheet;
use \PhpOffice\PhpSpreadsheet\Reader\Xls;
use \PhpOffice\PhpSpreadsheet\Writer\Xlsx;

$db_host = '127.0.0.1';
$db_name = 'ems_bbec';
$db_user = 'root';
$db_pass = '';
ORM::configure('id_column', 'ID');
ORM::configure('mysql:host='.$db_host.';dbname='.$db_name);
ORM::configure('username', $db_user);
ORM::configure('password', '');


$current_date = date("Ymd",time());
$file_to_read =  'data/CM-Import-Events-2014.xlsx';
$table_to_use = 'cm_import_events_2104';
$inputFileType = PhpOffice\PhpSpreadsheet\IOFactory::identify($file_to_read);
$reader = PhpOffice\PhpSpreadsheet\IOFactory::createReader($inputFileType);
$spreadsheet = $reader->load($file_to_read);
$sheetData = $spreadsheet->getActiveSheet()->toArray(null, true, true, true);

// Remove the first row which is the header
unset($sheetData[1]);

foreach( $sheetData as $data){

	$contact = ORM::for_table($table_to_use )->create();

	$contact->EmailAddress = $data['C'];
	$contact->FirstName = addslashes($data['A']);
	$contact->LastName = $data['B'];
	$contact->Language = $data['D'];
	$contact->JFJBranch = $data['F'];
	$contact->JFJStaffStatus = $data['G'];
	$contact->CreatedDatetime = date('Y-m-d H:i:s');
	$contact->MissionaryCode = $data['H'];
	$contact->MissionaryAssignment = $data['I'];
	$contact->FirstSourceAppealCode = $data['L'];
	$contact->BBECSystemID = $data['P'];
	$contact->BBECLookupID = $data['Q'];
	$contact->Tags = $data['BB'];
	$contact->ContactCode = $data['E'];
	$contact->HouseholdFirstRecognitionAmount = $data['J'];
	$contact->JFJStaffRole = $data['W'];
	$contact->HouseholdLastRecognitionAmount = $data['M'];
	$contact->HouseholdLargestRecognitionAmount = $data['N'];
	$contact->LastDeputationDate = $data['O'];
	$contact->Address = $data['R'];
	$contact->LifetimeHouseholdRecognitionAmount = $data['V'];
	$contact->DaysSinceHouseholdFirstRecognition = $data['X'];
	$contact->DaysSinceHouseholdLastRecognition = $data['Y'];
	$contact->DaysSinceLastInteraction = $data['Z'];
	$contact->DaysSinceAddedtoBBEC = $data['AA'];
	$contact->InteractionSummary = $data['AB'];
	$contact->InteractionDate = $data['AC'];
	$contact->DaysSinceHouseholdLargestRecognition = $data['AD'];
	$contact->IsChurchContact = $data['AE'];
	//$contact->Salutation = $data['AH'];
	$contact->UnbouncePageID = $data['S'];
	$contact->UnbouncePageVariant = $data['T'];
	$contact->UnbounceSubmissionDate = $data['U'];


	try{
		$contact->save();	
	} catch ( Exception $e) {
		// Print out the email address of the record that it fails to store
		print $data['C'] . PHP_EOL;
	}
	

}