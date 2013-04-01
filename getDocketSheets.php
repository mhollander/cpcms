<?php


// a docket number looks like this: MC-51-CR-2101243-2012
// MC could be replaced with CP; 51 could be replaced with any number (but 51 = Philadelphia)
// The last 4 digist are a year; the CR means criminal (it could also be SU or MD for summary and misc
// A docket sheet can be downloaded with this URL: 
// http://ujsportal.pacourts.us/DocketSheets/CPReport.ashx?docketNumber=MC-51-SU-2101243-2012

$basedir = "C:\cpcms\\dockets\\";
$baseURL = "http://ujsportal.pacourts.us/DocketSheets/CPReport.ashx?docketNumber=";
$mdjBaseURL = "http://ujsportal.pacourts.us/DocketSheets/MDJReport.aspx?docketNumber=";
$baseYear = "2010";
$baseMC = "MC-51-CR-";
$baseCP = "CP-51-CR-";

$mdj = getMDJ();
// @return true if the read was successful, false if there was an error
// @param url - the URL to read
// @param file - the file to write to
function readPDFToFile($ch, $file)
{
	$fp = fopen($file, "w");
	curl_setopt($ch, CURLOPT_FILE, $fp);
	
	curl_exec($ch);
	
	fclose($fp);
	
	return;
}

function initConnection()
{
	$ch = curl_init();
	curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
	curl_setopt($ch, CURLOPT_FORBID_REUSE, FALSE);
	return $ch;
}

// @param $start is the first docket number to start with; usually 1
// @param $end is the last docket number to try.  It doesn't hurt to be very high as there is little overhead
// to downloading lots of crap
// @param $prefix - MC, CP, etc...
// @param court - CR, TR, SU, etc...
function getDocketSheets($start, $end, $prefix, $county, $court, $year, $basedir, $baseURL)
{
	$ch = initConnection();
	$endOfLine = 0;
	foreach (range($start, $end) as $num)
	{
		$dir = 1000*(1+floor($num/1000));
		$storeDir = $basedir . $year . "\\" . $county . "\\" . $court . "\\" . $dir;
		if (!file_exists($storeDir))
			mkdir($storeDir, 0, true);

		if ($num % 50 == 0)
		{
			curl_close($ch);
			$ch = initConnection();
		}
		
		$docketNumber = $prefix . "-" . $county . "-" . $court . "-" . str_pad($num, 7, "0", STR_PAD_LEFT) .  "-" . $year;
		print "\n$docketNumber";
		
		$url = $baseURL . $docketNumber;
		curl_setopt($ch, CURLOPT_URL, $url); 
		//print $url . "\n";
		
		$file = $storeDir . "\\" . $docketNumber . ".pdf";
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
			break;
	}
	curl_close($ch);
}

// get all CP dockets in the state except for philly
foreach (range("52", "67") as $num)
{

	if ($num=="51")
		continue;
	getDocketSheets("1", "100000", "CP", str_pad($num, 2, "0", STR_PAD_LEFT), "CR", "2009", $basedir, $baseURL);

}

// get philly docket sheets
//getDocketSheets("1", "100000", "CP", "51", "CR", "2009", $basedir, $baseURL);
//getDocketSheets("48429", "100000", "MC", "51", "CR", "2009", $basedir, $baseURL);
//getDocketSheets("1", "100000", "MC", "51", "SU", "2009", $basedir, $baseURL);

// loop over all of the MDJ dockets
// MDJ dockets = MJ-#####-TR/CR/CV/SU?/NT-#######-YYYY

foreach ($mdj as $court)
{
/*
	if (($court == "51304" || $court == "51302") || $court == "51301")
		continue;
	getDocketSheets("1", "100000", "MJ", str_pad($court, 5, "0", STR_PAD_LEFT), "CR", "2010", $basedir . "MDJ\\", $mdjBaseURL);
	getDocketSheets("1", "100000", "MJ", str_pad($court, 5, "0", STR_PAD_LEFT), "NT", "2010", $basedir . "MDJ\\", $mdjBaseURL);
*/
}

// getDocketSheets("1", "50000", "MC", "51", "SU", "2010", $basedir, $baseURL);

/*
foreach (range("1001", "100000") as $num)
{
	$dir = 1000*(1+floor($num/1000));
	if (!file_exists($basedir . "\\data\\" . $dir))
		mkdir($basedir . "\\data\\" . $dir);

	if ($num % 50 == 0)
	{
		curl_close($ch);
		$ch = initConnection();
	}
	
	$docketNumber = $baseMC . str_pad($num, 7, "0", STR_PAD_LEFT) . $baseYear;
	print "\n$docketNumber";
	
	$url = $baseURL . $docketNumber;
	curl_setopt($ch, CURLOPT_URL, $url); 
	//print $url . "\n";
	
	$file = $basedir . "\\data\\" . $dir . "\\" . $docketNumber . ".pdf";
	//print $file;
	
	
	
	readPDFToFile($ch, $file);
}

curl_close($ch);
$ch = initConnection();
	
// get the CP data
foreach (range("1", "100000") as $num)
{
	$dir = 1000*(1+floor($num/1000));
	if (!file_exists($basedir . "\\CP\\" . $dir))
		mkdir($basedir . "\\CP\\" . $dir);

	if ($num % 50 == 0)
	{
		curl_close($ch);
		$ch = initConnection();
	}
	
	$docketNumber = $baseCP . str_pad($num, 7, "0", STR_PAD_LEFT) . $baseYear;
	print "\n$docketNumber";
	
	$url = $baseURL . $docketNumber;
	curl_setopt($ch, CURLOPT_URL, $url); 
	//print $url . "\n";
	
	$file = $basedir . "\\CP\\" . $dir . "\\" . $docketNumber . ".pdf";
	//print $file;
	
	
	
	readPDFToFile($ch, $file);
}

*/

function getMDJ()
{
	$mdj = array(51304, 51302, 51301, 51303, 5211, 5217, 5205, 5304, 5218, 5216, 5208, 5310, 5306, 5312, 5231, 5203, 5302, 5236, 5207, 5210, 5206, 5223, 5314, 5204, 5219, 5309, 5313, 5235, 5221, 5305, 5226, 5238, 5225, 5214, 5202, 5228, 5242, 5213, 5227, 5240, 5317, 5247, 5201, 5303, 5243, 5215, 5212, 5220, 5232, 5222, 33303, 33301, 33304, 33302, 36103, 36202, 36101, 36201, 36102, 36302, 36301, 36303, 36304, 57303, 57302, 57304, 57301, 23306, 23204, 23307, 23202, 23303, 23305, 23301, 23304, 23302, 23201, 23203, 23102, 23106, 23105, 23103, 23309, 23101, 24303, 24103, 24302, 24102, 24301, 24304, 42303, 42302, 42304, 42301, 7107, 7203, 7201, 7101, 7111, 7202, 7112, 7109, 7208, 7303, 7108, 7103, 7207, 7102, 7205, 7302, 7301, 7110, 7104, 7106, 50303, 50304, 50101, 50306, 50301, 50305, 50302, 47102, 47307, 47303, 47103, 47101, 47304, 47201, 47306, 47301, 47305, 59301, 56304, 56302, 56303, 56301, 49305, 49201, 49302, 49304, 49101, 49303, 15206, 15403, 15101, 15306, 15205, 15301, 15207, 15307, 15103, 15104, 15105, 15203, 15304, 15305, 15201, 15404, 15401, 15102, 15402, 18304, 18301, 18302, 18303, 46301, 46304, 46302, 46303, 25302, 25303, 25301, 26301, 26302, 26201, 26303, 30201, 30301, 30306, 30302, 30303, 9304, 9301, 9102, 9202, 9101, 9302, 9303, 9103, 9201, 9305, 12204, 12302, 12203, 12101, 12202, 12106, 12301, 12304, 12102, 12205, 12201, 12104, 12303, 12305, 12105, 32135, 32125, 32249, 32241, 32124, 32120, 32130, 32136, 32238, 32243, 32133, 32128, 32253, 32127, 32244, 32246, 32251, 32239, 32123, 32252, 32126, 32132, 32134, 32240, 32254, 32122, 32248, 32237, 32242, 32131, 32129, 32121, 32247, 59303, 59302, 6103, 6105, 6302, 6301, 6104, 6101, 6308, 6202, 6305, 6204, 6201, 6304, 6303, 6306, 6396, 6102, 14302, 14304, 14203, 14202, 14201, 14102, 14101, 14306, 37403, 37493, 39305, 39306, 39201, 39302, 39303, 39307, 39304, 39403, 39401, 39402, 13302, 13301, 13303, 20303, 20301, 20304, 20302, 40201, 40303, 40302, 40301, 54303, 54301, 54302, 41302, 41301, 45103, 45102, 45105, 45301, 45101, 45303, 45302, 45108, 45304, 45106, 2308, 2301, 2201, 2303, 2205, 2302, 2309, 2307, 2101, 2306, 2103, 2204, 2206, 2102, 2304, 2202, 2207, 2203, 2305, 2208, 53101, 53301, 53304, 53302, 53303, 52201, 52101, 52305, 52304, 52301, 52303, 31102, 31301, 31203, 31105, 31201, 31101, 31302, 31107, 31202, 31303, 31104, 31103, 31106, 31108, 11104, 11106, 11201, 11101, 11304, 11307, 11305, 11203, 11301, 11102, 11308, 11303, 11105, 11306, 11309, 11302, 11103, 29101, 29303, 29301, 29102, 29304, 29302, 48101, 48304, 48303, 48302, 35202, 35303, 35301, 35302, 35201, 58302, 58303, 58301, 43201, 43404, 43301, 43401, 43302, 43403, 43304, 43202, 43303, 43402, 38124, 38113, 38128, 38101, 38103, 38117, 38118, 38208, 38119, 38125, 38202, 38116, 38120, 38104, 38112, 38115, 38114, 38123, 38109, 38108, 38102, 38122, 38111, 38105, 38203, 38106, 38121, 38107, 38110, 38204, 26304, 3203, 3208, 3206, 3205, 3301, 3201, 3207, 3210, 3104, 3211, 3302, 3303, 3209, 3204, 3212, 8302, 8304, 8303, 8201, 41305, 41303, 41304, 60303, 60301, 60304, 60302, 55403, 55301, 55401, 21306, 21303, 21201, 21305, 21304, 21301, 21307, 17304, 17303, 16302, 16306, 16303, 16301, 16305, 44303, 34303, 34302, 34301, 4303, 4301, 4302, 17301, 17302, 28302, 28301, 28304, 28303, 37301, 37201, 37401, 27306, 27301, 27307, 27103, 27303, 27201, 27101, 27302, 27310, 27305, 27102, 22301, 22304, 22303, 22302, 10210, 10308, 10305, 10206, 10302, 10103, 10101, 10311, 10201, 10203, 10208, 10301, 10310, 10105, 10104, 10309, 10209, 44304, 44301, 44302, 19204, 19311, 19105, 19203, 19201, 19103, 19307, 19301, 19205, 19309, 19104, 19304, 19202, 19102, 19305, 19303, 19306, 19310, 19101);
	return $mdj;
}

?>