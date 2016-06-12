<?php

set_include_path("../lib" . PATH_SEPARATOR .
                get_include_path());

include_once('eduid.auto.php');

use EduID\Client as Client;
/**
 * -f <url>  - federation Server url
 * -c <path> - config home
 * -C - create config dir
 * -x - unprotected
 */

$cli = new Client();

if ($cli->authorize()) {
    $cli->report("Client accepted");
}
else {
    $cli->fatal("Client rejected");
}

?>
