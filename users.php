<?php

include_once "output.php";
include_once "defines.php";

session_cache_expire(30);

session_start();
session_regenerate_id();

$IsLoggedIn = IsLoggedIn();
$LoginErrorMessage = null;
if(!$IsLoggedIn)
{
	$IsLoggedIn = AttemptLogin();
}

function IsLoggedIn()
{
	return array_key_exists('login', $_SESSION) && $_SESSION['login'] == true;
}

function LoginInformationSent()
{
	return $_SERVER['REQUEST_METHOD'] == 'POST'
		&& array_key_exists('username', $_POST)
		&& array_key_exists('password', $_POST);
}

function AttemptLogin()
{
	global $LoginErrorMessage, $UserLog, $DBLog;
	
	if(!LoginInformationSent())
		return false;
	
	$UserLog->log("Starting login");
	
	$username = $_POST['username'];
	$password = $_POST['password'];
	
	$username = htmlspecialchars($username);
	$password = htmlspecialchars($password);
	
	$password = crypt($password, '$1$0M5.Hg..$XPxNWyFzXv8BT.sEJ.CFf0');
	
	require_once "C:\pear\pear\propel\Propel.php";
	Propel::init("\propel\build\conf\magic_db-conf.php");
	set_include_path("propel/build/classes/" . PATH_SEPARATOR . get_include_path());
	
	$UserQuery = new UserQuery();
	$UserResult = $UserQuery->filterByUsername($username)
		->filterByPassword($password)
		->findOne();
	
	if($UserResult)
	{
		$_SESSION['login'] = true;
		$_SESSION['userid'] = $UserResult->GetId();
		$_SESSION['username'] = $username;
		$_SESSION['password'] = $password;
		$UserLog->log("Successful login for $username");
		return true;
	}
	else
	{
		$UserLog->warning("Login failure for $username: Invalid username or password");
		$LoginErrorMessage =  "Invalid username or password";
		return false;
	}
	return false;
}
?>