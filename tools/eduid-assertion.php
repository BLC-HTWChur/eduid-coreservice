<?php
set_include_path("../lib" . PATH_SEPARATOR .
                get_include_path());

// load RESTling
include_once('eduid.auto.php');

use EduID\Client\AppAssertion as Client;

$cli = new Client();

if ($cli->authorize()) {
    $cli->report("Client accepted");
    $cli->getAssertion();
}
else {
    $cli->fatal("Client rejected");
}


?>
