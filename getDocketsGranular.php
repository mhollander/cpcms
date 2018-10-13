<?php

// getDocketsGranular.php - Gets dockets continously from CPCMS and processes them into the database

require_once("config.php");
#require_once("Docket.php");


// if the file was called from the command line, then we want to get the options, etc...
// otherwise, we just want the function definitions
if(__FILE__ == realpath($_SERVER['SCRIPT_FILENAME'])) 
{

	$options = getopt("y:s::e::i::z::j::m::h::");

	if (!empty($options["h"]))
	{
		print "Usage: php getDocketsGranular.php [-y\"<year>\"] [-c\"<county code>\"] [-h]\n";
		print "-y The year that you want to process, for example '2010'\n";
		print "-s Optional. The code for the county that you want to start with (e.g. Philly is 51).  If none is specified, will start with 1\n";
		print "-e Optional. The code for the county that you want to end with (e.g. Philly is 51).  If none is specified, will end with 67\n";
		print "-i Optional.  The type of docket you want to ignore.  Can be CP, MC, or SU.  Can include multiple with a '|'.  If none is specified, will default to including all types of dockets.";
		print "-z Optional.  Include -z if you want to download summary dockets rather than full dockets for an individual";
		print "-j Optional.  Include -j if you want to download json instead of pdf docket sheets.";
		print "-m Optional.  Include -m if you want to download mdj dockets.";
		print "-h shows this message\n";
		exit;
	}

	$ignore = array();
	$downloadURL = $GLOBALS['baseURL'];
    $GLOBALS['json'] = FALSE;
    $GLOBALS['mdj'] = FALSE;

	if (!empty($options["y"]))
		$year = $options["y"];

	$countyStart = $countyEnd = "";
	if (!empty($options["s"]))
		$countyStart = $options["s"];

	if (!empty($options["e"]))
		$countyEnd = $options["e"];

	if (!empty($options["i"]))
		$ignore = explode("|", $options["i"]);

	if (!empty($options["z"]))
		$downloadURL = $GLOBALS['baseURLSummary'];

    if (!empty($options["j"]))
    {
      $GLOBALS['json'] = TRUE;
      $downloadURL = $GLOBALS['jsonBaseURL'];
    }
    
    if (!empty($options["m"]))
    {
        $GLOBALS['mdj']= TRUE;
    }

    if ($GLOBALS['mdj'])
      getMDJDocketsGranular($year);
    
    else
	    getDocketsGranular($year, $countyStart, $countyEnd, $ignore);
}
else
	$downloadURL = $GLOBALS['baseURL'];

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
	curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_TIMEOUT, 60);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 60);
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
            
            // check to see if we've already downloaded and processed this file.  If so, then skip it.
            $postfix = "pdf";
            if ($GLOBALS['json'])
                $postfix = "json";

            if (file_exists($GLOBALS['contDocketDir'] . DIRECTORY_SEPARATOR . $year . DIRECTORY_SEPARATOR . $docketNumber . "." . $postfix))
              { 
                print "\nSkipping $docketNumber";
                continue;
                  
              }
			
			$noContent = downloadDocket($ch, $docketNumber, $year);
			
			if ($noContent)
				$endOfLine += 1;
			else
				$endOfLine = 0;
		
			if ($endOfLine > 500)
				break;

		}
	}
	curl_close($ch);
}


function getMDJDocketsGranular($year)
{
    $mdj = array(2101, 2102, 2103, 2201, 2202, 2203, 2204, 2205, 2206, 2207, 2208, 2301, 2302, 2303, 2304, 2305, 2306, 2307, 2308, 2309, 3104, 3201, 3203, 3204, 3205, 3206, 3207, 3208, 3209, 3210, 3211, 3212, 3301, 3302, 3303, 4301, 4302, 4303, 5201, 5202, 5203, 5204, 5205, 5206, 5207, 5208, 5210, 5211, 5212, 5213, 5214, 5215, 5216, 5217, 5218, 5219, 5220, 5221, 5222, 5223, 5225, 5226, 5227, 5228, 5231, 5232, 5235, 5236, 5238, 5240, 5242, 5243, 5247, 5302, 5303, 5304, 5305, 5306, 5309, 5310, 5312, 5313, 5314, 5317, 6101, 6102, 6103, 6104, 6105, 6201, 6202, 6204, 6301, 6302, 6303, 6304, 6305, 6306, 6308, 6396, 7101, 7102, 7103, 7104, 7106, 7107, 7108, 7109, 7110, 7111, 7112, 7201, 7202, 7203, 7205, 7207, 7208, 7301, 7302, 7303, 8201, 8302, 8303, 8304, 9101, 9102, 9103, 9201, 9202, 9301, 9302, 9303, 9304, 9305, 10101, 10103, 10104, 10105, 10201, 10203, 10206, 10208, 10209, 10210, 10301, 10302, 10305, 10308, 10309, 10310, 10311, 11101, 11102, 11103, 11104, 11105, 11106, 11201, 11203, 11301, 11302, 11303, 11304, 11305, 11306, 11307, 11308, 11309, 12101, 12102, 12104, 12105, 12106, 12201, 12202, 12203, 12204, 12205, 12301, 12302, 12303, 12304, 12305, 13301, 13302, 13303, 14101, 14102, 14201, 14202, 14203, 14302, 14304, 14306, 15101, 15102, 15103, 15104, 15105, 15201, 15203, 15205, 15206, 15207, 15301, 15304, 15305, 15306, 15307, 15401, 15402, 15403, 15404, 16301, 16302, 16303, 16305, 16306, 17301, 17302, 17303, 17304, 18301, 18302, 18303, 18304, 19101, 19102, 19103, 19104, 19105, 19201, 19202, 19203, 19204, 19205, 19301, 19303, 19304, 19305, 19306, 19307, 19309, 19310, 19311, 20301, 20302, 20303, 20304, 21201, 21301, 21303, 21304, 21305, 21306, 21307, 22301, 22302, 22303, 22304, 23101, 23102, 23103, 23105, 23106, 23201, 23202, 23203, 23204, 23301, 23302, 23303, 23304, 23305, 23306, 23307, 23309, 24102, 24103, 24301, 24302, 24303, 24304, 25301, 25302, 25303, 26201, 26301, 26302, 26303, 26304, 27101, 27102, 27103, 27201, 27301, 27302, 27303, 27305, 27306, 27307, 27310, 28301, 28302, 28303, 28304, 29101, 29102, 29301, 29302, 29303, 29304, 30201, 30301, 30302, 30303, 30306, 31101, 31102, 31103, 31104, 31105, 31106, 31107, 31108, 31201, 31202, 31203, 31301, 31302, 31303, 32120, 32121, 32122, 32123, 32124, 32125, 32126, 32127, 32128, 32129, 32130, 32131, 32132, 32133, 32134, 32135, 32136, 32237, 32238, 32239, 32240, 32241, 32242, 32243, 32244, 32246, 32247, 32248, 32249, 32251, 32252, 32253, 32254, 33301, 33302, 33303, 33304, 34301, 34302, 34303, 35201, 35202, 35301, 35302, 35303, 36101, 36102, 36103, 36201, 36202, 36301, 36302, 36303, 36304, 37201, 37301, 37401, 37403, 37493, 38101, 38102, 38103, 38104, 38105, 38106, 38107, 38108, 38109, 38110, 38111, 38112, 38113, 38114, 38115, 38116, 38117, 38118, 38119, 38120, 38121, 38122, 38123, 38124, 38125, 38128, 38202, 38203, 38204, 38208, 38209, 39201, 39302, 39303, 39304, 39305, 39306, 39307, 39401, 39402, 39403, 40201, 40301, 40302, 40303, 41301, 41302, 41303, 41304, 41305, 42301, 42302, 42303, 42304, 43201, 43202, 43301, 43302, 43303, 43304, 43401, 43402, 43403, 43404, 44301, 44302, 44303, 44304, 45101, 45102, 45103, 45105, 45106, 45108, 45301, 45302, 45303, 45304, 46301, 46302, 46303, 46304, 47101, 47102, 47103, 47201, 47301, 47303, 47304, 47305, 47306, 47307, 48101, 48302, 48303, 48304, 49101, 49201, 49302, 49303, 49304, 49305, 50101, 50301, 50302, 50303, 50304, 50305, 50306, 51301, 51302, 51303, 51304, 52101, 52201, 52301, 52303, 52304, 52305, 53101, 53301, 53302, 53303, 53304, 54301, 54302, 54303, 55301, 55401, 55403, 56301, 56302, 56303, 56304, 57301, 57302, 57303, 57304, 58301, 58302, 58303, 59301, 59302, 59303, 60301, 60302, 60303, 60304);
                 
    foreach ($mdj as $court)
    {
        
        // skip these three MDJ courts.  But why?
        //if (($court == "51304" || $court == "51302") || $court == "51301")
        //    continue;
        
        getMDJDocketSheet(str_pad($court, 5, "0", STR_PAD_LEFT), "CR", $year);
        getMDJDocketSheet(str_pad($court, 5, "0", STR_PAD_LEFT), "NT", $year);
    }
}

function getMDJDocketSheet($court, $prefix, $year)
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
			
		$docketNumber = join("-", array("MJ", $court, $prefix, str_pad($num,7,"0",STR_PAD_LEFT), $year));
           
        // check to see if we've already downloaded and processed this file.  If so, then skip it.
        $postfix = "pdf";
        if ($GLOBALS['json'])
            $postfix = "json";

        if (file_exists($GLOBALS['contDocketDir'] . DIRECTORY_SEPARATOR . $year . DIRECTORY_SEPARATOR . $docketNumber . "." . $postfix))
        { 
          print "\nSkipping $docketNumber";
          continue;
        }
			
		$noContent = downloadDocket($ch, $docketNumber, $year);
			
		if ($noContent)
			$endOfLine += 1;
		else
			$endOfLine = 0;
		
		if ($endOfLine > 500)
			break;

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
					// check to see if this docket number is already in our database; if so, don't redownload as this takes a long time
                    // check to see if we've already downloaded and processed this file.  If so, then skip it.
                    $postfix = "pdf";
                    if ($GLOBALS['json'])
                    $postfix = "json";

                    if (file_exists($GLOBALS['contDocketDir'] . DIRECTORY_SEPARATOR . $year . DIRECTORY_SEPARATOR . $docketNumber . "." . $postfix))
                    { 
                        print "\nSkipping $docketNumber";
                        continue;
                    }

#					if (duplicateDocket($docketNumber))
#					{
#						"\nDuplicate Case: $docketNumber";
#						break;
#					}


					print "\nDownloading: $docketNumber" . " $codefBlanks | $counterBlanks | $dayBlanks | $monthBlanks";
		    
					$url = $GLOBALS['downloadURL'] . $docketNumber;
					curl_setopt($ch, CURLOPT_URL, $url); 
					print $url . "\n";

        			$noContent = downloadDocket($ch, $docketNumber, $year);
			

                    
					//$file = $GLOBALS['contDocketDir'] . DIRECTORY_SEPARATOR . $docketNumber . ".pdf";
					//print $file;
			
				#	readPDFToFile($ch, $file);
		
					// a) checks the filesize; b) if the file size is < 4kb, increment a counter; if it is more than 4kb, reset the counter; c) if the counter is ever > 500, break the loop
					#$filesize = filesize($file);
					if (!$noContent) // $filesize < 4000)
						$codefBlanks = $counterBlanks = $dayBlanks = $monthBlanks = 0;
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

function downloadDocket($ch, $docketNumber, $year)
{
	return downloadDocketWithPrefix($ch, $docketNumber, $year, null);
}

// downloads a specified docket number from CPCMS and stores it in the filesystem
// if $prefix is not null, adds the prefix followed by "#" before the filename so that we can store some information
// in the filename itself
function downloadDocketWithPrefix($ch, $docketNumber, $year, $prefix)
{
	print "\ndownloading docket: $docketNumber";
	// check to see if this docket number is already in our database; if so, don't redownload as this takes a long time
#	if (duplicateDocket($docketNumber))
#	{
#		"\nDuplicate Case: $docketNumber";
#		return false;
#	}
		
	print "\nDownloading: $docketNumber";
	
	$url = $GLOBALS['downloadURL'] . $docketNumber;

	curl_setopt($ch, CURLOPT_URL, $url); 
	print $url . "\n";
	
    $postfix = "pdf";
    if ($GLOBALS['json'])
      $postfix = "json";
    
	if (is_null($prefix))
		$file = $GLOBALS['contDocketDir'] . DIRECTORY_SEPARATOR . $year . DIRECTORY_SEPARATOR . $docketNumber . "." . $postfix;
	else
		$file = $GLOBALS['contDocketDir'] . DIRECTORY_SEPARATOR . $year . DIRECTORY_SEPARATOR . $prefix . "#" . $docketNumber . "." . $postfix;
	
	print $file;
	
	readPDFToFile($ch, $file);
	
	// a) checks the filesize; b) if the file size is < 5kb, increment a counter; if it is more than 5kb, reset the counter; c) if the counter is ever > 500, break the loop
	$filesize = filesize($file);

	if ($filesize < 2000)
	{
		// delete the file so that it doesn't pollute the file system
		unlink($file);
		return true;
	}
	else
		return false;
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

// checks the database to see if this docket number already exists; if so, returns false
#function duplicateDocket($docketNumber)
#{
#	$id = Docket::checkInDB($GLOBALS['db'], "`Case`", "docket", $docketNumber, "", "", "id");
		
	// if ID = 0, that means that this case is not in the DB
#	if ($id==0)
#		return false;
#	else
#		return true;
#}


?>

