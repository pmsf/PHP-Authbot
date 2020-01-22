<?php
//Database
define('DBTYPE', 'mysql');
define('DBUSER', 'username');
define('DBPW', 'password');
define('DBHOST', 'localhost');
define('DBNAME', 'pmsf_manual');
define('CHARSET', 'utf8mb4');
//Discord Bot
define('BOTTOKEN', 'discordbottoken');
define('LOGLEVEL', 'INFO'); // DO NOT CHANGE UNLESS ASKED BY A DEVELOPER
//Guilds ***Replace values within <>*** *** Match 1,2,3,4 values with access levels in PMSF
$guilds = [
	'guildIDS' => [
		'<guildid>' => [
			'<roleid>' => 1,
			'<roleid>' => 2,
			'<roleid>' => 3,
			'<roleid>' => 4
		],
		'<guildid>' => [
			'<roleid>' => 1,
			'<roleid>' => 2,
			'<roleid>' => 3,
			'<roleid>' => 4
		],
		'<guildid>' => [
			'<roleid>' => 1,
			'<roleid>' => 2,
			'<roleid>' => 3
		]
	]
];
?>
