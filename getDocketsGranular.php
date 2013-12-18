<?php

// getDocketsGranular.php - Gets dockets continously from CPCMS and processes them into the database

require_once("config.php");

$options = getopt("y:s::e::h::");

if (!empty($options["h"]))
{
	print "Usage: php getDocketsGranular.php [-y\"<year>\"] [-c\"<county code>\"] [-h]\n";
	print "-y The year that you want to process, for example '2010'\n";
	print "-s Optional. The code for the county that you want to start with (e.g. Philly is 51).  If none is specified, will start with 1\n";
	print "-e Optional. The code for the county that you want to end with (e.g. Philly is 51).  If none is specified, will end with 67\n";
	print "-h shows this message\n";
	exit;
}

if (!empty($options["y"]))
	$year = $options["y"];

$countyStart = $countyEnd = "";
if (!empty($options["s"]))
	$countyStart = $options["s"];

if (!empty($options["e"]))
	$countyEnd = $options["e"];

getDocketsGranular($year, $countyStart, $countyEnd);

function getDocketsGranular($year, $countyStart, $countyEnd)
{	
	// MCT or CP court?
	$prefix = array("MC", "CP");
	
	// set the county start and end parameters
	$c_start = 1;
	$c_end = 67;
	// if a specific county was requested, then start and end and county are all the same
	if (!empty($countyStart))
		$c_start = $countyStart;	
	if (!empty($countyEnd))
		$c_end = $countyEnd;	

	print "\nDownloading docket sheets from the year $year starting with county number $c_start and ending with $c_end.";
	// cycle through each county
	foreach (range($c_start, $c_end) as $countyNum)
	{
		getDocketSheetsByYearCountyType($year, $countyNum, "CR", "CP");
		
		// if this is philly, also get all of the MC dockets, which include CR and SU
		if ($countyNum == 51)
		{
			getDocketSheetsByYearCountyType($year, $countyNum, "CR", "MC");
			getDocketSheetsByYearCountyType($year, $countyNum, "SU", "MC");
		}
	}
	curl_close($ch);
	

}

function initConnection()
{
	$ch = curl_init();
	curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
	curl_setopt($ch, CURLOPT_FORBID_REUSE, FALSE);
	return $ch;
}

// returns something in the form CP-01-CR-0002323-1999
function getDocketNumber($num, $year, $countyNumber, $prefix, $court)
{
	return $prefix . "-" . str_pad($countyNumber, 2, "0", STR_PAD_LEFT) . "-" . $court . "-" . str_pad($num, 7, "0", STR_PAD_LEFT) .  "-" . $year;
}

function getDocketSheetsByYearCountyType($year, $countyNum, $courtType, $courtLevel)
{
	// the first docket number to start with and the last to test for possible cases
	$start = 1;
	$end = 300000;

	// initialize the curl connection
	$ch = initConnection();
	$endOfLine = 0;

	// cycle through each docket in the county
	foreach (range($start, $end) as $num)
	{

		// need to add in detection of county 51 to do both CP and MC
		// need to add in detection of county to do both MC CR and SU
		
		// refresh the curl connection from time to time
		if ($num % 50 == 0)
		{
			curl_close($ch);
			$ch = initConnection();
		}
		
		$docketNumber = getDocketNumber($num, $year, $countyNum, $courtLevel, $courtType);
		print "\nDownloading: $docketNumber";
		
		$url = $GLOBALS['baseURL'] . $docketNumber;
		curl_setopt($ch, CURLOPT_URL, $url); 
		//print $url . "\n";
		
		$file = $GLOBALS['contDocketDir'] . DIRECTORY_SEPARATOR . $docketNumber . ".pdf";
		//print $file;
		
		readPDFToFile($ch, $file);
		
		// a) checks the filesize; b) if the file size is < 5kb, increment a counter; if it is more than 5kb, reset the counter; c) if the counter is ever > 500, break the loop
		$filesize = filesize($file);
		if ($filesize < 4000)
		{
			// delete the file so that it doesn't pollute the file system
			unlink($file);
			$endOfLine += 1;
		}
		else
			$endOfLine = 0;
			
		if ($endOfLine > 500)
		{
			//$ch->close();
			break;
		}
			
	}
}

// @param ch - the curl stream
// @param file - the file to write to
function readPDFToFile($ch, $file)
{
	$fp = fopen($file, "w");
	curl_setopt($ch, CURLOPT_FILE, $fp);
	
	curl_exec($ch);	
	fclose($fp);
	
	return;
}



?>

