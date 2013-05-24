<?php
	// @@@@@@@@ TODO - Add extra columns to the arrest column to see if expungement, summary, ard, etc...
	// @todo make aliases work automatically - they should be able to be read off of the case summary
	// @todo think about whether I want to have the charges array check to see if a duplicate
	//		 charge is being added and prevent duplicate charges.  A good example of this is if
	//       a charge is "replaced by information" and then later there is a disposition. 
	// 		 we probably don't want both the replaced by information and the final disposition on 
	// 		 the petition.  This is especially true if the finally dispoition is Guilty

require_once("Charge.php");
require_once("Person.php");
require_once("utils.php");
require_once("config.php");

class Docket
{

	private $mdjDistrictNumber;
	private $county;
	private $OTN;
	private $DC;
	private $docketNumber;
	private $arrestingOfficer;
	private $arrestingAgency;
	private $arrestDate;
	private $complaintDate;
	private $judgeAssigned;
	private $dateFiled;
	private $initiationDate;
	private $DOB;
	private $dispositionDate;
	private $firstName;
	private $lastName;
	private $charges = array();
	private $costsTotal;
	private $costsPaid;
	private $costsCharged;
	private $costsAdjusted;
	private $bailTotal;
	private $bailCharged;
	private $bailPaid;
	private $bailAdjusted;
	private $bailTotalTotal;
	private $bailChargedTotal;
	private $bailPaidTotal;
	private $bailAdjustedTotal;
	private $isCP;
	private $isCriminal;
	private $isARDExpungement;
	private $isExpungement;
	private $isRedaction;
	private $isHeldForCourt;
	private $isSummaryArrest = FALSE;
	private $isArrestSummaryExpungement;
	private $isArrestOver70Expungement;
	private $pdfFile;
	private $crossCourtDocket;
	private $lowerCourtDocket;
	private $initialIssuingAuthority;
	private $finalIssuingAuthority;
	private $city;
	private $state;
	private $zip;
	private $aliases = array();
	private $pastAliases = FALSE; // used to stop checking for aliases once we have reached a certain point in the docket sheet
	private $commonwealthAgency;
	private $commonwealthRole;
	private $commonwealthSupremeCourtNumber;
	private $dLawyer;
	private $dRole; 
	private $dSupremeCourtNumber; 
	private $bAttorneyInfo = FALSE; //used to stop checking for aliases once we have reached a certain point in the docket sheet
	private $costGeneric = array(); 
	private $costTotals = array();
	private $finalDisposition = FALSE;
	private $caseFinancialInformation = FALSE;
	// isMDJ = 0 if this is not an mdj case at all, 1 if this is an mdj case and 2 if this is a CP case that decended from MDJ
	private $isMDJ = 0;
		
	protected static $unknownInfo = "N/A";
	protected static $unknownOfficer = "Unknown officer";
	
	protected static $mdjDistrictNumberSearch = "/Magisterial District Judge\s(.*)/i";
	protected static $countySearch = "/\sof\s(\w+)\sCOUNTY/i";
	protected static $mdjCountyAndDispositionDateSearch = "/County:\s+(.*)\s+Disposition Date:\s+(.*)/";
	// $1 = OTN, $2 = CCDocket
	protected static $OTNSearch = "/OTN:\s+(\D(?:\s)?\d+(?:\-\d)?)\s+Lower Court Docket No:\s+(.*)\s*/";
	protected static $DCSearch = "/District Control Number\s+(\d+)/";
	protected static $docketSearch = "/Docket Number:\s+((MC|CP)\-\d{2}\-(\D{2})\-\d*\-\d{4})/";
	protected static $mdjDocketSearch = "/Docket Number:\s+(MJ\-\d{5}\-(\D{2})\-\d*\-\d{4})/";
	protected static $arrestingAgencyAndOfficerSearch = "/Arresting Agency:\s+(.*)\s+Arresting Officer: (\D+)/";
	protected static $mdjArrestingOfficerSearch = "/^\s*Arresting Officer (\D+)\s*$/";
	protected static $mdjArrestingAgencyAndArrestDateSearch = "/Arresting Agency:\s+(.*)\s+Arrest Date:\s+(\d{1,2}\/\d{1,2}\/\d{4})?/";
	protected static $arrestDateSearch = "/Arrest Date:\s+(\d{1,2}\/\d{1,2}\/\d{4})/";
	protected static $complaintDateSearch = "/Complaint Date:\s+(\d{1,2}\/\d{1,2}\/\d{4})/";
	protected static $mdjComplaintDateSearch = "/Issue Date:\s+(\d{1,2}\/\d{1,2}\/\d{4})/";
	protected static $issuingAuthoritySearch = "/Initial Issuing Authority:\s+(.*)\s+Final Issuing Authority:\s+(.*)/";
	protected static $judgeAssignedSearch = "/Judge Assigned:\s+(.*)\s+(?:Date Filed|Issue Date):\s+(.*)\s+Initiation Date:\s+(.*)/";
	protected static $crossCourtDocketSearch = "/Cross Court Docket Nos:\s+(.*)\s*$/"; 
	protected static $dateFiledSearch = "/Date Filed:\s+(\d{1,2}\/\d{1,2}\/\d{4})/";
	protected static $lowerCourtDocketSearch = "/Lower Court Docket No:\s+(.*)\s*/";
	protected static $cityStateZipSearch = "/City\/State\/Zip:\s+(.*), (\w{2})\s+(\d{5})/";
	
	#note that the alias name search only captures a maximum of six aliases.  
	# This is because if you do this: /^Alias Name\r?\n(?:(^.+)\r?\n)*/m, only the last alias will be stored in $1.  
	# What a crock!  I can't figure out a way around this
	protected static $aliasNameStartSearch = "/^Alias Name/"; // \r?\n(?:(^.+)\r?\n)(?:(^.+)\r?\n)?(?:(^.+)\r?\n)?(?:(^.+)\r?\n)?(?:(^.+)\r?\n)?(?:(^.+)\r?\n)?/m"; 
	protected static $aliasNameEndSearch = "/CASE PARTICIPANTS/";
	protected static $endOfPageSearch = "/(CPCMS 9082|AOPC 9082)/";
	
	// there are two special judge situations that need to be covered.  The first is that MDJ dockets sometimes say
	// "magisterial district judge xxx".  In that case, there could be overflow to the next line.  We want to capture that
	// overflow.  The second is that sometimes the judge assigned says "migrated judge".  We want to make sure we catch that.
	protected static $magisterialDistrictJudgeSearch = "/Magisterial District Judge (.*)/";
	protected static $judgeSearchOverflow = "/^\s+(\w+\s*\w*)\s*$/";
	protected static $migratedJudgeSearch = "/migrated/i";
	protected static $DOBSearch = "/Date Of Birth:?\s+(\d{1,2}\/\d{1,2}\/\d{4})/i";
	protected static $nameSearch = "/^Defendant\s+(.*), (.*)/";

	// ($1 = charge, $2 = disposition, $3 = grade, $4 = code section
	// explanation: .+? - the "?" means to do a lazy match of .+, so it isn't greedy; I have it twice, the first time
	// is to match the charge, the second is to match the disposition.  The final part is to match the code section that is violated.
	protected static $chargesSearch = "/\d\s+\/\s+(.+?)(?=\s\s)\s{2,}(.+?)(?=\s\s)\s{2,}(\w{0,2})\s+(\w{1,2}\s?\247\s?\d+(\-|\247|\w+)*)/";
	
	// regexes to get information about the attorneys
	protected static $attorneyInfoHeaderSearch = "/\s*COMMONWEALTH INFORMATION\s+ATTORNEY INFORMATION/";
	protected static $attorneyInfoSearch = "/Name:\s+(.+?)(\s{2,}Name:\s+(.+)$|\s$)/";
	protected static $entriesSearch = "/ENTRIES/";
	// the idea in this next search is that we have two columns: one for the P and one for the D; they are separated by a lot of 
	// whitespace.  We want to grab both pieces of info and store it somewhere.  Here are variations on what we might see:
/*
 COMMONWEALTH INFORMATION                                                      ATTORNEY INFORMATION 

   Name:             Adams County District Attorney's                            Name:              Jeffery M. Cook * 
                     Office, Esq.                                                                   Public Defender 
                     Prosecutor                                                  Supreme Court No:            025449 

   Supreme Court No:                                                             Rep. Status:                 Lower Court 
   
   OR 
   
    COMMONWEALTH INFORMATION                                                     ATTORNEY INFORMATION 

   Name:             Brian Ray Sinnett                                          Name:               Matthew Raymond Gover, Esq. * 
                     District Attorney                                                              Private 
   Supreme Court No:          084188                                            Supreme Court No:            047593 
   
   OR 
   
    COMMONWEALTH INFORMATION                                                    ATTORNEY INFORMATION 

   Name:             Philadelphia County District Attorney's                   Name:              Jeremy C. Gelb 
                     Office, Esq.                                                                 Private 
                     Prosecutor                                                Supreme Court No:           032886 
*/
	protected static $attorneyInfoExtraSearch = "/\s*(.+?)((?=\s\s)\s{2,}(.+)\s*|\s$)/";
	// these match match Supreme Court No is on the P or D side
	protected static $supremeCourtLeftSearch = "/^\s*Supreme Court No:\s+(\d*).*/";
	protected static $supremeCourtRightSearch = "/\S+\s+Supreme Court No:\s+(\d*).*/";
	
	// $1 = code section, $3 = grade, $4 = charge, $5 = offense date, $6 = disposition
	protected static $mdjChargesSearch = "/^\s*\d\s+((\w|\d|\s(?!\s)|\-|\247|\*)+)\s{2,}(\w{0,2})\s{2,}([\d|\D]+)\s{2,}(\d{1,2}\/\d{1,2}\/\d{4})\s{2,}(\D{2,})/";
	
	protected static $chargesSearchOverflow = "/^\s+(\w+\s*\w*)\s*$/";
	// disposition date can appear in two different ways (that I have found) and a third for MDJ cases:
	// 1) it can appear on its own line, on a line that looks like: 
	//    Status   mm/dd/yyyy    Final  Disposition
	//    Trial   mm/dd/yyyy    Final  Disposition
	//    Preliminary Hearing   mm/dd/yyyy    Final  Disposition
	//    Migrated Dispositional Event   mm/dd/yyyy    Final  Disposition
	// 2) on the line after the charge disp
	// 3) for MDJ cases, disposition date appears on a line by itself, so it is easier to find
	protected static $dispDateSearch = "/(?:Plea|Status|Status of Restitution|Status - Community Court|Status Listing|Migrated Dispositional Event|Trial|Preliminary Hearing|Pre-Trial Conference)\s+(\d{1,2}\/\d{1,2}\/\d{4})\s+Final Disposition/";
	protected static $dispDateSearch2 = "/(.*)\s(\d{1,2}\/\d{1,2}\/\d{4})/";	
	
	// this is a crazy one.  Basically matching whitespace then $xx.xx then whitespace then 
	// -$xx.xx, etc...  The fields show up as Assesment, Payment, Adjustments, Non-Monetary, Total
	protected static $caseFinancialInformationSearch = "/Case Financial Information/i";
	protected static $costsFeesTotalSearch = "/Costs\/Fees Totals:\s+(-?\\$[\d\,]+\.\d{2})\s+(-?\\$[\d\,]+\.\d{2})\s+(-?\\$[\d\,]+\.\d{2})\s+\s+(-?\\$[\d\,]+\.\d{2})\s+(-?\\$[\d\,]+\.\d{2})/";
	protected static $grandTotalsSearch = "/Grand Totals:\s+(-?\\$[\d\,]+\.\d{2})\s+(-?\\$[\d\,]+\.\d{2})\s+(-?\\$[\d\,]+\.\d{2})\s+\s+(-?\\$[\d\,]+\.\d{2})\s+(-?\\$[\d\,]+\.\d{2})/";
	protected static $restitutionTotalsSearch = "/Restitution Totals:\s+(-?\\$[\d\,]+\.\d{2})\s+(-?\\$[\d\,]+\.\d{2})\s+(-?\\$[\d\,]+\.\d{2})\s+\s+(-?\\$[\d\,]+\.\d{2})\s+(-?\\$[\d\,]+\.\d{2})/";
	protected static $finesTotalsSearch = "/Fines Totals:\s+(-?\\$[\d\,]+\.\d{2})\s+(-?\\$[\d\,]+\.\d{2})\s+(-?\\$[\d\,]+\.\d{2})\s+\s+(-?\\$[\d\,]+\.\d{2})\s+(-?\\$[\d\,]+\.\d{2})/";
	
	// if this fails, then we are in an overflow line (if there is a $ or a CPCMS on the line, then it is not a continuation of our line)
	protected static $finesTotalOverflowNegativeSearch = "(\\$|CPCMS|AOPC 9082)";
Criminal Conspiracy Engaging - Theft By
	// this will find any fines/costs line, including the ones above.  Before using it, test the line for a ":"; if it contains
	// an ":", then we don't want to match the line.
	protected static $genericFineCostSearch = "/\s*(.+)\s+(-?\\$[\d\,]+\.\d{2})\s+(-?\\$[\d\,]+\.\d{2})\s+(-?\\$[\d\,]+\.\d{2})\s+(-?\\$[\d\,]+\.\d{2})\s+(-?\\$[\d\,]+\.\d{2})/";
	public function __construct () {}
	
	
	//getters
	public function getCounty() { return $this->county; }
	public function getOTN() { return $this->OTN; }
	public function getDC() { return $this->DC; }
	public function getDocketNumber() { return $this->docketNumber; }
	public function getArrestingOfficer() { return $this->arrestingOfficer; }
	public function getArrestingAgency() { return $this->arrestingAgency; }
	public function getArrestDate() { return $this->arrestDate; }
	public function getComplaintDate() { return $this->complaintDate; }
	//  getDispositionDate() exists elsewhere
	public function getJudgeAssigned() { return $this->judgeAssigned; }
	public function getDOB() { return $this->DOB; }
	public function getfirstName() { return $this->firstName; }
	public function getLastName() { return $this->lastName; }
	public function getCharges() { return $this->charges; }
	public function getCostsTotal() { if (!isset($this->costsTotal)) $this->setCostsTotal("0"); return $this->costsTotal; }
	public function getCostsPaid()  { if (!isset($this->costsPaid)) $this->setCostsPaid("0");return $this->costsPaid; }
	public function getCostsCharged() { if (!isset($this->costsCharged)) $this->setCostsCharged("0"); return $this->costsCharged; }
	public function getCostsAdjusted()  { if (!isset($this->costsAdjusted)) $this->setCostsAdjusted("0");return $this->costsAdjusted; }
	public function getBailTotal() { if (!isset($this->bailTotal)) $this->setBailTotal("0"); return $this->bailTotal; }
	public function getBailPaid()  { if (!isset($this->bailPaid)) $this->setBailPaid("0");return $this->bailPaid; }
	public function getBailCharged() { if (!isset($this->bailCharged)) $this->setBailCharged("0"); return $this->bailCharged; }
	public function getBailAdjusted()  { if (!isset($this->bailAdjusted)) $this->setBailAdjusted("0");return $this->bailAdjusted; }
	public function getBailTotalTotal() { if (!isset($this->bailTotalTotal)) $this->setBailTotalTotal("0"); return $this->bailTotalTotal; }
	public function getBailPaidTotal()  { if (!isset($this->bailPaidTotal)) $this->setBailPaidTotal("0");return $this->bailPaidTotal; }
	public function getBailChargedTotal() { if (!isset($this->bailChargedTotal)) $this->setBailChargedTotal("0"); return $this->bailChargedTotal; }
	public function getBailAdjustedTotal()  { if (!isset($this->bailAdjustedTotal)) $this->setBailAdjustedTotal("0");return $this->bailAdjustedTotal; }
	public function getIsCP()  { return $this->isCP; }
	public function getIsCriminal()  { return $this->isCriminal; }
	public function getIsARDExpungement()  { return $this->isARDExpungement; }
	public function getIsExpungement()  { return $this->isExpungement; }
	public function getIsRedaction()  { return $this->isRedaction; }
	public function getIsHeldForCourt()  { return $this->isHeldForCourt; }
	public function getIsSummaryArrest()  { return $this->isSummaryArrest; }
	public function getIsMDJ() { return $this->isMDJ; }
	public function getPDFFile() { return $this->pdfFile;}
	public function getCrossCourtDocket() { return $this->crossCourtDocket; }
	public function getDateFiled() { return $this->dateFiled; }
	public function getLowerCourtDocket() { return $this->lowerCourtDocket; }
	public function getInitialIssuingAuthority() { return $this->initialIssuingAuthority; }
	public function getFinalIssuingAuthority() { return $this->finalIssuingAuthority; }
	public function getCity() { return $this->city; }
	public function getState() { return $this->state; }
	public function getZip() { return $this->zip; }
	public function getAliases() { return $this->aliases; }
	public function getCommonwealthAgency() { return $this->commonwealthAgency; }
	public function getCommonwealthRole() { return $this->commonwealthRole; }
	public function getCommonwealthSupremeCourtNumber() { return $this->commonwealthSupremeCourtNumber ; }
	public function getDLawyer() { return $this->dLawyer; }
	public function getDRole() { return $this->dRole; }
	public function getDSupremeCourtNumber() { return $this->dSupremeCourtNumber; }
	public function getCostGeneric() { return $this->costGeneric; }
	public function getCostTotal() { return $this->costTotals; }
	
		
	//setters
	public function setMDJDistrictNumber($mdjDistrictNumber) { $this->mdjDistrictNumber = $mdjDistrictNumber; }
	public function setCounty($county) { $this->county = ucwords(strtolower($county)); }
	public function setOTN($OTN) 
	{ 
		// OTN could have a "-" before the last digit.  It could also have unnecessary spaces.  
		// We want to chop that off since it isn't important and messes up matching of OTNs
		$this->OTN = str_replace(" ", "", str_replace("-", "", $OTN));
	}
	public function setDC($DC) { $this->DC = $DC; }
	public function setDocketNumber($docketNumber) { $this->docketNumber = $docketNumber; }
	public function setIsSummaryArrest($isSummaryArrest)  { $this->isSummaryArrest = $isSummaryArrest; } 
	public function setArrestingOfficer($arrestingOfficer) {  $this->arrestingOfficer = ucwords(strtolower($arrestingOfficer)); }
	
	// when we set the arresting agency, replace any string "PD" with "Police Dept"
	public function setArrestingAgency($arrestingAgency) {  $this->arrestingAgency = preg_replace("/\bpd\b/i", "Police Dept",$arrestingAgency); }
	
	public function setArrestDate($arrestDate) {  $this->arrestDate = $arrestDate; }
	public function setComplaintDate($complaintDate) {  $this->complaintDate = $complaintDate; }
	public function setJudgeAssigned($judge) { $this->judgeAssigned = $judge; }
	public function setDateFiled($a) { $this->dateFiled = $a; }
	public function setInitiationDate($a) { $this->initiationDate = $a; }				
	public function setDispositionDate($dispositionDate) { $this->dispositionDate = $dispositionDate; }
	public function setDOB($DOB) { $this->DOB = $DOB; }
	public function setFirstName($firstName) { $this->firstName = $firstName; }
	public function setLastName($lastName) { $this->lastName = $lastName; }
	public function setCharges($charges) {  $this->charges = $charges; }
	public function setCostsTotal($costsTotal) {  $this->costsTotal = $costsTotal; }
	public function setCostsPaid($costsPaid)  {  $this->costsPaid = $costsPaid; }
	public function setCostsCharged($costsCharged) {  $this->costsCharged = $costsCharged; }
	public function setCostsAdjusted($costsAdjusted)  {  $this->costsAdjusted = $costsAdjusted; }
	public function setBailTotal($bailTotal) {  $this->bailTotal = $bailTotal; }
	public function setBailPaid($bailPaid)  {  $this->bailPaid = $bailPaid; }
	public function setBailCharged($bailCharged) {  $this->bailCharged = $bailCharged; }
	public function setBailAdjusted($bailAdjusted)  {  $this->bailAdjusted = $bailAdjusted; }
	public function setBailTotalTotal($bailTotal) {  $this->bailTotalTotal = $bailTotal; }
	public function setBailPaidTotal($bailPaid)  {  $this->bailPaidTotal = $bailPaid; }
	public function setBailChargedTotal($bailCharged) {  $this->bailChargedTotal = $bailCharged; }
	public function setBailAdjustedTotal($bailAdjusted)  {  $this->bailAdjustedTotal = $bailAdjusted; }
	public function setIsCP($isCP)  {  $this->isCP = $isCP; }
	public function setIsCriminal($isCriminal)  {  $this->isCriminal = $isCriminal; }
	public function setIsARDExpungement($isARDExpungement)  {  $this->isARDExpungement = $isARDExpungement; }
	public function setIsExpungement($isExpungement)  {  $this->isExpungement = $isExpungement; }
	public function setIsRedaction($isRedaction)  {  $this->isRedaction = $isRedaction; }
	public function setIsArrestSummaryExpungement($isSummaryExpungement) { $this->isArrestSummaryExpungement = $isSummaryExpungement; }
	public function setIsArrestOver70Expungement($isOver70Expungement) { $this->isArrestOver70Expungement = $isOver70Expungement; }
	public function setIsHeldForCourt($isHeldForCourt)  {  $this->isHeldForCourt = $isHeldForCourt; }
	public function setIsMDJ($isMDJ)  {  $this->isMDJ = $isMDJ; }
	public function setPDFFile($pdfFile) { $this->pdfFile = $pdfFile; }
	public function setCrossCourtDocket($a) { $this->crossCourtDocket = $a; }
	public function setLowerCourtDocket($a) { $this->lowerCourtDocket = $a; }
	public function setInitialIssuingAuthority($a) { $this->initialIssuingAuthority = $a; }
	public function setFinalIssuingAuthority($a) { $this->finalIssuingAuthority = $a; }
	public function setCity($a) { $this->city = $a; }
	public function setState($a) { $this->state = $a; }
	public function setZip($a) { $this->zip = $a; }
	public function setAliases($a) { $this->aliases = $a; }
	public function addAlias($a) { $this->aliases[] = $a; }
	public function setCommonwealthAgency($a) { $this->commonwealthAgency = $a; }
	public function setCommonwealthRole($a) { $this->commonwealthRole = $a; }
	public function setCommonwealthSupremeCourtNumber($a) { $this->commonwealthSupremeCourtNumber = $a; }
	public function setDLawyer($a) { $this->dLawyer = $a; }
	public function setDRole($a) { $this->dRole = $a; }
	public function setDSupremeCourtNumber($a) { $this->dSupremeCourtNumber = $a; }
	public function setCost(&$variable, $name, $cost1, $cost2, $cost3, $cost4, $cost5)
	{
		$variable[] = array($name, str_replace("\$", "", $cost1), str_replace("\$", "", $cost2), str_replace("\$", "", $cost3), str_replace("\$", "", $cost4), str_replace("\$", "", $cost5));
	}
	
	// push a single chage onto the charge array
	public function addCharge($charge) {  $this->charges[] = $charge; }
	
		
	// @return true if the arrestRecordFile is a docket sheet, false if it isn't
	public function isDocketSheet($arrestRecordFile)
	{
		if (preg_match("/Docket/i", $arrestRecordFile))
			return true;
		else
			return false;
	}

	// @return true if the arrestRecordFile is a docket sheet, false if it isn't
	public function isMDJDocketSheet($arrestRecordFile)
	{
		if (preg_match("/Magisterial District Judge/i", $arrestRecordFile))
			return true;
		else
			return false;
	}

	
	// reads in a record and sets all of the relevant variable.
	// assumes that the record is an array of lines, read through the "file" function.
	// the file should be created by running pdftotext.exe on a pdf of the defendant's arrest.
	// this does not read the summary.
	public function readArrestRecord($arrestRecordFile)
	{
		// check to see if this is an MDJ docket sheet.  If it is, we have to
		// read it a bit differently in places
		if ($this->isMDJDocketSheet($arrestRecordFile[0]))
		{
			$this->setIsMDJ(1);
			if (preg_match(self::$mdjDistrictNumberSearch, $arrestRecordFile[0], $matches))
				$this->setMDJDistrictNumber(trim($matches[1]));

		}

		foreach ($arrestRecordFile as $line_num => $line)
		{
			// print "$line_num: $line<br/>";
			
			// do all of the searches that are common to the MDJ and CP/MC docket sheets
								
			// figure out which county we are in
			if (empty($this->county) && preg_match(self::$countySearch, $line, $matches))
				$this->setCounty(trim($matches[1]));
			elseif (preg_match(self::$mdjCountyAndDispositionDateSearch, $line, $matches))
			{
				$this->setCounty(trim($matches[1]));
				$this->setDispositionDate(trim(($matches[2])));
			}
				
			// find the docket Number
			else if (empty($this->docketNumber) && preg_match(self::$docketSearch, $line, $matches))
			{
				$this->setDocketNumber(trim($matches[1]));

				// we want to set this to be a summary offense if there is an "SU" in the 
				// docket number.  The normal docket number looks like this:
				// CP-##-CR-########-YYYY or CP-##-SU-#######-YYYYYY; the latter is a summary
				if (trim($matches[3]) == "SU")
					$this->setIsSummaryArrest(TRUE);
				else
					$this->setIsSummaryArrest(FALSE);
			}
			else if (empty($this->docketNumber) && preg_match(self::$mdjDocketSearch, $line, $matches))
			{
				$this->setDocketNumber(trim($matches[1]));
			}
			
			// search for OTN and Lower Court Docket Number
			else if (empty($this->OTN) && preg_match(self::$OTNSearch, $line, $matches))
			{
				$this->setOTN(trim($matches[1]));
				$this->setLowerCourtDocket(trim($matches[2]));
			}

			else if (empty($this->DC) && preg_match(self::$DCSearch, $line, $matches))
				$this->setDC(trim($matches[1]));
			
			// find the arrest date.  First check for agency and arrest date (mdj dockets).  Then check for arrest date alone
			else if (empty($this->ArrestingAgency) && preg_match(self::$mdjArrestingAgencyAndArrestDateSearch, $line, $matches))
			{
				$this->setArrestingAgency(trim($matches[1]));
				if (isset($matches[2]))
					$this->setArrestDate(trim($matches[2]));
			}
				
			else if (empty($this->arrestDate) && preg_match(self::$arrestDateSearch, $line, $matches))
				$this->setArrestDate(trim($matches[1]));

			// find the complaint date
			else if (empty($this->complaintDate) && preg_match(self::$complaintDateSearch, $line, $matches))
				$this->setComplaintDate(trim($matches[1]));

			// for non-mdj, aresting agency and officer are on the same line, so we have to find
			// them together and deal with them together.
			else if (empty($this->arrestingAgency) && preg_match(self::$arrestingAgencyAndOfficerSearch, $line, $matches))
			{
				// first set the arresting agency
				$this->setArrestingAgency(trim($matches[1]));

				// then deal with the arresting officer
				$ao = trim($matches[2]);
				$this->setArrestingOfficer($ao);
			}	

			// mdj dockets have the arresting office on a line by himself, as last name, first
			else if (empty($this->arrestingOfficer) && preg_match(self::$mdjArrestingOfficerSearch, $line, $matches))
			{
				$officer = trim($matches[1]);
				// find the comma and switch the order of the names
				$officerArray = explode(",", $officer, 2);
				if (sizeof($officerArray) > 0)
					$officer = trim($officerArray[1]) . " " . trim($officerArray[0]);
				
				$this->setArrestingOfficer($officer);				
			}
				
			// new stuff for the CMCPS shindig
			// initial and final issuing authority
			else if (empty($this->initialIssuingAuthority) && preg_match(self::$issuingAuthoritySearch, $line, $matches))
			{
				$initialIA = trim($matches[1]);
				$finalIA = trim($matches[2]);
				$this->setInitialIssuingAuthority($initialIA);
				$this->setfinalIssuingAuthority($finalIA);
			}
			
			else if (empty($this->crossCourtDocket) && preg_match(self::$crossCourtDocketSearch, $line, $matches))
			{
				$ccDocketNo = trim($matches[1]);
				$this->setCrossCourtDocket($ccDocketNo);
			}
			
				
			// judge assigned search has the judge assignd, date filed, initiation date
			else if (empty($this->judgeAssigned) && preg_match(self::$judgeAssignedSearch, $line, $matches))
			{
				$judge = trim($matches[1]);
				$dateFiled = trim($matches[2]);
				$initiationDate = trim($matches[3]);
				
				$this->setJudgeAssigned($judge);
				$this->setDateFiled($dateFiled);
				$this->setInitiationDate($initiationDate);
			}
				
			else if  (empty($this->DOB) && preg_match(self::$DOBSearch, $line, $matches))
			{
				$this->setDOB(trim($matches[1]));
			
				// also try to match the city/state/zip if there is one set
				if (preg_match(self::$cityStateZipSearch, $line, $matches))
				{
					$this->setCity(trim($matches[1]));
					$this->setState(trim($matches[2]));
					$this->setZip(trim($matches[3]));
				}
			}
			
			else if (empty($this->firstName) && preg_match(self::$nameSearch, $line, $matches))
			{
				$this->setFirstName(trim($matches[2]));
				$this->setLastName(trim($matches[1]));
			}

			else if (!$this->pastAliases && preg_match(self::$aliasNameStartSearch, $line))
			{
				
				$i = $line_num+1;
				while (!preg_match(self::$aliasNameEndSearch, $arrestRecordFile[$i]))
				{
					// once in a while, the aliases are at the end of a page, which means we get to the footer information
					// before we get to the regular marker of the end of the aliases.  We have to watch out for this
					// and break if we find it
					if (preg_match(self::$endOfPageSearch, $arrestRecordFile[$i]))
						break;
						
					//push the alias onto the array of aliases
					if (preg_match("/\w/", $arrestRecordFile[$i]))
						$this->addAlias(trim($arrestRecordFile[$i]));
					$i++;
				}
				
				// once we match the CASE PARTICIPANTS line, we know we are done with this iteration
				$this->pastAliases = TRUE;					
			}			

			// only do this section if we haven't gotten to the line that says Commonwealth Information && we match that line, 
			// which means that we can get information on the lawyers in the case
			else if (!$this->bAttorneyInfo && preg_match(self::$attorneyInfoHeaderSearch, $line))
			{
				$i = $line_num+1;
				// first get information about the P and then the D
		
				$pInformation = array();
				// grab the name of the agency/attorney
				if (preg_match(self::$attorneyInfoSearch, $arrestRecordFile[$i], $matches))
					$pInformation[] = trim($matches[1]);
				$i++;
				// as long as we haven't gotten the left section
				while (!preg_match(self::$supremeCourtLeftSearch, $arrestRecordFile[$i], $matches))
				{
					// this means that there was some problem with the way the attorney was entered
					if (preg_match(self::$entriesSearch, $arrestRecordFile[$i]))
						break;
					if (preg_match(self::$attorneyInfoExtraSearch, $arrestRecordFile[$i], $aMatches))
						$pInformation[] = trim($aMatches[1]);
					$i++;
				}
				
				$this->setCommonwealthRole(array_pop($pInformation));
				$this->setCommonwealthAgency(implode(" ", $pInformation));
				$this->setCommonwealthSupremeCourtNumber(trim($matches[1]));
				
				// now find and set the D information
				$i = $line_num+1;
				$dInformation = array();
				// grab the name of the agency/attorney
				if (preg_match(self::$attorneyInfoSearch, $arrestRecordFile[$i], $matches) && !empty($matches[3]))
					$dInformation[] = trim($matches[3]);
				$i++;
								
				$hasAttorneyInfo = TRUE;
				// as long as we haven't finished looking through the right section (which ends with Supreme Court ID)
				while (!preg_match(self::$supremeCourtRightSearch, $arrestRecordFile[$i], $matches))
				{
					// this will only be hit if we are int he strange case where there is a problem with the attorney information
					if (preg_match(self::$entriesSearch, $arrestRecordFile[$i]))
					{
						$hasAttorneyInfo = FALSE;
						break;
					}
					if (preg_match(self::$attorneyInfoExtraSearch, $arrestRecordFile[$i], $aMatches) && !empty($aMatches[3]))
						$dInformation[] = trim($aMatches[3]);
					$i++;
				}
				
				// only insert attorney information for the D if we actually found something
				if ($hasAttorneyInfo)
				{
					$this->setDRole(array_pop($dInformation));
					$this->setDLawyer(implode(" ", $dInformation));
					$this->setDSupremeCourtNumber(trim($matches[1]));
				}
				else 
				{
					$this->setDRole("NA");
					$this->setDLawyer("NA");
					$this->setDSupremeCourtNumber("0");
				}
					

				// once we find the attorney information, we are done with this search
				$this->bAttorneyInfo = TRUE;					
				
			}
			
			else if (!$this->finalDisposition && preg_match(self::$dispDateSearch, $line, $matches))
			{
				$this->setDispositionDate($matches[1]);
				$this->finalDisposition = TRUE;
			}
				
			// charges can be spread over two lines sometimes; we need to watch out for that
			else if (preg_match(self::$chargesSearch, $line, $matches))
			{
				
				$charge = trim($matches[1]);
				// we need to check to see if the next line has overflow from the charge.
				// this happens on long charges, like possession of controlled substance
				$i = $line_num+1;
				
				if (preg_match(self::$chargesSearchOverflow, $arrestRecordFile[$i], $chargeMatch))
				{
					$charge .= " " . trim($chargeMatch[1]);
					$i++;
				}
	
			
				// need to grab the disposition date as well, which is on the next line
				if (isset($this->dispositionDate))
					$dispositionDate = $this->getDispositionDate();
				else if (preg_match(self::$dispDateSearch2, $arrestRecordFile[$i], $dispMatch))
					// set the date;
					$dispositionDate = $dispMatch[2];
				else
					$dispositionDate = NULL;
					
				$charge = new Charge($charge, $matches[2], trim($matches[4]), trim($dispositionDate), trim($matches[3]), $this->finalDisposition);
				$this->addCharge($charge);
			}
			
			// match a charge for MDJ
			else if ($this->getIsMDJ() && preg_match(self::$mdjChargesSearch, $line, $matches))
			{
				$charge = trim($matches[4]);

				// we need to check to see if the next line has overflow from the charge.
				// this happens on long charges, like possession of controlled substance
				$i = $line_num+1;
				
				if (preg_match(self::$chargesSearchOverflow, $arrestRecordFile[$i], $chargeMatch))
				{
					$charge .= " " . trim($chargeMatch[1]);
					$i++;
				}

				
				// add the charge to the charge array
				if (isset($this->dispositionDate))
					$dispositionDate = $this->getDispositionDate();
				else
					$dispositionDate = NULL;
				$charge = new Charge($charge, trim($matches[6]), trim($matches[1]), trim($dispositionDate), trim($matches[3]));
				$this->addCharge($charge);
			}			

			// get all of our costs and fines information
			// but first, set a flag so that we know we should be searching for this information, so that we don't
			// kill our search speed
			else if (!$this->caseFinancialInformation && preg_match(self::$caseFinancialInformationSearch, $line, $matches))
				$this->caseFinancialInformation = TRUE;
			else if ($this->caseFinancialInformation && preg_match(self::$costsFeesTotalSearch, $line, $matches))
				$this->setCost($this->costTotals, "Costs/Fees Totals", trim($matches[1]),  trim($matches[2]), trim($matches[3]), trim($matches[4]), trim($matches[5]));
			else if ($this->caseFinancialInformation && preg_match(self::$grandTotalsSearch, $line, $matches))
				$this->setCost($this->costTotals, "Grand Totals", trim($matches[1]),  trim($matches[2]), trim($matches[3]), trim($matches[4]), trim($matches[5]));
			else if ($this->caseFinancialInformation && preg_match(self::$restitutionTotalsSearch, $line, $matches))
				$this->setCost($this->costTotals, "Restitution Totals", trim($matches[1]),  trim($matches[2]), trim($matches[3]), trim($matches[4]), trim($matches[5]));
			else if ($this->caseFinancialInformation && preg_match(self::$finesTotalsSearch, $line, $matches))
				$this->setCost($this->costTotals, "Fines Totals", trim($matches[1]),  trim($matches[2]), trim($matches[3]), trim($matches[4]), trim($matches[5]));

			else if ($this->caseFinancialInformation && preg_match(self::$genericFineCostSearch, $line, $matches))
			{

				$costName = trim($matches[1]);
				
				// check for overflow				
				$i = $line_num+1;
				if (!preg_match(self::$finesTotalOverflowNegativeSearch, $arrestRecordFile[$i], $overlfowMatch))
					$costName .= " " . trim($arrestRecordFile[$i]);

				$this->setCost($this->costGeneric, $costName, trim($matches[2]),  trim($matches[3]), trim($matches[4]), trim($matches[5]), trim($matches[6]));
			}

			/*
			else if (preg_match(self::$bailSearch, $line, $matches))
			{
				$this->addBailCharged(doubleval(str_replace(",","",$matches[1])));  
				$this->addBailPaid(doubleval(str_replace(",","",$matches[2])));  // the amount paid
				$this->addBailAdjusted(doubleval(str_replace(",","",$matches[3])));
				$this->addBailTotal(doubleval(str_replace(",","",$matches[5])));  // tot final amount, after all adjustments
			}

			else if (preg_match(self::$costsSearch, $line, $matches))
			{
				$this->setCostsCharged(doubleval(str_replace(",","",$matches[1])));  
				$this->setCostsPaid(doubleval(str_replace(",","",$matches[2])));  // the amount paid
				$this->setCostsAdjusted(doubleval(str_replace(",","",$matches[3])));
				$this->setCostsTotal(doubleval(str_replace(",","",$matches[5])));  // tot final amount, after all adjustments
			}
			*/
		}
	}
		
	// Compares two arrests to see if they are part of the same case.  Two arrests are part of the 
	// same case if the DC or OTNs match; first check DC, then check OTN.
	// There are some cases where the OTNs match, but not the DC.  This can happen when:
	// someone is arrest and charged with multiple sets of crimes; all of these cases go to CP court
	// but they aren't consolidated.  B/c the arrests happened at the same time, OTN will
	// be the same on all cases, but the DC numbers will only match from the MC to the CP that 
	// follows
	// Don't match true if we match ourself
	public function compare($that)
	{
		// return false if we match ourself
		if ($this->getFirstDocketNumber() == $that->getFirstDocketNumber())
			return FALSE;
		else if ($this->getDC() != self::$unknownInfo && $this->getDC() == $that->getDC())
			return TRUE;
		else if ($this->getDC() == self::$unknownInfo && ($this->getOTN() != self::$unknownInfo && $this->getOTN() == $that->getOTN()))
		  	return TRUE;
		else
			return FALSE;
	}

	// combines the $this and $that. We assume for the purposes of this function that
	// $this and $that are the same docket number as that was previously checked
	// @param $that is an Arrest, but obtained from the Summary docket sheet, so it doesn't
	// have charge information with, just judge, arrest date, etc...
/*
	public function combineWithSummary($that)
	{
		if ($that->getJudge() != "")
			$this->setJudge($that->getJudge());
		if (!isset($this->arrestDate) || $this->getArrestDate() == self::$unknownInfo)
			$this->setArrestDate($that->getArrestDate());
		if (!isset($this->dispositionDate) || $this->getDispositionDate() == self::$unknownInfo)
			$this->setDispositionDate($that->getDispositionDate());
	}
*/
	//gets the first docket number on the array
	// Compares $this arrest to $that arrest and determines if they are actually part of the same
	// case.  Two arrests are part of the same case if they have the same OTN or DC number.
	// If the two arrests are part of the same case, combines them by taking all of the information
	// from one case and adding it to the other case (unless that information is already there.
	// It is important to note that you can only combine a CP case with an MC case.  You cannot
	// two MC cases together without a CP.
	// @param $that = Arrest to combine with $this
/*
	public function combine($that)
	{
		
		// if $this isn't a CP case, then don't combine.  If $that is a CP case, don't combine.
		if (!$this->isCP() || $that->isCP())
		{
			return FALSE;
		}
		
		// return false if we don't find something with the same DC or OTN number
		if (!$this->compare($that))
			return FALSE;
		
		// if $that (the MC case) is an expungement itself, then we don't want to combine.
		// If the MC case was an expungement, then no charges will move up from the MC case
		// to the associated CP case.  This happens in the following situation: 
		// Person is arrested and charged with three different sets of crimes that show up on
		// 3 different MC cases.  One of the MC cases is completely resolved at the prelim hearing
		// and charges are dismissed.  The other two MC cases have "held for court" charges
		// which are brought up to a CP case.  THe CP case OTN will match all three MC cases, but 
		// will only have charges from the two MC cases that were "held for court"
		if ($that->isArrestExpungement())
			return FALSE;
		
		// combine docket numbers
		$this->setDocketNumber(array_merge($this->getDocketNumber(),$that->getDocketNumber()));
		
		// combine charges.  Only include $that charges that are not "held for court"
		// The reason for this is that held for court charges will already appear on the CP,
		// they will just appear with a disposition.  We don't want to include held for court
		// charges and then assume that this isn't an expungement in our later logic.
		// This is a possible future thing to change.  Perhaps held for court should be put on
		// And something should be "expungeable" regardless of whether "held for court"
		// charges are on there.
		$thatChargesNoHeldForCourt = array();
		foreach ($that->charges as $charge)
		{
			$thatDisp = $charge->getDisposition();

			// note strange use of strpos.  strpos returns the location of the first occurrence of the string
			// or boolean false.  you have to check with === FALSE b/c the first occurence of the strong could
			// be position 0 or 1, which would otherwise evaluate to true and false!
			if (strpos($thatDisp, "Held for Court")===FALSE && strpos($thatDisp, "Waived for Court")===FALSE)
				$thatChargesNoHeldForCourt[] = $charge;
		}
		
		// if $thatChargesNoHeldForCourt[] has less elements than $that->charges, we know that
		// some charges were disposed of at the lower court level.  In that case, we need to
		// add the lower court judges in as well on the expungement sheet.
		// @todo add judges here
		$this->setCharges(array_merge($this->getCharges(),$thatChargesNoHeldForCourt));
		
		// combine bail amounts.  This isn't used for the petitions, but it is helpful for later
		// when we print out the overview of bail.  
		// Generally speaking, an individual could have a bail assessment on an MC case, even if
		// all charged went to CP court (this would happen if they failed to appear for a hearing
		// and then later appeared, were sent to CP court, and were tried there.
		// generally speaking, there are not fines on an MC case that is ultimately combined with
		// a CP case.
		$this->setBailChargedTotal($this->getBailChargedTotal()+$that->getBailChargedTotal());
		$this->setBailTotalTotal($this->getBailTotalTotal()+$that->getBailTotalTotal());
		$this->setBailAdjustedTotal($this->getBailAdjustedTotal()+$that->getBailAdjustedTotal());
		$this->setBailPaidTotal($this->getBailPaidTotal()+$that->getBailPaidTotal());

		// set MDJ as "2" if that is an an mdj.  "2" means that this is a case descending from MDJ
		// also set the mdj number
		if ($that->getIsMDJ())
		{
			$this->setIsMDJ(2);
			$this->setMDJDistrictNumber($that->getMDJDistrictNumber());
		}
		return TRUE;
	}
*/

/*
	// @return a comma separated list of all of the dispositions that are on the "charges" array
	// @param if redactableOnly is true (default) returns only redactable offenses
	public function getDispList($redactableOnly=TRUE)
	{
		$disposition = "";
		foreach ($this->getCharges() as $charge)
		{
			// if we are only looking for redactable charges, skip this charge if it isn't redactable
			if ($redactableOnly && !$charge->isRedactable())
				continue;
			if ((stripos($disposition,$charge->getDisposition())===FALSE))
			{
				if ($disposition != "")
					$disposition .= ", ";
				$disposition .= $charge->getDisposition();
			}
		}
		return $disposition;
	}
*/	

/*
	// @param redactableOnly - boolean defaults to false; if set to true, only returns redactable charges
	// @return a string holding a comma separated list of charges that are in the charges array; 
	// @return if "redactableOnly" is TRUE, returns only those charges that are expungeable	
	public function getChargeList($redactableOnly=FALSE)
	{
		$chargeList = "";
		foreach ($this->getCharges() as $charge)
		{
			// if we are trying to only get the list of "Expungeable" offenses, then 
			// continue to the next charge if this charge is not Expungeable
			if ($redactableOnly && !$charge->isRedactable())
				continue;
			if ($chargeList != "")
				$chargeList .= ", ";
			$chargeList .= ucwords(strtolower($charge->getChargeName()));
		}
		return $chargeList;
	}
*/
	
	
	// returns the age based off of the DOB read from the arrest record
	public function getAge()
	{
		$birth = new DateTime($this->getDOB());
		$today = new DateTime();
		return dateDifference($today, $birth);
	}

	// @return the disposition date of the first charge on the charges array
	// @return if no disposition date exists on the first chage, then sets the dipsositionDate to the migrated disposition date
	public function getDispositionDate()
	{
		if (!isset($this->dispositionDate))
		{
			if (count($this->charges))
			{
				$firstCharge = $this->getCharges();
				$this->setDispositionDate($firstCharge[0]->getDispDate());
			}
			else
				$this->setDispositionDate(self::$unknownInfo);
		}	
		
		return $this->dispositionDate;
	}
	
	// @function getBestDispotiionDate returns a dispotition date if available.  Otherwise returns
	// the arrest date.
	// @return a date
	public function getBestDispositionDate()
	{
		if ($this->getDispositionDate() != self::$unknownInfo)
			return $this->getDispositionDate();
		else
			return $this->getArrestDate();
	}

	// returns true if this is a criminal offense.  this is true if we see CP|MC-##-CR|SU, 
	// not SA or MD
	public function isArrestCriminal()
	{
		if (isset($this->isCriminal))
			return  $this->getIsCriminal();
		else
		{
			$criminalMatch = "/CR|SU|MJ/";
			if (preg_match($criminalMatch, $this->getFirstDocketNumber()))
			{
					$this->setIsCriminal(TRUE);
					return TRUE;
			}
			$this->setIsExpungement(FALSE);
			return FALSE;
		}
	}
/*
	// returns true if this arrest includes ARD offenses.
	public function isArrestARDExpungement()
	{
		if (isset($this->isARDExpungement))
			return  $this->getIsARDExpungement();
		else
		{
			foreach ($this->getCharges() as $num=>$charge)
			{
				if($charge->isARD())
				{
					$this->setIsARDExpungement(TRUE);
					return TRUE;
				}
			}
			$this->setIsARDExpungement(FALSE);
			return FALSE;
		}
	}
*/

/*
	// @function isArrestOver70Expungement() - returns true if the petition is > 70yo and they have been arrest
	// free for at least the last 10 years.
	// @param arrests - an array of all of the other arrests that we are comparing this to to see if they are 
	// 10 years arrest free
	//@ return TRUE if the conditions above are me; FALSE if not
	public function isArrestOver70Expungement($arrests, $person)
	{
		// if already set, then just return the member variable
		if (isset($this->isArrestOver70Expungement))
			return $this->isArrestOver70Expungement;
			
		// return false right away if the petition is younger than 70
		if ($person->getAge() < 70)
		{
			$this->setIsArrestOver70Expungement(FALSE);
			return FALSE;
		} 	

		// also return false right away if there aren't any charges to actually look at
		if (count($this->getCharges())==0)
		{
			$this->setIsArrestOver70Expungement(FALSE);
			return FALSE;
		} 	
		
		// do an over 70 exp if at least one is not redactible; if this is a regular exp, just do a regular exp
		// NOTE: THis may be a problem for HELD FOR COURT charges; keep this in mind
		if ($this->isArrestExpungement())
		{
			$this->setIsArrestOver70Expungement(FALSE);
			return FALSE;
		}
		
		// at this point we know two things: we are over 70 and we need to get non-redactable charges off of 
		// the record
		// Loop through all of the arrests passed in to get the disposition dates or the 
		// arrest dates if the disposition dates don't exist.  
		// return false if any of them are within 10 years of today

		$dispDates = array();
		$dispDates[] = new DateTime($this->getBestDispositionDate());
		foreach ($arrests as $arrest)
		{
			$dispDates[] = new DateTime($arrest->getBestDispositionDate());
		}

		// look at each dispDate in the array and make sure it was more than 10 years ago
		$today = new DateTime();
		foreach ($dispDates as $dispDate)
		{
			if (abs(dateDifference($dispDate, $today)) < 10)
			{
				$this->setIsArrestOver70Expungement(FALSE);
				return FALSE;
			}
		}
		
		// if we got here, it means there are no five year periods of freedom
		$this->setIsArrestOver70Expungement(TRUE);
		return TRUE;
			
	}
*/

/*
	// @function isArrestSummaryExpungement - returns true if this is an expungeable summary 
	// arrest.  
	// This is true in a slightly more complicated sitaution than the others.  To be a 
	// summary expungement a few things have to be true:
	// 1) This has to be a summary offense, characterized by "SU" in the docket number.
	// 2) The person must have been found guilty or plead guilty to the charges (if they were
	// not guilty or dismissed, then there is nothing to worry about - normal expungmenet.
	// 3) The person must have five years arrest free AFTER the arrest.  This doesn't have to be 
	// the five years immediately following the arrest nor does it have to be the most recent five
	// years.  It just has to be five years arrest free at some point post arrest.  
	// @note - a problem that might come up is if someone has a summary and then is confined in jail
	// for a long period of time (say 10 years).  This will apear eligible for a summary exp, but
	// is not.
	// @param arrests - an array of all of the other arrests that we are comparing this too to see
	// if they are 5 years arrest free
	// @return TRUE if the conditions above are met; FALSE if not.
	public function isArrestSummaryExpungement($arrests)
	{
		// if already set, then just return the member variable
		if (isset($this->isArrestSummaryExpungement))
			return $this->isArrestSummaryExpungement;
			
		// return false right away if this is not a summary arrest
		if (!$this->getIsSummaryArrest())
		{
			$this->setIsArrestSummaryExpungement(FALSE);
			return FALSE;
		} 	

		// also return false right away if there aren't any charges to actually look at
		if (count($this->getCharges())==0)
		{
			$this->setIsArrestSummaryExpungement(FALSE);
			return FALSE;
		} 	
		
		// loop through all of the charges; only do a summary exp if none are redactible
		// NOTE: THis may be a problem for HELD FOR COURT charges; keep this in mind
		// NOTE: Is it possible that someone has some not guilty and some guilty for summary charges?
		foreach ($this->getCharges() as $num=>$charge)
		{
			if($charge->isRedactable())
			{
				$this->setIsArrestSummaryExpungement(FALSE);
				return FALSE;
			}
		}
			
		// at this point we know two things: summary arrest and the charges are all guilties.
		// now we need to check to see if they are arrest free for five years.	
		// Loop through all of the arrests passed in to get the disposition dates or the 
		// arrest dates if the disposition dates don't exist.  
		// Drop dates that are before this date.
		// Make a sorted array of all of the dates and find the longest gap.
		$thisDispDate = new DateTime($this->getBestDispositionDate());
		$dispDates = array();

		$dispDates[] = $thisDispDate;
		$dispDates[] = new DateTime(); // add today onto the array as well
		foreach ($arrests as $arrest)
		{
			$thatDispDate = new DateTime($arrest->getBestDispositionDate());
			// if the disposition date of that arrest was before this arrest, ignore it
			if ($thatDispDate < $thisDispDate)
				continue;
			else
				$dispDates[] = $thatDispDate;
		}
		// sort array
		asort($dispDates);

		// sort through the first n-1 members of the dateArray and compare them to the next
		// item in the array to see if there is more than 5 years between them
		for ($i=0; $i<(sizeof($dispDates)-1); $i++)
		{
			if (abs(dateDifference($dispDates[$i+1], $dispDates[$i])) >= 5)
			{
				$this->setIsArrestSummaryExpungement(TRUE);
				return TRUE;
			}
		}
		
		// if we got here, it means there are no five year periods of freedom
		$this->setIsArrestSummaryExpungement(FALSE);
		return FALSE;
			
	}
*/
/*
	// returns true if this is an expungeable arrest.  this is true if no charges are guilty
	// or guilty plea or held for court.
	public function isArrestExpungement()
	{
		if (isset($this->isExpungement))
			return  $this->getIsExpungement();

		else
		{
			foreach ($this->getCharges() as $num=>$charge)
			{
				// the quirky case where on a CP, the held for court charges are listed from the MC
				// case.
				if ($this->isCP() && $charge->getDisposition() == "Held for Court")
					continue;
				if(!$charge->isRedactable())
				{
					$this->setIsExpungement(FALSE);
					return FALSE;
				}
			}
			
			// deal with the quirky case where there are no charges on the array.  This happens
			// rarely where there is a docket sheet that lists charges, but doesn't list
			// dispositions at all.
			if (count($this->getCharges()) == 0)
			{
					$this->setIsExpungement(FALSE);
					return FALSE;
			}
			
			$this->setIsExpungement(TRUE);
			return TRUE;
		}
	}
*/

	// returns true if the first docket number starts with "CP"
	public function isCP()
	{
		if (isset($this->isCP))
			return $this->getIsCP();
		else
		{
			$match = "/^CP/";
			if (preg_match($match, $this->getFirstDocketNumber()))
			{
				$this->setIsCP(TRUE);
				return TRUE;
			}
			
			$this->setIsCP(FALSE);
			return FALSE;
		}
	}

/*	
	// returns true if this is a redactable offense.  this is true if there are charges that are NOT
	// guilty or guilty plea or held for court.  returns true for expungements as well.
	public function isArrestRedaction()
	{
		if (isset($this->isRedaction))
			return  $this->getIsRedaction();

		else
		{
			foreach ($this->getCharges() as $charge)
			{
				// if we don't match Guilty|Guilty Plea|Held for court, this is redactable
				if ($charge->isRedactable())
				{
					$this->setIsRedaction(TRUE);
					return TRUE;
				}
			}
			// if we ever get here, we have no redactable offenses, so return false
			$this->setIsRedaction(FALSE);
			return FALSE;
		}
	}
*/

/*
	// return true if any of the charges are held for court.  this means we are ripe for 
	// consolodating with another arrest
	public function isArrestHeldForCourt()
	{
		if (isset($this->isHeldForCourt))
				return  $this->getIsHeldForCourt();
		else
		{
			$heldForCourtMatch = "/[Held for Court|Waived for Court]/";
			foreach ($this->getCharges() as $num=>$charge)
			{
				// if we match Held for court, setheldforcourt = true
				if (preg_match($heldForCourtMatch,$charge->getDisposition()))
				{
					$this->setIsHeldForCourt(TRUE);
					return TRUE;
				}
			}
			// if we ever get here, we have no heldforcourt offenses, so return false
			$this->setIsHeldForCourt(FALSE);
			return FALSE;
	
		}
	}
*/

/*	
	// @returns an associative array with court information based on the county name
	public function getCourtInformation($db)
	{
		// $sql is going to be different based on whether this is an mdj case or a regular case
		$table = "court";
		$column = "county";
		$value = $this->getCounty();
		
		if ($this->getIsMDJ() == 1)
		{
			$table = "mdjcourt";
			$column = "district";
			$value = $this->getMDJDistrictNumber();
		}

		// sql statements are case insensitive by default		
		$query = "SELECT * FROM $table WHERE $table.$column='$value'";
		$result = mysql_query($query, $db);

		if (!$result) 
		{
			if ($GLOBALS['debug'])
				die('Could not get the court information from the DB:' . mysql_error());
			else
				die('Could not get the court Information from the DB');
		}
		$row = mysql_fetch_assoc($result);
		return $row;
	}
*/	
		
	public function simplePrint()
	{
		print "\nDocket Num: ";
		foreach ($this->getDocketNumber() as $value)
			print "$value | ";
 		print "\nCross Court Docket: " . $this->getCrossCourtDocket();
		print "\nLower Court Docket: " . $this->getLowerCourtDocket();
		print "\nOTN: " . $this->getOTN();
		print "\nDC: " . $this->getDC();
		print "\nArresting Officer: " . $this->getArrestingOfficer();
		print "\nArresting Agency: " . $this->getArrestingAgency();
		print "\nDate Filed: " . $this->getDateFiled();
		print "\nArrest Date: " . $this->getArrestDate();
		print "\nComplaint Date: " . $this->getComplaintDate();
		print "\nInitial Authority: " . $this->getInitialIssuingAuthority();
		print "\nFinal Authority: " . $this->getFinalIssuingAuthority();
		print "\nJudge Assigned: " . $this->getJudgeAssigned();
		print "\nFirst Name: " . $this->getFirstName();
		print "\nLast Name: " . $this->getLastName();
		print_r ($this->getAliases());
		print "\nDOB: " . $this->getDOB();
		print "\nCity: " . $this->getCity();
		print "\nState: " . $this->getState();
		print "\nZip: " . $this->getZip();
		print "\nCounty: " . $this->getCounty();
		print "\nCW Agency: " . $this->getCommonwealthAgency();
		print "\nCW Role: " . $this->getCommonwealthRole();
		print "\nCW Supreme Ct ID: " . $this->getCommonwealthSupremeCourtNumber();
		print "\nD Lawyer: " . $this->getDLawyer();
		print "\nD Role: " . $this->getDRole();
		print "\nD SupremeCt ID: " . $this->getDSupremeCourtNumber();
		
		foreach ($this->getCharges() as $num=>$charge)
		{
			print "\n\tcharge $num: " . $charge->getChargeName() . "|".$charge->getDisposition()."|".$charge->getCodeSection()."|".$charge->getGrade()."|".$charge->getDispDate()."|".$charge->getFinalDisposition();
		}
		
		foreach ($this->costTotals as $cost)
			print_r($cost);
		foreach ($this->costGeneric as $cost)
			print_r($cost);

	}
	
 	public function writeDocketToDatabase($db)
	{
		// write the defendant to the database and get the ID
		$defendantID = $this->writeDefendantToDatabase($db);
		
		// write both attorneys to the database and get the IDs
		$defAttorneyID = $this->writeAttorneyToDatabase($db, $this->getDLawyer(), $this->getDRole(), $this->getDSupremeCourtNumber());
		$cwAttorneyID = $this->writeAttorneyToDatabase($db, $this->getCommonwealthAgency(), $this->getCommonwealthRole(), $this->getCommonwealthSupremeCourtNumber());

		// write the arresting agency to the database and get the ID
		$arrestingAgencyID = $this->writeArrestingAgencyToDatabase($db);
		
		// write the main docket information to the database
		$caseID = $this->writeCaseToDatabase($db, $defendantID, $defAttorneyID, $cwAttorneyID, $arrestingAgencyID);

		
		// write the charges to the database
		$this->writeChargesToDatabase($db, $caseID);
		
		// write the fines and costs to the database		
		$this->writeFinesCostsToDatabase($db, $caseID, $this->getCostGeneric(), 0);
		$this->writeFinesCostsToDatabase($db, $caseID, $this->getCostTotal(), 1);

	}

	// @return the id of the defendant just inserted into the database
	// @param $db - the database handle
	public function writeDefendantToDatabase($db)
	{
		$aliasesDelimited = "";
		foreach ($this->getAliases() as $name)
		{
			if (empty($aliasesDelimited))
				$aliasesDelimited = "$name";
			else
				$aliasesDelimited .= ";$name";
		}
	
		$sql = "INSERT INTO Defendant (`firstName`, `lastName`, `DOB`, `city`, `state`, `zip`, `aliases`) VALUES ('" . mysql_real_escape_string($this->getFirstName()) . "', '" . mysql_real_escape_string($this->getlastName()) . "', '" . dateConvert($this->getDOB()) . "', '" . mysql_real_escape_string($this->getCity()) . "', '" . mysql_real_escape_string($this->getState()) . "', '" . mysql_real_escape_string($this->getZip()) . "', '" . mysql_real_escape_string($aliasesDelimited) . "')";
		
		$result = mysql_query($sql, $db);
		if (!$result) 
			die('Could not add defendant to the DB:' . mysql_error());

		return mysql_insert_id();
	}
	
	// @return the id of the attorney just inserted into the database (or found in the database)
	// @param $db - the database handle
	// @param $name - the name of the attorney or agency
	// @param $role - the role (private attorney, prosecutor, etc...)
	// @param $barID - the attorney's pa bar ID number
	public function writeAttorneyToDatabase($db, $name, $role, $barID)
	{
		// look up the attorney first.  If we find the attorney, then return the attorney ID; if not, then insert the attorney
		$id = $this->checkInDB($db, "Attorney", "supremeCourtID", $barID, "AttorneyName", $name, "id");
		
		// $id will only equal 0 if there is no attorney with this name in the DB
		if ($id == 0)
		{
			//print "\nInserting new attorney: $name";
			$sql = "INSERT INTO Attorney (`attorneyName`, `role`, `supremeCourtID`) VALUES ('" . mysql_real_escape_string($name) . "', '" . mysql_real_escape_string($role) . "', '" . mysql_real_escape_string($barID) . "')";

			//print "\n" . $sql; 
			
			$result = mysql_query($sql, $db);
			if (!$result) 
				die('Could not add defendant to the DB:' . mysql_error());
			
			return mysql_insert_id();
		
		}
		else 
			return $id;
	}

	// @return the id of the arresting agency just inserted into the database (or found in the database)
	// @param $db - the database handle
	public function writeArrestingAgencyToDatabase($db)
	{
		// look up the attorney first.  If we find the attorney, then return the attorney ID; if not, then insert the attorney
		$id = $this->checkInDB($db, "ArrestingAgency", "agencyName", $this->getArrestingAgency(), null, null, "id");
		
		// $id will only equal 0 if there is no attorney with this name in the DB
		if ($id == 0)
		{
			// print "\nInserting new agency: " . $this->getArrestingAgency();
			$sql = "INSERT INTO ArrestingAgency (`agencyName`) VALUES ('" . mysql_real_escape_string($this->getArrestingAgency()). "')";

			// print "\n" . $sql; 
			
			$result = mysql_query($sql, $db);
			if (!$result) 
				die('Could not add agency to the DB:' . mysql_error());
			
			return mysql_insert_id();
		
		}
		else 
			return $id;
	}

	// @return the id of the arresting agency just inserted into the database (or found in the database)
	// @param $db - the database handle
	// @param $caseID - the id of the case that we are writing charges for
	public function writeChargesToDatabase($db, $caseID)
	{
		// iterate over each charge on the charges array
		foreach ($this->getCharges() as $charge)
		{
		
			// look up the charge first.  If we find the charge, then return the charge ID; if not, then insert the charge
			$chargeid = $this->checkInDB($db, "Charges", "chargeName", $charge->getChargeName(), "codeSection", $charge->getCodeSection(), "id");
		
			// $id will only equal 0 if there is no attorney with this name in the DB
			// if the ID is 0, then we start by adding the charge to the chargeDB
			if ($chargeid == 0)
			{
			//	 print "\nInserting new charge: " . $charge->getChargeName();
				$sql = "INSERT INTO Charges (`chargeName`, `codeSection`) VALUES ('" . mysql_real_escape_string($charge->getChargeName()). "', '" . mysql_real_escape_string($charge->getCodeSection()). "')";

				 // print "\n" . $sql; 
			
				$result = mysql_query($sql, $db);
				if (!$result) 
					die('Could not add charge to the DB:' . mysql_error());
			
				$chargeid = mysql_insert_id();
			}
			
			// next, check to see if the disposition is in the database
			$dispositionID = $this->checkInDB($db, "Dispositions", "dispositionName", $charge->getDisposition(), null, null, "id");
		
			// $id will only equal 0 if there is no attorney with this name in the DB
			// if the ID is 0, then we start by adding the charge to the chargeDB
			if ($dispositionID == 0)
			{
				// print "\nInserting new disposition: " . $charge->getDisposition();
				$sql = "INSERT INTO Dispositions (`dispositionName`) VALUES ('" . mysql_real_escape_string($charge->getDisposition()). "')";

				 // print "\n" . $sql; 
			
				$result = mysql_query($sql, $db);
				if (!$result) 
					die('Could not add disposition to the DB:' . mysql_error());
			
				$dispositionID = mysql_insert_id();
			}
		
			// now that we have all of our IDs, add the actual charge to the database
			$sql = "INSERT INTO CaseCharges (`caseID`, `chargeID`, `dispositionID`, `grade`, `dispDate`, `isFinalDisposition`) VALUES ('$caseID', '$chargeid', '$dispositionID', '" . mysql_real_escape_string($charge->getGrade()). "', '" . dateConvert($charge->getDispDate()) . "', '" . (int)$charge->getFinalDisposition(). "')";

			//print "\n" . $sql; 
		
			$result = mysql_query($sql, $db);
			if (!$result) 
				die('Could not add case-charge to the DB:' . mysql_error());
		
		}
	}
	
	// @return the id of the arresting agency just inserted into the database (or found in the database)
	// @param $db - the database handle
	// @param $caseID - the id of the case that we are writing charges for
	// @param $fineCostArray is the array that we are looping over.  It is the generic costs or the total costs
	// @param $isTotal is set to 0 for generic costs and 1 otherwise
	public function writeFinesCostsToDatabase($db, $caseID, $fineCostArray, $isTotal)
	{
		// iterate over each charge on the fines/costs array passed in
		foreach ($fineCostArray as $finesCosts)
		{
		
			// look up the fine name first.  If we find the fine name in the DB, then return the fineID; if not, then insert it
			$fineID = $this->checkInDB($db, "FinesCosts", "fineName", $finesCosts[0], null, null, "id");
		
			// $id will only equal 0 if there is no fine with this name in the DB
			// if the ID is 0, then we start by adding the fine to the fine table
			if ($fineID == 0)
			{
				 //print "\nInserting new fine: " . $finesCosts[0];
				$sql = "INSERT INTO FinesCosts (`fineName`) VALUES ('" . mysql_real_escape_string($finesCosts[0]) . "')";

				 //print "\n" . $sql; 
			
				$result = mysql_query($sql, $db);
				if (!$result) 
					die('Could not add fine to the DB:' . mysql_error());
			
				$fineID = mysql_insert_id();
			}

			// now that we have our ID, add the actual fine to the database
			$sql = "INSERT INTO CaseFines (`caseID`, `costFineID`, `assessment`, `payment`, `adjustment`, `non-monetary`, `total`, `isTotal`) VALUES ('$caseID', '$fineID', '$finesCosts[1]', '$finesCosts[2]', '$finesCosts[3]', '$finesCosts[4]', '$finesCosts[5]', '$isTotal')";

			//print "\n" . $sql; 
		
			$result = mysql_query($sql, $db);
			if (!$result) 
				die('Could not add case-fine to the DB:' . mysql_error());
		
		}
		
		
	}

	// checks to see if an item is in the specified table in the db
	// if it is, return the row number; if not, return 0
	// @return 0 if there is nothing in the DB and the $fieldSought otherwise
	// @param $db the database handle
	// @param $table - the table to search in
	// @param $field - the field to match to see if we have a unique entry
	// @param $value - the value of the field to match to see if we have a unique entry
	// @param $field2 - the other field to match to see if we have a unique entry
	// @param $value2 - the other value of the field to match to see if we have a unique entry
	// @param $fieldSought - the field we want returned if this is not a unique entry
	public function checkInDB($db, $table, $field, $value, $field2, $value2, $fieldSought)
	{
		$sql = "SELECT id FROM $table WHERE $field='" . mysql_real_escape_string($value) . "'";
		if (!empty($field2) && !empty($value2))
			$sql .= " AND $field2='" . mysql_real_escape_string($value2) . "'";

		$result = mysql_query($sql, $db);
		if (!$result) 
			die('Could not check if the item existed in table $table in the DB:' . mysql_error());
	
		// print "\n$sql";
		
		// if there is a row already, then set the person ID, return true, and get out
		if (mysql_num_rows($result)>0)
			return mysql_result($result,0);
		else
			return 0;
	}	

	// @return the id of the arrest just inserted into the database
	// @param $defendantID - the id of the defendant that this arrest concerns
	// @param $db - the database handle
	// @param $defAttorneyID - the id of the defense attorney
	// @param $cwAttorneyID - the id of the commonwealth attorney
	public function writeCaseToDatabase($db, $defendantID, $defAttorneyID, $cwAttorneyID, $arrestingAgencyID)
	{
		$sql = "INSERT INTO `Case` (`docket`, `crossCourtDocket`, `lowerCourtDocket`, `OTN`, `DC`, `dateFiled`, `arrestDate`, `complaintDate`, `arrestingOfficer`, `arrestingAgencyID`, `initialAuthority`, `finalAuthority`, `judgeAssigned`, `county`, `defendantID`, `CWAttorneyID`, `defAttorneyID`) VALUES ('" . mysql_real_escape_string($this->getDocketNumber()) ."', '" . mysql_real_escape_string($this->getCrossCourtDocket()) ."', '" . mysql_real_escape_string($this->getLowerCourtDocket()) ."', '" . $this->getOTN() . "', '" . $this->getDC() . "', '" . dateConvert($this->getDateFiled()) . "', '" . dateConvert($this->getArrestDate()) . "', '" . dateConvert($this->getComplaintDate()) . "', '" . mysql_real_escape_string($this->getArrestingOfficer()) . "', '" . $arrestingAgencyID . "', '" . mysql_real_escape_string($this->getInitialIssuingAuthority()) . "', '" . mysql_real_escape_string($this->getFinalIssuingAuthority()) . "', '" . mysql_real_escape_string($this->getJudgeAssigned()) . "', '" . mysql_real_escape_string($this->getCounty()) . "', '" . $defendantID . "', '" . $cwAttorneyID . "', '" . $defAttorneyID . "')";

		// print $sql;
		$result = mysql_query($sql, $db);
		if (!$result) 
				die('Could not add the arrest to the DB:' . mysql_error());
		return mysql_insert_id();
	}


	
}  // end class Docket

?>