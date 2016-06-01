<?php
/* *********************************************************************** *
 *
 * *********************************************************************** */

// require_once("Models/class.JWTValidator.php");

use Lcobucci\JWT as JWT;
use Lcobucci\JWT\Signer as Signer;

/**
 *
 */
class AssertionService extends ServiceFoundation {

   /**
    *
    */
    public function __construct() {
        parent::__construct();
        $this->tokenValidator->resetAcceptedTokens(array("Bearer", "MAC"));

        // add assertion data validator
    }

   /**
    * @protected @function get()
    *
    * The get method expects a service grant token as search string.
    * it will lookup the provided jti as kid for jwt-bearer tokens
    */
    protected function get()

        $this->log("get data");
        $this->data = array('hello' => 'get');
    }

   /**
    * @protected @function post()
    *
    * POST Assertions are used if the edu-ID Mobile App wants to access to a
    * Federation Service. The edu-ID MUST post the base URL for the service.
    *
    * If the service URL exists, it will create an service grant token for the
    * edu-ID Mobile App instance. The instance is signed using the target
    * service's acces_key.
    *
    *
    */
    protected function post() {
        $this->log("post data");

        // check the type of token reuquest
        // client authorization is performed for "aUthorziation code requests"

        switch ($this->inputData["grant_type"]) {
            case 'client_credentials': // As in RFC-6749  Section 4.4

                // an authorization code has NO user_uuid or service UUID
                $client_id = $this->tokenValidator->getJWT()->getClaim("sub");
                $sasertion_type = "urn:ietf:params:oauth:client-assertion-type:jwt-bearer";

                if ($this->inputData["client_assertion_type"] == $assertion_type &&
                    $this->tokenValidator->verifyRawToke($this->inputData("client_assertion"))) {

                    // ONLY this part should remain here
                    $tm = $hits->tokenvalidator->getTokenIssuer('MAC');
                    $tm->addToken(array("client_id" => $client_id));

                    $token = $tm->getToken();

                    $this->data = array(
                        "client_id" => $token["kid"],
                        "code" => $token["mac_key"],    // used for password encryption and is never shared after registration
                        "client_secret" => $token["access_key"]
                    );
                }
                else {
                    $this->forbidden();
                }
                break;
            case 'assertion': // not defined anywhere :(
                $user = $this->tokenvalidator->getTokenUser();

                $tm = $this->tokenvalidator->getTokenIssuer('assertion');

                $this->inputData["redirect_uri"];

                $jwt = new JWT\Builder();
                $jwt->setIssuer('https://eduid.htwchur.ch');
                $jwt-setSubject();
                $jwt->setAudience($this->inputData["redirect_uri"];);

                break;
            default:
                $this->forbidden();
                break;
        }

    }
}

?>
