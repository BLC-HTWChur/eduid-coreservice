<?php
/* *********************************************************************** *
 * Edu-ID Caller
 *
 * This script will identify which service is requested and launches it.
 * *********************************************************************** */

// set the include path so we can find our classes
set_include_path("./lib" . PATH_SEPARATOR .
                get_include_path());

// load RESTling

include_once('eduid.auto.php');

if(array_key_exists("PATH_INFO", $_SERVER)) {
    $pi = explode("/", $_SERVER["PATH_INFO"]);
    array_shift($pi);
    $serviceName = array_shift($pi);
}

if (isset($serviceName) && !empty($serviceName)) {

    $serviceName = trim($serviceName);
    $ts = explode("-", $serviceName);
    $serviceName = "EduID\\Service\\";

    foreach ($ts as $v) {
        $serviceName .= ucfirst(strtolower($v));
    }

    try {
        $service = new $serviceName();
    }
    catch (\Exception $e) {
        $service = new EduID\Service\Error($e->getMessage());
    }
}

if (!isset($service)) {
    $service = new EduID\Service\Error("no service set");
}

$service->run();

?>
