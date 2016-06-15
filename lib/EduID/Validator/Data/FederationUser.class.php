<?php
/* *********************************************************************** *
 *
 * *********************************************************************** */

namespace EduID\Validator\Data;

use EduID\Validator\Base as Validator;

class FederationUser extends Validator {
    private $user;
    private $federationOperations = array();

    public function setRequiredOperations($ops) {
        if (!empty($ops)) {
            if (!is_array($ops)) {
                $ops = [$ops];
            }
            $this->federationOperations = $ops;
        }
    }

    public function validate() {
        $this->user = $this->service->getTokenUser();

        if (!$this->check_methods()) {
            $this->log("user " . $this->user->getUUID() . " no accepted");
            return false;
        }
        return true;
    }

    private function check_methods() {
        if (in_array($this->operation, $this->federationOperations)) {
            if ($this->user) {
                return $this->user->isFederationUser();
            }
            return false;
        }
        return true;
    }

    public function error() {
        if (!$this->isValid())
        {
            // return authentication required by default
            $this->service->forbidden();
            return "";
        }
    }

    public function mandatory() {
        return true;
    }
}

?>
