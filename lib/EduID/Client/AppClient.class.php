<?php
namespace EduID\Client;
use EduID\Client as ClientBase;

class AppClient extends ClientBase {

    private $clientlist = [];

    private $info = array();

    public function __construct() {
        $this->paramShort .= "i:e:I:r:l";
        parent::__construct();
        // -I client info
        // -i client id (ch.edu.xxx)
        // -r release version

        if (!$this->authorize()) {
            $this->log(json_encode($this->user));
            $this->fatal("Client rejected");
        }
        $this->log(json_encode($this->user));
    }
    public function chooseFunction() {
        $this->curl->setPathInfo("client");
        if (array_key_exists("l", $this->param)) {
            $this->reportClientList();
        }
        else if ($this->param["i"] && $this->param["e"]) {
            $this->addClient();
        }
        else if ($this->param["i"] && $this->param["r"]) {
            $this->addClientVersion();
        }
    }

    private function getClientList() {
        $this->log("get client list");
        $this->curl->setPathInfo("client");
        $this->curl->get();

        if ($this->curl->getStatus() == 200) {
            $this->clientlist = json_decode($this->curl->getBody(), true);
        }
        else {
            $this->log($this->curl->getStatus());
        }
        return $this->clientlist;
    }

    private function reportClientList() {
        foreach ($this->getClientList() as $cli) {
            echo $cli["client_uuid"] . " " . $cli["client_id"]. "\n";
        }
    }

    public function addClient() {
        $this->log("get client list add client");
        $this->getClientList();
        $cliid = is_array($this->param["i"]) ? $this->param["i"][0] : $this->param["i"];

        $cl = array();
        if ($this->clientlist) {

            $this->curl->setPathInfo("client");

            // if app id already exists, then add emails as cient admins
            $cl = $this->filterValidObjects($this->clientlist,
                                            array("client_id" => $cliid));

        }

        if (empty($cl)) {
            $this->log("create new client");
            $this->processInfo();
            $data = array(
                "client_id" => $cliid,
                "info"=> $this->info
            );

            $this->curl->put(json_encode($data), "application/json");

            if ($this->curl->getStatus() == 200 ||
                $this->curl->getStatus() == 204) {

                $this->log("client created");
            }
            else {
                $this->fatal("client was not created");
            }
        }

        $this->log("update client admins");
        $this->curl->setPathInfo("client/user/$cliid");

        if (is_array($this->param["e"])) {
            foreach ($this->param["e"] as $e) {
                $data = array("user_mail" => $e);
                $this->curl->put(json_encode($data), "application/json");
                if ($this->curl->getStatus() == 200 || $this->curl->getStatus() == 204) {
                    $this->log("user was granted client admin privileges");
                }
                else {
                    $this->log("user was not accepted " .$this->param["e"]);
                }
            }
        }
        else {
            $data = array("user_mail" => $this->param["e"]);
            $this->curl->put(json_encode($data), "application/json");
            if ($this->curl->getStatus() == 200 || $this->curl->getStatus() == 204) {
                $this->log("user was granted client admin privileges");
            }
            else {
                $this->log("user was not accepted " .$this->param["e"]);
            }
        }
    }

    private function addClientVersion() {
        $this->getClientList();
        if ($this->clientlist) {

            $cliid = is_array($this->param["i"]) ? $this->param["i"][0] : $this->param["i"];

            $this->curl->setPathInfo("client/$cliid");

            // if app id already exists, then add emails as cient admins
            $cl = $this->filterValidObjects($this->clientlist,
                                            array("client_id" => $cliid));

            if (!empty($cl)) {
                $data = array("version_id" => $this->param["r"]);
                $this->curl->post(json_encode($data), "application/json");
                if ($this->curl->getStatus() == 200 || $this->curl->getStatus() == 204) {
                    $this->log("client version has been created");
                    echo $this->curl->getBody() . "\n";
                }
                else {
                    $this->fatal("client version has not been created ". $this->curl->getStatus());
                }
            }
            else {
                $this->fatal("client not found");
            }
        }
        else {
            $this->fatal("client not found");
        }
    }

    private function processInfo() {
        if (array_key_exists("I", $this->param)) {
            if (is_array($this->param["I"])) {
                foreach ($this->param["I"] as $i) {
                    $this->handlInfo($i);
                }
            }
            else {
                $this->handleInfo($this->param["I"]);
            }
        }
    }

    private function handleInfo($info) {
        $aI = explode(":", $info);

        if (count($aI) == 1) {
            $this->info["name"] = $aI[0];
        }
        else {
            $this->info[$aI[0]] = $aI[1];
        }
    }
}
?>
