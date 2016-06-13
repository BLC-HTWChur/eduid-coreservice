<?php

namespace EduID\Client;
use EduID\Client as ClientBase;

class ChangePassword extends ClientBase {
    private $opwd;
    private $npwd;

    public function __construct() {
        $this->paramShort .= "g:r:";
        parent::__construct();

        if (!$this->authorize()) {
            $this->fatal("Client rejected");
        }
    }

    public function askPassword() {
        $this->opwd = readline("current password: ");
        $this->npwd = readline("new password: ");

        if (!empty($this->opwd) &&
            !empty($this->npwd) &&
            strtolower($this->opwd) != strtolower($this->npwd)) {
            return true;
        }
        return false;
    }

    public function updatePassword() {
        $ud = array(
            "oldpassword" => $this->opwd,
            "newpassword" => $this->npwd
        );

        $this->curl->setPathInfo("user-profile");
        $this->curl->post(json_encode($ud), "application/json");

        if ($this->curl->getStatus() == 204 || $this->curl->getStatus() == 200) {
            $this->log("password updated");
            return true;
        }
        return false;
    }

    public function chooseFunction() {
        if (array_key_exists("g", $this->param)) {
            $this->grantAdmin();
        }
        else if (array_key_exists("r", $this->param)) {
            $this->revokeAdmin();
        }
    }

    private function grantAdmin() {
        $this->curl->setPathInfo("user-profile/federation/admin");
        $this->curl->put(json_encode(array("user_mail"=>$this->param["g"])), "application/json");
    }

    private function revokeAdmin() {
        $this->curl->setPathInfo("user-profile/federation/admin");
        $this->curl->delete(array("user_mail"=>$this->param["r"]));
    }
}

?>