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
            $this->service->forbidden();
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
}

?>
