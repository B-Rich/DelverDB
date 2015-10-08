<?php

header('Content-Type: text/xml;  charset=utf-8');

include_once "passwords.php";
include_once "defines.php";
include_once "users.php";

if(!IsLoggedIn())
{
	ReturnErrorXML(1, "User not logged in.");
}

$MySQLUser = $SQLUsers['deckmaker'];
$DelverDBLink = new mysqli("localhost", $MySQLUser->username, $MySQLUser->password, "delverdb");
if($DelverDBLink->connect_errno)
{
	$DBLog->error("Error connecting to database ($DelverDBLink->connect_errno): $DelverDBLink->connect_error");
	ReturnErrorXML(2, "Internal error");
}

$UserID = $_SESSION['userid'];

$QueryStmt = $DelverDBLink->prepare("SELECT * FROM decklists WHERE ownerid = ?");
$QueryStmt->bind_param("i", $UserID);
$QueryStmt->execute();

$QueryResult = $QueryStmt->get_result();

$responseXML = new SimpleXMLElement("<response></response>");
$responseXML->addAttribute('errno', 0);
while($DeckRow = $QueryResult->fetch_assoc())
{
	$DeckName = $DeckRow['deckname'];
	$DeckID = $DeckRow['deckid'];
	
	$DeckNode = $responseXML->addChild('deck');
	$DeckNode->addAttribute('id', $DeckID);
	$DeckNode->addAttribute('name', $DeckName);
	
}
echo $responseXML->asXML();

exit;
////////////////////////////////////////////////////////////////////////////////
/// FUNCTIONS

function ReturnErrorXML($_errno, $_message)
{
	$xmlstr = "<response>$_message</response>";
	$response = new SimpleXMLElement($xmlstr);
	$response->addAttribute('errno', $_errno);
	echo $response->asXML();
	exit;
}