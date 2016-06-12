<?php
set_include_path("../lib" . PATH_SEPARATOR .
                get_include_path());

// load RESTling
include_once('eduid.auto.php');

use EduID\Client\ServiceRegistration as Client;

$cli = new Client();

if ($cli->authorize()) {
    $cli->report("Client accepted");
    
    if ($si = $cli->verify_service()) {
        if($cli->register_service($si)) {
            $cli->report("Service registered");
        }
        else {
            $cli->fatal("Service rejected");
        }
    }
    else {
        $cli->fatal("Service invalid");
    }
}
else {
    $cli->fatal("Client rejected");
}

?>
