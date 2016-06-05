<?php
/* *********************************************************************** *
 *
 * *********************************************************************** */

class TokenDataValidator extends EduIDValidator {
    private $user;

    protected function validate() {
        $this->valid = 0;
        
        if (!$this->checkDataForMethod()) {
            return false;
        }

        if (!$this->checkAuthToken()) {
            return false;
        }
        
        if (!$this->checkDataFields(array("grant_type"))) {
            return false;
        } 
        
        $aFields = array();
        
        switch ($this->data["grant_type"]) {
            case "authorization_code": // Section 4.1.3
                $aFields = array("redirect_uri", "code", "client_id");
                break;
            case "password": // Section 4.3.2
                $aFields = array("username", "password");
                break;
            case "client_credentials": // Section 4.4
            default:
                break;
        }

        if (!($this->checkDataFields($aFields) &&
              $this->checkGrantType())) {
            // problem already logged
            return false; 
        }

        $this->valid = 1;
        return true;
    }
    
    private function checkGrantType() {
        if (method_exists($this, "check_" . $this->data["grant_type"])) {
            return call_user_func(array($this, "check_" . $this->data["grant_type"])); 
        }
        
        $this->log("bad grant type found " . $this->data["grant_type"]);
        return false;
    } 
    
    private function check_authorization_code() {
        $token = $this->service->getAuthToken();

        if ($token["access_key"] != $this->data["code"]) {
            $this->log("mismatching code presented");
            return false;
        }
        
        if (!(array_key_exists("extra", $token) &&
              array_key_exists("client_type", $token["extra"]) &&
              $token["extra"]["client_type"] == $this->data["client_id"])) {
            
            $this->log("mismatching eduid app id presented");
            return false;
        }
        
        $service = $this->service->getTargetService();
        $service->findServiceByURI($this->data["redirect_uri"]);
        
        if (!$service->hasUUID()) {
            $this->log("no service found for URI " . $this->data["redirect_uri"]);
            return false;
        }
        
        return true;
    }
    
    private function check_client_credentials() {
        // verify claims
        $jwt = $this->service->getJWT();

        if (!$jwt->hasClaim("sub") ||
            empty($jwt->getClaim("sub")) ||
            !$jwt->hasClaim("name") ||
            empty($jwt->getClaim("name"))) {

            $this->log("missing instance information for client credentials");
            return false;
        }
        return true;
    }
    
    private function check_password() {
        // ckeck if we know the requested user
        $this->user = new UserManager($this->db);

        if (!$this->user->findByMailAddress($this->data["username"])) {
            $this->log("user not found");
            $this->service->forbidden();
            return false;
        }
        return true;
    }

    public function getUser() {
        return $this->user;
    }
}
?>
