<?php
/* *********************************************************************** *
 *
 * *********************************************************************** */

namespace EduID\Service;

use EduID\ServiceFoundation;
use EduID\Validator\Data\UserAuth;
use EduID\Model\User;
use EduID\Model\Service as ServiceModel;

use Lcobucci\JWT as JWT;
use Lcobucci\JWT\Signer as Signer;

/**
 *
 */
class AuthorizationService extends ServiceFoundation {
    private $userValidator;

    public function __construct() {
        parent::__construct();

        $this->tokenValidator->resetAcceptedTokens("Basic");
        //$this->tokenValidator->setAcceptedTokenTypes("Client");

        $this->dataValidator = new TokenDataValidator($this->db);
        $this->dataValidator->requireEmpty("get");
        $this->addDataValidator($this->dataValidator);
    }

    public function getAuthToken() {
        return $this->tokenValidator->getToken();
    }

    public function getJWT() {
        return $this->tokenValidator->getJWT();
    }

    protected function get() {
        $this->data = array("status"=> "OK",
                            "message"=>"POST user information");
    }

    protected function post() {
        $token = $this->getAuthToken();

        switch ($this->inputData["grant_type"]) {
            case "password": // Section 4.3 - normal username/password authentication
                $this->passwordAuth();
                break;
            case "client_credentials": // Section 4.4 - client registration via JWT
                $this->clientCredentials();
                break;
            case "authorization_code": // Section 4.1 - assertion
                $this->authorizationCode();
                break;
            default:
                $this->forbidden();
                break;
        }
    }

    /**
     *
     */
    private function passwordAuth() {
        $um = $this->dataValidator->getUser();

        if ($um->authenticate($this->inputData["password"])) {
            $tokenType = "MAC";

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

    /**
     *
     * client registraton
     */
    private function clientCredentials() {
        $tokenType = "MAC";

        $token = $this->tokenValidator->getToken();
        $extras = array("device_name" => $this->inputData["device_name"],
                        "eduid_appid" => $token["client_id"])

        $tm = $this->tokenValidator->getTokenIssuer($tokenType);
        $tm->addToken(array("client_id" => $this->inputData["device_id"],
                            "extra"     => $extras));

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

    /**
     *
     */
    private function authorizationCode() {
        // code needs to match the authorization key

        $user = $this->tokenValidator->getTokenUser();

        if (isset($user)) {

            // find service info in the federation
            $service = new ServiceModel($this->db);

            // the redirect URI must point to a service within the federation
            if ($service->findByURI($this->inputData["redirect_uri"])) {

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
                $jwt->setIssuedAt($token["issued_at"]);
                $jwt->setExpiration(time() + 3600); //1h valid - FIXME make configurable

                $jwt->setSubject($profiles[0]["userid"]); // eduid ID

                $jwt->set("azp", $token["extra"]["client_type"]);

                foreach (array("name", "given_name", "family_name", "email") as $k) {
                    $jwt->set($k, $profile["extra"][$k]);
                }

                $jwt->sign($signer, $service->tokenKey("mac_key"));

                // now record that the service has been accessed
                $service->trackUser($token);

                $this->data = array("token" => (string) $jwt->getToken());
            }
            else {
                $this->forbidden();
            }
        }
        else {
            $this->authorization_required();
        }
    }
}

?>
