<?php
include_once "users.php";

global $IsLoggedIn, $LoginErrorMessage;

if ( $IsLoggedIn == false )
{
	header( "Location: index.php" );
}

require_once 'C:/pear/pear/twig/autoloader.php';
Twig_Autoloader::register();
$loader = new Twig_Loader_Filesystem('templates');
$twig = new Twig_Environment($loader);

require_once "C:\pear\pear\propel\Propel.php";
Propel::init("\propel\build\conf\magic_db-conf.php");
set_include_path("propel/build/classes/" . PATH_SEPARATOR . get_include_path());

////////////////////////////////////////////////////////////////////////////////
/// HEADER

$template = $twig->loadTemplate('header.twig');
$args = array();
$args["title"] = "My Decks - DDB";
$args["heading"] = "My Decks";
$args['isloggedin'] = $IsLoggedIn;

if($LoginErrorMessage != null)
	$args['loginerrormessage'] = $LoginErrorMessage;
$args['loginurl'] = $_SERVER['PHP_SELF'];
if($IsLoggedIn)
{
	$args['username'] = $_SESSION['username'];
}
echo $template->render($args);

////////////////////////////////////////////////////////////////////////////////
/// MY DECKS

echo "<a href='deckmaker.php'>Make a Deck</a>";

class deck
{
	public $index;
	public $id;
	public $name;
	public $datecreated;
	public $datemodified;
};

$UserID = $_SESSION['userid'];

$DeckQuery = new DecklistQuery();
$DeckResults = $DeckQuery->filterByOwnerID($UserID)->find();

$Decks = array();
$DeckCount = 0;
foreach($DeckResults as $Deck)
{
	$deck = new Deck();
	$deck->index = ++$DeckCount;
	$deck->name = $Deck->GetDeckName();
	$deck->id = $Deck->GetDeckID();
	$deck->datecreated = $Deck->GetDateCreated();;
	$deck->datemodified = $Deck->GetDateModified();
	$Decks[] = $deck;
}

if($DeckCount > 0)
{
	$args['decks'] = $Decks;
	
	$template = $twig->loadTemplate('mydecks.twig');
	
	echo $template->render($args);
}
else
{
	echo "<h3>You don't have any decks</h3>";
}

////////////////////////////////////////////////////////////////////////////////
/// FOOTER
$template = $twig->loadTemplate('footer.twig');
echo $template->render(array());

?>