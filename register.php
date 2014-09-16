<?php
include_once "users.php";

global $IsLoggedIn, $LoginErrorMessage;

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
$args["title"] = "Regster - DDB";
$args["heading"] = "Register";
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
/// REGISTRATION



$errmsg = "";
$UserInserted = false;

if(array_key_exists('submit', $_POST))
{
	$UserInserted = InsertUser();
	if(!$UserInserted)
	{
		echo $errmsg;
	}
	else
	{
		echo "New user account made, you can now log in";
	}
}

// Show registration form
if(!$UserInserted)
{
	$template = $twig->loadTemplate('register.twig');
	echo $template->render($args);
}

function InsertUser()
{
	global $errmsg, $UserLog, $DBLog;
	
	$UserLog->log("Starting user registration");
	if(!$_POST['username'] || !$_POST['pass'] || !$_POST['pass2'])
	{
		$errmsg = 'You did not complete all of the required files';
		$UserLog->log("User registration failure: Fields missing");
		return false;
	}

	$username = $_POST['username'];
	$password1 = $_POST['pass'];
	$password2 = $_POST['pass2'];
	
	if($password1 != $password2)
	{
		$errmsg = 'Your passwords did not match.';
		$UserLog->log("User registration failure: Passwords didn't match");
		return false;
	}

	// Verify decent length username
	$usernameLength = strlen($username);
	$lowerLimit = 3;
	$upperLimit = 40;
	if($usernameLength < $lowerLimit || $usernameLength > $upperLimit)
	{
		$errmsg = "Username '$username' is invalid. Username must be between $lowerLimit and $upperLimit characters long";
		$UserLog->log("User registration failure: Bad username $username");
		return false;
	}
	
	// Verify unique username
	$UserQuery = new UserQuery();
	$UserResults = $UserQuery->filterByUsername($username)->findOne();
	if($UserResults)
	{
		$errmsg = 'The username '.$username.' is already in use.';
		$UserLog->log("User registration failure: User with name $username already exists");
		return false;
	}
	
	$encryptedPassword = crypt($password1, '$1$0M5.Hg..$XPxNWyFzXv8BT.sEJ.CFf0');

	$User = new User();
	$User->setUsername($username);
	$User->setPassword($encryptedPassword);
	$User->save();
	
	$UserLog->log("New user created Username: $username");
	?>
		
	<p>Thank you, you have registered - you may now log in.</p>
	
	<?php
}

////////////////////////////////////////////////////////////////////////////////
/// FOOTER
$template = $twig->loadTemplate('footer.twig');
echo $template->render(array());

?>