<?php

// getDocketsGranular.php - Gets dockets continously from CPCMS and processes them into the database

require_once("config.php");

$options = getopt("y:s::e::i::h::");

if (!empty($options["h"]))
{
	print "Usage: php getDocketsGranular.php [-y\"<year>\"] [-c\"<county code>\"] [-h]\n";
	print "-y The year that you want to process, for example '2010'\n";
	print "-s Optional. The code for the county that you want to start with (e.g. Philly is 51).  If none is specified, will start with 1\n";
	print "-e Optional. The code for the county that you want to end with (e.g. Philly is 51).  If none is specified, will end with 67\n";
	print "-i Optional.  The type of docket you want to ignore.  Can be CP, MC, or SU.  Can include multiple with a '|'.  If none is specified, will default to including all types of dockets.";
	print "-h shows this message\n";
	exit;
}

$ignore = array();

if (!empty($options["y"]))
	$year = $options["y"];

$countyStart = $countyEnd = "";
if (!empty($options["s"]))
	$countyStart = $options["s"];

if (!empty($options["e"]))
	$countyEnd = $options["e"];

if (!empty($options["i"]))
	$ignore = explode("|", $options["i"]);

getDocketsGranular($year, $countyStart, $countyEnd, $ignore);

function getDocketsGranular($year, $countyStart, $countyEnd, $ignore)
{	
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
		if (!in_array("CP", $ignore))
			getDocketSheetsByYearCountyType($year, $countyNum, "CR", "CP");
		
		// if this is philly, also get all of the MC dockets, which include CR and SU
		if ($countyNum == 51)
		{
			if (!in_array("MC", $ignore))
				getDocketSheetsByYearCountyType($year, $countyNum, "CR", "MC");
			if (!in_array("SU", $ignore))
				getDocketSheetsByYearCountyType($year, $countyNum, "SU", "MC");
		}
	}

	

}

function initConnection()
{
	$ch = curl_init();
	curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
	curl_setopt($ch, CURLOPT_FORBID_REUSE, FALSE);
	return $ch;
}

// returns something in the form CP-01-CR-0002323-2007
function getDocketNumber($num, $year, $countyNumber, $prefix, $court)
{
	return $prefix . "-" . str_pad($countyNumber, 2, "0", STR_PAD_LEFT) . "-" . $court . "-" . str_pad($num, 7, "0", STR_PAD_LEFT) .  "-" . $year;
}

// returns something in the form CP-51-CR-0100323-1999
function getPre2007PhillyDocketNumber($month, $day, $counter, $codef, $year, $countyNumber, $prefix, $court)
{
	return $prefix . "-" . str_pad($countyNumber, 2, "0", STR_PAD_LEFT) . "-" . $court . "-" . str_pad($month, 2, "0", STR_PAD_LEFT) . str_pad($day, 2, "0", STR_PAD_LEFT) . str_pad($counter, 2, "0", STR_PAD_LEFT) . $codef . "-" . $year;
}

function getDocketSheetsByYearCountyType($year, $countyNum, $courtType, $courtLevel)
{
	// we know that the philly numbering scheme is different pre 2006, so we have to use a different function to get our dockets
	if (($year < 2007 && $countyNum == 51) && $courtType != "SU")
		getPhillyPre2007($year, $countyNum, $courtType, $courtLevel);
	else
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
	curl_close($ch);
}


function getPhillyPre2007($year, $countyNum, $courtType, $courtLevel)
{
	/* Here are some pre2007 dockets in Philly:
	CP-51-CR-0126701-1982
	CP-51-CR-0317591-1980
	CP-51-CR-0318711-1986
	CP-51-CR-0800811-1994 - complaint date 8/2/1994
	CP-51-CR-0836081-1992 - complaint date 8/26/1992
	
	So my thought is that the breakdown of the middle numbers is this:
	aabbccd
	where aa = a month, ranging from 01-12
	bb = a day? or is it just a rough day?  It definitely starts at 0 and seems to end, at highest, in the mid-30s, but we can check that
	cc = a counter that seems to go from 01-99
	d = a counter that goes from 1-10 for codefefendants

	which means that we need to march through all three sets of numbers to find the docket sheets.  We can't count
	from 1-300000 like we do for post 2006 cases.  What we have to do is run three separate counters:
	01-15 or 20
	00-40 or 50
	001-999
	
	And we don't want to check too many extra numbers since there is 3-4 second delay to download any given case
	so we will check all three sets of numbers to see if there has been a long period with no new docket downloaded
	*/
	
	// the first docket number to start with and the last to test for possible cases

	// initialize the curl connection
	$ch = initConnection();
	$codefBlanks = $counterBlanks = $dayBlanks = $monthBlanks = 0;

	foreach (range(1,50) as $month)
	{
		$monthBlanks += 1;
		
		foreach (range(0,99) as $day)
		{
			$dayBlanks += 1;

			foreach (range(0,99) as $counter)
			{
				$counterBlanks += 1;
				
				foreach (range(1,9) as $codef)
				{
					$codefBlanks += 1;
					
					if ($counter % 50 == 0)
					{
						curl_close($ch);
						$ch = initConnection();
					}
			
					$docketNumber = getPre2007PhillyDocketNumber($month, $day, $counter, $codef, $year, $countyNum, $courtLevel, $courtType);
					print "\nDownloading: $docketNumber" . " $codefBlanks | $counterBlanks | $dayBlanks | $monthBlanks";
		
					$url = $GLOBALS['baseURL'] . $docketNumber;
					curl_setopt($ch, CURLOPT_URL, $url); 
					//print $url . "\n";
			
					$file = $GLOBALS['contDocketDir'] . DIRECTORY_SEPARATOR . $docketNumber . ".pdf";
					//print $file;
			
					readPDFToFile($ch, $file);
		
					// a) checks the filesize; b) if the file size is < 4kb, increment a counter; if it is more than 4kb, reset the counter; c) if the counter is ever > 500, break the loop
					$filesize = filesize($file);
					if ($filesize < 4000)
						unlink($file);
					else
					{
						$codefBlanks = $counterBlanks = $dayBlanks = $monthBlanks = 0;
					}	
					if ($codefBlanks > 3)
					{
						$codefBlanks = 0;
						break;
					}
				}
				
				if ($counterBlanks > 30)
				{
					$counterBlanks = 0;
					break;
				}
			}
			
			if ($dayBlanks > 5)
			{
				$dayBlanks = 0;
				break;
			}
		}
		if ($monthBlanks > 2)
			break;
	}
	curl_close($ch);
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

