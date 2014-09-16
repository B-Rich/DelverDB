<?php
include_once "passwords.php";
include_once "users.php";

global $IsLoggedIn, $LoginErrorMessage;

$ChangesPerPage = 15;

if(!$IsLoggedIn)
{
	header("Location: index.php");
}

require_once 'C:/pear/pear/twig/autoloader.php';
Twig_Autoloader::register();
$loader = new Twig_Loader_Filesystem( 'templates' );
$twig = new Twig_Environment( $loader );

////////////////////////////////////////////////////////////////////////////////
/// HEADER

$template = $twig->loadTemplate('header.twig');
$args = array();
$args["title"] = "Change Log - DDB";
$args["heading"] = "Change Log";
$args['isloggedin'] = $IsLoggedIn;

if ( $LoginErrorMessage != null )
{
	$args['loginerrormessage'] = $LoginErrorMessage;
}
$args['loginurl'] = $_SERVER['PHP_SELF'];

if ( $IsLoggedIn == true )
{
	$args['username'] = $_SESSION['username'];
}
echo $template->render( $args );

////////////////////////////////////////////////////////////////////////////////
/// CONNECTIOn

$SQLUser = $SQLUsers['ddb_usercards'];
$DelverDBLink = new mysqli( "localhost", $SQLUser->username, $SQLUser->password, "magic_db" );
if ( $DelverDBLink->connect_errno )
{
	$DBLog->err("Connection error (".$DelverDBLink->connect_errno.") ".$DelverDBLink->connect_error);
	die("Connection error");
}

$UserID = $_SESSION['userid'];

////////////////////////////////////////////////////////////////////////////////////
/// CARD CHANGES


$CardLogStmt = $DelverDBLink->prepare("SELECT cardid, setcode, datemodified, difference FROM usercardlog
		 WHERE userid = $UserID ORDER BY datemodified DESC");

$CardLogStmt->execute();
$CardLogResults = $CardLogStmt->get_result();

$CardOracleStmt = $DelverDBLink->prepare( "SELECT name FROM oracle WHERE cardid = ?" );


$PageNumber = 0;
if ( array_key_exists( 'page', $_REQUEST )
  && is_numeric( $_REQUEST['page'] )
  && $_REQUEST['page'] >= 0 )
{
	$PageNumber = $_REQUEST['page'];
}
$TotalChangeCount = $CardLogResults->num_rows;

if ( $PageNumber * $ChangesPerPage > $TotalChangeCount )
{
	$PageNumber = floor( $TotalChangeCount / $ChangesPerPage );
}
$ChangeIndex = $PageNumber * $ChangesPerPage;

$CardLogResults->data_seek( $ChangeIndex ) or die();

$args = array();

$args['pageNumber'] = $PageNumber;
$args['pageLimit'] = ceil( $TotalChangeCount / $ChangesPerPage );
$args['pageOffset'] = 4;
$args['pageURL'] = "cardchanges.php?";
 
for ( $i = 0; $i < $ChangesPerPage; ++$i )
{
	$cardLogRow = $CardLogResults->fetch_assoc();
	
	if ( $cardLogRow == null )
		break;
	
	$cardChange = new CardChange();
	
	$cardChange->index = $ChangeIndex;
	$cardChange->cardID = $cardLogRow['cardid'];
	$cardChange->setcode = $cardLogRow['setcode'];
	$cardChange->dateModified = $cardLogRow['datemodified'];
	$cardChange->difference = $cardLogRow['difference'];
	
	$CardOracleStmt->bind_param( "i", $cardChange->cardID );
	$CardOracleStmt->execute();
	$oracleResults = $CardOracleStmt->get_result();
	$oracleRow = $oracleResults->fetch_assoc();
	
	$cardChange->name = $oracleRow['name'];
	$args['changes'][] = $cardChange;
	++$ChangeIndex;
}

$template = $twig->loadTemplate( "cardchanges.twig" );
echo $template->render( $args );

////////////////////////////////////////////////////////////////////////////////
/// FOOTER
$template = $twig->loadTemplate('footer.twig');
echo $template->render(array());

class CardChange
{
	public $index;
	public $cardID;
	public $setcode;
	public $dateModified;
	public $difference;
	public $name;
};

?>