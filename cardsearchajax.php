<?php

header('Content-Type: text/xml;  charset=utf-8');

include_once "passwords.php";
include_once "output.php";
include_once "defines.php";
include_once "users.php";

require_once "C:\pear\pear\propel\Propel.php";
Propel::init("\propel\build\conf\magic_db-conf.php");
set_include_path("propel/build/classes/" . PATH_SEPARATOR . get_include_path());

if ( __DEBUG__ )
{
	$SearchLog->log( "Starting new AJAX card search" );
	$SearchLog->log( "URI: ".$_SERVER['REQUEST_URI'] );
}

$SQLUser = $SQLUsers['oracle_search'];
$DelverDBLink = new mysqli("localhost", $SQLUser->username, $SQLUser->password, "magic_db");
if($DelverDBLink->connect_errno)
{
	$DBLog->err( "Database connection failure ($DelverDBLink->connect_errno): $DelverDBLink->connect_error" );
	ReturnXMLError( 0, "Internal error" );
}

// Find and store user card data
if ( $IsLoggedIn )
{
	$UserCardQuery = new UserCardQuery();
	$UserCardResults = $UserCardQuery
		->filterByOwnerid( $_SESSION['userid'] )
		->find();
	
	$MyCardsArray = array();
	
	foreach ( $UserCardResults as $UserCard )
	{
		$cardid = $UserCard->GetCardid();
		$setcode = $UserCard->GetSetcode();
		$count = $UserCard->GetCount();
		if ( !array_key_exists($cardid, $MyCardsArray) )
		{
			$MyCardsArray[$cardid] = array();
		}
		$MyCardsArray[$cardid][$setcode] = $count;
	}
}

$MyCardsOnly = array_key_exists('MyCards', $_GET) && ($_GET['MyCards'] == 1);

$QueryString = "SELECT oracle.name, oracle.cardid, cardsets.setcode, cardsets.rarity
		 FROM oracle INNER JOIN cardsets ON oracle.cardid = cardsets.cardid WHERE ";

$QueryStack = array();
$QueryFormat = array();
	
AppendTextQuery();
$QueryString .= " AND ";
AppendTypeQuery();
$QueryString .= " AND ";
AppendColourQuery();
$QueryString .= " AND ";
AppendRarityQuery();
$QueryString .= " AND ";
AppendSetQuery();

$QueryString .= " ORDER BY name ASC";
$SearchLog->log( $QueryString );
$SearchStmt = $DelverDBLink->prepare( $QueryString );
if ( !$SearchStmt )
{
	$DBLog->err( "Error preparing SeachStmt for CardSearchAJAX: $DelverDBLink->error for $SearchStmt" );
	ReturnXMLError( 4, "Internal error" );
}

// Bind the parameters to the statement
$params = array();
for ( $i = 0; $i < count($QueryStack); ++$i )
{
	$params[] = &$QueryStack[$i];
}
array_unshift( $params, implode( $QueryFormat ) );
if ( count($QueryStack) > 0 )
{
	call_user_func_array( array( $SearchStmt, 'bind_param' ), $params );
}

$SearchLog->log( print_r( $QueryStack, true ) );

$SearchStmt->execute();
$SearchResults = $SearchStmt->get_result();

$CardsFound = array();
$SearchResults->data_seek(0);

$UniqueCardsFound = 0;
$UniqueCardLimit = 500;

while ( $cardArray = $SearchResults->fetch_assoc() )
{
	$cardid = $cardArray['cardid'];
	
	//print $cardid . " " . $cardArray['name'] . "\n";
	
	// If searching for the users cards, remove cards that the user doesn't own
	if ( $IsLoggedIn && $MyCardsOnly && !array_key_exists($cardid, $MyCardsArray) )
	{
		//print "Ignoring\n";
		continue;
	}
	
	$cardname = $cardArray['name'];
	$setcode = $cardArray['setcode'];
	$rarity = $cardArray['rarity'];
	
	// Add the card if it isn't already added
	if ( !array_key_exists($cardid, $CardsFound) )
	{
		$card = array();
		$card['sets'] = array();
		$card['name'] = $cardname;
		$CardsFound[$cardid] = $card;
		++$UniqueCardsFound;
	}
	
	// Add the new set to the card
	$CardsFound[$cardid]['sets'][$setcode] = $rarity;
	
	if ( $UniqueCardsFound >= $UniqueCardLimit )
	{
		break;
	}
}

// Sort the results by name
$SortedCards = array();
foreach($CardsFound as $cardid => $card)
{
	$SortedCards[$card['name']] = $cardid;
}

// XML Output
$responseXML = new SimpleXMLElement("<response></response>");
$responseXML->addAttribute('cardsfound', $UniqueCardsFound);

foreach($SortedCards as $cardname => $cardid)
{
	$card = $CardsFound[$cardid];
	$totalCount = 0;
	if ( $IsLoggedIn && array_key_exists($cardid, $MyCardsArray) )
	{
		foreach ( $MyCardsArray[$cardid] as $setcode => $count )
		{
			$totalCount += $count;
		}
	}
	
	$cardname = $card['name'];
	$cardname = str_replace( '"', '', $cardname );
	$cardname = htmlspecialchars( $cardname, ENT_COMPAT );
	
	$cardXML = $responseXML->addChild('card');
	$cardXML->addAttribute( 'name', utf8_encode($cardname) );
	$cardXML->addAttribute( 'id', $cardid);
	$cardXML->addAttribute( 'totalcount', $totalCount );
	
	$sets = $CardsFound[$cardid]['sets'];
	
	// Sort the sets for each card
	$setIndices = array();
	foreach($sets as $setcode => $rarity)
	{
		$setIndices[] = Defines::$SetcodeToIndices[$setcode];
	}
	sort($setIndices);
	array_reverse($setIndices);
	
	for ( $i = count($setIndices) - 1; $i >= 0; --$i )
	{
		$count = 0;
		$setIndex = $setIndices[$i];
		$setcode = Defines::$SetcodeOrder[$setIndex];
		$rarity = $card['sets'][$setcode];
		
		if($IsLoggedIn && array_key_exists($cardid, $MyCardsArray)
		 && array_key_exists($setcode, $MyCardsArray[$cardid]))
		{
			$count = $MyCardsArray[$cardid][$setcode];
		}
		
		$setXML = $cardXML->addChild('set');
		$setXML->addAttribute('setcode', $setcode);
		$setXML->addAttribute('rarity', $rarity);
		$setXML->addAttribute('count', $count);
	}
}

echo $responseXML->asXML();

$SearchLog->log("Search complete");

exit;


///////////////////////////////////////////////////////////////////////////////
// FUNCTIONS

function AppendTextQuery()
{
	global $QueryString, $QueryStack, $QueryFormat;
	
	$QueryString .= " ( ";
	if ( !array_key_exists('text', $_GET) )
	{
		$QueryString .= " TRUE )";
		return;
	}
	
	if ( !array_key_exists( 'Rules', $_GET )
      && !array_key_exists( 'Name', $_GET )
      && !array_key_exists( 'Types', $_GET ) )
	{
		// The user has specified a strig to search for, but nowhere to search, return nothing
		$QueryString .= " FALSE ";
		return;	
	}
	
	$text = $_GET['text'];
	//$text = '%'.$text.'%';

	$first = true;
	
	if ( array_key_exists( 'Name', $_GET ) && $_GET['Name'] == 1)
	{
		$QueryString .= " (oracle.name REGEXP ?)";
		$first = false;
		array_push( $QueryStack, $text );
		array_push( $QueryFormat,'s' );
	}
	
	if ( array_key_exists( 'Types', $_GET ) && $_GET['Types'] == 1)
	{
		if ( $first == false )
		{
			$QueryString .= " OR ";
		}
		$QueryString .= " (oracle.subtype REGEXP ? OR oracle.type REGEXP ?)";
		array_push( $QueryStack, $text, $text );
		array_push( $QueryFormat,'s', 's' );
		$first = false;
	} 
	
	if ( array_key_exists( 'Rules', $_GET ) && $_GET['Rules'] == 1 )
	{
		if ( $first == false )
		{
			$QueryString .= " OR ";
		}
		$QueryString .= " (oracle.rules REGEXP ?)";
		array_push( $QueryStack, $text );
		array_push( $QueryFormat,'s' );
	}
	$QueryString .= " ) ";
}

function AppendTypeQuery()
{
	global $QueryString;
	$Types = array
	(
		'Creature',
		'Planeswalker',
		'Artifact',
		'Enchantment',
		'Instant',
		'Sorcery',
		'Land',
	);
	$TypeQuery = " (";
	$AllTypesSelected = true;
	$AnyTypesSelected = false;
	$first = true;
	foreach($Types as $type)
	{
		if(array_key_exists($type, $_GET) && $_GET[$type] != '0')
		{
			$AnyTypesSelected = true;
			if($first)
			{
				$first = false;
			}
			else
			{
				$TypeQuery .= " OR ";
			}
			$TypeQuery .= " oracle.type LIKE '%$type%' ";
		}
		else
		{
			$AllTypesSelected = false;
		}
	}
	$TypeQuery .= ") ";
	
	if($AllTypesSelected)
	{
		$QueryString .= " TRUE ";
		return;
	}
	if(!$AnyTypesSelected)
	{
		$QueryString .= " FALSE ";
		return;
	}
	$QueryString .= $TypeQuery;
}

function AppendColourQuery()
{
	global $QueryString;
	$ColourQuery = " ( ( ";
	$AllColoursSelected = true;
	$AnyColourSelected = false;
	$first = true;
	
	foreach(Defines::$ColourSymbolsToNames as $symbol => $colour)
	{
		if(array_key_exists($colour, $_GET) && $_GET[$colour] != '0')
		{
			$AnyColourSelected = true;
			if($first)
			{
				$first = false;
			}
			else
			{
				$ColourQuery .= " OR ";
			}
			$ColourFlag = Defines::$ColourSymbolsToInt[$symbol];
			$ColourQuery .= " oracle.colour & '$ColourFlag' ";
		}
		else
		{
			$AllColoursSelected = false;
		}
	}
	
	if(!$AnyColourSelected)
	{
		$ColourQuery .= " FALSE ";
	}
	
	$ColourQuery .= " ) ";
	
	if(array_key_exists('Colourless', $_GET))
	{
		$AnyColourSelected = true;
		$ColourQuery .= " OR oracle.colour = '0'";
	}
	else
	{
		$AllColoursSelected = false;
	}
	$ColourQuery .= " ) ";
	
	if($AllColoursSelected)
	{
		$QueryString .= "TRUE";
		return;
	}
			
	if(!$AnyColourSelected)
	{
		$QueryString.= " FALSE ";
		return;
	}
	$QueryString .= $ColourQuery;
}

function AppendRarityQuery()
{
	global $QueryString;
	$RarityQuery = " ( ";
	$AllRaritysSelected = true;
	$AnyRaritySelected = false;
	$first = true;
	foreach ( Defines::$RarityNameToSymbol as $rarity => $symbol )
	{
		if ( $rarity != "Land" && array_key_exists( $rarity, $_GET ) && $_GET[$rarity] == '1' )
		{
			$AnyRaritySelected = true;
			if ( $first )
			{
				$first = false;
			}
			else
			{
				$RarityQuery .= " OR ";
			}
			$RarityQuery .= " cardsets.rarity = '$symbol' ";
			if ( $symbol == 'C' )
			{
				$RarityQuery .= " OR cardsets.rarity = 'L' ";
			}
		}
		else
		{
			$AllRaritysSelected = false;
		}
	}

	$RarityQuery .= " OR cardsets.rarity = 'S' ) ";
	if($AllRaritysSelected)
	{
		$QueryString .= " TRUE ";
		return;
	}
	if(!$AnyRaritySelected)
	{
		$QueryString .= " FALSE ";
		return;
	}
	$QueryString .= $RarityQuery;
}

function AppendSetQuery()
{
	global $QueryString;
	if(!array_key_exists('set', $_GET))
	{
		$QueryString .= " TRUE ";
		return;
	}
	$first = true;
	$SetQuery = " ( ";
	foreach($_GET['set'] as $index => $setcode)
	{
		if(array_key_exists($setcode, Defines::$SetCodeToNameMap))
		{
			if(!$first)
			{
				$SetQuery .= " OR ";
			}
			else 
			{
				$first = false;
			}
			
			$SetQuery.= " cardsets.setcode = '$setcode' ";
		}
	}
	$SetQuery .= " ) ";
	$QueryString .= $SetQuery;
}


function ReturnXMLError($_errno, $_errmsg)
{
	$xmlstr = "<response>$_errmsg</response>";
	$response = new SimpleXMLElement($xmlstr);
	$response->addAttribute('errno', $_errno);
	echo $response->asXML();
	exit;
}

?>