<?php

namespace EduID\Client;

use Lcobucci\JWT as JWT;
use Lcobucci\JWT\Signer as Signer;

use EduID\Client as ClientBase;
use EduID\Curler;

class ServiceRegistration extends ClientBase {

    private $serviceUrl = "";

    public function __construct() {
        $this->paramShort .= "u:";
        parent::__construct();

        if (!$this->authorize()) {
            $this->fatal("Client rejected");
        }

        if (array_key_exists("u", $this->param)) {
            $this->serviceUrl = $this->param["u"];
        }
    }

    public function verify_service($servicehome="") {
        $servicehome = trim($servicehome);

        if(empty($servicehome)) {
            $servicehome = $this->serviceUrl;
        }

        if (empty($servicehome)) {
            $this->log("no service to verify");
            return null;
        }

        if (!preg_match("/^https?:\/\//", $servicehome)) {
            $prefix = "https";
            if (array_key_exists("x", $this->param)) {
                $prefix = "http";
            }

            $servicehome = "$prefix://$servicehome";
        }

        $s = new Curler($servicehome);

        $s->setPathInfo("service.txt");
        $s->get();

        if ($s->getStatus() == 200) {
            $lines = explode("\n" , $s->getBody());

            foreach ($lines as $line) { // check for services
                $line = trim($line);
                if (!empty($line)) {
                    list($type, $path) = explode("; ", $line);

                    if ($type == "application/x-rsd+json") {
                        $s->setPath($path);
                        $s->setPathInfo();

                        // get the rsd
                        $s->get();

                        if ($s->getStatus() == 200) {
                            $rsd = json_decode($s->getBody(),true);
                            $servicename = $rsd["engineName"];
                            $basepath    = rtrim($rsd["homePageLink"], "/");
                            $enginepath  = trim($rsd["engineLink"], "/");
                            $tokenservice = "token";

                            if (array_key_exists("org.ietf.oauth2", $rsd["apis"])) {
                                $apilink = trim($rsd["apis"]["org.ietf.oauth2"]["apiLink"], "/");

                                $tokenendpoint = $basepath;
                                if (!empty($enginepath)) {
                                    $tokenendpoint .= "/$enginepath";
                                }
                                $tokenendpoint .= "/$apilink/$tokenservice";

                                // the serviceInfo is stored in the federation
                                return array("service_uuid" => $this->generateUuid(),
                                             "name" => $servicename,
                                             "mainurl" => $servicehome,
                                             "idurl" => $tokenendpoint,
                                             "rsdurl" => $s->getLastUri());
                            }
                            else {
                                $this->log("RSD request failed with " . $s->getStatus());
                            }

                            break; // end for loop.
                        }
                        else {
                            $this->log("cannot get rsd");
                        }
                    }
                }
            }
        }
        else {
            $this->fatal("I cannot determine service endpoints!");
        }
        return null;
    }

    public function register_service($serviceInfo) {
        if (!empty($serviceInfo)) {
            $this->curl->setPathInfo("service-discovery/federation");

            // verify service info
            if(
                $this->checkMandatoryFields($serviceInfo,
                                            array(
                                                "service_uuid",
                                                "name",
                                                "mainurl",
                                                "idurl",
                                                "rsdurl"
                                            ))
              ) {
                $this->curl->put(json_encode($serviceInfo), 'application/json');
                if ($this->curl->getStatus() == "200") {
                    // ok we have the registration token
                    // print out
                    echo $this->curl->getBody();
                    return true;
                }
                else {
                    $this->log("federation service refused service infomation: " .
                               $this->curl->getStatus());
                    $this->log($this->curl->getBody());
                }
            }
            else {
                $this->log("service information is incomplete " . json_encode($serviceInfo));
            }
        }

        return false;
    }
}

?>
