<?php

require_once("config.php");
require_once("Docket.php");
require_once("ArrestSummary.php");
require_once("getDocketsGranular.php");
// foreach dir
// open dir
// for each file
//$file = $docketDir . "2010\\51\\CP\\1000\\CP-51-CR-0000011-2010.pdf";
//$file = $docketDir . "2009\\01\\CR\\1000\\CP-01-CR-0000012-2009.pdf";

$processDir = $GLOBALS['docketDir'];

$options = getopt("d::h::c::a::");

if (!empty($options["h"]))
{
	print "Usage: php processDocketSheets.php [-d\"<directory name>\"] [-h]\n";
	print "-d should be followed by a directory name where processing can start\n";
	print "-c Means that you want continuous processing of the contDirectory";
	print "-a Means that you want to process a directory with LOTS of files in it.  This allows use of an iterator rather than reading everything into memory";
	print "-h shows this message\n";
	exit;
}

if (!empty($options["d"]))
	getAndProcessFiles($options["d"]);;
	
if (!empty ($options["c"]))
	processContinuous();

if (!empty ($options["a"]))
	processLargeDir();

function getAndProcessFiles($dir)
{
	if ($handle = opendir($dir)) 
	{ 
		while (false !== ($file = readdir($handle))) 
		{
			 if ($file != "." && $file != "..") 
			 { 
				$fullFileName = $dir . DIRECTORY_SEPARATOR . $file; 
				if(is_dir($fullFileName)) 
					getAndProcessFiles($fullFileName); 
				else if (fnmatch("*pdf", $file))
					processDocket($fullFileName);
			}
		}
	}
}

function processLargeDir()
{
	foreach (new DirectoryIterator($GLOBALS['contDocketDir']) as $fileInfo)
	{
		if ($fileInfo->isDot() || $fileInfo->isDir())
			continue;
		$file = $fileInfo->getPathname();
		if (filesize($file) > 1)
		{
			processDocket($file);
			// and then delete the file so that we don't reprocess it
			//unlink($file);
		}
	}
}

function processContinuous()
{	
	while (	$files = scandir($GLOBALS['contDocketDir']))
	{
		$processedfile = false;
		foreach (range(3, count($files)) as $num)
		{
			$file = $GLOBALS['contDocketDir'] . DIRECTORY_SEPARATOR . $files[$num-1];
			if (filesize($file) > 1)
			{
				processDocket($file);
				// and then delete the file so that we don't reprocess it
				//unlink($file);
				$processedfile = true;
			}
			else
				print ".";
		}
		
		clearstatcache();
		if (!$processedfile)
		{
			print ".";
			sleep(5);
		}
	}
				
	/*		
			{
				// try to get another file
				if (count($files) > 3)
					$file = $GLOBALS['contDocketDir'] . DIRECTORY_SEPARATOR . $files[3];
			}
		if (count($files) > 2) 
		{
			// process the top file in the directory
			$file = $GLOBALS['contDocketDir'] . DIRECTORY_SEPARATOR . $files[2];
			
			// don't process empty temp files waiting to be downloaded by the curl script
			if (filesize($file) < 10)
			{
				// try to get another file
				if (count($files) > 3)
					$file = $GLOBALS['contDocketDir'] . DIRECTORY_SEPARATOR . $files[3];
			}

			if (filesize($file) > 1)
			{
				processDocket($file);
			
				// and then delete the file so that we don't reprocess it
				unlink($file);
			}
			else
			{
			
				// clear the cache so that when we check again, we don't get a cached filesize result
				clearstatcache();
				print ".";
				sleep(5);
			}

		}
		else
		{
			print ".";
			sleep(1);
		}
	}
	*/
}

function processDocket($file)
{
	$command = $GLOBALS['toolsDir'] . $GLOBALS['pdftotext']	. " -layout \"" . $file . "\" \"" . $GLOBALS['tempFile'] . "\"";
	system($command, $ret);
	if($GLOBALS['debug'])
		print "\nThe pdftotext command: $command \n";

	if ($ret == 0)
	{
		print "\n**************" . $file;
		$thisDocket = file($GLOBALS['tempFile']);

		$docket = new Docket();

		if ($docket->isDocketSheet($thisDocket[1]))
		{
			// if this is a regular docket sheet, use the regular parsing function
			$docket->readArrestRecord($thisDocket);
		
			$docket->writeDocketToDatabase($GLOBALS['db']);
		
			if($GLOBALS['debug'])
				$docket->simplePrint();
			else
				print "\nProcessing " . $docket->getDocketNumber();
		}
		
		// otherwise check if this is a summary docketSheet
		if (ArrestSummary::isArrestSummaryFromLine($thisDocket[1]))
		{
			// and process accordingly
			$arrestSummary = new ArrestSummary();
			$arrestSummary->processArrestSummary($thisDocket);
			
			// iterate over all the dockets; if any are summary dockets, download them so they can be later processed	
			// otherwise write the docket to the database
			$ch = initConnection();
			foreach($arrestSummary->getDockets() as $sDocket)
			{
				if ($sDocket->getIsArchived())
				{
					//$basedir = ".";
					//$baseURL = "http://ujsportal.pacourts.us/DocketSheets/CPReport.ashx?docketNumber=";
					downloadDocket($ch, $sDocket->getFirstDocketNumber());
				}
				else
					$sDocket->writeDocketToDatabase($GLOBALS['db']);
		
			}
			curl_close($ch);

		}
	}
}

?>