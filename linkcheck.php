<?php

include_once "passwords.php";
include_once "users.php";

global $IsLoggedIn, $LoginErrorMessage;

if(!$IsLoggedIn)
{
    header("Location: index.php");
}

require_once 'C:/pear/pear/twig/autoloader.php';
Twig_Autoloader::register();
$loader = new Twig_Loader_Filesystem('templates');
$twig = new Twig_Environment($loader);

////////////////////////////////////////////////////////////////////////////////
/// HEADER

$template = $twig->loadTemplate('header.twig');
$args = array();
$args["title"] = "Linked Card Check - DDB";
$args["heading"] = "Linked Card Check";
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
/// LINK CHECK

$SQLUser = $SQLUsers['ddb_usercards'];
$DelverDBLink = new mysqli("localhost", $SQLUser->username, $SQLUser->password, "delverdb");
if($DelverDBLink->connect_errno)
{
    $DBLog->err("Connection error (".$DelverDBLink->connect_errno.") ".$DelverDBLink->connect_error);
    die("Connection error");
}

$UserID = $_SESSION['userid'];

$LinkedCardStmt = $DelverDBLink->prepare("SELECT cards.name, cards.id, cards.linkid, usercards.count
                        FROM cards JOIN usercards
                        ON cards.id = usercards.cardid
                        WHERE cards.linkid IS NOT NULL
                        AND usercards.ownerid = ?");
if(!$LinkedCardStmt)
    die($DelverDBLink->error);

$LinkedCardStmt->bind_param("i", $UserID);
$LinkedCardStmt->execute();
$LinkCardResult = $LinkedCardStmt->get_result();

$CountStmt = $DelverDBLink->prepare("SELECT count FROM usercards
                                WHERE cardid = ? AND ownerid = ?");

if( !$CountStmt )
{
    die($DelverDBLink->error);
}
    
while ( $row = $LinkCardResult->fetch_assoc() )
{
    $cardid = $row['cardid'];
    $linkid = $row['linkid'];
    $name = $row['name'];
    $count = $row['count'];
    
    $CountStmt->bind_param( "ii", $linkid, $UserID );
    $CountStmt->execute();
    
    $linkResult = $CountStmt->get_result();
    
    $linkRow = $linkResult->fetch_assoc();
    
    if ( !$linkRow )
    {
        echo "You own $count x $name, but not the other half</br>";
        continue;
    }
    
    $linkCount = $linkRow['count'];
    
    if ( $linkCount != $count )
    {
        echo "You own $count x $name ($cardid), but you own $linkCount of the other half ($linkid)</br>";
    }
}

////////////////////////////////////////////////////////////////////////////////
/// FOOTER
$template = $twig->loadTemplate('footer.twig');
echo $template->render(array());

?>