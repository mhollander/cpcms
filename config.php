<?php
// config.php
// configuration settings for the expungement generator
//

$debug=true;

// database connection information

$dbPassword = "2xj9cpJXt4vBxxmf";
$dbUser = "cpcms";
$dbName = "cpcms1999";
$dbHost = "localhost";

require_once("c:\wamp\\tools\dbconnect.php");

$docketDir = "c:" . DIRECTORY_SEPARATOR . "cpcms" . DIRECTORY_SEPARATOR . "dockets";
$toolsDir = "c:\wamp\\tools\\";
// $baseURL = "http://localhost//";
$tempFile = tempnam($docketDir, "FOO");
$pdftotext = "pdftotext.exe";
$baseURL = "https://ujsportal.pacourts.us/DocketSheets/CPReport.ashx?docketNumber=";
$baseURLSummary = "https://ujsportal.pacourts.us/DocketSheets/CourtSummaryReport.ashx?docketNumber=";
$mdjBaseURL = "http://ujsportal.pacourts.us/DocketSheets/MDJReport.aspx?docketNumber=";
$contDocketDir = "c:" . DIRECTORY_SEPARATOR . "cpcms" . DIRECTORY_SEPARATOR . "contDocket";

// this logs a user in; must happen early on b/c of header requirements with session vars
//require_once("doLogin.php");

?>