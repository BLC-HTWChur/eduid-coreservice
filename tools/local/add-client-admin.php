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

$user = array_pop($argv);
$cli = array_pop($argv);

$cm = new ClientModel($db);
$um = new UserModel($db);

if ($cm->findClient($cli) && $um->findByMailAddress($user)) {
    if (!$cm->isClientAdmin($um->getUUID())) {
        $cm->addClientAdmin($um->getUUID());
    }
}
else {
    echo "no client $cli $user\n";
    exit(1);
}

?>