<?php

namespace EduID\Client;

use Lcobucci\JWT as JWT;
use Lcobucci\JWT\Signer as Signer;

use EduID\Client as ClientBase;
use EduID\Curler;

class UserRegistration extends ClientBase {

    private $userinfo = array();

    public function __construct() {
        $this->paramShort .= "e:G:F:a";
        parent::__construct();

        if (!$this->authorize()) {
            $this->fatal("Client rejected");
        }

        if (array_key_exists("e", $this->param)) {
            $this->userinfo["mailaddress"] = $this->param["e"];
        }
        if (array_key_exists("G", $this->param)) {
            $this->userinfo["given_name"] = $this->param["G"];
        }
        if (array_key_exists("F", $this->param)) {
            $this->userinfo["family_name"] = $this->param["F"];
        }
        // -a adds the user with the identity as federation user
    }

    public function verify_user() {

        // read email if needed
        if (!array_key_exists("mailaddress", $this->userinfo)) {
            $this->userinfo["mailaddress"] = readline("mail-address: ");
        }
        // read given name if needed
        if (!array_key_exists("given_name", $this->userinfo)) {
            $this->userinfo["given_name"]  = readline("given name:   ");
        }
        // read family name if needed
        if (!array_key_exists("family_name", $this->userinfo)) {
            $this->userinfo["family_name"] = readline("family name:  ");
        }
        // read password
        $this->userinfo["user_password"]   = readline("password:     ");

        $n = array();
        if (array_key_exists("given_name", $this->userinfo)) {
            $n[] = $this->userinfo["given_name"];
        }
        if (array_key_exists("family_name", $this->userinfo)) {
            $n[] = $this->userinfo["family_name"];
        }
        $this->userinfo["name"] = implode(" ", $n);

        if(
            $this->checkMandatoryFields($this->userinfo,
                                        array(
                                            "mailaddress",
                                            "user_password",
                                            "given_name",
                                            "family_name",
                                            "name"
                                        ))
        ) {
            return $this->userinfo;
        }
        return null;
    }

    public function register_user($userInfo) {
        if (!empty($userInfo)) {
            $this->curl->setPathInfo("user-profile/federation");

            $this->curl->put(json_encode($userInfo), 'application/json');

            if ($this->curl->getStatus() == "200") {
                // ok we have the registration token
                // print out
                // echo $this->curl->getBody();
                $this->log("user successfully registered");
                return true;
            }
            else {
                $this->log("federation service refused user infomation: " .
                           $this->curl->getStatus());
                $this->log($this->curl->getBody());
            }
        }

        return false;
    }
}

?>
