<?php
/* *********************************************************************** *
 * Edu-ID Caller
 *
 * This script will identify which service is requested and launches it.
 * *********************************************************************** */

// set the include path so we can find our classes
set_include_path("./lib" . PATH_SEPARATOR .
                get_include_path());

// include autoloader
include_once('eduid.auto.php');

use \EduID\Service\Error as ErrorService;

if(array_key_exists("PATH_INFO", $_SERVER)) {
    $pi = explode("/", $_SERVER["PATH_INFO"]);
    array_shift($pi);
    $serviceName = array_shift($pi);
}

$service;
if (!empty($serviceName)) {

    $serviceName = trim($serviceName);
    $ts = explode("-", $serviceName);
    $serviceName = "EduID\\Service\\";

    $serviceName .= implode("", array_map(function($v) {return ucfirst(strtolower($v));}, $ts));

    $service = new $serviceName();
}

if (!isset($service)) {
    (new ErrorService(400 , "no service set"))->run();
}
else {
    $service->run();
}

?>
