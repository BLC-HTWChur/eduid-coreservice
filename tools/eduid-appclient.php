<?php

set_include_path("../lib" . PATH_SEPARATOR .
                get_include_path());

// load RESTling
include_once('eduid.auto.php');

use EduID\Client\AppClient as Client;

$cli = new Client();

$cli->chooseFunction();

?>