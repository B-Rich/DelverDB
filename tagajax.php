<?php

header( "Content-Type: text/xml; charset=utf-8" );

include_once "passwords.php";

global $DBLog;

$SQLUser = $SQLUsers['ddb_tagmaker'];
$DelverDBLink = new mysqli( "localhost", $SQLUser->username, $SQLUser->password, "delverdb" );

if ( $DelverDBLink->connect_errno )
{
	$DBLog->err( "Error connecting to database (" . $DelverDBLink->connection_errorno . ") ". $DelverDBLink->connect_error );
	ReturnXML( -1, "Could not connect to database" );
}


if ( array_key_exists( "mode", $_GET ) == false )
{
	ReturnXML( 0, "No mode found" );	
}

if ( $_GET["mode"] == "create" )
{
	if ( array_key_exists( "tagname", $_GET ) == false )
	{
		ReturnXML( -2, "No tagname found" );
	}
	$TagName = $_GET['tagname'];
	
	$TagnameCheckStmt = $DelverDBLink->prepare( "SELECT * FROM tags WHERE name = ?" ) or die( $DelverDBLink->error );
	$TagnameCheckStmt->bind_param( "s", $TagName );
	$TagnameCheckStmt->execute();
	$TagNameCheckResults = $TagnameCheckStmt->get_result();
	
	// Tag already exists, abort
	if ( $TagNameCheckResults->fetch_assoc() )
	{
		ReturnXML( -3, "Tag \"$TagName\" already exists" );	
	}
	
	$CreateTagStmt = $DelverDBLink->prepare( "INSERT INTO tags (name) VALUES (?)" ) or die( $DelverDBLink->error );
	$CreateTagStmt->bind_param( "s", $TagName );
	$CreateTagStmt->execute() or die( $DelverDBLink->error );
	
	ReturnXML( 1, "Tag \"$TagName\" added" );
}
else if ( $_GET["mode"] == "add" || $_GET["mode"] == "remove" )
{
	if ( array_key_exists( "cardid", $_GET ) == false )
	{
		ReturnXML( -4, "No card ID found" );	
	}
	if ( array_key_exists( "tagid", $_GET ) == false )
	{
		ReturnXML( -5, "No tag ID found" );	
	}
	
	$TagID = $_GET['tagid'];
	$CardID = $_GET['cardid'];
	
	$TagNameStmt = $DelverDBLink->prepare( "SELECT * FROM tags WHERE id = ?" );
	$TagNameStmt->bind_param( "i", $TagID );
	$TagNameStmt->execute();
	$TagNameResult = $TagNameStmt->get_result();
	
	$TagName = null;
	if ( $row = $TagNameResult->fetch_assoc() )
	{
		$TagName = $row['name'];
	}
	
	$TagCardCheckStmt = $DelverDBLink->prepare( "SELECT * FROM taglinks WHERE tagid = ? AND cardid = ?" ) or die ( $DelverDBLink->error );
	$TagCardCheckStmt->bind_param( "ii", $TagID, $CardID );
	$TagCardCheckStmt->execute();
	$TagCardCheckResults = $TagCardCheckStmt->get_result();
	if ( $TagCardCheckResults->fetch_assoc() )
	{
		if ( $_GET["mode"] == "add" )
		{
			ReturnXML( -6, "Card already has that tag", $TagName );	
		}
		else
		{
			$RemoveTagStmt = $DelverDBLink->prepare( "DELETE FROM taglinks WHERE tagid = ? AND cardid = ?" ) or die( $DelverDBLink->error );
			$RemoveTagStmt->bind_param( "ii", $TagID, $CardID );
			$RemoveTagStmt->execute();
			
			ReturnXML( 3, "Tag removed", $TagName );
		}
	}
	else 
	{
		$TagCardStmt = $DelverDBLink->prepare( "INSERT INTO taglinks (tagid, cardid) VALUES( ?, ? )" ) or die( $DelverDBLink->error() );
		$TagCardStmt->bind_param( "ii", $TagID, $CardID );
		$TagCardStmt->execute();
		
		ReturnXML( 2, "Tag added", $TagName);
	}
}
else
{
	ReturnXML( 0, "Unknown mode" );	
}

function ReturnXML( $errno, $msg, $tagName = null )
{
	$xmlstr = "<response>$msg</response>";
	$response = new SimpleXMLElement($xmlstr);
	$response->addAttribute( 'errno', $errno);
	if ( $tagName != null )
	{
		$response->addAttribute( "tagname", $tagName );
	}
	echo $response->asXML();
	exit;
}

?>