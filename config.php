<?php
// config.php
// configuration settings for the expungement generator
//

$debug=false;

// database connection information

$dbPassword = "1428SJuniperSt.";
$dbUser = "cpcms_user";
$dbName = "cpcms";
$dbHost = "localhost";


#$docketDir = "c:" . DIRECTORY_SEPARATOR . "cpcms" . DIRECTORY_SEPARATOR . "dockets";
#$toolsDir = "c:\wamp\\tools\\";
// $baseURL = "http://localhost//";
$docketDir = join(DIRECTORY_SEPARATOR, array("", "home", "hollander", "cpcms", "temp"));
$tempFile = tempnam($docketDir, "FOO");

// note that this works with pdftotext 3.03, but not 3.04!  The text extraction is different in 3.04
#$pdftotext = "pdftotext.exe";
$toolsDir = join(DIRECTORY_SEPARATOR, array("", "usr",  "bin"));
$pdftotext = $toolsDir . DIRECTORY_SEPARATOR . "pdftotext";
$baseURL = "https://ujsportal.pacourts.us/DocketSheets/CPReport.ashx?docketNumber=";
$baseURLSummary = "https://ujsportal.pacourts.us/DocketSheets/CourtSummaryReport.ashx?docketNumber=";
$mdjBaseURL = "http://ujsportal.pacourts.us/DocketSheets/MDJReport.aspx?docketNumber=";
$contDocketDir = join(DIRECTORY_SEPARATOR, array("", "home", "hollander", "cpcms", "dockets"));

// this logs a user in; must happen early on b/c of header requirements with session vars
require_once("dbconnect.php");
//require_once("doLogin.php");

?>