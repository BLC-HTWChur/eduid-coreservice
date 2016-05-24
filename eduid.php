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
include_once('RESTling/contrib/Restling.auto.php');

// preload the service foundation, so the services don't have to.
require_once('class.ServiceFoundation.php');

// preload the error service
require_once('Service/class.ErrorService.php');

if(array_key_exists("PATH_INFO", $_SERVER)) {

    $pi = explode("/", $_SERVER["PATH_INFO"]);
    array_shift($pi);
    $serviceName = array_shift($pi);
}

if (isset($serviceName) && !empty($serviceName)) {

    $serviceName = trim($serviceName);
    $ts = explode("-", $serviceName);
    $serviceName = "";

    foreach ($ts as $v) {
        $serviceName .= ucfirst($v);
    }

    $serviceName .= "Service";

    try {
        require_once('Service/class.' . $serviceName . '.php');

        $service = new $servicename();
    }
    catch (\Exception $e) {
        $service = new ErrorService();
    }
}

if (!isset($service)) {
    $service = new ErrorService();
}

$service->run();

?>
