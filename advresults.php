<?php

include_once "passwords.php";

include_once "users.php";
include_once "output.php";

include_once "ddbcommon.php";
include_once "cardobj.php";

$ResultsPerPage = 25;

global $IsLoggedIn, $LoginErrorMessage;

$UserID = $IsLoggedIn == true ? $_SESSION['userid'] : null;

require_once 'C:/pear/pear/twig/autoloader.php';
Twig_Autoloader::register();
$loader = new Twig_Loader_Filesystem('templates');
$twig = new Twig_Environment($loader);

////////////////////////////////////////////////////////////////////////////////
/// HEADER

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
echo $template->render( $args );

////////////////////////////////////////////////////////////////////////////////
/// SEARCH RESULTS

$SQLUser = $SQLUsers['oracle_search'];
$DelverDBLink = new mysqli("localhost", $SQLUser->username, $SQLUser->password, "magic_db");
if ( $DelverDBLink->connect_errno )
{
	$DBLog->err( "Connection error (".$DelverDBLink->connect_errno.") ".$DelverDBLink->connect_error );
	die( "Connection error" );
}

$WarningMessages = array();
$ParamsDisplay = array();

$QueryStack = array();
$QueryFormat = array();
$QueryString = "";

$ParameterList = ParseParameters();
$QueryString = CreateQuery( $ParameterList );

/// Data Logging
if ( __DEBUG__ )
{
	$SearchLog->log( "Search URI: ".$_SERVER['REQUEST_URI'] );
	$SearchLog->log( "Search Query: ".$QueryString );
	foreach ( $QueryStack as $index => $value )
	{
		$SearchLog->log( "$index) '".$QueryFormat[$index]."' => $value" );
	}
	$SearchLog->log( "Object: " . print_r( $ParameterList, true ) );
}

if ( count($QueryFormat) != count($QueryStack) )
{
	$SearchLog->err( "Mismatched QueryFormat to QueryStack length" );
	die( "Internal error" );
}

$TemplateArgs = array();

// Display any warnings that have occurred
if ( count( $WarningMessages ) > 0 )
{
	foreach ( $WarningMessages as $index => $message )
	{
		$TemplateArgs['warnings'][] = $message;
		if ( __DEBUG__ )
		{
			$SearchLog->warning("Parameter Warning: ".$message);
		}
	}
}

/// Create an array of references to the query stack
$StackCopy = array();
$StackCount = count($QueryStack);
for ( $i = 0; $i < $StackCount; ++$i )
{
	$StackCopy[] = &$QueryStack[$i];
}
/// Merge the arrays together
array_unshift( $StackCopy, implode( $QueryFormat ) );

$SearchStmt = $DelverDBLink->prepare( "SELECT DISTINCT oracle.cardid " . $QueryString );
if ( $SearchStmt == null )
{
	$SearchLog->log( "Error preparing statement \"$QueryString\": $DelverDBLink->error" );
	die( "Internal error" );
}
	
if ( count($QueryStack) > 0 )
{
	call_user_func_array( array( $SearchStmt, 'bind_param' ), $StackCopy );
}
$SearchStmt->execute();
$SearchResults = $SearchStmt->get_result();

if ( $SearchResults == null )
{
	$SearchLog->log("Invalid query \"$QueryString\": " . $DelverDBLink->error);
	die("Search error");
}

$CardIDArray = array();

while ( $row = $SearchResults->fetch_assoc() )
{
	array_push( $CardIDArray, $row['cardid'] );
}

$CardCountStmt = null;
$IsCardCountSearch = $IsLoggedIn && ( array_key_exists( 'count', $_GET )
	 || ( array_key_exists( 'mycards', $_GET ) && $_GET['mycards'] == 1 ) );
$UserCardCountArray = array();

if ( $IsCardCountSearch )
{
	// Use the old query to find how many of each card ID teh user owns
	$newOwnershipStmt = $DelverDBLink->prepare( "SELECT cardid, count FROM usercards
			WHERE ownerid = 1 AND cardid
			IN ( SELECT DISTINCT oracle.cardid $QueryString ) ORDER BY cardid" );
	if ( $newOwnershipStmt == null )
	{
		$DBLog->err( "Error preparing CardOwnershipStmt " . $DelverDBLink->error );
		die(  "Internal error" );
	}
	
	if ( count( $QueryStack ) > 0 )
	{
		call_user_func_array( array( $newOwnershipStmt, 'bind_param' ), $StackCopy );
	}
	$newOwnershipStmt->execute();
	$newOwnershipResults = $newOwnershipStmt->get_result();
	
	while ( $row = $newOwnershipResults->fetch_row() )
	{
		if ( array_key_exists( $row[0], $UserCardCountArray ) == false )
		{
			$UserCardCountArray[$row[0]] = $row[1];
		}
		else
		{
			$UserCardCountArray[$row[0]] += $row[1];
		}
	}
}


if ( $IsLoggedIn && array_key_exists( 'mycards', $_GET ) && $_GET['mycards'] == 1 )
{
	RemoveCardIDsFromUserData();
}

if ( array_key_exists( 'count', $_GET ) && $IsLoggedIn )
{
	RemoveUserCountMatches();
}

// Does USERID own CARDID
$OwnershipStmt = null;
if ( $IsLoggedIn == true )
{
	$OwnershipStmt = $DelverDBLink->prepare( "SELECT setcode, count FROM usercards WHERE ownerid = $UserID AND cardid = ?" );
	if ( $OwnershipStmt == null )
	{
		$DBLog->err( "Error preparing statement: ".$DelverDBLink->error );
		die(  "Internal error" );
	}
}
$OwnershipResults = null;


$CardCount = count( $CardIDArray );

$NumberOfPages = ceil( $CardCount / $ResultsPerPage );

$CurrentPage = GetCurrentPage();
$CurrentPage = min($CurrentPage, $NumberOfPages - 1);

$TemplateArgs['pageNumber'] = $CurrentPage;
$TemplateArgs['pageLimit'] = $NumberOfPages;
$url = $_SERVER['REQUEST_URI'];
$regex = '/\&?page=(\d+)/';
$matches = array();
preg_match($regex, $url, $matches);
if ( sizeof( $matches ) > 0 )
{
	$url = str_replace( $matches[0], "", $url )."&";
}


$TemplateArgs['pageURL'] = $url;
$TemplateArgs['pageOffset'] = 3;
$TemplateArgs['cardCount'] = $CardCount;

$OracleDataStmt = $DelverDBLink->prepare( "SELECT * FROM oracle WHERE cardid = ?");
$CardSetStmt = $DelverDBLink->prepare( "SELECT setcode, rarity, cnum, artist FROM cardsets WHERE cardid = ?");

$MyCardsOnly = ( IsLoggedIn() && array_key_exists('mycards', $_GET) && $_GET['mycards'] != '0' );

$cardnum = 0;

$CardIndex = 0;
$NumberOfCardsDisplayed = 0;
$NumberOfCardsIgnored = 0;


$TemplateArgs['showOwnerships'] = $IsLoggedIn;

for ( $CardIndex = 0; $CardIndex < $CardCount; ++$CardIndex )
{
	$cardID = $CardIDArray[$CardIndex];
	$OwnershipResults = null;
	
	if ( $NumberOfCardsIgnored < $CurrentPage * $ResultsPerPage)
	{
		$NumberOfCardsIgnored++;
		continue;
	}
	
	if ( $MyCardsOnly == true )
	{
		$OwnershipStmt->bind_param( "i", $cardID );
		$OwnershipStmt->execute();
		$OwnershipResults = $OwnershipStmt->get_result();
	}
	
	
	$OracleDataStmt->bind_param( 'i', $cardID );
	$OracleDataStmt->execute();
	$oracleResult = $OracleDataStmt->get_result();
	$oracleRow = $oracleResult->fetch_assoc();
	
	$completeCard = new Card();
	$completeCard->ConstructFromResults( $oracleRow );
	
	$ownershipArray = null;
	if ( $IsLoggedIn == true )
	{
		// If we haven't already checked to see if I own that card, then do it here
		$ownershipArray = array();
		if ( $OwnershipResults == null )
		{
			$OwnershipStmt->bind_param( "i", $cardID );
			$OwnershipStmt->execute();
			$OwnershipResults = $OwnershipStmt->get_result();
		}
		
		while ( $ownRow = $OwnershipResults->fetcH_assoc() )
		{
			$setcode = $ownRow['setcode'];
			$count = $ownRow['count'];
			$ownershipArray[$setcode] = $count;
		}
		
		if ( $MyCardsOnly == true && count( $ownershipArray ) == 0 )
		{
			continue;
		}
		
	}
	
	$CardSetStmt->bind_param( 'i', $cardID );
	$CardSetStmt->execute();
	$cardSetResult = $CardSetStmt->get_result();
	
	$completeCard->total = 0;
	
	/// Fill out the sets for the card
	while ( $setRow = $cardSetResult->fetch_assoc() )
	{
		$setcode = $setRow['setcode'];
		$rarity = $setRow['rarity'];
		$cnum = $setRow['cnum'];
		$artist = $setRow['artist'];
		
		$count = 0;
		/// Use the count if the user is logged in, and owns the card
		if ( $ownershipArray != null
		  && array_key_exists( $setcode, $ownershipArray ) )
		{
			$count = $ownershipArray[$setcode];
		}
		$completeCard->AddSet( $setcode, $rarity, $cnum, $artist, $count );
	}
	$completeCard->imageurl = $completeCard->GetFirstImageURL();
	
	$completeCard->ratingStars = round($completeCard->rating * 2.0) / 2;
	
	$TemplateArgs['cards'][] = $completeCard;
	
	$NumberOfCardsDisplayed++;
	if ( $NumberOfCardsDisplayed >= $ResultsPerPage )
	{
		break;
	}
}

$TwigTemplate = $twig->loadTemplate( 'advresults.twig' );

echo $TwigTemplate->render( $TemplateArgs );

mysqli_close( $DelverDBLink );

////////////////////////////////////////////////////////////////////////////////
/// FOOTER
$template = $twig->loadTemplate('footer.twig');
echo $template->render( array() );

exit;


////////////////////////////////////////////////////////////////////////////////
/// FUNCTIONS

function GetCurrentPage( )
{
	$page = 0;
	if( array_key_exists('page', $_GET) )
	{
		$page = $_GET['page'];
	}
	$page = max(0, $page);
	return $page;
}

////////////////////////////////////////////////////////////////////////////////
/// Parameter Functions

function VerifyComparisonSymbol($_symbol)
{
	return $_symbol == '='
		|| $_symbol == '>'
		|| $_symbol == '<'
		|| $_symbol == '>='
		|| $_symbol == '<=';
}

function VerifyBooleanSymbol( $_symbol )
{
	return $_symbol == '&'
		|| $_symbol == '|'
		|| $_symbol == '!';
}

function BooleanSymbolToSQL( $_symbol )
{
	switch ( $_symbol )
	{
		case '|':
			return "OR";
		case '&':
			return "AND";
		case '!': 
			return "NOT";
		default:
			$WarningMessages[] = "Uncaught boolean converson \"$_symbol\"";
			return null;
	}
}

function nameCardMatch($name, $comp)
{
	global  $QueryStack, $QueryFormat, $ParamsDisplay;
	//$name = '%'.$name.'%';
	array_push($QueryStack, $name);
	array_push($QueryFormat, "s");
	return "(oracle.name REGEXP ? )";
}

function rulesCardMatch($rules, $comp)
{
	global $QueryStack, $QueryFormat, $ParamsDisplay;
	//$rules = '%'.$rules.'%';
	array_push($QueryStack, $rules);
	array_push($QueryFormat, "s");
	return "(oracle.rules IS NOT NULL AND oracle.rules REGEXP ? )";
}

function expansionCardMatch($expansion, $comp)
{
	global $QueryStack, $QueryFormat, $ParamsDisplay;
	array_push($QueryStack, $expansion);
	array_push($QueryFormat, "s");
	return "(cardsets.setcode = ? )";
}

function formatCardMatch($format, $comp)
{
	global $QueryStack, $QueryFormat, $WarningMessages, $ParamsDisplay;
	$str = "(";
	
	if(!array_key_exists($format, Defines::$CardFormats))
	{
		$WarningMessages[] = "Unrecognised format '$format' used.";
		return " FALSE ";
	}
	
	$sets = Defines::$CardFormats[$format];
	$count = 0;
	foreach($sets as $set)
	{
		if($count++ != 0)
			$str .= " OR ";
		
		array_push($QueryStack, $set);
		array_push($QueryFormat, "s");
		$str .= " cardsets.setcode = ? ";
	}
	$str .= ") ";
	return $str;
}

function colourCardMatch($colour, $comp)
{
	global $QueryStack, $QueryFormat, $WarningMessages, $ParamsDisplay, $ParamsDisplay;
	if(!array_key_exists($colour, Defines::$ColourSymbolsToInt))
	{
		$WarningMessages[] = "Unrecognised colour '$colour' used.";
		return " ( FALSE ) ";
	}
	
	$bitflag = Defines::$ColourSymbolsToInt[$colour];
	array_push($QueryStack, $bitflag);
	array_push($QueryFormat, "i");
	return " (oracle.colour & ?) ";
}

function colouridCardMatch( $colourid, $comp )
{
	global $QueryStack, $QueryFormat, $WarningMessages, $ParamsDisplay, $ParamsDisplay;
	if ( array_key_exists( $colourid, Defines::$ColourSymbolsToInt ) == false )
	{
		$WarningMessages[] = "Unrecognised colour '$colour' used in colour identity.";
		return " ( FALSE ) ";
	}

	$bitflag = Defines::$ColourSymbolsToInt[$colourid];
	array_push($QueryStack, $bitflag);
	array_push($QueryFormat, "i");
	return " (oracle.colouridentity & ?) ";
}

function numcoloursCardMatch( $numcolours, $comp )
{
	global $QueryStack, $QueryFormat, $WarningMessages, $ParamsDisplay;

	if( !VerifyComparisonSymbol($comp) )
	{
		$WarningMessages[] = "Unrecognised comparison symbol '$comp' used in Colour Count comparison.";
		return " FALSE ";
	}
	if( !is_numeric( $numcolours ) )
	{
		$WarningMessages[] = "Non numeric colour count '$cmc' used.";
		return " FALSE ";
	}
	array_push( $QueryStack, $numcolours );
	array_push( $QueryFormat, "i" );
	return " (oracle.numcolours $comp ?) ";
}

function typeCardMatch($type, $comp)
{
	global $QueryStack, $QueryFormat, $ParamsDisplay;
	//$type = '%'.$type.'%';
	array_push($QueryStack, $type);
	array_push($QueryFormat, "s");
	return " ( oracle.type IS NOT NULL AND oracle.type REGEXP ?) ";
}
function subtypeCardMatch($subtype, $comp)
{
	global $QueryStack, $QueryFormat, $ParamsDisplay;
	//$subtype = '%'.$subtype.'%';
	array_push($QueryStack, $subtype);
	array_push($QueryFormat, "s");
	return " ( oracle.subtype IS NOT NULL AND oracle.subtype REGEXP ? ) ";
}

function costCardMatch($cost, $comp)
{
	global $QueryStack, $QueryFormat, $ParamsDisplay;
	//$cost = '%'.$cost.'%';
	array_push($QueryStack, $cost);
	array_push($QueryFormat, "s");
	return "(oracle.cost IS NOT NULL AND oracle.cost REGEXP ? )";
}

function cmcCardMatch($cmc, $comp)
{
	global $QueryStack, $QueryFormat, $WarningMessages, $ParamsDisplay;
	
	if ( !VerifyComparisonSymbol($comp) )
	{
		$WarningMessages[] = "Unrecognised comparison symbol '$comp' used in CMC comparison.";
		return " FALSE ";
	}
	if ( !is_numeric($cmc) )
	{
		$WarningMessages[] = "Non numeric CMC '$cmc' used.";
		return " FALSE ";
	}
	array_push($QueryStack, $cmc);
	array_push($QueryFormat, "i");
	return " (oracle.cmc $comp ?) ";
}

function powerCardMatch($power, $comp)
{
	global $QueryStack, $QueryFormat, $WarningMessages, $ParamsDisplay;
	if(!VerifyComparisonSymbol($comp))
	{
		$WarningMessages[] = "Unrecognised comparison symbol '$comp' used in power comparison.";
		return " FALSE ";
	}
	if(!is_numeric($power))
	{
		$WarningMessages[] = "Non numeric power '$power' used.";
		return " FALSE ";
	}
	array_push($QueryStack, $power);
	array_push($QueryFormat, "i");
	return " (oracle.power IS NOT NULL AND oracle.numpower $comp ?) ";
}

function toughnessCardMatch($toughness, $comp)
{
	global $QueryStack, $QueryFormat, $WarningMessages, $ParamsDisplay;
	if(!VerifyComparisonSymbol($comp))
	{
		$WarningMessages[] = "Unrecognised comparison comp '$comp' used in toughness comparison.";
		return " FALSE ";
	}
	if(!is_numeric($toughness))
	{
		$WarningMessages[] = "Non numeric toughness '$toughness' used.";
		return " FALSE ";
	}
	
	array_push($QueryStack, $toughness);
	array_push($QueryFormat, "i");
	return " (oracle.toughness IS NOT NULL AND oracle.numtoughness $comp ?) ";
}

function rarityCardMatch($rarity, $comp)
{
	global $QueryStack, $QueryFormat, $WarningMessages, $ParamsDisplay;
	
	if ( $rarity != 'L'
	  && $rarity != 'C'
	  && $rarity != 'U'
	  && $rarity != 'R'
	  && $rarity != 'M'
	  && $rarity != 'B' )
	{
		$WarningMessages[] = "Rarity symbol '$rarity' not recognised";
		return " FALSE ";
	}
	
	array_push($QueryStack, $rarity);
	array_push($QueryFormat, "s");
	return " (cardsets.rarity = ?) ";
}

function artistCardMatch( $artist, $comp )
{
	global $QueryStack, $QueryFormat, $ParamsDisplay;
	//$artist = '%'.$artist.'%';
	array_push( $QueryStack, $artist );
	array_push( $QueryFormat, "s" );
	return " (cardsets.artist REGEXP ?) ";
}

///
////////////////////////////////////////////////////////////////////////////////
///

function CreateQuery( $_allParams )
{
	global $DelverDBLink, $QueryString, $QueryStack, $QueryFormat, $WarningMessages, $SearchLog;
	
	if ( array_key_exists( 'expansion', $_GET )
	  || array_key_exists( 'format', $_GET ) 
	  || array_key_exists( 'rarity', $_GET )
	  || array_key_exists( 'artist', $_GET ) )
	{
		$query = " FROM oracle INNER JOIN cardsets ON oracle.cardid = cardsets.cardid ";
	}
	else // Otherwise, we don't need the cardsets table joined on
	{
		$query = " FROM oracle ";
	}
	
	$paramCount = 0;
	foreach ( $_allParams->parameterArray as $list )
	{
		$paramCount += count($list);
	}
	
	if ( $paramCount == 0 )//&& !array_key_exists( 'count', $_GET ) )
	{
		$WarningMessages[] = "No valid parameters found.";
	}
	else 
	{
		$query .= " WHERE ";
		
		$typeIndex = 0;
		$selectedColours = array();
		foreach ( $_allParams->parameterArray as $type => $parameters )
		{
			if ( is_array( $parameters ) == false )
			{
				$WarningMessages[] = "Non array parameter $type =>  $parameter found.";
				continue;
			}
			
			if ( $typeIndex != 0 )
			{
				$query .= " AND ";
			}
			
			$query .= "(";	
			$parameterIndex = 0;
			foreach ( $parameters as $index => $parameter )
			{
				if ( $index != 0 && $parameter->bool != '!' )
				{
					$query .= ' ' . BooleanSymbolToSQL( $parameter->bool ) . ' ';
				}
				
				if ( $index != 0 && $parameter->bool == '!' )
				{
					$query .= " AND ";
				}
				
				if ( $parameter->bool == '!' )
				{
					$query .= " NOT ";	
				}
				
				if ( $type == 'colour' && $parameter->bool != '!' )
				{
					$selectedColours[$parameter->argument] = true;
				}
				
				$callbackName = $type.'CardMatch';
				if ( __DEBUG__ && is_callable( $callbackName ) == false )
				{
					$SearchLog->err("Cannot find function $callbackName");
					continue;
				}
				
				$str = call_user_func( $callbackName, $parameter->argument, $parameter->comp, $DelverDBLink );
				if ( $str == false )
				{
					$SearchLog->err( "Error running callback $callbackName" );
					continue;
				}
				$query .= $str;
			}
			
			$query .= ")";
			/*if ( array_key_exists( $type, $requiredFields ) == true )
			{
				$query .= " AND $type IS NOT NULL ";
			}*/
			
			++$typeIndex;
		}
	}
	
	if ( $_allParams->multicolouredOnly == true )
	{
		$query .= " AND (BIT_COUNT(oracle.colour) > 1) ";
	}

	if ( $_allParams->excludeUnselectedColours == true )
	{
		if ( array_key_exists( 'colour', $_GET ) == false )
		{
			$WarningMessages[] = "Cannot exclude unselected colours with no colours selected";
		}
		else
		{
			$colourFlags = 0;
			foreach ( Defines::$ColourSymbolsToInt as $symbol => $flag )
			{
				if ( array_key_exists( $symbol, $selectedColours) == false )
				{
					$SearchLog->log( "Flag: $flag" );
					$colourFlags |= $flag;
				}
			}
			$query .= " AND ( (oracle.colour & $colourFlags) = 0 ) ";
		}

	}
	
	if ( is_numeric( $_allParams->colourIdentity )
	  && $_allParams->colourIdentity != 31 )
	{
		$otherColours = ~($_allParams->colourIdentity + 0);
		$query .= " AND ( oracle.colouridentity & $otherColours = 0 ) ";
	}

	$query.= CreateSQLOrderString( $_allParams );

	return $query;
}

function CreateSQLOrderString( $_allParams )
{
	global $WarningMessages;
	$query = " ORDER BY ";
	
	$SortConversion = array(
		'name' => 'oracle.name',
		'id' => 'oracle.cardid',
		'cmc' => 'oracle.cmc',
		'power' => 'oracle.numpower',
		'toughness' => 'oracle.numtoughness',
		'rating' => 'oracle.rating',
	);
	
	foreach ( $_allParams->sortParameters as $str )
	{
		$matches = array();
		preg_match("/^(?P<comp>[<>]{1})?(?P<order>.+)$/", $str, $matches);

		if ( array_key_exists('comp', $matches) == false
		  || array_key_exists('order', $matches) == false )
		{
			$WarningMessages[] = "Badly formed sort parameter '$str'";
			continue;
		}

		$sort = $matches['order'];
		if ( array_key_exists($sort, $SortConversion) == false )
		{
			$WarningMessages[] = "Unrecognised sort type '$sort'";
			continue;
		}

		$comp = $matches['comp'];
		if ( $comp != '<' && $comp != '>' )
		{
			$WarningMessages[] = "Unrecognised sort order '$comp'";
			continue;
		}

		$orderWord =  $comp == '<' ? 'DESC' : 'ASC';
		$query .= ' '.$SortConversion[$sort]." $orderWord, ";
	}
	$query .= ' name ASC ';
	return $query;
}

function ParseParameters()
{
	global $WarningMessages, $SearchLog;
	
	$allParams = new AllParameters();
	
	$parameterArray = array();
	
	$paramTypes = array
	(
		'name', 'rules', 'expansion',
		'format', 'colour', //'colourid',
		'numcolours', 'type', 'subtype',
		'cost', 'cmc', 'power',
		'toughness', 'rarity', 'artist',
		'order',
	);
	
	$acceptableFlags = array
	(
		'excunscolours',
		'multionly',
		'mycards',
		'page',
		'colouridentity',
	);
	
	foreach ( $_REQUEST as $parameterType => $parameterValue )
	{
		$isFlag = !(array_search( $parameterType, $acceptableFlags ) === false);
		$isCallbackType = !(array_search( $parameterType, $paramTypes ) === false);
		$isArray = is_array( $parameterValue );
		
		if ( $isFlag == true )
		{
			if ( $isArray == true )
			{
				$WarningMessages[] = "Flag \"$parameterType\" cannot be array.";	
				continue;
			}
			
			switch ( $parameterType )
			{
				case 'excunscolours':
					$allParams->excludeUnselectedColours = ($parameterValue == 1);
					break;
				case 'multionly':
					$allParams->multicolouredOnly = ($parameterValue == 1);
					break;
				case 'mycards':
					$allParams->userCardsOnly = ($parameterValue == 1);
					break;
				case 'page':
					$allParams->currentPage = $parameterValue;
					break;
				case 'colouridentity':
					$allParams->colourIdentity = $parameterValue;
					break;
				case 'count': // Count is handled elsewhere
					break;
				default:
					$WarningMessages[] = "Uncaught flag $parameterType => $parameterValue";
					break;
						
			}
			continue;
		}
		else if ( $isCallbackType == true )
		{
			if ( $isArray == false )
			{
				$WarningMessages[] = "Parameter type $parameterType must be array.";
				continue;
			}
			SplitParameterArray( $allParams, $parameterValue, $parameterType );
		}
		else if ( $parameterType != 'count' )
		{
			$WarningMessages[] = "Uncaught parameter type $parameterType.";
		}
	}
	
	return $allParams;
}

function SplitParameterArray( $_allParams, $_paramList, $_parameterType )
{
	foreach ( $_paramList as $parameter )
	{
		if ( $_parameterType == 'order' )
		{
			$_allParams->sortParameters[] = $parameter;
			continue;
		}
	
		$paramObj = SplitParameter( $parameter );
		if ( $paramObj == null )
		{
			$WarningMessages[] = "Error splitting $_parameterType => $parameter";
			continue;
		}
	
		if ( array_key_exists( $_parameterType, $_allParams->parameterArray ) == false )
		{
			$_allParams->parameterArray[$_parameterType] = array();
		}
	
		$_allParams->parameterArray[$_parameterType][] = $paramObj;
	}	
}

function SplitParameter( $_param ) // Returns SearchParameter
{
	$Matches = array();
	preg_match( "/^(?P<bool>[&|!])?(?P<comp>[<=>]{1,3})?(?P<param>.+)$/", $_param, $Matches );
	if ( array_key_exists('param', $Matches) == false )
	{
		return null;
	}
	
	$param = $Matches['param'];
	$comp = array_key_exists('comp', $Matches) ? $Matches['comp'] : '';
	
	if ( $comp != '' && VerifyComparisonSymbol( $comp ) == false )
	{
		return null;
	}
	
	if ( array_key_exists('bool', $Matches) == false )
	{
		return null;	
	}
	
	$bool = $Matches['bool'];
	
	if ( VerifyBooleanSymbol( $bool ) == false )
	{
		return null;
	}
	
	$searchParameter = new SearchParameter();
	$searchParameter->argument = $param;
	$searchParameter->comp = $comp;
	$searchParameter->bool = $bool;
	
	return $searchParameter;
}

function RemoveCardIDsFromUserData()
{
	global $IsLoggedIn, $CardIDArray, $UserCardCountArray;

	$newArray = array();
	
	$myCardsOnly = ( $IsLoggedIn && array_key_exists('mycards', $_GET) && $_GET['mycards'] != '0' );

	foreach ( $CardIDArray as $cardID )
	{
		if ( array_key_exists( $cardID, $UserCardCountArray ) )
		{
			//unset( $CardIDArray[ $cardID ] );
			$newArray[] = $cardID;
		}
	}
	$CardIDArray = $newArray;
}

function RemoveUserCountMatches()
{
	global $IsLoggedIn, $CardCountStmt, $UserCardCountArray, $CardIDArray;
	
	$cardCountArgs = $_GET['count'];
	if ( is_array( $cardCountArgs ) == false )
	{
		die( "Card count args is not an array" );
		return;	
	}
	
	$paramArray = array();
	
	foreach ( $cardCountArgs as $index => $arg )
	{
		$paramObj = SplitParameter( $arg );
		if ( $paramObj == null )
		{
			die( "Error" );
		}
		$paramArray[] = $paramObj;
	}
	
	$newArray = array();
	foreach ( $CardIDArray as $id )
	{
		// No data means the user must own zero of the card
		$count = array_key_exists( $id, $UserCardCountArray ) ? $UserCardCountArray[$id] : 0;
		
		$goodID = true;
		foreach ( $paramArray as $param )
		{
			$thisIsGood = false;
			switch ( $param->comp )
			{
			case ">":
				$thisIsGood = ($count > $param->argument);
				break;
			case "<":
				$thisIsGood = ($count < $param->argument);
				break;
			case ">=":
				$thisIsGood = ($count >= $param->argument );
				break;
			case "<=":
				$thisIsGood = ($count <= $param->argument );
				break;
			case "=":
				$thisIsGood = ($count == $param->argument );
				break;
			default:
				die( "Uncaught type " . $param->comp );
				break;
			}
			
			switch ( $param->bool )
			{
				case "&":
					if ( $thisIsGood == false )
					{
						$goodID = false;
					}
					break;
				case "|":
					if ( $thisIsGood )
					{
						$goodID = true;	
					}
					break;
				case "!":
					if ( $thisIsGood )
					{
						$goodID = false;	
					}
					break;
				default:
					die( "Unknown bool type ".$param->bool );
					break;	
			}
		}
		
		if ( $goodID )
		{
			$newArray[] = $id;	
		}
	}
	$CardIDArray = $newArray;
}

class AllParameters
{
	public $parameterArray = array();
	
	public $sortParameters = array();
	
	public $excludeUnselectedColours = false;
	public $multicolouredOnly = false;
	public $userCardsOnly = false;
	public $currentPage = 0;
	public $colourIdentity = 31;
};

class SearchParameter
{
	public $comp;
	public $bool;
	public $argument;
}

class BasicCard
{
	public $id;
	public $dataIndex;
	public $sets = array();
}

?>