<?php

// getDocketsGranular.php - Gets dockets continously from CPCMS and processes them into the database

require_once("config.php");
#require_once("Docket.php");


// if the file was called from the command line, then we want to get the options, etc...
// otherwise, we just want the function definitions
if(__FILE__ == realpath($_SERVER['SCRIPT_FILENAME'])) 
{

	$options = getopt("y:l:h::");

	if (!empty($options["h"]))
	{
		print "Usage: php getDocketsGranular.php [-y\"<year>\"] [-c\"<county code>\"] [-h]\n";
		print "-y The year folder to drop files into";
		print "-l The list of dockets.  Should be in CSV format, withone docket number per line";
		print "-h shows this message\n";
		exit;
	}

	$ignore = array();
    $downloadURL = $GLOBALS['jsonBaseURL'];
    $GLOBALS['json'] = TRUE;

	if (!empty($options["y"]))
		$year = $options["y"];

	if (!empty($options["l"]))
		$fileName = $options["l"];


    getDocketsFromList($year, $fileName);
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

function getDocketsFromList($year, $fileName)
{

    // initialize the curl connection
	$ch = initConnection();
	$endOfLine = 0;
    $counter = 0;

    $dockets = file($fileName) or die ("Unable to open file $fileName");
    foreach ($dockets as $docket)
    {
        $docket = trim($docket);
        $postfix = "json";
        if (file_exists($GLOBALS['contDocketDir'] . DIRECTORY_SEPARATOR . $year . DIRECTORY_SEPARATOR . $docket . "." . $postfix))
        { 
          print "\nSkipping $docket";
          continue;
        }
			
		$noContent = downloadDocket($ch, $docket, $year);
        sleep(2);
			
		if ($noContent)
			$endOfLine += 1;
		else {
			$endOfLine = 0;
        }
		
		if ($endOfLine > 500)
			break;
        
        $counter += 1;
        if($counter > 50)
        {
			curl_close($ch);
            $ch = initConnection();
		}
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

	#print_r(curl_getInfo($ch))
   	return;
}


?>

