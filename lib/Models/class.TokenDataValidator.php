<?php
/* *********************************************************************** *
 *
 * *********************************************************************** */

class TokenDataValidator extends EduIDValidator {
    private $user;

    protected function validate() {
        $this->valid = 0;

        if (isset($this->data) && !empty($this->data)) {
            if (in_array($this->method, $this->requireEmptyMethod)) {
                $this->log("No Data Must Be Sent For " . $this->method);
                return false;
            }

            switch ($this->inputData["grant_type"]) {
                case "authorization_code": // Section 4.1.3
                    $aKeys = array("code", "client_id");
                    break;
                case "password": // Section 4.3.2
                    $aKeys = array("username", "password");
                    break;
                default:
                    this->log("unaccepted Authorization");
                    return false;
                    break;
            }

            foreach ($aKeys as $k) {
                if (!array_key_exists($k, $this->data) ||
                    empty($this->data[$k])) {
                    $this->log("missing value in key " . $k);
                    return false;
                }
            }

            // verify that there is a client token
            $gT = $this->service->getAuthToken();
            if (!isset($gT) || empty($gT)) {
                $this->log("no token found");
                $this->service->forbidden();
                return false;
            }

            if ($this->inputData["grant_type"] == "authorization_code") {
                // verify that the code is the same as the auth token

                if ($this->service->verifyRawToken($this->data["code"])) {
                    $this->log("client token mismatch");
                    return false;
                }

                if ($this->service->verifyTokenClaim("sub", $this->data["client_id"])) {
                    $this->log("client id mismatch");
                    return false;
                }

            }
            else if ($this->inputData["grant_type"] == "password") {
                // ckeck if we know the requested user
                $this->user = new UserManager($this->db);

                if (!$this->user->findByMailAddress($this->data["username"])) {
                    $this->log("user not found");
                    $this->service->forbidden();
                    return false;
                }
            }
        }
        else if (!in_array($this->method, $this->requireEmptyMethod)) {
            $this->log("no data present");
            return false;
        }

        $this->valid = 1;
        return true;
    }

    public function getUser() {
        return $this->user;
    }
}
?>
