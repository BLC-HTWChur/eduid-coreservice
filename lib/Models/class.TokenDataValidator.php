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
                case "client_credentials": // Section 4.4 everything is in the token
                    $aKeys = array();
                    break;
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

            if ($this->inputData["grant_type"] == "client_credentials") {
                // verify claims
                $jwt = $this->service->getJWT();

                // we expect extra information from the client.
                $device_id    = $jwt->getClaim("sub");
                $device_name  = $jwt->getClaim("name");

                if (isset($device_id) &&
                    !empty($device_id) &&
                    isset($device_name) &&
                    !empty($device_id)) {

                    $this->inputData["device_name"] = $device_name;
                    $this->inputData["device_id"]   = $device_id;
                }
                else {
                    $this->log("missing instance information for client credentials");
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
            else if ($this->inputData["grant_type"] == "authorization_code") {
                $token = $this->service->getAuthToken();

                if ($token["authorization_key"] != $this->inputData["code"]) {
                    $this->log("mismatching code presented");
                    return false;
                }

                if ($token["extras"]["eduid_appid"] != $this->inputData["client_id"]) {
                    $this->log("mismatching eduid app id presented");
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
