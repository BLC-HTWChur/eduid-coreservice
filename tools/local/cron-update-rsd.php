<?php

set_include_path("../../lib" . PATH_SEPARATOR .
                get_include_path());

include_once('eduid.auto.php');
require_once('MDB2.php');

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

use EduID\Model\Service as ServiceModel;
use EduID\Curler as Curler;

$sm = new ServiceModel($db);

$curl  = new Curler();
$curl->setDebugMode(false);

$services = $sm->fetchAllRsdServices();
if (empty($services)) {
    error_log("no services found");
}
else {
    foreach ($services as $sd) {
        $now = time();

        if ($sm->findServiceById($sd["service_uuid"])) {
            $lastupdate = $sm->lastRSDUpdate();

            if (($now - $lastupdate) > 86500) {

                error_log("$now: update service " . $sd["service_uuid"] . "($lastupdate)");

                $curl->setUrl($sd["rsdurl"]);

                $t = json_decode($sd["token"], true);
                $t["client_id"] = "https://eduid.htwchur.ch"; // this MUST be configurable

                $curl->setToken($t);
                $curl->useJwtToken();

                $curl->get(); // get the RSD (using our JWT)

                if ($curl->getStatus() == 200) {
                    $sm->updateServiceRSD($curl->getBody());
                }
                else {
                    $now = time();
                    error_log("$now: service " .
                              $sd["service_uuid"] .
                              "returned with error: " .
                              $curl->getStatus() .
                              ": " . $curl->getBody());
                }
            }
        }
        else {
            error_log("$now: service not found");
        }
    }
}
?>
