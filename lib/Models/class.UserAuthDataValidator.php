<?php
/* *********************************************************************** *
 *
 * *********************************************************************** */

class UserAuthDataValidator extends EduIDValidator {
    private $user;

    protected function validate() {
        $this->valid = 0;

        if (isset($this->data) && !empty($this->data)) {
            if (in_array($this->method, $this->requireEmptyMethod)) {
                $this->log("Not Data Must Be Sent For " . $this->method);
                return false;
            }

            // check the data fields
            $aKeys = array("username", "password", "challenge");

            foreach ($aKeys as $k) {
                if (!array_key_exists($k, $this->data) || empty($this->data[$k])) {
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

            // verify that the client is who it claims
            $tChallenge = sha1($gT["access_key"] . $gT["mac_key"]);
            if ($tChallenge != $this->data["challenge"]) {

                $this->log("bad client challenge ");
                $this->service->forbidden();
                return false;
            }

            // ckeck if we know the requested user
            $this->user = new UserManager($this->db);

            if (!$this->user->findByMailAddress($this->data["username"])) {
                $this->log("no user found");
                $this->service->forbidden();
                return false;
            }
        }
        else if (!in_array(strtolower($this->method), $this->allowEmptyMethod) &&
                 !in_array(strtolower($this->method), $this->requireEmptyMethod)) {
            $this->log("missing data for method " . $this->method);
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
