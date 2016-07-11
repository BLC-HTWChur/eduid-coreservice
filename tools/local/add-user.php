<?php

set_include_path("../../lib" . PATH_SEPARATOR .
                get_include_path());

// load RESTling
include_once('eduid.auto.php');
require_once('MDB2.php');

use EduID\Model\User as UserModel;

$aCfg = parse_ini_file('/etc/eduid/eduid.ini', true);

$dbCfg = $aCfg["database"];

$dsn = array("phptype"  => $dbCfg["driver"],
             "username" => $dbCfg["user"],
             "password" => $dbCfg["pass"],
             "database" => "eduid");

if (array_key_exists("server", $dbCfg)) {
    $dsn["hostspec"] = $dbCfg["server"];
}
if (array_key_exists("name", $dbCfg)) {
    $dsn["database"] = $dbCfg["name"];
}

$db =& \MDB2::factory($dsn);

$userinfo = [];

$userinfo["mailaddress"]     = readline("mail-address: ");
$userinfo["given_name"]      = readline("given name:   ");
$userinfo["family_name"]     = readline("family name:  ");
$userinfo["user_password"]   = readline("password:     ");

$n = array();
$n[] = $userinfo["given_name"];
$n[] = $userinfo["family_name"];
$userinfo["name"] = implode(" ", $n);

$um = new UserModel($db);
$um->addUser($userinfo);

$fuser                       = readline("admin: [y/N]  ");

if (strtolower($fuser) == "y") {
    $um->grantFederationUser();
}

?>
