<?php
header('Content-Type: text/xml;  charset=utf-8');

include_once "passwords.php";
include_once "defines.php";
include_once "users.php";
include_once "output.php";

global $DBLog, $CardLog, $UserLog;

$SQLUser = $SQLUsers['oracle_search'];
$DelverDBLink = new mysqli("localhost", $SQLUser->username, $SQLUser->password, "magic_db");

if(!array_key_exists('cardname', $_GET))
{
	ReturnXMLError(1, "No card name sent");
}

$CardName = $_GET['cardname'];
$CardName = $CardName."%";

$SearchStmt = $DelverDBLink->prepare("SELECT oracle.name FROM oracle WHERE oracle.name LIKE ? ORDER BY oracle.name ASC");
$SearchStmt->bind_param("s", $CardName);
$SearchStmt->execute();
$SearchResult = $SearchStmt->get_result();

$xmlstr = "<response></response>";
$response = new SimpleXMLElement($xmlstr);
$rowcount = 0;
$resultCap = 5;

while($row = $SearchResult->fetch_assoc())
{
	$card = $response->addChild("card");
	$card->addAttribute('name', utf8_encode($row['name']));
	
	$rowcount ++;
	if($rowcount >= $resultCap)
		break;
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