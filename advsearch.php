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
$args["title"] = "Advanced Search - DDB";
$args["heading"] = "Advanced Search";
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
	//'<script type="text/javascript" src="http://code.jquery.com/jquery-1.4.2.js" ></script>',
	'<script type="text/javascript" src="common.js" ></script>',
	'<script type="text/javascript" src="advsearch.js" ></script>'
);

echo $template->render($args);

////////////////////////////////////////////////////////////////////////////////
/// ADVANCED SEARCH

$template = $twig->loadTemplate('advsearch.twig');

class ParameterType
{
	public $id;
	public $name;
	public $boolField;
	public $defaultbool;
	public $fieldtype;
	public $optiongroups = array();
	public $options = array();
	public function ParameterType($_id, $_name, $_usebool, $_defaultbool, $_fieldType)
	{
		$this->id = $_id;
		$this->name = $_name;
		$this->boolField = $_usebool;
		$this->defaultbool = $_defaultbool;
		$this->fieldtype = $_fieldType;
	}
};

class OptionGroup
{
	public $title;
	public $options = array();
	
	public function OptionGroup($_title)
	{
		$this->title = $_title;
	}
}
class Option
{
	public $value;
	public $label;
	
	public function Option($_value, $_label)
	{
		$this->value = $_value;
		$this->label = $_label;
	}
	
}

$args['parametertypes'] = array();

/// NAME
$args['parametertypes'][] = new ParameterType("name", "Name", true, 'and', 'text');

/// RULES
$args['parametertypes'][] = new ParameterType( "rules", "Rules Text", true, 'and', 'text' );

/// EXPANSIONS
$expansions = new ParameterType('expansion', 'Expansion', true, 'or', 'groupselect');
foreach(Defines::$CardBlocksToSetCodes as $block => $codesarray)
{
	$optGroup = new OptionGroup($block);
	foreach($codesarray as $code)
	{
		$optGroup->options[] = new Option($code, Defines::$SetCodeToNameMap[$code]);
	}
	$expansions->optiongroups[] = $optGroup;
}
$args['parametertypes'][] = $expansions;


/// FORMATS
$formats = new ParameterType('format', 'Format', true, 'or', 'select');
foreach(Defines::$CardFormats as $format => $codesarray)
{
	$formats->options[] = new Option($format, $format);
}

$args['parametertypes'][] = $formats;

/// COLOURS
$colours = new ParameterType('colour', 'Colour', true, 'and', 'select');
foreach(Defines::$ColourNamesToSymbols as $name => $symbol)
{
	$colours->options[] = new Option($symbol, $name);
}
$args['parametertypes'][] = $colours;

/// COLOUR IDENTITY

$args['colours'] = array( 'w', 'u', 'b', 'r', 'g' );

/*$colourIdentity = new ParameterType('colourid', 'Colour Identity', true, 'and', 'select');
foreach ( Defines::$ColourNamesToSymbols as $name => $symbol )
{
	$colourIdentity->options[] = new Option($symbol, $name);
}
$args['parametertypes'][] = $colourIdentity;*/

/// NUMCOLOURS
$args['parametertypes'][] = new ParameterType("numcolours", "Colour Count", true, 'or', 'numeric');

/// TYPES
$types = new ParameterType('type', 'Type', true, 'and', 'select');
foreach(Defines::$Types as $type)
{
	$types->options[] = new Option($type, $type);
}
$args['parametertypes'][] = $types;

/// SUBTYPES
$subtypes = new ParameterType('subtype', 'Subtype', true, 'and', 'select');
foreach(Defines::$Subtypes as $subtype)
{
	$subtypes->options[] = new Option($subtype, $subtype);
}
$args['parametertypes'][] = $subtypes;

/// CMC
$args['parametertypes'][] = new ParameterType("cmc", "CMC", true, 'or', 'numeric');

/// COST
$args['parametertypes'][] = new ParameterType("cost", "Mana Cost", true, 'or', 'text');

/// POWER
$args['parametertypes'][] = new ParameterType("power", "Power", true, 'or', 'numeric');

/// TOUGHNESS
$args['parametertypes'][] = new ParameterType("toughness", "Toughness", true, 'or', 'numeric');

/// RARITY
$rarities = new ParameterType('rarity', 'Rarity', true, 'and', 'select');
foreach(Defines::$RarityNameToSymbol as $name => $symbol)
{
	$rarities->options[] = new Option($symbol, $name);
}
$args['parametertypes'][] = $rarities;

/// ARTIST
$args['parametertypes'][] = new ParameterType( "artist", "Artist", true, 'and', 'text' );

/// USER COUNT
if ( $IsLoggedIn == true )
{
	$args['parametertypes'][] = new ParameterType( 'count', 'Count', true, 'and', 'numeric' );
}

/// SORT

$SortLabelToValue = array(
	'Name' =>'name',
	'ID' =>'id',
	'CMC' =>'cmc',
	'Power' =>'power',
	'Toughess' =>'toughness',
	'Community Rating' => 'rating',
);

$sort = new ParameterType('order', 'Sort', false, null, 'select');
foreach($SortLabelToValue as $label => $value)
{
	$sort->options[] = new Option($value, $label);
}
$args['parametertypes'][] = $sort;

echo  $template->render($args);

////////////////////////////////////////////////////////////////////////////////
/// FOOTER

$template = $twig->loadTemplate('footer.twig');

echo $template->render($args);

?>
