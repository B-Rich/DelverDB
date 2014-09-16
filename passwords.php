<?php

class SQLUser
{
	public $username;
	public $passsword;

	public function SQLUser($uname, $pword)
	{
		$this->username = $uname;
		$this->password = $pword;
	}
};

$SQLUsers = array (
	'oracle_search' => new SQLUser('ddb_search',    'K4no4EdOFE8puBXvIdRPG0'),
	'ddb_usercards' => new SQLUser('ddb_usercards', 'Nu.ikl0LiUpFZUN2cOX850'),
	'deckmaker' =>     new SQLUser('ddb_deckmaker', 'BIuHl4fTfBeuSzXQIu8L20'),
	'user_handler' =>  new SQLUser('ddb_usermaker', 'CMm7nulWWGXM8NY5AAr1e'),
);
?>