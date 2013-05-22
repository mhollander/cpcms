<?php

require_once("config.php");
require_once("Docket.php");

// foreach dir
// open dir
// for each file
$file = $docketDir . "2010\\51\\CP\\1000\\CP-51-CR-0000011-2010.pdf";
$file = $docketDir . "2009\\01\\CR\\1000\\CP-01-CR-0000012-2009.pdf";

getAndProcessFiles($GLOBALS['docketDir']);
//processDocket($file);


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

function processDocket($file)
{
	$command = $GLOBALS['toolsDir'] . $GLOBALS['pdftotext']	. " -layout \"" . $file . "\" \"" . $GLOBALS['tempFile'] . "\"";
	system($command, $ret);
	if($GLOBALS['debug'])
		print "\nThe pdftotext command: $command \n";

	if ($ret == 0)
	{
		//print $filename . "<br />";
		$thisDocket = file($GLOBALS['tempFile']);

		$docket = new Docket();

		if ($docket->isDocketSheet($thisDocket[1]))
		{
			// if this is a regular docket sheet, use the regular parsing function
			$docket->readArrestRecord($thisDocket);
		}
		
		$docket->writeDocketToDatabase($GLOBALS['db']);
		
		$docketNumber = $docket->getDocketNumber();
		
		if($GLOBALS['debug'])
			$docket->simplePrint();
		else
			print "\n" . $docketNumber[0];
	}
}

?>