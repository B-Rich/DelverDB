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
    global $LoginErrorMessage, $UserLog, $DBLog, $SQLUsers;
    
    if(!LoginInformationSent())
        return false;
    
    $UserLog->log("Starting login");
    
    $username = $_POST['username'];
    $password = $_POST['password'];
    
    $username = htmlspecialchars($username);
    $password = htmlspecialchars($password);
    
    $password = crypt($password, '$1$0M5.Hg..$XPxNWyFzXv8BT.sEJ.CFf0');
    
    $SQLUser = $SQLUsers['user_handler'];
    $DelverDBLink = new mysqli( "localhost", $SQLUser->username, $SQLUser->password, "delverdb" );
    if ( $DelverDBLink->connect_errno )
    {
        $errno = $DelverDBLink->connect_errno;
        $error = $DelverDBLink->connect_error;
        $DBLog->err( "Connection error (".$errno.") ".$error );
        die( "Connection error ($errno)" );
    }
    
    $userStmt = $DelverDBLink->prepare(
            "SELECT * FROM users WHERE username = ? AND password = ?") or die( $DelverDBLink->error );
    print_r( $password );
    $userStmt->bind_param( "ss", $username, $password ) or die( $DelverDBLink->error );
    $userStmt->execute() or die( $DelverDBLink->error );
    $userResult = $userStmt->get_result();
    
    if ( $row = $userResult->fetch_assoc() )
    {
        $_SESSION['login'] = true;
        $_SESSION['userid'] = $row['id'];
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