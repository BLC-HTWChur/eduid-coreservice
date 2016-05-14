<?php
/* *********************************************************************** *
 *
 * *********************************************************************** */

require_once("Models/class.EduIDValidator.php");
require_once("Models/class.UserManager.php");

class UserAuthDataValidator extends EduIDValidator {
    private $user;

    protected function validate() {
        $this->valid = 0;

        if (isset($this->data) && !empty($this->data)) {
            if (in_array($this->method, $this->requireEmptyMethod)) {
                $this->log("Not Data Must Be Sent For " . $this->method);
                return false;
            }

            // check the data fields
            $aKeys = array("username", "password", "challenge");

            foreach ($aKeys as $k) {
                if (!array_key_exists($k, $this->data) || empty($this->data[$k])) {
                    $this->log("missing value in key " . $k);
                    return false;
                }
            }

            // verify that there is a client token
            $gT = $this->service->getToken();
            if (!isset($gT) || empty($gT)) {
                $this->log("no token found");
                $this->service->forbidden();
                return false;
            }

            // verify that the client is who it clains
            $tChallenge = sha1($gT["access_key"] . $gT["mac_key"]);
            if ($tChallenge != $this->data["challenge"]) {
                $this->log("bad client challenge");
                $this->service->forbidden();
                return false;
            }

            // ckeck if we know the requested user
            $this->user = new UserManager($this->db);

            if (!$this->findByMailAddress($this->inputData["username"])) {
                $this->log("no user found");
                $this->service->forbidden();
                return false;
            }
        }
        else if (!in_array(strtolower($this->method), $this->allowEmptyMethod) &&
                 !in_array(strtolower($this->method), $this->requireEmptyMethod)) {
            $this->log("missing data for method " . $this->method);
            return false;
        }

        $this->valid = 1;
        return true;
    }

    public function getUser() {
        return $this->user;
    }
}

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
        $token = $this->getToken();

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
