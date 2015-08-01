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

$SQLUser = $SQLUsers['ddb_usercards'];
$DelverDBLink = new mysqli( "localhost", $SQLUser->username, $SQLUser->password, "magic_db" );
if ( $DelverDBLink->connect_errno )
{
	$errno = $DelverDBLink->connect_errno;
	$error = $DelverDBLink->connect_error;
	$DBLog->err( "Connection error (".$errno.") ".$error );
	die( "Connection error ($errno)" );
}

$stmt = $DelverDBLink->prepare( "SET NAMES 'utf8'" ) or die( $DelverDBLink->error );
$stmt->execute();

///////////////////////////////////////////////////////////////////////////////
/// DATA

$CardID = null; $Setcode = null;
if ( array_key_exists( 'id', $_GET ) == false )
{
	die( "No card ID supplied." );
}
$CardID = $_GET["id"];

if ( array_key_exists( 'set', $_GET ) == true )
{
	$Setcode = $_GET['set'];
}

$CardObj = new Card();

/////////////
/// ORACLE DATA

$OracleDataStmt = $DelverDBLink->prepare( "SELECT * FROM oracle WHERE cardid = ?" ) or die();
$OracleDataStmt->bind_param( "i", $CardID ) or die();
$OracleDataStmt->execute();

$OracleResult = $OracleDataStmt->get_result();
$OracleRow = $OracleResult->fetch_assoc();
if ( $OracleRow == null )
{
	die( "No card with ID $CardID exists." );
}

$CardObj->ConstructFromResults( $OracleRow );

////////////////////////////
/// OWNERSHIP DATA

$OwnershipArray = null;
if ( $IsLoggedIn == true )
{
	$OwnershipArray = array();
	$OwnershipStmt = $DelverDBLink->prepare
	(
		"SELECT setcode, count FROM usercards WHERE ownerid = ? AND cardid = ?"
	) or die();
	
	$OwnershipStmt->bind_param( "ii", $UserID, $CardID ) or die();
	$OwnershipStmt->execute();

	$OwnershipResults = $OwnershipStmt->get_result();
	
	while ( $row = $OwnershipResults->fetcH_assoc() )
	{
		$set = $row['setcode'];
		$count = $row['count'];
		$OwnershipArray[$set] = $count;
	}
}


//////////////////////////
/// SET DATA

$CardSetStmt = $DelverDBLink->prepare( "SELECT setcode, rarity, cnum, artist FROM cardsets WHERE cardid = ?");
$CardSetStmt->bind_param( "i", $CardID );
$CardSetStmt->execute();
$CardSetResult = $CardSetStmt->get_result();

$CardObj->total = 0;

$SelectedSetIndex = 0;
$index = 0;
/// Fill out the sets for the card
while ( $setRow = $CardSetResult->fetch_assoc() )
{
	$set = $setRow['setcode'];
	$rarity = $setRow['rarity'];
	$cnum = $setRow['cnum'];
	$artist = $setRow['artist'];
	
	if ( strcasecmp( $Setcode, $set ) == 0 )
	{
		$SelectedSetIndex = $index; 
	}

	$count = 0;
	/// Use the count if the user is logged in, and owns the card
	if ( $OwnershipArray != null
	  && array_key_exists( $set, $OwnershipArray ) )
	{
		$count = $OwnershipArray[$set];
	}
	$CardObj->AddSet( $set, $rarity, $cnum, $artist, $count );
	++$index;
}
$CardObj->imageurl = $CardObj->GetFirstImageURL();

$SelectedSet = $CardObj->sets[$SelectedSetIndex];
$RaritySymbol = $SelectedSet->rarity;
$RarityString = Defines::$RaritySymbolToName[$RaritySymbol];

$ArtistString = $SelectedSet->artist;
$ArtistSearchURL = "advresults.php?artist[]=".urlencode("&".$ArtistString);

////////////////////////////////////////////////////////////////////////////////
/// CHANGE DATA

$CardChanges = array();

// If the user is logged in and owns the card
if ( $IsLoggedIn && $OwnershipArray != null )
{
	$CardChangeStmt = $DelverDBLink->prepare(
			"SELECT setcode, datemodified, difference FROM usercardlog
			WHERE userid = ? AND cardid = ? ORDER BY datemodified DESC") or die( $DelverDBLink->error );
	
	$CardChangeStmt->bind_param( "ii", $UserID, $CardID ) or die();
	$CardChangeStmt->execute() or die();
	$CardChangeResult = $CardChangeStmt->get_result();
	
	$Total = 0;
	while ( $row = $CardChangeResult->fetch_assoc() )
	{
		$change = new Change();
		
		$setcode = $row['setcode'];
		if ( array_key_exists( $setcode, Defines::$SetCodeToNameMap ) == false )
		{
			die( "Unknown set code $setcode" );	
		}
		$setname = Defines::$SetCodeToNameMap[ $setcode ];
		
		$change->date = $row["datemodified"];
		$change->difference = $row["difference"];
		
		if ( array_key_exists( $setcode, $CardChanges ) == false )
		{
			$CardChanges[ $setcode ] = new SetChange();
			$CardChanges[ $setcode ]->setname = $setname;
		}
		
		$CardChanges[ $setcode ]->changes[] = $change;
	}
}

class SetChange
{
	public $total;
	public $setname;
	public $changes = array();
};

class Change
{
	public $difference;
	public $date;	
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

if ( $LoginErrorMessage != null )
{
	$args['loginerrormessage'] = $LoginErrorMessage;
}
$args['loginurl'] = $_SERVER['PHP_SELF'];
if ( $IsLoggedIn )
{
	$args['username'] = $_SESSION['username'];
}
echo $template->render($args);

////////////////////////////////////////////////////////////////////////////////
/// CARD DETAILS

$template = $twig->loadTemplate( 'carddetails.twig' );
	
$args['showownerships'] = $IsLoggedIn;
$args['card'] = $CardObj;
$args['setindex'] = $SelectedSetIndex;
$args['rarity'] = $CardObj->sets[$SelectedSetIndex]->rarity;
$args['raritystring'] = $RarityString;
$args['artisturl'] = $ArtistSearchURL;
$args['changes'] = $CardChanges;
$args['SpacelessName'] = str_replace( ' ', '+', $CardObj->name );

echo $template->render( $args );

////////////////////////////////////////////////////////////////////////////////
/// FOOTER
$template = $twig->loadTemplate( 'footer.twig' );
echo $template->render( array() );

exit;

?>