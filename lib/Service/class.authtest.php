<?php
/* *********************************************************************** *
 *
 * *********************************************************************** */


class AuthTestService extends ServiceFoundation {

    protected function get() {
        $token = $this->tokenValidator->getToken();

        if ($token["user_uuid"] != "TESTUUID") {
            $this->forbidden();
        }
        else {
            $this->data = $token;
        }
    }
}

?>
