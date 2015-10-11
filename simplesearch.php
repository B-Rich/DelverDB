<?php
include_once "users.php";
include_once "searcharea.php";

global $IsLoggedIn, $LoginErrorMessage;

require_once 'C:/pear/pear/twig/autoloader.php';
Twig_Autoloader::register();
$loader = new Twig_Loader_Filesystem('templates');
$twig = new Twig_Environment($loader);

////////////////////////////////////////////////////////////////////////////////
/// HEADER

$template = $twig->loadTemplate('header.twig');
$args = array();
$args["title"] = "Simple Search - DDB";
$args["heading"] = "Simple Search";
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
	'<script type="text/javascript" src="common.js" ></script>',
	'<script type="text/javascript">
		var IsLoggedIn = '.($IsLoggedIn ? "true" : "false").';
		var PageMode = "Search"; 
	</script>',
	'<script type="text/javascript" src="simplesearch.js" ></script>'
);

echo $template->render($args);

////////////////////////////////////////////////////////////////////////////////
/// SIMPLE SEARCH

CreateSearchArea($twig, $args);

$template = $twig->loadTemplate('simplesearch.twig');
echo $template->render($args);

////////////////////////////////////////////////////////////////////////////////
/// FOOTER
$template = $twig->loadTemplate('footer.twig');
echo $template->render(array());

?>