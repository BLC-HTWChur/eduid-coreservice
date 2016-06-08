<?php
/* *********************************************************************** *
 *
 * *********************************************************************** */

namespace EduID\Validator\Data;

use EduID\Validator\Base as Validator;

class FederationUser extends Validator {

    private $user;
    private $methods = [];

    public function setOperations($methods) {
        if (isset($methods) &&
            !empty($methods) &&
            is_array($methods)) {

            $this->methods = $methods;
        }
        else {
            $this->methods = [];
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
        foreach ($this->methods as $m) {
            if ($this->method == $m) {
                return $this->user->isFederationUser();
            }
        }
        return true;
    }
}

?>
