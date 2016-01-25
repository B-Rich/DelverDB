<?php

include "output.php";

session_start();
if(array_key_exists('login', $_SESSION) && $_SESSION['login'] != '')
{
    $UserLog->log("User ".$_SESSION['userid']." is logging off");
    session_destroy();
}
else
{
    $UserLog->warn("Error, attempted to log off a non-existent session");
}
header("Location: index.php");

?>