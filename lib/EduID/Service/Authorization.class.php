<?php
/* *********************************************************************** *
 *
 * *********************************************************************** */

namespace EduID\Service;

use EduID\Validator\Data\Token as TokenDataValidator;
use EduID\Model\User;
use EduID\Model\Service;

//
//require_once("Models/class.EduIDValidator.php");
//require_once("Models/class.UserManager.php");
//require_once("Models/class.ServiceManager.php");
//require_once("Models/class.TokenDataValidator.php");

use Lcobucci\JWT as JWT;

/**
 *
 */
class Authorization extends ServiceFoundation {
    private $userValidator;
    private $serviceManager;

    public function __construct() {
        parent::__construct();

        $this->setOperationParameter("request_type");
        $this->tokenValidator->resetAcceptedTokens("Bearer");

        // the internal representation requires a token type;
        $this->tokenValidator->requireUser();
        $this->tokenValidator->requireClient();

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

    protected function post_code() {
        // clients MUST present a user token
        // as specficied by RFC 6749 (there it says that users must authorize)
        // obtain access code as assertion JWT as specified in RFC 7521
        
        $token = $this->getAuthToken();

        $redirectUri = $this->inputData["redirect_uri"];
        $clientId    = $this->inputData["client_id"];
        $state       = $this->inputData["state"];
        
        // create Token
        $tm = $this->tokenValidator->getTokenIssuer("Assertion");
        
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
        $jwt->setIssuedAt($token["issued_at"]);
        $jwt->setExpiration($token["issued_at"] + 3600); //1h valid - FIXME make configurable

        $jwt->setSubject($profiles[0]["userid"]); // eduid ID

        $jwt->set("azp", $token["extra"]["client_type"]);

        foreach (array("name", "given_name", "family_name", "email") as $k) {
            $jwt->set($k, $profile[$k]);
        }

        $jwt->sign($this->serviceManager->getTokenSigner(),
                   $this->serviceManager->getSignKey());

        $this->data = array(
            "code" => (string) $jwt->getToken(),
        );
        if (!empty($state)) {
            $this->data["state"] = $state;
        }
    }
}

?>
