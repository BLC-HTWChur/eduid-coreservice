<?php
/* *********************************************************************** *
 *
 * *********************************************************************** */


namespace EduID\Service;

use EduID\ServiceFoundation;
use EduID\Model\User;

class UserProfile extends ServiceFoundation {
    protected function initializeRun() {
        $this->tokenValidator->resetAcceptedTokens(array("Bearer", "MAC"));
        $this->tokenValidator->setAcceptedTokenTypes(array("Bearer", "MAC"));
        $this->tokenValidator->requireUser();
        $this->tokenValidator->requireClient();
    }

    protected function get() {
        if ($user = $this->tokenValidator->getTokenUser()) {
            $this->data = $user->getAllProfiles();
        }
        else {
            $this->forbidden();
        }
    }

    protected function put() {
        $user = new User($this->db);

        $user->addUser($this->data);
    }
}

?>
