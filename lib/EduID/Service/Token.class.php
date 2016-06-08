<?php
/* *********************************************************************** *
 *
 * *********************************************************************** */

namespace EduID\Service;

use EduID\ServiceFoundation;
use EduID\Validator\Data\Token;
use EduID\Model\User;
use EduID\Model\Service;
use EduID\ServiceFoundation;
//
//require_once("Models/class.EduIDValidator.php");
//require_once("Models/class.UserManager.php");
//require_once("Models/class.ServiceManager.php");
//require_once("Models/class.TokenDataValidator.php");

use Lcobucci\JWT as JWT;

/**
 *
 */
class Token extends ServiceFoundation {
    private $userValidator;
    private $serviceManager;

    public function __construct() {
        parent::__construct();

        $this->setOperationParameter("grant_type");

        $this->tokenValidator->resetAcceptedTokens(array("Bearer", "MAC"));

        $this->dataValidator = new TokenDataValidator($this->db);

        $this->addDataValidator($this->dataValidator);
    }

    public function verifyRawToken($code) {
        return $this->tokenValidator->verifyRawToken($code);
    }

    public function verifyTokenClaim($claim, $value) {
        return $this->tokenValidator->verifyJWTClaim($claim, $value);
    }

    public function getAuthToken() {
        return $this->tokenValidator->getToken();
    }

    public function getJWT() {
        return $this->tokenValidator->getJWT();
    }

    public function getTargetService() {
        if (!isset($this->serviceManager)) {
            $this->serviceManager = new ServiceManager($this->db);
        }
        return $this->serviceManager;
    }

    protected function post_password() { // OAuth2 Section 4.3.2
        $token = $this->getAuthToken();
        $tokenType = "MAC";
        $tm = $this->tokenValidator->getTokenIssuer($tokenType);

        $um = $this->dataValidator->getUser();
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

        // transpose token claims
        $jwt = $this->getJWT();
        $this->inputData["device_name"] = $jwt->getClaim("name");
        $this->inputData["device_id"]   = $jwt->getClaim("sub");

        $tokenType = "MAC";
        $tm = $this->tokenValidator->getTokenIssuer($tokenType);

        // get the root token info
        $clientToken = $this->tokenValidator->getToken();

        $this->log(json_encode($this->inputData));

        $token_extra = array("client_type" => $clientToken["client_id"],
                             "device_name" => $this->inputData["device_name"]);

        // get extra info from the current token
        $tm->addToken(array("client_id" => $this->inputData["device_id"],
                            "extra"     => $token_extra));

        $token = $tm->getToken();

        $this->data = array(
            "access_token"  => $token["access_key"],
            "token_type"    => strtolower($token["token_type"]),
            "kid"           => $token["kid"],
            "mac_key"       => $token["mac_key"],
            "mac_algorithm" => $token["mac_algorithm"]
        );

        if (array_key_exists("expires_in", $token) && !empty($token["expires_in"])) {
            $this->data["expires_in"] = $token["expires_in"];
        }
    }

    protected function post_authorization_code() {
        // service needs to be validated by tokendata validator

        // $token = $this->getAuthToken();
        $tokenType = "Assertion"; // we will never test this ourselves in the authorization header

        $tm = $this->tokenValidator->getTokenIssuer($tokenType);

        $user = $this->tokenValidator->getTokenUser();

        $user->loadProfileIdentities();
        $profiles = $user->getAllProfiles();
        $profile  = $profiles[0]["extra"];
        $profile["email"] = $profiles[0]["mailaddress"];

        $tm = $this->tokenValidator->getTokenIssuer($tokenType);

        $tm->addToken(array("service_uuid"=>$this->serviceManager->getUUID()));

        $token = $tm->getToken();

        $jwt = new JWT\Builder();

        $jwt->setIssuer('https://eduid.htwchur.ch');

        $jwt->setAudience($this->serviceManager->getTokenEndpoint()); // the client MUST sent the endpoint
        $jwt->setId($token["kid"]);
        $jwt->setIssuedAt(time());
        $jwt->setExpiration(time() + 3600); //1h valid - FIXME make configurable

        $jwt->setSubject($profiles[0]["userid"]); // eduid ID

        $this->log($token["extra"]);

        $jwt->set("azp", $token["extra"]["client_type"]);

        foreach (array("name", "given_name", "family_name", "email") as $k) {
            $jwt->set($k, $profile[$k]);
        }

        $jwt->sign($this->serviceManager->getTokenSigner(),
                   $this->serviceManager->getSignKey());

        $this->data = array(
            "access_token" => (string) $jwt->getToken(),
            "token_type"   => "urn:ietf:oauth:param:jwt-bearer"
        );
    }
}

?>
