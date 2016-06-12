<?php

namespace EduID;

use EduID\Curler;

use Lcobucci\JWT as JWT;
use Lcobucci\JWT\Signer as Signer;

class Client extends \RESTling\Logger {

    private $param;
    private $config;
    private $configFile;
    private $configDir;

    private $fedService;

    private $writeConfig = false;

    private $clientToken;
    private $userToken;

    private $credentials;
    private $client_id;

    public $curl;

    public function __construct() {
        $this->credentials = array(
            "issuer" => "ch.htwchur.eduid.cli",
            "kid"    => "1234test-12",
            "key"    => "helloWorld",
            "access" => "acf5acfaa58665e6e74f9d03e504b7dce7bc9568",
            "host"   => gethostname()
        );

        $this->param = getopt("c:f:Cx");

        if (array_key_exists("C", $this->param)) {
            $this->writeConfig = true;
        }

        $this->load_config();
    }

    private function load_config() {
        $cfg = ["/etc/eduid", $_SERVER["HOME"] . "/.eduid"];
        $cfgfile = "config.json";

        if  (array_key_exists("c", $this->param)) {
            array_push($cfg,$this->param["c"]);
        }

        $cj = null;
        $config = [];

        foreach($cfg as $cd) {
            if (file_exists("$cd/$cfgfile")) {
                $cn = "$cd/$cfgfile";
                $cfgdir = $cd;

                $cj = $this->read_config_file($cn);
                if ($cj) {
                    foreach ($cj as $k => $v) {
                        $config[$k] = $v;
                    }
                }
            }
        }

        if (empty($config)) {
            // init the config if we found nothing
            if ($this->writeConfig) {
                $cfgdir = array_pop($cfg);
                if (!file_exists($cfgdir)) {
                    mkdir($cfgdir, 0700);
                }

                $cn = "$cfgdir/$cfgfile";

                $config["client_id"] = $this->generate_uuid();

                $this->write_config_file($config, $cn);
            }
        }

        $this->config = $config;

        $this->client_id = $this->config["client_id"];

        $this->configDir  = $cfgdir;
        $this->configFile = $cn;
    }

    private function verify_federation_service() {
        if (!array_key_exists("federation_service", $this->config) &&
            !array_key_exists("f", $this->param)) {

            return false;
        }

        if (array_key_exists("federation_service", $this->config)) {
            $fs = $this->config["federation_service"];
        }

        if (array_key_exists("f", $this->param) && !empty($this->param["f"]) ) {
            $fs = $this->param["f"];


            if (!preg_match("/^https?:\/\//", $fs)) {
                if (array_key_exists("x", $this->param)) {
                    $fs = "http://$fs";
                }
                else {
                    $fs = "https://$fs";
                }
            }

            if (!preg_match("/\/eduid.php$/", $fs)) {
                 $fs .= "/eduid/eduid.php";
            }

            $this->log("fed service ". $fs);

            $c = new Curler($fs);
            $c->get();

            if ($c->getStatus() != 403) {
                $this->log($c->getStatus());
                return false;
            }
            if ($this->writeConfig) {
                $this->config["federation_service"] = $fs;
                $this->write_config_file($this->config);
            }
        }

        if ($this->config["federation_service"]) {
            $this->curl = new Curler($this->config["federation_service"]);
        }
        return true;
    }

    public function authorize() {
        if ($this->client_id) {
            // read client token
            $cliToken = $this->read_config_file($this->configDir . "/client.json");
            $usrToken = $this->read_config_file($this->configDir . "/user.json");

            if (!$cliToken) {
                if(!$this->verify_federation_service()) {
                    $this->log("federation service not verified.");
                    return false;
                }

                $cliToken = $this->register_client();
                if (!$cliToken) {
                    $this->log("no client token");
                    return false;
                    // die("Client refused by server");
                }
                $this->curl->setMacToken($cliToken);
            }

            $this->curl->setMacToken($cliToken);

            if (!$usrToken) {
                $usrToken = $this->auth_with_server();
                if (!$usrToken) {
                    $this->log("invalid client credentials");
                    return false;
                    // die("Invalid credentials");
                }
            }

            $this->curl->setMacToken($usrToken);

            // verify the user token.
            $this->curl->setPathInfo("user-profile");
            $this->curl->get();

            if ($this->curl->getStatus() != 200) {
                // try again
                $this->curl->setMacToken($cliToken);
                $this->curl->setPathInfo("token");
                $usrToken = $this->auth_with_server();
                if (!$usrToken) {
                    $this->log("user credentials rejected");
                    return false;
                    // die("Invalid credentials");
                }
            }

            // OK we are good.
            $this->curl->setMacToken($usrToken);
            return true;
        }

        $this->log("no client id");
        return false;
    }

    private function auth_with_server() {
        $username = readline("email");
        $password = readline("password");

        if (!empty($username) && !empty($password)) {
            $data = array("grant_type" => "password",
                          "username"   => $username,
                          "password"   => $password);

            $this->curl->post(json_encode($data), "application/json");

            if ($this->curl->getStatus() == 200) {
                $token = json_decode($this->curl->getBody(), true);
                $this->write_config_file($this->curl->getBody(),
                                         $this->configDir . "/user.json");

                return $token;
            }
        }
        return null;
    }

    private function register_client() {
        $this->curl->setPathInfo("token");

        $jwt = new JWT\Builder();

        $jwt->setIssuer($this->credentials["issuer"]);
        $jwt->setAudience($this->curl->getUrl());
        $jwt->setHeader("kid", $this->credentials["kid"]);
        $jwt->setSubject($this->client_id);
        $jwt->set("name", $this->credentials["host"]);

        $cn = 'Lcobucci\JWT\Signer\Hmac\Sha256';
        $signer = new $cn;
        $jwt->sign($signer,
                   $this->credentials["key"]);

        $t = $jwt->getToken();

        $this->curl->setHeader(array("Authorization"=> "Bearer $t"));
        $data = array("grant_type" => "client_credentials");
        $this->curl->post(json_encode($data), "application/json");

        if ($this->curl->getStatus() == 200) {
            // store the body in our config file
            $this->write_config_file($this->curl->getBody(),
                                     $this->configDir . "/client.json");

            return json_decode($this->curl->getBody(), true);
        }
        else {
            $this->log("error: " . $this->curl->getStatus());
        }
        return null;
    }

    private function read_config_file($fileName="") {
        if (!isset($fileName) || empty($fileName)) {
            $fileName = $this->configFile;
        }

        if (file_exists($fileName)) {
            $file = fopen($fileName,"r");
            $clToken = fread($file,filesize($fileName));
            fclose($file);

            return json_decode($clToken, true);
        }
        return null;
    }

    private function write_config_file($data, $fileName="") {
        if (!isset($fileName) || empty($fileName)) {
            $fileName = $this->configFile;
        }

        if ($this->writeConfig) {
            $cf = fopen($fileName, "w");
            if ($cf) {
                if (is_string($data)) {
                    fwrite($cf,$data);
                }
                else {
                    fwrite($cf,json_encode($data));
                }
                fclose($cf);
            }
        }
    }

    public function generate_uuid() {
        return sprintf( '%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
            mt_rand( 0, 0xffff ), mt_rand( 0, 0xffff ),
            mt_rand( 0, 0xffff ),
            mt_rand( 0, 0x0fff ) | 0x4000,
            mt_rand( 0, 0x3fff ) | 0x8000,
            mt_rand( 0, 0xffff ), mt_rand( 0, 0xffff ), mt_rand( 0, 0xffff )
        );
    }
}

?>