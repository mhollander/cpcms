<?php
// config.php
// configuration settings for the expungement generator
//

$debug=TRUE;

// database connection information
/*
$dbPassword = "XRNRJR6vHGejwNCp";
$dbUser = "ronholla_ExpUser";
$dbName = "ronholla_expungementsite";
$dbHost = "localhost";
*/
/*
require_once("/home/ronholla/tools/dbconnect.php");

$dataDir = "/home/ronholla/www/crepdb/data/";
$baseURL = "http://www.ronhollander.com/crepdb/";
$templateDir = "templates/";
$signatureDir = "/home/ronholla/www/crepdb/images/sigs/";
$toolsDir = "/home/ronholla/tools/";
$pdftotext = "pdftotext";
$tempFile = tempnam($dataDir, "FOO");

*/
// require_once("c:\wamp\\tools\dbconnect.php");

$docketDir = "c:\cpcms\\dockets\\";
$toolsDir = "c:\wamp\\tools\\";
// $baseURL = "http://localhost//";
$tempFile = tempnam($docketDir, "FOO");
$pdftotext = "pdftotext.exe";


// this logs a user in; must happen early on b/c of header requirements with session vars
//require_once("doLogin.php");

?>