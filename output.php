<?php
require_once "C:/pear/pear/Log.php";
date_default_timezone_set('Australia/Canberra');

$UserLog   = Log::singleton('file', 'logs/user.log', 'USER');
$DBLog     = Log::singleton('file', 'logs/db.log', 'DB');
$DeckLog   = Log::singleton('file', 'logs/deck.log', 'DECK');
$SearchLog = Log::singleton('file', 'logs/search.log', 'SEARCH');
$CardLog   = Log::singleton('file', 'logs/card.log', 'CARD');

?>