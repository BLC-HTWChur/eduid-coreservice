<?php
/* *********************************************************************** *
 *
 * *********************************************************************** */

require_once("Models/class.EduIDValidator.php");
require_once("Models/class.UserManager.php");
require_once("Models/class.UserAuthDataValidator.php");

/**
 *
 */
class UserAuthService extends ServiceFoundation {
    private $userValidator;

    public function __construct() {
        parent::__construct();

        $this->tokenValidator->resetAcceptedTokens("Basic");
        $this->tokenValidator->setAcceptedTokenTypes("Client");

        $this->userValidator = new UserAuthDataValidator($this->db);
        $this->userValidator->requireEmpty("get");
        $this->addDataValidator($this->userValidator);
    }

    public function getAuthToken() {
        return $this->tokenValidator->getToken();
    }

    protected function get() {
        $this->data = array("status"=> "OK",
                            "message"=>"POST user information");
    }

    protected function post() {
        $token = $this->getAuthToken();

        $um = $this->userValidator->getUser();

        if ($um->authenticate($this->inputData["password"], $token["mac_key"])) {

            $tokenType = "MAC";
            if (array_key_exists("preferred-token", $this->inputData) &&
                in_array($this->inputData["preferred-token"], array("Bearer", "MAC"))) {

                $tokenType = $this->inputData["preferred-token"];
            }

            $tm = $this->tokenValidator->getTokenIssuer($tokenType);
            $tm->addToken(array("user_uuid" => $um->getUUID()));

            $ut = $tm->getToken();

            $this->data = array(
                "access_token"  => $ut["access_key"],
                "token_type"    => strtolower($ut["token_type"]),
            );

            if ($tokenType == "MAC") {
                $this->data["kid"]            =  $ut["kid"];
                $this->data["mac_key"]        = $ut["mac_key"];
                $this->data["mac_algorithm"]  =  $ut["mac_algorithm"];
            }

            if (array_key_exists("expires_in", $ut)) {
                $this->data["expires_in"] = $ut["expires_in"];
            }
        }
        else {
            $this->log("failed to authenticate user " . $this->inputData["username"]);
            $this->forbidden();
        }
    }
}

?>
