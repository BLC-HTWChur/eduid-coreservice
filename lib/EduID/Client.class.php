<?php

namespace EduID;

use EduID\ModelFoundation;

use EduID\Curler;

class Client extends ModelFoundation {

    protected $paramShort = "c:f:Cxvt";
    protected $paramLong = "";

    protected $param;

    protected $config;
    private $configFile;
    private $configDir;

    private $fedService;

    private $writeConfig = false;

    private $clientToken;
    protected $userToken;

    private $credentials;
    protected $client_id;

    protected $user;

    public $curl;

    public function __construct() {

        $this->setDebugMode(false);

        // use the actual token reported from the service
        $this->credentials = array(
            "client_id" => "ch.htwchur.eduid.cli",
            "kid"    => "1234test-12",
            "mac_algorithm"    => "HS256",
            "mac_key"    => "helloWorld",
            "access_key" => "acf5acfaa58665e6e74f9d03e504b7dce7bc9568",
            "token_type" => "Bearer",
            "host"   => gethostname()
        );

        $this->param = getopt($this->paramShort);

        if (array_key_exists("v", $this->param)) {
            $this->setDebugMode(true);
        }

        if (array_key_exists("C", $this->param)) {
            $this->writeConfig = true;
        }

        if (array_key_exists("t", $this->param)) {
            $this->remove_config_file("user.json");
        }
        $this->load_config();
    }

    public function getParam() {
        return $this->param;
    }

    private function load_config() {
        $this->mark();
        $cfg = ["/etc/eduid", $_SERVER["HOME"] . "/.eduid"];
        $cfgfile = "config.json";

        if  (array_key_exists("c", $this->param)) {
            $d = rtrim ($this->param["c"], "/");
            array_push($cfg,$d);
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

                $config["client_id"] = $this->generateUuid();

                $this->write_config_file($config, $cn);
            }
        }

        $this->config = $config;

        $this->client_id = $this->config["client_id"];

        $this->configDir  = $cfgdir;
        $this->configFile = $cn;
    }

    private function verify_federation_service() {
        $this->mark();
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
                $prefix = "https";
                if (array_key_exists("x", $this->param)) {
                    $prefix = "http";
                }
                $fs = "$prefix://$fs";
            }

            if (!preg_match("/\/eduid.php$/", $fs)) {
                 $fs .= "/eduid/eduid.php";
            }

            $this->log("fed service ". $fs);

            $c = new Curler($fs);

            if (array_key_exists("k", $this->param)) {
                $c->ignoreSSLCertificate();
            }

            $c->setDebugMode($this->getDebugMode());
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
            $this->log("init curler using " . $this->config["federation_service"]);
            $this->curl = new Curler($this->config["federation_service"]);
            $this->curl->setDebugMode($this->getDebugMode());
        }
        return true;
    }

    public function authorize() {
        $this->mark();
        if ($this->client_id) {
            // read client token
            $cliToken = $this->read_config_file($this->configDir . "/client.json");
            $usrToken = $this->read_config_file($this->configDir . "/user.json");

            if(!$this->verify_federation_service()) {
                $this->log("federation service not verified.");
                return false;
            }

            if (!$cliToken) {
                $cliToken = $this->register_client();
                if (!$cliToken) {
                    $this->log("no client token");
                    return false;
                    // die("Client refused by server");
                }
                $this->curl->useJwtToken();
                $this->curl->setToken($cliToken);
            }

            $this->curl->useMacToken();
            $this->curl->setToken($cliToken);

            if (!$usrToken) {
                $usrToken = $this->auth_with_server();
                if (!$usrToken) {
                    $this->log("invalid client credentials");
                    return false;
                    // die("Invalid credentials");
                }
            }
            else {
                $this->log("user token present");
            }

            $this->curl->setToken($usrToken);

            // verify the user token.
            $this->curl->setPathInfo("user-profile");
            $this->curl->get();

            if ($this->curl->getStatus() != 200) {
                // try again
                $this->log("user token rejected? " . $this->curl->getStatus());
                $this->log("user token rejected? " . $this->curl->getLastURI());

                $this->curl->setToken($cliToken);
                $this->curl->setPathInfo("token");
                $usrToken = $this->auth_with_server();
                if (!$usrToken) {
                    $this->log("user credentials rejected");
                    return false;
                    // die("Invalid credentials");
                }
            }
            else {
                $this->log($this->curl->getBody());
                $this->user = json_decode($this->curl->getBody(), true);
            }

            // OK we are good.
            $this->curl->setToken($usrToken);
            $this->userToken = $usrToken;
            return true;
        }

        $this->log("no client id");
        return false;
    }

    private function auth_with_server() {
        $this->mark();
        $this->curl->setPathInfo("token");

        $username = readline("email:     ");
        $password = readline("password:  ");

        if (!empty($username) && !empty($password)) {
            $data = array("grant_type" => "password",
                          "username"   => $username,
                          "password"   => $password);

            $this->curl->post(json_encode($data), "application/json");

            if ($this->curl->getStatus() == 200) {
                $this->log($this->curl->getBody());
                $token = json_decode($this->curl->getBody(), true);
                $this->write_config_file($this->curl->getBody(),
                                         $this->configDir . "/user.json",
                                         true); // always store the user token

                return $token;
            }
            else {
                $this->log($this->curl->getStatus());
            }
        }
        return null;
    }

    private function register_client() {
        $this->mark();
        $this->curl->setPathInfo("token");
        // client_credentials expects a JWT
        $this->curl->setToken($this->credentials);
        $this->curl->useJwtToken(array(
            "subject" => $this->client_id,
            "name"    => $this->credentials["host"]
        ));

        $data = array("grant_type" => "client_credentials");
        $this->curl->post(json_encode($data), "application/json");

        if ($this->curl->getStatus() == 200) {
            // store the body in our config file
            $this->write_config_file($this->curl->getBody(),
                                     $this->configDir . "/client.json",
                                     true); // always store the client token

            $this->log($this->curl->getBody());
            return json_decode($this->curl->getBody(), true);

        }
        else {
            $this->log("error: " . $this->curl->getStatus());
        }
        return null;
    }

    private function read_config_file($fileName="") {
        if (empty($fileName)) {
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

    private function write_config_file($data, $fileName="", $force=false) {
        if (empty($fileName)) {
            $fileName = $this->configFile;
        }

        if ($this->writeConfig || $force) {
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

    private function remove_config_file($filename) {
        $cfg = ["/etc/eduid", $_SERVER["HOME"] . "/.eduid"];

        if  (array_key_exists("c", $this->param)) {
            $d = rtrim ($this->param["c"], "/");
            array_unshift($cfg,$d);
        }

        foreach($cfg as $d) {
            if (file_exists($d . "/" .$filename)) {
                $this->log("remove $d/$filename");
                unlink($d . "/" . $filename);
                break;
            }
        }
    }

    public function report($msg) {
        $this->log($msg);
    }

    public function fatal($msg) {
        parent::fatal($msg);
        exit(1);
    }
}

?>
