<?php

set_include_path("../../lib" . PATH_SEPARATOR .
                get_include_path());

include_once('eduid.auto.php');
require_once('MDB2.php');


use EduID\Model\Client as ClientModel;
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

$version = array_pop($argv);
$cli = array_pop($argv);

$cm = new ClientModel($db);

if ($cm->findClient($cli)) {
    $t = $cm->addClientVersion("1");
    echo "\n". json_encode($t) . "\n";
}
else {
    echo "no client $cli $version\n";
    exit(1);
}

?>