<?php

include_once "passwords.php";
include_once "users.php";
include_once "output.php";
include_once "defines.php";
include_once "ddbcommon.php";
include_once "cardobj.php";

global $IsLoggedIn, $LoginErrorMessage;

$UserID = $IsLoggedIn == true ? $_SESSION['userid'] : null;

///////////////////////////////////////////////////////////////////////////////
/// LINK

$SQLUser = $SQLUsers['deckmaker'];
$DelverDBLink = new mysqli( "localhost", $SQLUser->username, $SQLUser->password, "magic_db" );
if ( $DelverDBLink->connect_errno )
{
	$errno = $DelverDBLink->connect_errno;
	$error = $DelverDBLink->connect_error;
	$DBLog->err( "Connection error (".$errno.") ".$error );
	die( "Connection error ($errno)" );
}

///////////////////////////////////////////////////////////////////////////////
/// DATA

$CardID = null;
if ( array_key_exists( 'id', $_GET ) == false )
{
	die( "No card ID supplied." );
}
$CardID = $_GET["id"];

///////////////////////////////////////
/// DECK DATA

class Deck
{
	public $ID;
	public $ownerID;
	public $name;
	public $dateCreated;
	public $dateModified;
};

$UserDeckArray = array();

$DeckDetailsStmt = $DelverDBLink->prepare( "SELECT deckid, deckname, datecreated, datemodified FROM decklists WHERE ownerid = ? ORDER BY datemodified DESC" )
	or die( $DelverDBLink->error );
$DeckDetailsStmt->bind_param( 'i', $UserID );
$DeckDetailsStmt->execute();
$DeckDetailsResult = $DeckDetailsStmt->get_result();

while ( $deckDetailRow = $DeckDetailsResult->fetch_assoc() )
{
	$deck = new Deck();
	$deck->ID = $deckDetailRow['deckid'];
	$deck->name = $deckDetailRow['deckname'];
	$deck->dateCreated = $deckDetailRow['datecreated'];
	$deck->dateModified = $deckDetailRow['datemodified'];
	$UserDeckArray[$deck->ID] = $deck;
}


$DeckCardArray = array();

$DeckCardStmt = $DelverDBLink->prepare( "SELECT deckid, count FROM deckcards WHERE cardid = ?")
	or die( $DelverDBLink->error );
$DeckCardStmt->bind_param( 'i', $CardID );
$DeckCardStmt->execute();

$DeckCardResults= $DeckCardStmt->get_result();

while ( $deckCardRow = $DeckCardResults->fetch_assoc() )
{
	$deckID = $deckCardRow['deckid'];
	$count = $deckCardRow['count'];
	
	if ( array_key_exists( $deckID, $UserDeckArray ) == true )
	{
		$DeckCardArray[$deckID] = $count;
	}
}




////////////////////////////////////////////////////////////////////////////////
/// HEADER

require_once 'C:/pear/pear/twig/autoloader.php';
Twig_Autoloader::register();
$loader = new Twig_Loader_Filesystem('templates');
$twig = new Twig_Environment($loader);

$template = $twig->loadTemplate('header.twig');
$args = array();
$args["title"] = "Results - DDB";
$args["heading"] = "Advanced Search Results";
$args['isloggedin'] = $IsLoggedIn;

if($LoginErrorMessage != null)
	$args['loginerrormessage'] = $LoginErrorMessage;
$args['loginurl'] = $_SERVER['PHP_SELF'];
if($IsLoggedIn)
{
	$args['username'] = $_SESSION['username'];
}
echo $template->render($args);

///////////////////////////////////////////////////////////////////////////////
/// DECKS

echo '<table>';
foreach ( $DeckCardArray as $deckID => $count )
{
	echo '<tr>';
	$deck = $UserDeckArray[$deckID];
	echo "<td>$deck->ID</td><td>$deck->name</td><td>$count</td>";
	echo '</tr>';
}
echo '</table>';

////////////////////////////////////////////////////////////////////////////////
/// FOOTER
$template = $twig->loadTemplate('footer.twig');
echo $template->render(array());

exit;

?>