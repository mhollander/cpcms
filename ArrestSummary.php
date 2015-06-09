<?php

/**************************************
*
*	ArrestSummary.php
*	Describes a Summary Arrest Record and has functions to parse a summary for helpful information
*
*	Copyright 2011-2015 Community Legal Services
* 
* Licensed under the Apache License, Version 2.0 (the "License");
* you may not use this file except in compliance with the License.
* You may obtain a copy of the License at
*
*    http://www.apache.org/licenses/LICENSE-2.0

* Unless required by applicable law or agreed to in writing, software
* distributed under the License is distributed on an "AS IS" BASIS,
* WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
* See the License for the specific language governing permissions and
* limitations under the License.
*
**************************************/

class ArrestSummary
{
	private $dockets = array();
	private $SID;
	private $PID;
	private $archived = false;  // a flag used when reading a summary docket to determine whether we have gotten to the archived cases yet
	private $aliases = array();

	public function __construct() {}

	protected static $SIDSearch = "/SID:\s?((\d+\-)*\d+)/";
	protected static $PIDSearch = "/PID:\s?(\d+)/";
	
	// gets the docket number, DC number, and OTN all in one search
	protected static $docketDCNOTNSearch = "/((MC|CP)\-\d{2}\-\D{2}\-\d*\-\d{4}).+DC No:\s*(\d*).*OTN:\s*(\D\d+)*/";
	
	// matches the arrest Date, Disposition Date, and judge from the summary arrest record
	protected static $docketDateDispDateJudgeSearch = "/Arrest Dt:\s*(\d{1,2}\/\d{1,2}\/\d{4})?.*Disp Date:\s*(\d{1,2}\/\d{1,2}\/\d{4})?\s*Disp Judge:(.*)/";
	
	// match any line with charges on it
	// $1 = code section; $3 = charge name; $4 = disposition
	// maybe have to deal with grading on non-philly cases?
	protected static $chargesSearch = "/^\s+\d{1,2}\s+(.+?)(?=\s\s)\s{12,}(\w{0,2})\s{2,}(.+?)(?=\s\s)\s{8,}(\w.+?)\s*$/";
	protected static $chargesSearchOverflow	 = "/^\s+(\w+( \w+)*)+\s+$/";
	
	protected static $archivedSearch = "/^Archived$/";
	protected static $archivedCaseNumberSearch = "/((MC|CP)\-\d{2}\-\D{2}\-\d*\-\d{4})/";
	protected static $migratedJudgeSearch = "/migrated/i";
	
	public function getSID() { return $this->SID; }
	public function getPID() { return $this->PID; }
	public function getDockets() { return $this->dockets; }
	
	public function setSID($SID) { $this->SID = $SID; }
	public function setPID($PID) { $this->PID = $PID; }
	
	public function getArrestKeys() { return array_keys($this->docketss); }

	// @return true if the arrestRecordFile is a summary docket sheet of all arrests, false if it isn't
	public static function isArrestSummary($docketRecordFile)
	{
		if (preg_match("/Court Summary/i", $docketRecordFile[1]))
			return true;
		else
			return false;
	}

	// @return true if the arrestRecordFile is a summary docket sheet of all arrests, false if it isn't
	public static function isArrestSummaryFromLine($docketRecord)
	{
		if (preg_match("/Court Summary/i", $docketRecord))
			return true;
		else
			return false;
	}

	
	// @return true if there is an arrest key that has the docket number supplied
	// @param a docket number (CP-51-CR...)
	public function isArrestInSummary($docket)
	{
		if (isset($this->arrests[$docket]))
			return TRUE;
		else
			return FALSE;
	}

	// @return the arrest summary requestsed based on the $docket as key
	// @param a docket number (CP-51-CR...)
	public function getArrest($docket)
	{
		if (isset($this->arrests[$docket]))
			return $this->arrests[$docket];
		else
			return null;
	}
	
	
	// @return true if there are arrests in here, false otherwise
	public function hasValuableInformation()
	{
		if (count($this->arrests) > 0)
			return true;
		else
			return false;
	}
	
	// @input arrestRecordFile - the arrest record summary as an array of lines, as read by the file function
	// reads through the arrestRecordFile, constructs a proper ArrestSummary, combines like cases
	public function processArrestSummary($docketRecordFile)
	{
		$this->readArrestSummary($docketRecordFile);
	}
	
	// reads in a record summary and sets all of the relevant variable.
	// assumes that the record is an array of lines, read through the "file" function.
	// the file should be created by running pdftotext.exe on a pdf of the defendant's arrest.
	public function readArrestSummary($docketRecordFile)
	{
		foreach ($docketRecordFile as $line_num => $line)
		{		
			//print "$line_num: $line <br/>";
			
			// first check to see if we have gotten to the archived section of the dockets
			if ($this->archived)
			{
				// add a new arrest to the array and 
				if (preg_match(self::$archivedCaseNumberSearch, $line, $matches))
				{
					$docket = new Docket();
					$docket->setDocketNumber(trim($matches[1]));
					// note that this is an archived case, so that the DB can have that clearly marked
					$docket->setIsArchived(true);
					
					// add add the docket to the array of dockets as part of this summary
					$this->dockets[trim($matches[1])] = $docket;
					// print "\n" . $docket->getDocketNumber();
				}
				continue;
			}
				
			// check to see if we are at the "archived" section, where only case numbers are listed without information beyond that
			if (preg_match(self::$archivedSearch, trim($line),$matches))
			{
				$this->archived=true;
				continue;
			}			
			
			if (preg_match(self::$SIDSearch, $line, $matches))
				$this->setSID(trim($matches[1]));
			if (preg_match(self::$PIDSearch, $line, $matches))
				$this->setPID(trim($matches[1]));

			// if we match the docket/DC/OTN, we are looking at a new case.  We want to keep reading ahead until we get to the next 
			// case.  
			// We also want to create a new arrest and add that arrest to the array
			if (preg_match(self::$docketDCNOTNSearch, $line, $matches))
			{
				//print "\n" . $line;
				$docket = new Docket();
				$docket->setDocketNumber(trim($matches[1]));
				if (isset($matches[3]))
					$docket->setDC(trim($matches[3]));
				if (isset($matches[4]))
					$docket->setOTN(trim($matches[4]));
				
				// print "\n" . $docket->getDocketNumber() . "|" . $docket->getDC() . "|" . $docket->getOTN() . "|";
				
				// start with the next line int he file and keep reading until we either hit another case, the archived section, or the end of the file.
				// we shoudl take in all information as possible, like any charges on the arrest, etc...
				for ($a = $line_num+1; $a < sizeof($docketRecordFile); $a++)
				{
					$aLine = $docketRecordFile[$a];
					// print "\n".trim($aLine)	;
					// if we get to the next case or the archived section, break out of the loop and continue processing the rest of the summary
					if (preg_match(self::$docketDCNOTNSearch, $aLine, $junk) || preg_match(self::$archivedSearch, trim($aLine),$junk))
					{
						//print "\nbreaking";
						//print "\n" . $aLine;
						break;
					}
					
					if (preg_match(self::$docketDateDispDateJudgeSearch,$aLine,$matches2))
					{
						//print "\n Getting a judge!";
						// only set these if the variables are not empty (can't do empty(trim($matches) until PHP 5.5))
						if (trim($matches2[1]) != false)
							$docket->setArrestDate(trim($matches2[1]));
						if (trim($matches2[2]) != false)
							$docket->setDispositionDate(trim($matches2[2]));

						// we don't want to set the judge if the judge is "Migrated Judge" or there is 
						// no judge listed.
						if (!preg_match(self::$migratedJudgeSearch, $matches2[3], $junk) && trim($matches2[3]) != "")
							$docket->setJudgeAssigned(trim($matches2[3]));
					
						// print $docket->getJudgeAssigned() . "|" . $docket->getArrestDate() . "|" . $docket->getDispositionDate();
					}

					else if (preg_match(self::$chargesSearch,$aLine,$matches3))
					{
						// print "\nMatching charges";
						$codeSection = trim($matches3[1]);
						$grade = trim($matches3[2]);  // the grade often doesn't exist, especially in Philly
						$chargeName = trim($matches3[3]);
						$disposition = "";
						// if there is no disposition on the charge, the chargeName gets set as the disposition, so we have to do some hacky stuff
						if ($chargeName=="")
							$chargeName = trim($matches3[4]);
						else
							$disposition = trim($matches3[4]);
						
						// we need to check to see if the next line has overflow from the charge.
						// this happens on long charges, like possession of controlled substance
						if (preg_match(self::$chargesSearchOverflow, $docketRecordFile[$a+1], $chargeMatch))
							$chargeName .= " " . trim($chargeMatch[1]);
			
						// also, knock out any strange multiple space situations in the charge, which comes up sometimes.
						$chargeName = preg_replace("/\s{2,}/", " ", $chargeName);
					
						// final disposition is hardcoded
						$charge = new Charge($chargeName, $disposition, $codeSection, $docket->getDispositionDate(), $grade, TRUE);
						
						$docket->addCharge($charge);
						
						//print "\ncharge: " . $charge->getChargeName() . "|".$charge->getDisposition()."|".$charge->getCodeSection()."|".$charge->getGrade()."|".$charge->getDispDate();
					}
				}
				$this->dockets[trim($matches[1])] = $docket;
			}
		}
	}
	

}
?>
