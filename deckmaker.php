<?php

include_once "passwords.php";
include_once "users.php";
include_once "searcharea.php";

global $IsLoggedIn, $LoginErrorMessage;

require_once 'C:/pear/pear/twig/autoloader.php';
Twig_Autoloader::register();
$loader = new Twig_Loader_Filesystem('templates');
$twig = new Twig_Environment($loader);

require_once "C:\pear\pear\propel\Propel.php";
Propel::init("\propel\build\conf\magic_db-conf.php");
set_include_path("propel/build/classes/" . PATH_SEPARATOR . get_include_path());

$IsLoggedIn = IsLoggedIn();
$UseExistingDeck = false;
$ExistingDeckID = -1;
$DeckContents = array();
$DeckName = null;

// DeckCards are used for deck modification, and are passed into the javascript
class DeckCard
{
    public $name;
    public $setcode;
    public $id;
    public $count;
    public $numOwn;
};

/// If the user is editing a deck, cards from the deck will have to be loaded into the javascript
/// First the cards have to be found
if($IsLoggedIn && array_key_exists('edit', $_GET))
{
    $ExistingDeckID = $_GET['edit'];
    $userID = $_SESSION['userid'];
    $DeckLog->log("Starting deck load for modification by user $userID");
    
    $SQLUser = $SQLUsers['deckmaker'];
    $DelverDBLink = new mysqli("localhost", $SQLUser->username, $SQLUser->password, "delverdb");
    
    if($DelverDBLink->connect_errno)
    {
        $DBLog->err("Cannot connect to database ($DelverDBLink->connect_errno) $DelverDBLink->connect_error");
        die("Internal error");
    }
    
    $DeckEditStmt = $DelverDBLink->prepare("SELECT * FROM decklists WHERE deckid = ? AND ownerid = ?");
    if(($DeckEditStmt->bind_param("ii", $ExistingDeckID, $userID)) == false)
    {
        $DBLog->err("Error binding parameters for deck modification load");
        die("Internal error");
    }
    $DeckEditStmt->execute();
    $DeckEditResult = $DeckEditStmt->get_result();
    if($DeckRow = $DeckEditResult->fetch_assoc())
    {
        // The deck has been found, and the user can modify it
        // Now the cards in the deck have to be found and attached to the script
        $DeckName = $DeckRow['deckname'];
        $UseExistingDeck = true;
        
        // This statement will be used to find the details for every card
        $CardStmt = $DelverDBLink->prepare("SELECT oracle.name, cardsets.setcode
                 FROM oracle, cardsets, deckcards
                 WHERE oracle.cardid = cardsets.cardid AND oracle.cardid = deckcards.cardid AND cardsets.cardid = deckcards.cardid
                AND deckcards.cardid = ?");
        
        // This statement will find the total number of a given card the user has
        $OwnershipStmt = $DelverDBLink->prepare("SELECT SUM(usercards.count) FROM usercards WHERE cardid = ? AND ownerid = ?");
        
        // Find the deck contents
        $DeckContentsStmt = $DelverDBLink->prepare("SELECT * FROM deckcards WHERE deckid = ?");
        $DeckContentsStmt->bind_param('i', $ExistingDeckID);
        $DeckContentsStmt->execute();
        $DeckContentsResult = $DeckContentsStmt->get_result();

        // Loop through the deck contents and store each card in the DeckContents array
        while($row = $DeckContentsResult->fetch_assoc())
        {
            $card = new DeckCard();
            $card->id = $row['cardid'];
            $card->count = $row['count'];
            
            if($card->count <= 0)
            {
                $DeckLog->warning("Invalid card coun $card->count for card $card->id");
                continue;
            }
            
            // The card name and most recent setcode have to be found so an image can be displayed
            $CardStmt->bind_param('i', $card->id);
            $CardStmt->execute();
            $CardResults = $CardStmt->get_result();
            if($cardRow = $CardResults->fetch_assoc())
            {
                $card->name = $cardRow['name'];
                $card->setcode = $cardRow['setcode'];
            }
            else
            {
                $DeckLog->warning("Could not find card with ID $card->id when loading deck $ExistingDeckID ($DeckName)");
                continue;    
            }
            
            
            /// The number of cards that the user owns also needs to be found, if they have any
            $OwnershipStmt->bind_param("ii", $card->id, $userID);
            $OwnershipStmt->execute();
            $CardResults = $OwnershipStmt->get_result();
            if($CardResults->num_rows > 0)
            {
                $cardRow = $CardResults->fetch_row();
                $card->numOwn = $cardRow[0] != null ? $cardRow[0] : 0;
            }
            else
            {
                $card->numOwn = 0;
            }
            
            $DeckContents[$card->id] = $card;
            $DeckLog->log("Adding $card->count x $card->name to deck (own $card->numOwn)");
        }
    }
    $DeckLog->log("Deck load complete");
}

/// Make a custom javascript that will give the deck builder a list of cards to use
$CustomScript = "
var IsLoggedIn = ".($IsLoggedIn ? 'true' : 'false').";
var PageMode = 'CreateDeck';
var ExistingDeckID = $ExistingDeckID;
var UseExistingDeck =  ".($UseExistingDeck ? 'true' : 'false').";";

if($UseExistingDeck)
{
    $CustomScript .= "var OldDeckContents = new Array();";
    $count = 0; 
    foreach($DeckContents as $cardID => $card)
    {
        $CustomScript .=  'OldDeckContents['.$cardID.']'
             .'= new DeckCard("'
                .$card->name.'", "'
                .$card->setcode.'",'
                .$cardID.', '
                .$card->count.', '
                .$card->numOwn.');'."\n";
    }
}


////////////////////////////////////////////////////////////////////////////////
/// HEADER

$template = $twig->loadTemplate('header.twig');
$args = array();
$args["title"] = "Make a Deck - DDB";
$args["heading"] = "Make a Deck";
$args['isloggedin'] = $IsLoggedIn;

if($LoginErrorMessage != null)
    $args['loginerrormessage'] = $LoginErrorMessage;
$args['loginurl'] = $_SERVER['PHP_SELF'];
if($IsLoggedIn)
{
    $args['username'] = $_SESSION['username'];
}

$args['scripts'] = array
(
    '<script type="text/javascript" src="js/jquery-1.11.1.min.js"></script>',
    //"<script type='text/javascript' src='http://code.jquery.com/jquery-1.4.2.js' ></script>",
    "<script type='text/javascript' src='deckcard.js' ></script>",
    "<script type='text/javascript' src='common.js' ></script>",
    "<script type='text/javascript'>$CustomScript</script>",
    "<script type='text/javascript' src='simplesearch.js' ></script>"
);

echo $template->render($args);

////////////////////////////////////////////////////////////////////////////////
/// SIMPLE SEARCH

if($UseExistingDeck)
{
    ?>
    <h2>Editing Deck: <?php echo $DeckName; ?></h2>
    <?php
} 

if(!IsLoggedIn())
{
    ?>
    <h3>Warning: You cannot save a deck if you are not logged in</h3>
    <?php 
}

CreateSearchArea($twig, $args);

$args['useexistingdeck'] = $UseExistingDeck;
$args['deckname'] = $DeckName;

$template = $twig->loadTemplate('deckmaker.twig');
echo $template->render($args);


////////////////////////////////////////////////////////////////////////////////
/// FOOTER
$template = $twig->loadTemplate('footer.twig');
echo $template->render(array());


?>