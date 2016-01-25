<?php

header('Content-Type: text/xml;  charset=utf-8');

include_once "passwords.php";
include_once "output.php";
include_once "defines.php";
include_once "users.php";

if ( __DEBUG__ )
{
    $SearchLog->log( "Starting new AJAX card search" );
    $SearchLog->log( "URI: ".$_SERVER['REQUEST_URI'] );
}

$SQLUser = $SQLUsers['oracle_search'];
$DelverDBLink = new mysqli("localhost", $SQLUser->username, $SQLUser->password, "delverdb");
if($DelverDBLink->connect_errno)
{
    $DBLog->err( "Database connection failure ($DelverDBLink->connect_errno): $DelverDBLink->connect_error" );
    ReturnXMLError( 0, "Internal error" );
}

// Find and store user card data
if ( $IsLoggedIn )
{
    $userCardStmt = $DelverDBLink->prepare( "SELECT cardid, setcode, count FROM usercards WHERE ownerid = ?" ) or die( $DelverDBLink->error );
    
    $userCardStmt->bind_param( 'i', $_SESSION['userid'] ) or die( $DelverDBLink->error );
    $userCardStmt->execute() or die( $DelverDBLink->error );
    
    $userCardResults = $userCardStmt->get_result();
    
    $MyCardsArray = array();
    
    while ( $row = $userCardResults->fetch_assoc() )
    {
        $cardid = $row['cardid'];
        $setcode = $row['setcode'];
        $count = $row['count'];
        if ( !array_key_exists( $cardid, $MyCardsArray ) )
        {
            $MyCardsArray[$cardid] = array();
        }
        $MyCardsArray[$cardid][$setcode] = $count;
    }
}

$MyCardsOnly = array_key_exists('MyCards', $_GET) && ($_GET['MyCards'] == 1);

$QueryString = "SELECT cards.name, cards.id, cardsets.setcode, cardsets.rarity, cardsets.multiverseid
         FROM cards INNER JOIN cardsets ON cards.id = cardsets.cardid WHERE ";

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

$UniqueCardsFound = 0;
$UniqueCardLimit = 500;

while ( $cardArray = $SearchResults->fetch_assoc() )
{
    $cardid = $cardArray['id'];
    
    // If searching for the users cards, remove cards that the user doesn't own
    if ( $IsLoggedIn && $MyCardsOnly && !array_key_exists($cardid, $MyCardsArray) )
    {
        continue;
    }
    
    $cardname = $cardArray['name'];
    $setcode = $cardArray['setcode'];
    $rarity = $cardArray['rarity'];
    $multiverseid = $cardArray['multiverseid'];
    
    // Add the card if it isn't already added
    if ( !array_key_exists( $cardid, $CardsFound ) )
    {
        $card = array();
        $card['sets'] = array();
        $card['name'] = $cardname;
        $CardsFound[$cardid] = $card;
        ++$UniqueCardsFound;
    }
    
    // Add the new set to the card
    $CardsFound[$cardid]['sets'][$setcode] = array( 'rarity' => $rarity, 'multiverseid' => $multiverseid );
    
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
    
    $cardXML = $responseXML->addChild('card');
    $cardXML->addAttribute( 'name', utf8_encode($cardname) );
    $cardXML->addAttribute( 'id', $cardid);
    $cardXML->addAttribute( 'totalcount', $totalCount );
    
    $sets = $card['sets'];

    foreach ( $sets as $setcode => $set )
    {
        $rarity = $set['rarity'];;
        $multiverseid = $set['multiverseid'];
        $count = 0;
        
        if($IsLoggedIn && array_key_exists($cardid, $MyCardsArray)
         && array_key_exists($setcode, $MyCardsArray[$cardid]))
        {
            $count = $MyCardsArray[$cardid][$setcode];
        }
        
        $setXML = $cardXML->addChild('set');
        $setXML->addAttribute('setcode', $setcode);
        $setXML->addAttribute('rarity', $rarity);
        $setXML->addAttribute('count', $count);
        $setXML->addAttribute('multiverseid', $multiverseid);
    }
}

function setSortAsc( $a, $b )
{
    if ( $a == $b )
    {
        return 0;    
    }
    
    $sets = ddb\Defines::GetSetList();
    
    $dateA = $sets[$a]->release_date;
    $dateB = $sets[$b]->release_date;

    return $dateA < $dateB ? -1 : 1; 
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

    $first = true;
    
    if ( array_key_exists( 'Name', $_GET ) && $_GET['Name'] == 1)
    {
        $QueryString .= " (cards.name REGEXP ?)";
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
        $QueryString .= " (cards.subtype REGEXP ? OR cards.type REGEXP ?)";
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
        $QueryString .= " (cards.rules REGEXP ?) ";
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
            $TypeQuery .= " cards.type LIKE '%$type%' ";
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
    
    foreach ( ddb\Defines::$colourList as $symbol => $colour )
    {
        if ( array_key_exists( $colour->name, $_GET ) && $_GET[$colour->name] != '0')
        {
            $AnyColourSelected = true;
            if ( $first )
            {
                $first = false;
            }
            else
            {
                $ColourQuery .= " OR ";
            }
            $ColourFlag = $colour->flag;
            $ColourQuery .= " cards.colour & '$ColourFlag' ";
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
        $ColourQuery .= " OR cards.colour = '0'";
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
    foreach ( ddb\Defines::$rarityList as $symbol => $name )
    {
        if ( $name != "Land" && array_key_exists( $name, $_GET ) && $_GET[$name] == '1' )
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
    
    $sets = ddb\Defines::getSetList();
    
    foreach ( $_GET['set'] as $index => $setcode )
    {
        if ( array_key_exists( $setcode, $sets ) )
        {
            if ( !$first )
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