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
require_once('Service/class.Error.php');

if(array_key_exists("PATH_INFO", $_SERVER)) {

    error_log("found path_info " . $_SERVER["PATH_INFO"]);
    $pi = explode("/", $_SERVER["PATH_INFO"]);
    array_shift($pi);
    $serviceName = array_shift($pi);
    error_log("got service: " . $serviceName);
}

if (isset($serviceName) && !empty($serviceName)) {

    $serviceName = trim($serviceName);

    require_once('Service/class.' . $serviceName . '.php');

    switch($serviceName) {
        case "user-auth":
            $service = new UserAuthService();
            break;
        case "user-profile":
            $service = new UserProfileService();
            break;
        case "client-register":
            $service = new ClientRegisterService();
            break;
//        case "protocol-discovery":
//            $service = new ProtocolDiscoveryService();
//            break;
//        case "user-service":
//            $service = new UserServiceDiscoveryService();
//            break;
//        case "service-authorization":
//            $service = new ServiceAuthorizationService();
//            break;
//        case "federation-manager":
//            $service = new FederationManagerService();
//            break;

        case "authtest":
            $service = new AuthTestService();
            break;
        default:
            $service = new ErrorService();
            break;
    }
}

if (!isset($service)) {
    $service = new ErrorService();
}

$service->run();

?>
