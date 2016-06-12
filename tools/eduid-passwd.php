<?php
set_include_path("../lib" . PATH_SEPARATOR .
                get_include_path());

// load RESTling
include_once('eduid.auto.php');

use EduID\Client\ChangePassword as Client;

$cli = new Client();

if ($cli->authorize()) {
    $cli->report("Client accepted");
    
    if($cli->askPassword()) {
        $cli->report("New Password ok");
        if ($cli->updatePassword()) {
            $cli->report("Password updated");
        }
        else {
            $cli->fatal("Password not updated");
        }
    }
    else {
        $cli->report("User Input Failed");
    }
}
else {
    $cli->fatal("Client rejected");
}

?>