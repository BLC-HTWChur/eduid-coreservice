<?php
set_include_path("../lib" . PATH_SEPARATOR .
                get_include_path());

// load RESTling
include_once('eduid.auto.php');

use EduID\Client\UserRegistration as Client;

$cli = new Client();

if ($si = $cli->verify_user()) {
    if($cli->register_user($si)) {
        $cli->report("Service registered");
    }
    else {
        $cli->fatal("Service rejected");
    }
}
else {
    $cli->fatal("Service invalid");
    }
?>