<?php
include_once "users.php";

global $IsLoggedIn, $LoginErrorMessage;

require_once 'C:/pear/pear/twig/autoloader.php';
Twig_Autoloader::register();
$loader = new Twig_Loader_Filesystem('templates');
$twig = new Twig_Environment($loader);

////////////////////////////////////////////////////////////////////////////////
/// HEADER

$template = $twig->loadTemplate('header.twig');
$args = array();
$args["title"] = "Home - DDB";
$args["heading"] = "Home";
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
/// FOOTER
$template = $twig->loadTemplate('footer.twig');
echo $template->render(array());

?>