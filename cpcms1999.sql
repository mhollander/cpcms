-- phpMyAdmin SQL Dump
-- version 3.5.1
-- http://www.phpmyadmin.net
--
-- Host: localhost
-- Generation Time: Jun 08, 2015 at 09:21 PM
-- Server version: 5.5.24-log
-- PHP Version: 5.4.3

SET SQL_MODE="NO_AUTO_VALUE_ON_ZERO";
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8 */;

--
-- Database: `cpcms1999`
--

DELIMITER $$
--
-- Procedures
--
DROP PROCEDURE IF EXISTS `fines_costs_by_year`$$
CREATE DEFINER=`root`@`localhost` PROCEDURE `fines_costs_by_year`()
begin

	declare year int default 2001;
	declare end int default  2013;

	while year <= end DO
		select year;

		SELECT
			count(b.id) as totalcases,
			AVG(b.assessment) as avgassessment
		FROM
		(
			SELECT
				a.id,
				SUM(a.assessment) as assessment
			FROM
			(
				SELECT 
					`case`.id,
					`case`.docket,
					finescosts.finename,
					casefines.assessment
				FROM
				
					`case` LEFT JOIN casefines   
						ON (`case`.id=casefines.caseid) LEFT JOIN finescosts   
						ON (casefines.costfineid=finescosts.id)
				WHERE
					docket LIKE concat('%51%-', year) AND
					finename NOT LIKE 'Bail%' AND
					finename NOT LIKE 'Bond%' AND
					finename NOT LIKE 'OSP%'
			) as a
			group by a.id
		) as b;


		set year = year+1;

		END WHILE;
END$$

DROP PROCEDURE IF EXISTS `summary_by_year`$$
CREATE DEFINER=`root`@`localhost` PROCEDURE `summary_by_year`()
begin

	declare year int default 2001;
	declare end int default  2013;

	while year <= end DO
		select year as '';
		SELECT count(docket) as 'total summaries' from `case` where docket LIKE CONCAT("%SU%",year);
		SELECT COUNT(DISTINCT casecharges.caseid) as 'cases with at least one conviction' FROM `case` left join casecharges on `case`.id = casecharges.caseid left join dispositions on casecharges.dispositionID = dispositions.id WHERE `case`.docket like CONCAT("%SU%", year) and (dispositions.dispositionName like "Guilty%" OR dispositions.dispositionName LIKE "Summary Guilty%" OR dispositions.dispositionName like "Community Court Program" or dispositions.dispositionName like "Summary Diversion%" OR dispositions.dispositionName like "Nolo Con%");
		SELECT COUNT(DISTINCT casecharges.caseid) as 'cases with at least one held for court' FROM `case` left join casecharges on `case`.id = casecharges.caseid left join dispositions on casecharges.dispositionID = dispositions.id WHERE `case`.docket like CONCAT("%SU%", year) and (dispositions.dispositionName like "Proceed%" OR dispositions.dispositionName LIKE "Held%");

		set year = year+1;
	END WHILE;
END$$

DELIMITER ;

-- --------------------------------------------------------

--
-- Table structure for table `arrestingagency`
--

DROP TABLE IF EXISTS `arrestingagency`;
CREATE TABLE IF NOT EXISTS `arrestingagency` (
  `id` smallint(6) NOT NULL AUTO_INCREMENT,
  `agencyName` varchar(75) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB  DEFAULT CHARSET=latin1 AUTO_INCREMENT=3 ;

-- --------------------------------------------------------

--
-- Table structure for table `attorney`
--

DROP TABLE IF EXISTS `attorney`;
CREATE TABLE IF NOT EXISTS `attorney` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `attorneyName` varchar(75) NOT NULL,
  `role` varchar(30) NOT NULL,
  `supremeCourtID` varchar(6) NOT NULL COMMENT 'This isn''t unique because some attorneys don''t have their ID listed (like most of the prosecuting agencies)',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB  DEFAULT CHARSET=latin1 AUTO_INCREMENT=5 ;

-- --------------------------------------------------------

--
-- Table structure for table `case`
--

DROP TABLE IF EXISTS `case`;
CREATE TABLE IF NOT EXISTS `case` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `docket` varchar(21) NOT NULL,
  `crossCourtDocket` varchar(26) DEFAULT NULL,
  `lowerCourtDocket` varchar(21) DEFAULT NULL,
  `OTN` varchar(11) NOT NULL,
  `DC` varchar(11) DEFAULT NULL,
  `dateFiled` date DEFAULT NULL,
  `arrestDate` date DEFAULT NULL,
  `complaintDate` date DEFAULT NULL,
  `arrestingOfficer` varchar(50) DEFAULT NULL,
  `arrestingAgencyID` smallint(6) DEFAULT NULL,
  `initialAuthority` varchar(50) DEFAULT NULL,
  `finalAuthority` varchar(50) DEFAULT NULL,
  `judgeAssigned` varchar(50) DEFAULT NULL,
  `county` varchar(30) NOT NULL,
  `defendantID` int(11) NOT NULL,
  `CWAttorneyID` int(11) DEFAULT NULL,
  `defAttorneyID` int(11) DEFAULT NULL,
  `timestamp` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `id` (`id`),
  KEY `docket` (`docket`)
) ENGINE=InnoDB  DEFAULT CHARSET=latin1 AUTO_INCREMENT=22 ;

-- --------------------------------------------------------

--
-- Table structure for table `casecharges`
--

DROP TABLE IF EXISTS `casecharges`;
CREATE TABLE IF NOT EXISTS `casecharges` (
  `caseID` int(11) NOT NULL,
  `chargeID` smallint(6) NOT NULL,
  `dispositionID` smallint(6) NOT NULL,
  `grade` varchar(5) DEFAULT NULL,
  `dispDate` date DEFAULT NULL,
  `isFinalDisposition` tinyint(1) NOT NULL DEFAULT '0' COMMENT '1 if this is a final disposition on the docket sheet, 0 otherwise.  This is due to the fact that the same charge will appear 3-4 times on one docket sheet sometimes, with only one of those times being a final disposition',
  `timestamp` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY `caseID` (`caseID`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

-- --------------------------------------------------------

--
-- Table structure for table `casefines`
--

DROP TABLE IF EXISTS `casefines`;
CREATE TABLE IF NOT EXISTS `casefines` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `caseID` int(11) NOT NULL,
  `costFineID` smallint(6) NOT NULL,
  `assessment` int(11) NOT NULL DEFAULT '0' COMMENT 'Taken directly from a CPCMS docketsheet',
  `payment` int(11) NOT NULL DEFAULT '0' COMMENT 'Taken directly from a CPCMS docketsheet',
  `adjustment` int(11) NOT NULL DEFAULT '0' COMMENT 'Taken directly from a CPCMS docketsheet',
  `non-monetary` int(11) NOT NULL DEFAULT '0' COMMENT 'Taken directly from a CPCMS docketsheet',
  `total` int(11) NOT NULL DEFAULT '0' COMMENT 'Taken directly from a CPCMS docketsheet',
  `isTotal` tinyint(1) NOT NULL DEFAULT '0' COMMENT 'if this is a "totals" fine, like "Bail Forfeiture Totals", then this is 1.  This is to make joins a bit easier.',
  `timestamp` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1 AUTO_INCREMENT=1 ;

-- --------------------------------------------------------

--
-- Table structure for table `charges`
--

DROP TABLE IF EXISTS `charges`;
CREATE TABLE IF NOT EXISTS `charges` (
  `id` smallint(6) NOT NULL AUTO_INCREMENT,
  `chargeName` varchar(75) NOT NULL COMMENT 'The name of the charge, like "Aggravated Assault"',
  `codeSection` varchar(25) NOT NULL COMMENT 'The code section, like 32 PA s 3432',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB  DEFAULT CHARSET=latin1 AUTO_INCREMENT=29 ;

-- --------------------------------------------------------

--
-- Table structure for table `defendant`
--

DROP TABLE IF EXISTS `defendant`;
CREATE TABLE IF NOT EXISTS `defendant` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `firstName` varchar(20) NOT NULL,
  `lastName` varchar(30) NOT NULL,
  `DOB` date NOT NULL,
  `city` varchar(30) DEFAULT NULL,
  `state` varchar(2) DEFAULT NULL,
  `zip` varchar(5) DEFAULT NULL,
  `aliases` varchar(150) DEFAULT NULL COMMENT 'A list of alias names, separated by a ";"',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB  DEFAULT CHARSET=latin1 AUTO_INCREMENT=22 ;

-- --------------------------------------------------------

--
-- Table structure for table `dispositions`
--

DROP TABLE IF EXISTS `dispositions`;
CREATE TABLE IF NOT EXISTS `dispositions` (
  `id` smallint(6) NOT NULL AUTO_INCREMENT,
  `dispositionName` varchar(50) NOT NULL COMMENT 'The name of the disposition, like "Not Guilty"',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB  DEFAULT CHARSET=latin1 AUTO_INCREMENT=13 ;

-- --------------------------------------------------------

--
-- Table structure for table `finescosts`
--

DROP TABLE IF EXISTS `finescosts`;
CREATE TABLE IF NOT EXISTS `finescosts` (
  `id` smallint(6) NOT NULL AUTO_INCREMENT,
  `fineName` varchar(75) NOT NULL COMMENT 'The name of the individual fine or cost, as taken from a docket sheet',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1 AUTO_INCREMENT=1 ;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
