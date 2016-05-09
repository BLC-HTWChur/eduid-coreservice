<?php
/* *********************************************************************** *
 * Edu-ID Caller
 *
 * This script will identify which service is requested and launches it.
 * *********************************************************************** */

// set the include path so we can find our classes
set_include_path($cwd ."/lib" . PATH_SEPARATOR .
                get_include_path());

// load RESTling
include_once('RESTling/contrib/Restling.auto.php');

// preload the service foundation, so the services don't have to.
require_once('class.ServiceFoundation.php');

// preload the error service
require_once('Service/class.Error.php');

$serviceName = array_shift(explode("/", $_SERVER["PATH_INFO"]));

if (isset($serviceName) && !empty($serviceName)) {

    $serviceName = trim($serviceName);

    if ($serviceName == "oauth") {
        $serviceName .= "2";
    }

    require_once('Service/class.' . $serviceName . '.php');

    switch($serviceName) {
        case "oauth2":
            $service = new OAuth2Service();
            break;
        case "user-profile":
            $service = new UserProfileService();
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
