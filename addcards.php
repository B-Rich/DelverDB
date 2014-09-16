<?php
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
$args["title"] = "Add Cards - DDB";
$args["heading"] = "Add Cards";
$args['isloggedin'] = $IsLoggedIn;

if($LoginErrorMessage != null)
	$args['loginerrormessage'] = $LoginErrorMessage;
$args['loginurl'] = $_SERVER['PHP_SELF'];
if($IsLoggedIn)
{
	$args['username'] = $_SESSION['username'];
}
$args['scripts'][] = '<script type="text/javascript" src="common.js" ></script>';
$args['scripts'][] = '<script type="text/javascript" src="addcards.js" ></script>';

echo $template->render($args);

////////////////////////////////////////////////////////////////////////////////
/// ADD CARDS

/// Set selector data
$args['blocks'] = array();
class block
{
	public $name;
	public $sets = array();
};

class set
{
	public $name;
	public $setcode;
};

foreach(Defines::$CardBlocksToSetCodes as $blockname => $setcodes)
{
	$block = new block();
	$block->name = $blockname;
	foreach($setcodes as $setcode)
	{
		$set = new set();
		$set->setcode = $setcode;
		$set->name = Defines::$SetCodeToNameMap[$setcode];
		$block->sets[] = $set;
	}
	$args['blocks'][] = $block;
}

$template = $twig->loadTemplate('addcards.twig');
echo $template->render($args);

////////////////////////////////////////////////////////////////////////////////
/// FOOTER
$template = $twig->loadTemplate('footer.twig');
echo $template->render(array());

?>