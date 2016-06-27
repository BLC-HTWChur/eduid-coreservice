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
        $token = $this->getAuthToken();

        $redirectUri = $this->inputData["redirect_uri"];
        $clientId    = $this->inputData["client_id"];


    }
}

?>
