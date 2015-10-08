<?php
header('Content-Type: text/xml;  charset=utf-8');

include_once "passwords.php";
include_once "defines.php";
include_once "users.php";
include_once "output.php";

global $DBLog, $CardLog, $UserLog;

$SQLUser = $SQLUsers['ddb_usercards'];
$DelverDBLink = new mysqli("localhost", $SQLUser->username, $SQLUser->password, "delverdb");

if($DelverDBLink->connect_errno)
{
	$DBLog->err("Error connecting to database (".$DelverDBLink->connect_errno.") ".$DelverDBLink->connect_error);
	ReturnXML(1, "Internal Error");
	exit;
}

if(!IsLoggedIn())
{
	$UserLog->warning("Attempted access to usercardsajax.php by non-connected user");
	ReturnXML(2, "Login Required");
	exit;
}

$userid = $_SESSION['userid'];

if(!array_key_exists('count', $_GET))
{
	$CardLog->warning("No card count found during card insertion");
	ReturnXML(3, "Card count not found");
	exit;
}

$count = $_GET['count'];
if(!is_numeric($count) || $count == 0 || $count != floor($count))//!is_int($count))
{
	$CardLog->warning("Invalid card count: $count");
	ReturnXML(4, "Card count is invalid: ".$count);
	exit;
}

if(!array_key_exists('cardid', $_GET) && !array_key_exists('cardname', $_GET))
{
	$CardLog->warning("Neither card ID or name found");
	ReturnXML(5, "Card ID or name required.");
	exit;
}

$cardid = null;
$cardname = null;
if(array_key_exists('cardid', $_GET))
{
	$cardid = $_GET['cardid'];
	if(!is_numeric($cardid))
	{
		$CardLog->warning("Invalid card ID: ".$cardid);
		ReturnXML(6, "Invalid card ID: ".$cardid);
	}
}
else if(array_key_exists('cardname', $_GET))
{
	$cardname = $_GET['cardname'];
}
else
{
	$CardLog->warning("Neither card ID or name found");
	ReturnXML(5, "Card ID or Name not found");
}

if(!array_key_exists('setcode', $_GET))
{
	$CardLog->warning("Setcode not found");
	ReturnXML(7, "Setcode not found");
}

$setcode = $_GET['setcode'];
if(!array_key_exists($setcode, Defines::$SetCodeToNameMap))
{
	$CardLog->warning("Invalid setcode: ".$setcode);
	ReturnXML(7, "Invalid setcode: $setcode");
}
$setname = Defines::$SetCodeToNameMap[$setcode];

if($cardname != null)
{
	$cardid = FindCardID();
}
else
{
	$cardname = FindCardName();
}

ConfirmCardIsInSet();

$cardLinkName;
$cardLinkID = FindCardLinkID();
if($cardLinkID != null)
{
	$cardLinkName = FindCardLinkName();
}

$cardname_safe = utf8_encode($cardname);
$cardLinkName_Safe = null;
if($cardLinkID != null)
{
	$cardLinkName_Safe = utf8_encode($cardLinkName);
}

$existingCardCount = ExistingCardCount();

// If the user already has that card we need to either change the values or remove the record entirely
if ( $existingCardCount > 0 )
{
	$newCount = $existingCardCount + $count;
	if ( $newCount <= 0 )
	{
		$count = -$existingCardCount;
		DeleteCardData( $cardid );
		if ( $cardLinkID != null )
		{
			DeleteCardData( $cardLinkID );
		}
		
		$logMsg = "Removed $cardname"
			 . ($cardLinkID != null ? "/$cardLinkName" : "")
			 . " from $setcode for user ($userid)";
		$xmlMsg = "Removed $cardname_safe"
			 . ($cardLinkID != null ? "/$cardLinkName_Safe" : "")
			 . "  from $setname";
		
		$CardLog->log($logMsg);
		ReturnXML( 0, $xmlMsg, $cardid, $setcode, 0 );
	}
	else
	{
		AlterCardData($cardid);
		if($cardLinkID != null)
		{
			AlterCardData($cardLinkID);
		}
		
		if($count > 0)
		{
			$logMsg = "Added $count x $cardname "
				 . ($cardLinkID != null ? "/$cardLinkName" : "")
				 . " to $setcode for user ($userid). Now has $newCount in $setcode";
			$xmlMsg = "Added an extra $count x $cardname_safe"
				 . ($cardLinkID != null ? "/$cardLinkName_Safe" : "")
				 . " to $setname";
	
			$CardLog->log($logMsg);
			ReturnXML(0, $xmlMsg, $cardid, $setcode, $existingCardCount + $count);
		}
		else
		{
			$logMsg = "Removed $count x $cardname"
				 . ($cardLinkID != null ? "/$cardLinkName" : "")
				 . " from $setcode for user ($userid). Now has $newCount in $setcode";
			$xmlMsg = "Removed ".abs($count)." x $cardname_safe"
				 . ($cardLinkID != null ? "/$cardLinkName_Safe" : "")
				 . " from $setname";
			
			$CardLog->log($logMsg);
			ReturnXML(0, $xmlMsg, $cardid, $setcode, $existingCardCount + $count);
		}
	}
}
else if( $count > 0 )
{
	InsertNewCardData( $cardid );
	if ( $cardLinkID != null )
	{
		InsertNewCardData( $cardLinkID );
	}
	
	$logMsg = "Inserted $count x $cardname"
		 . ($cardLinkID != null ? "/$cardLinkName" : "")
		 . " into $setcode for user ($userid)";
	$xmlMsg = "Inserted $count x $cardname_safe"
		 . ($cardLinkID != null ? "/$cardLinkName_Safe" : "")
		 . " into $setname";
		
	$CardLog->log($logMsg);
	ReturnXML(0, $xmlMsg, $cardid, $setcode, $existingCardCount + $count);
}
else
{
	$CardLog->warning("Tried to remove $count x $cardname from $setcode, user ($userid) has none in that set");
	ReturnXML(0, "You do not have a $cardname_safe in $setname!", $cardid, $setcode, 0);
	exit;
}

AddCardChangeLog( $userid, $cardid, $setcode, $count );
if ( $cardLinkID != null)
{
	AddCardChangeLog( $userid, $cardLinkID, $setcode, $count );
}
exit;

///////////////////////////////////////////
// FUNCTIONS

function FindCardName()
{
	global $DelverDBLink, $userid, $cardid, $setcode, $DBLog, $CardLog;
	$stmt = $DelverDBLink->prepare("SELECT * FROM oracle WHERE cardid = ?");
	
	$stmt->bind_param("i", $cardid);
	$stmt->execute();
	$result = $stmt->get_result();
	
	if($result->num_rows == 0)
	{
		$CardLog->warning("Card with ID '$cardid' does not exist");
		ReturnXML(9, "Card with ID '$cardid' does not exist");
	}
	$row = $result->fetch_assoc();
	$cardname = $row['name'];	
	return $cardname;
}

function FindCardID()
{
	global $DelverDBLink, $cardname, $DBLog, $CardLog;
	$stmt = $DelverDBLink->prepare(" SELECT * FROM oracle WHERE name = ? ");
	
	$stmt->bind_param("s", $cardname);
	$stmt->execute();
	$result = $stmt->get_result();
	if($result->num_rows == 0)
	{
		$CardLog->warning("Card with name \"".utf8_encode($cardname)."\" does not exist");
		ReturnXML(11, "\"".utf8_encode($cardname)."\" is not a card");
	}
	$row = $result->fetch_assoc();
	$id = $row['cardid'];	
	$cardname = $row['name'];
	return $id;
}

function ConfirmCardIsInSet()
{
	global $DelverDBLink, $userid, $cardid, $setcode, $setname, $cardname, $DBLog, $CardLog;
	$stmt = $DelverDBLink->prepare("SELECT * FROM oracle, cardsets WHERE oracle.cardid = cardsets.cardid AND oracle.cardid = ? AND cardsets.setcode = ?");
	
	$stmt->bind_param("is", $cardid, $setcode);
	$stmt->execute();
	$result = $stmt->get_result();
	if($result->num_rows == 0)
	{
		$CardLog->warning("Card with name \"".utf8_encode($cardname)."\" (ID: $cardid) does not exist in $setname ($setcode)");
		ReturnXML(11, utf8_encode($cardname)." is not in $setname");
	}
}

function ExistingCardCount()
{
	global $DelverDBLink, $userid, $cardid, $setcode, $DBLog, $CardLog;
	$stmt = $DelverDBLink->prepare("SELECT * FROM usercards WHERE ownerid = ? AND cardid = ? AND setcode = ?");
	$stmt->bind_param("iis", $userid, $cardid, $setcode);
	$stmt->execute();
	$result = $stmt->get_result();
	$row = $result->fetch_assoc();
	
	if(!$row)
	{
		return 0;
	}
	return $row['count'];
}

function AlterCardData($_cardid)
{
	global $DelverDBLink, $userid, $setcode, $existingCardCount, $count;
	
	$stmt = $DelverDBLink->prepare("UPDATE usercards SET count = ? WHERE ownerid = ? AND cardid = ? AND setcode = ?");
	$newCount = $existingCardCount + $count;
	$stmt->bind_param("iiis", $newCount, $userid, $_cardid, $setcode);
	
	return $stmt->execute();
}

function InsertNewCardData($_cardid)
{
	global $DelverDBLink, $userid, $setcode, $count;
	
	$stmt = $DelverDBLink->prepare("INSERT INTO usercards (ownerid, cardid, setcode, count) VALUES (?, ?, ?, ?)");
	$stmt->bind_param("iisi", $userid, $_cardid, $setcode, $count);
	
	return $stmt->execute();
}

function DeleteCardData( $_cardid )
{
	global $DelverDBLink, $userid, $setcode, $count;
	
	$stmt = $DelverDBLink->prepare("DELETE FROM usercards WHERE ownerid = ? AND cardid = ? AND setcode = ?");
	$stmt->bind_param("iis", $userid, $_cardid, $setcode);
	
	return $stmt->execute();
}

function FindCardLinkID()
{
	global $DelverDBLink, $userid, $cardid;
	
	$stmt = $DelverDBLink->prepare("SELECT * FROM oracle WHERE cardid = ? AND linkid IS NOT NULL");
	$stmt->bind_param("i", $cardid);
	
	$stmt->execute();
	$result = $stmt->get_result();
	$row = $result->fetch_assoc();
	if(!$row)
		return null;
	
	return $row['linkid'];
}

function FindCardLinkName()
{
	global $DelverDBLink, $userid, $cardLinkID;

	$stmt = $DelverDBLink->prepare("SELECT * FROM oracle WHERE cardid = ?");
	$stmt->bind_param("i", $cardLinkID);

	$stmt->execute();
	$result = $stmt->get_result();
	$row = $result->fetch_assoc();
	if(!$row)
		return null;

	return $row['name'];
}

function ReturnXML($errno, $msg, $cardid=null, $setcode=null, $newcount=0)
{
	$xmlstr = "<response>$msg</response>";
	$response = new SimpleXMLElement($xmlstr);
	$response->addAttribute('errno', $errno);
	$response->addAttribute('cardid', $cardid);
	$response->addAttribute('setcode', $setcode);
	$response->addAttribute('newcount', $newcount);
	echo $response->asXML();
	//exit;
}

function AddCardChangeLog( $_userID, $_cardID, $_setcode, $_changeAmount )
{
	global $DelverDBLink;
	//"INSERT INTO usercards (ownerid, cardid, setcode, count) VALUES (?, ?, ?, ?)"
	$stmt = $DelverDBLink->prepare( "INSERT INTO usercardlog (userid, cardid, setcode, datemodified, difference) VALUES (?, ?, ?, ?, ?)" );
	
	if ( $stmt == null )
	{
		echo "Error preparing statement: ".$DelverDBLink->error;
		exit;
	}
	
	date_default_timezone_set('Australia/Canberra');
	$date = date('y-m-d H:i:s', time());
	
	$stmt->bind_param( "iissi", $_userID, $_cardID, $_setcode, $date, $_changeAmount );
	return $stmt->execute();
}

?>