<?php
header('Content-Type: text/xml;  charset=utf-8');

include_once "passwords.php";
include_once "defines.php";
include_once "users.php";
include_once "output.php";

global $DBLog, $CardLog, $UserLog;

$SQLUser = $SQLUsers['oracle_search'];
$DelverDBLink = new mysqli("localhost", $SQLUser->username, $SQLUser->password, "delverdb");

if(!array_key_exists('cardname', $_GET))
{
	ReturnXMLError(1, "No card name sent");
}

$CardName = $_GET['cardname'];

$SearchStmt = $DelverDBLink->prepare("
		SELECT DISTINCT cardsets.setcode FROM cards, cardsets
		WHERE cards.name = ?
		AND cards.id = cardsets.cardid");

$SearchStmt->bind_param("s", $CardName);
$SearchStmt->execute();
$SearchResult = $SearchStmt->get_result();

$xmlstr = "<response></response>";
$response = new SimpleXMLElement($xmlstr);

$sets = ddb\Defines::getSetList();

while($row = $SearchResult->fetch_assoc())
{
	$card = $response->addChild("set");
	$setcode = $row['setcode'];
	$card->addAttribute('code', $setcode);
	$card->addAttribute('name', $sets[$setcode]->name);
}

echo $response->asXML();

exit;


function ReturnXMLError($errno, $msg)
{
	$xmlstr = "<response>$msg</response>";
	$response = new SimpleXMLElement($xmlstr);
	$response->addAttribute('errno', $errno);
	echo $response->asXML();
	exit;
}

?>