<?php
/* *********************************************************************** *
 *
 * *********************************************************************** */

require_once("Models/class.EduIDValidator.php");
require_once("Models/class.UserManager.php");
require_once("Models/class.TokenDataValidator.php");

use Lcobucci\JWT as JWT;
use Lcobucci\JWT\Signer as Signer;

/**
 *
 */
class TokenService extends ServiceFoundation {
    private $userValidator;

    public function __construct() {
        parent::__construct();

        $this->queryOperation("grant_type");

        $this->tokenValidator->resetAcceptedTokens(array("Bearer", "MAC"));

        $this->dataValidator = new TokenDataValidator($this->db);
        $this->dataValidator->requireEmpty("get");
        $this->addDataValidator($this->dataValidator);
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

    protected function post_password() { // OAuth2 Section 4.3.2
        $token = $this->getAuthToken();
        $tokenType = "MAC";
        $tm = $this->tokenValidator->getTokenIssuer($tokenType);

        $um = $this->userValidator->getUser();
        if ($um->authenticate($this->inputData["password"])) {

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
    }

    protected function post_client_credentials() {
        $token = $this->getAuthToken();

        $tokenType = "MAC";

        $tm = $this->tokenValidator->getTokenIssuer($tokenType);

        // get the root token info
        $ciToken = $this->tokenValidator->getToken();

        $token_extra = array("client_type" => $ciToken["kid"],
                             "device_name" => $this->inputData["device_name"]);

        // get extra info from the current token
        $tm->addToken(array("client_id" => $this->inputData["device_id"],
                            "extra": $token_extra));

        $token = $tm->getToken();

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

    protected function post_authorization_code() {
        // service needs to be validated by tokendata validator

        $token = $this->getAuthToken();
        $tokenType = "Assertion"; // we will never test this ourselves in the authorization header

        $tm = $this->tokenValidator->getTokenIssuer($tokenType);

        $signer = new Signer\Hmac\Sha256();

        $user->loadProfileIdentities();
        $profiles = $user->getAllProfiles();
        $profile = $profiles[0]["extra"];
        $profile["email"] = $profiles[0]["mailaddress"];

        $tm = $this->tokenvalidator->getTokenIssuer('assertion');

        $tm->addToken();

        $token = $tm->getToken();

        $jwt = new JWT\Builder();

        $jwt->setIssuer('https://eduid.htwchur.ch');

        $jwt->setAudience($service->getTokenEndpoint()); // the client MUST sent the endpoint
        $jwt->setId($token["kid"]);
        $jwt->setIssuedAt(time());
        $jwt->setExpiration(time() + 3600); //1h valid - FIXME make configurable

        $jwt->setSubject($profiles[0]["userid"]); // eduid ID

        $jwt->set("azp", $token["extra"]["client_type"]);

        foreach (array("name", "given_name", "family_name", "email") as $k) {
            $jwt->set($k, $profile["extra"][$k]);
        }

        $jwt->sign($signer, $service->tokenKey("mac_key"));

        $this->data = array(
            "access_token" => (string) $jwt->getToken(),
            "token_type"   => "urn:ietf:oauth:param:jwt-bearer"
        );
    }
}

?>
