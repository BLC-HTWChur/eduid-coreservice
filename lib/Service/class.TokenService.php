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
class TokenService extends ServiceFoundation {
    private $userValidator;

    public function __construct() {
        parent::__construct();

        $this->tokenValidator->resetAcceptedTokens(array("Bearer", "MAC"));

        $this->userValidator = new TokenDataValidator($this->db);
        $this->userValidator->requireEmpty("get");
        $this->addDataValidator($this->userValidator);
    }

    public function verifyRawToken((string) $code) {
        return $this->tokenValidator->verifyRawToken($code);
    }

    public function verifyTokenClaim((string) $claim, (string) $value) {
        return $this->tokenValidator->verifyJWTClaim($claim, $value);
    }

    public function getAuthToken() {
        return $this->tokenValidator->getToken();
    }

    protected function get() {
        $this->data = array("status"=> "OK",
                            "message"=>"POST information");
    }

    protected function post() {
        $token = $this->getAuthToken();

        $tokenType = "MAC";
        $tm = $this->tokenvalidator->getTokenIssuer($tokenType);

        switch ($this->inputData["grant_type"]) {
            case "password": // Section 4.3.2
                $um = $this->userValidator->getUser();
                if ($um->authenticate($this->inputData["password"], $token["mac_key"])) {

                    $tm->addToken(array("user_uuid" => $um->getUUID()));

                    $ut = $tm->getToken();

                    $this->data = array(
                        "access_token"  => $ut["access_key"],
                        "token_type"    => strtolower($ut["token_type"]),
                        "kid"           => $ut["kid"],
                        "mac_key"       => $ut["mac_key"],
                        "mac_algorithm" => $ut["mac_algorithm"]
                    );
                    if (array_key_exists("expires_in", $ut)) {
                        $this->data["expires_in"] = $ut["expires_in"];
                    }
                }
                else {
                    $this->log("failed to authenticate user " . $this->inputData["username"]);
                    $this->forbidden();
                }
                break;
            case "authorization_code": // Section 4.1.3
                $tm->addToken(array("client_id" => $this->inputData["client_id"]));

                $token = $tm->getToken();

                $this->data = array(
                    "client_id" => $token["kid"],
                    "code" => $token["mac_key"],    // used for password encryption and is never shared after registration
                    "client_secret" => $token["access_key"]
                );
                break;
            default:
                break;
        }
    }
}

?>
