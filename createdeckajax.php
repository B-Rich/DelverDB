<?php
header('Content-Type: text/xml;  charset=utf-8');

include_once "passwords.php";
include_once "users.php";
include_once 'defines.php';

$idExists = array_key_exists('id', $_GET);
$countExists = array_key_exists('count', $_GET);
$DeckNameExists = array_key_exists('deckname', $_GET);
$overwriteExists = array_key_exists('overwrite', $_GET);
$deckIDExists = array_key_exists('deckid', $_GET);

if(__DEBUG__) $DeckLog->log("Starting deck creation: ".$_SERVER['REQUEST_URI']);

if(!$IsLoggedIn)
{
	$DeckLog->warning("Aborting deck creation: Not logged in");
	ReturnXML(4, "Not logged in");
}

if(__DEBUG__) $DeckLog->log("User: ".$_SESSION['userid']);

if(!$DeckNameExists)
{
	$DeckLog->warning("Aborting deck creation: Deck name required");
	ReturnXML(1, "Deck name required.");
}

if($overwriteExists && !$deckIDExists)
{
	$DeckLog->warning("Aborting deck creation: DeckID must be supplied in overwrite mode");
	ReturnXML(3, "DeckID must be supplied in overwrite mode");
}

if(count($_GET['id']) != count($_GET['count']))
{
	$DeckLog->warning("Aborting deck creation: Mismatched number of card counts to IDs");
	ReturnXML(2, "Mismatched number of card counts to IDs");
}

$SQLUser = $SQLUsers['deckmaker'];
$DelverDBLink = new mysqli("localhost", $SQLUser->username, $SQLUser->password, "delverdb");

if($DelverDBLink->connect_errno)
{
	$DBLog->err("Aborting deck creation: Database connection error ($DelverDBLink->connect_errno): $DelverDBLink->connect_error");
	ReturnXML(3, "Internal error");
}

$UserID = $_SESSION['userid'];
$DeckID = 0;
$DeckName = $_GET['deckname'];

date_default_timezone_set('Australia/Canberra');
$Date = date('y-m-d', time());

if($overwriteExists && $_GET['overwrite'] == 1 && $deckIDExists)
{
	$DeckOwnerStmt = $DelverDBLink->prepare("SELECT * FROM decklists WHERE deckid = ? AND ownerid = ?");
	if(!$DeckOwnerStmt)
	{
		$DBLog->err("Error preparing statement: ".$DelverDBLink->error);
		ReturnXML(5, "Internal error");
	}
	$DeckID = $_GET['deckid'];
	
	$DeckOwnerStmt->bind_param("ii", $DeckID, $UserID);
	
	$DeckOwnerStmt->execute();
	$DeckOwnerResults = $DeckOwnerStmt->get_result();
	if(!$DeckOwnerResults->fetch_assoc())
	{
		$DeckLog->warn("Overwrite failure: User $UserID does not own deck $DeckID");
		ReturnXML(6, "You do not own that deck, and cannot overwrite it");
	}
	
	$DeckUpdateStmt = $DelverDBLink->prepare("UPDATE decklists SET datemodified = ?, deckname = ? WHERE deckid = ? AND ownerid = ?");
	
	if(($DeckUpdateStmt->bind_param("ssii", $Date, $DeckName, $DeckID, $UserID)) == false)
	{
		$DBLog->err("Error binding parameters: ".$Date);
		ReturnXML(6, "Internal error");
	}
	 
	$DeckUpdateStmt->execute();
	
	$DeckDeletionStmt = $DelverDBLink->prepare("DELETE FROM deckcards WHERE deckid = ?");
		
	if(($DeckDeletionStmt->bind_param("i", $DeckID)) == false)
	{
		$DBLog->err("Error binding DeckID: $DeckID $DelverDBLink->error");
		ReturnXML(8, "Internal error");
	}
	$DeckDeletionStmt->execute();
}
else
{
	$DeckStmt = $DelverDBLink->prepare("INSERT INTO decklists (ownerid, deckname, datecreated, datemodified) VALUES (?, ?, ?, ?)");
	
	$DeckStmt->bind_param("isss", $UserID, $DeckName, $Date, $Date);
	
	$DeckStmt->execute() or ReturnXML(6, $DelverDBLink->error);
	
	// Set the deck id to the latest auto increment
	$DeckID = $DelverDBLink->insert_id;
	
	if($DeckID == 0)
	{
		$DeckLog->err("Error inserting deck");
		ReturnXML(6, "Internal error");
	}
}

$ContentsQuery = "INSERT INTO deckcards (deckid, cardid, count) VALUES ";
$ContentsStack = array();
$ContentsFormat = array();
foreach($_GET['id'] as $index => $cardID)
{
	if(!array_key_exists($index, $_GET['count']))
	{
		$DeckLog->warn("Mismatched number of card counts to IDs");
		ReturnXML(2, "Mismatched number of card counts to IDs");
	}
	
	$cardCount = $_GET['count'][$index];
	
	$ContentsQuery .= '(?, ?, ?)';
	array_push($ContentsStack, $DeckID, $cardID, $cardCount);
	array_push($ContentsFormat, 'i', 'i', 'i');
	if($index != count($_GET['id']) - 1)
	{
		$ContentsQuery .= ', ';	
	}
	if(__DEBUG__) $DeckLog->log("Adding $cardCount x $cardID");
}

$ContentsStmt = $DelverDBLink->prepare($ContentsQuery);
if(!$ContentsStmt)
{
	$DBLog->err("Error preparing statement: $DelverDBLink->error for $ContentsQuery");
	ReturnXML(8, "Internal error");
}

$StackCopy = array();
for($i = 0; $i < count($ContentsStack); ++$i)
{
	$StackCopy[] = &$ContentsStack[$i];
}
array_unshift($StackCopy, implode($ContentsFormat));
call_user_func_array(array($ContentsStmt, 'bind_param'), $StackCopy);

$ContentsStmt->execute();
if(__DEBUG__) $DeckLog->log("$DeckName created for $UserID");
ReturnXML(0, "Deck save successful");


////////////////////////////////////////////////////////////////////////////////
/// FUNCTIONS

function ReturnXML($errno, $msg)
{
	$xmlstr = "<response>$msg</response>";
	$response = new SimpleXMLElement($xmlstr);
	$response->addAttribute('errno', $errno);
	echo $response->asXML();
	exit;
}

?>