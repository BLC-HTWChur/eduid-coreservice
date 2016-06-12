<?php
/* *********************************************************************** *
 *
 * *********************************************************************** */


namespace EduID\Service;

use EduID\ServiceFoundation;
use EduID\Model\User;
use EduID\Validator\Data\FederationUser;

class UserProfile extends ServiceFoundation {
    protected function initializeRun() {
        $this->tokenValidator->resetAcceptedTokens(array("Bearer", "MAC"));
        $this->tokenValidator->setAcceptedTokenTypes(array("Bearer", "MAC"));
        $this->tokenValidator->requireUser();
        $this->tokenValidator->requireClient();
        
        $fu = new FederationUser($this->db);
        $fu->setRequiredOperations(array("put_federation"));
        
        $this->addHeaderValidator($fu);
    }

    protected function get() {
        $this->log("get user profile");
        if ($user = $this->tokenValidator->getTokenUser()) {
            $this->data = $user->getAllProfiles();
        }
        else {
            $this->forbidden();
        }
    }

    protected function put_federation() {
        $this->log("add user from federation");
        
        $user = new User($this->db);

        $user->addUser($this->inputData);
    }
}

?>
