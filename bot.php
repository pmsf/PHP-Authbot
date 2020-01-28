<?php

require __DIR__.'/vendor/autoload.php';

$logo = "\033[95m _____  __  __  _____ ______                 _   _       _           _   \n|  __ \|  \/  |/ ____|  ____|     /\        | | | |     | |         | |  \n| |__) | \  / | (___ | |__       /  \  _   _| |_| |__   | |__   ___ | |_ \n|  ___/| |\/| |\___ \|  __|     / /\ \| | | | __| '_ \  | '_ \ / _ \| __|\n| |    | |  | |____) | |       / ____ \ |_| | |_| | | | | |_) | (_) | |_ \n|_|    |_|  |_|_____/|_|      /_/    \_\__,_|\__|_| |_| |_.__/ \___/ \__|\n\n\n\n";
if (! file_exists('config.php')) {
    echo $logo;
    echo "Config file does not exist create first 'cp example.config.php config.php'" . PHP_EOL;
    die();
}
require 'config.php';

use Medoo\Medoo;
use Monolog\Logger;
use Monolog\Handler\StreamHandler;
use Monolog\Handler\FirePHPHandler;
use RestCord\DiscordClient;

//PMSF variables
$columns = ['id', 'user', 'password', 'temp_password', 'expire_timestamp', 'session_id', 'login_system', 'access_level'];
//END PMSF variables


switch (LOGLEVEL) {
case "NOTICE":
    $loglevel = Logger::NOTICE;
    break;
case "INFO":
    $loglevel = Logger::INFO;
    break;
case "DEBUG":
    $loglevel = Logger::DEBUG;
    break;
default:
    $loglevel = Logger::INFO;
}

$logger = new Logger('PMSFLogger');
$logger->pushHandler(new StreamHandler(__DIR__.'/log/pmsf_auth.log', $loglevel));
$logger->pushHandler(new FirePHPHandler());


$discord = new DiscordClient(['token' => BOTTOKEN, 'logger' => $logger]); // Token is required

echo $logo;
echo "\033[34mStarting mandatory checks" . PHP_EOL;

try {
    $db = new Medoo(
        [
        'database_type' => DBTYPE,
        'database_name' => DBNAME,
        'server' => DBHOST,
        'username' => DBUSER,
        'password' => DBPW,
        'charset' => CHARSET,
                'option' => array(
        \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION
        )
        ]
    );
} catch (Exception $e) {
    $error = 1;
    echo "\033[31mDatabase connection failed with error:" . PHP_EOL . "" . $e . "" . PHP_EOL;
    die();
}

echo PHP_EOL;
echo "\033[32mConnection to " . DBTYPE . " succesfull." . PHP_EOL;

echo PHP_EOL;
echo "\033[34mDatabase info" . PHP_EOL;
$info = $db->info();
echo "\033[32mDriver:     " . $info['driver'] . PHP_EOL;
echo "\033[32mClient:     " . $info['client'] . PHP_EOL;
echo "\033[32mVersion:    " . $info['version'] . PHP_EOL;
echo "\033[32mConnection: " . $info['connection'] . PHP_EOL;
echo "\033[32mDSN:        " . $info['dsn'] . PHP_EOL;
echo PHP_EOL;
echo "\033[34mChecking tables" . PHP_EOL;
$dbcolumns = $db->query('SHOW COLUMNS FROM users')->fetchAll();
$columncheck = [];
foreach ( $dbcolumns as $key => $c ) {
    $columcheck = array_push($columncheck, $c['Field']);
    echo "\033[32m " . $c['Field'] . PHP_EOL;
}
echo PHP_EOL;
$results = array_diff($columns, $columncheck);
if (empty($results) ) {
    echo "\033[32mColumn check PASSED" . PHP_EOL;
} else {
    echo "\033[31mColumn check FAILED" . PHP_EOL;
    foreach ( $results as $key => $result ) {
        echo "\033[31mCheck column: " . $result . PHP_EOL;
    }
    die();
}
$memberslist = [];
echo PHP_EOL;
echo "\033[34mInitial check guilds and roles on startup". PHP_EOL;
// Build a full memberlist from across all discord servers
foreach ( $guilds['guildIDS'] as $guild => $roles) {
    if (is_numeric($guild)) {
        $discord_name = $discord->guild->getGuild(['guild.id' => $guild]);
        echo "\033[32m" . $guild . " is a valid guildID. GuildName = " . $discord_name->name . "" . PHP_EOL;
        foreach ( $roles as $role => $access_level ) {
            if (is_numeric($role)) {
                $discord_role_name = $discord->guild->getGuildRoles(['guild.id' => $guild]);
                $result = array_search($role, array_column($discord_role_name, 'id'));
                if ($result === false ) {
                    echo "\033[32mRole:" . $role . " is not found on " . $discord_name->name . PHP_EOL;
                    die();
                } else {
                    echo "\033[32mRole: " . $discord_role_name[$result]->name . " is set to access level: " . $access_level . " " . PHP_EOL;
                }
            }
        }
    } else {
        echo "\033[31m" . $guild . " is not a valid guildID" . PHP_EOL;
        die(); 
    }
    $members = $discord->guild->listGuildMembers(['guild.id' => $guild, 'limit' => 1000]);
    if (count($members) == "1000" ) {
        do {
            $last = end($members);
            $moremembers = $discord->guild->listGuildMembers(['guild.id' => $guild, 'limit' => 1000, 'after' => $last->user->id]);
            $members = array_merge($members, $moremembers);
        } while ( count($members) % 1000 == 0 );
    }
    // Flatten memberlist
    foreach ( $members as $member) {
        // Only process members with roles
        if ( in_array($guild, array_flip($roles)) ) {
            array_push($member->roles, $role);
        }
        if (!empty($member->roles) ) {
            $accesslevels = array_intersect_key($roles, array_flip(array_filter($member->roles)));
            // Filter out all members with roles without access levels
            if (!empty($accesslevels) ) {
                $exists = array_search($member->user->id, array_column($memberslist, 'id'));
                if ($exists && ($memberslist[$exists]["highest_access"] <  max($accesslevels))) {
                    $memberslist[$exists]["highest_access"] = max($accesslevels);
                } else if (!$exists ) {
                    $user["id"] = $member->user->id;
                    $user["username"] = $member->user->username;
                    $user["discriminator"] = $member->user->discriminator;
                    $user["highest_access"] = max($accesslevels);
                    $memberslist[] = $user;

                }
            }
        }
    }
    echo "\033[33mTotal member count for " . $discord_name->name . ": " . count($members) . PHP_EOL;
    echo PHP_EOL;
}
echo "\033[34mChecking access rules and updating if needed" . PHP_EOL;
$noupdate = false;
foreach ( $memberslist as $member) {
    $memberindb = $db->get("users", ["id", "access_level"], ["id" => $member["id"]]);
    if (empty($memberindb) ) {
        $newmembers[] = [
            "id" => $member["id"],
            "user" => $member["username"] . "#" . $member["discriminator"],
            "login_system" => "discord",
            "access_level" => $member["highest_access"]
            ];
        echo "\033[92mNew member " . $member["username"] . " added with access level " . $member["highest_access"] . PHP_EOL;
    } else if (intval($memberindb['access_level']) !== $member["highest_access"]) {
        $db->update(
            "users", [
            "access_level" => $member["highest_access"]
            ], [
            "id" => $member["id"]
            ]
        );
        echo "\033[92mMember " . $member["username"] . " updated with new access level " . $member["highest_access"] . PHP_EOL;
    } else {
        $noupdate = true;
    }
}
if (!empty($newmembers)) {
    $db->insert("users", $newmembers);
}
if ( $noupdate ) {
    echo "\033[92mNo member updates" . PHP_EOL;
}
echo PHP_EOL;
echo PHP_EOL;

echo "\033[32mPre startup checks finished succesfull Starting BOT" . PHP_EOL;

$getaudit = 'audit-log';
$audit = $discord->$getaudit->getGuildAuditLog(['guild.id' => $guild]);
//print_r($audit);
// Return to default color
echo "\033[39m" . PHP_EOL;
