<?php

set_include_path("../../lib" . PATH_SEPARATOR .
                get_include_path());

include_once('eduid.auto.php');
require_once('MDB2.php');


use EduID\Model\Client as ClientModel;
use EduID\Model\User as UserModel;

$aCfg = parse_ini_file('../../config/eduid.ini', true);

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

$userinfo = ["info" => array()];

$userinfo["client_id"]         = readline("id: ");
$userinfo["info"]["os"] = readline("OS: ");

$cm = new ClientModel($db);
if (!$cm->addClient($userinfo)) {
    echo "ERROR CLIENT NOT ADDED";
    exit(1);
}

$um = new UserModel($db);

while ($u = readline("admin e-mail: ")) {
    if (empty($u) || !$um->findByMailAddress($u)) {
        break;
    }
    $cm->addClientAdmin($um->getUUID());
}



$fuser                  = readline("version: [Y/n] ");

if (empty($fuser) || strtolower($fuser) == "y") {
    $t = $cm->addClientVersion("1");
    echo json_encode($t) . "\n";
}

?>
