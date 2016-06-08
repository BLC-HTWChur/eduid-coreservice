<?php
/* *********************************************************************** *
 * Error Service
 *
 * The Error Service is a dummy service to be called whenever an invalid
 * service is requested by a client.
 *
 * The Error Service will not record misbehaving clients, but it may do so
 * in a later version.
 * *********************************************************************** */

namespace EduID\Service;

class ErrorService extends \RESTling\Logger {
    public function __construct($msg) {
        $this->mark("Start Error Service");
        $this->log("ErrorService::__construct - FATAL ERROR: Error Service launched");

        $this->log('internal message: ' . $msg);
    }

    public function run() {
        if (function_exists('http_response_code'))
        {
            http_response_code(403);
        }
        else {
            header('HTTP/1.1 403 Forbidden');
        }
        $this->mark("END Error Service");
    }
}

?>