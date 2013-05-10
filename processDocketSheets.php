<?php

require_once("config.php");
require_once("Docket.php");

// foreach dir
// open dir
// for each file
$file = $docketDir . "2010\\51\\CP\\1000\\CP-51-CR-0000011-2010.pdf";
processDocket($file);

function processDocket($file)
{
	$command = $GLOBALS['toolsDir'] . $GLOBALS['pdftotext']	. " -layout \"" . $file . "\" \"" . $GLOBALS['tempFile'] . "\"";
	system($command, $ret);
	if($GLOBALS['debug'])
		print "The pdftotext command: $command \n";

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
		
		$docket->simplePrint();
	}
}

?>