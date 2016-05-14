<?php
/* *********************************************************************** *
 *
 * *********************************************************************** */

require_once("Models/class.TokenManager.php");

/**
 *
 */
class ClientRegisterService extends ServiceFoundation {
   /**
    *
    */
    public function __construct() {
        parent::__construct();
        $this->tokenValidator->resetAcceptedTokens("Basic");
    }

    /**
     *
     */
    protected function get() {
        $this->data = array("status"=> "OK", "message"=>"POST client information");
    }

    /**
     *
     */
    protected function post() {
        $tMgnt  = new TokenManager($this->db,
                                   array("token_type"=>"Client"));

        $gToken = $this->tokenValidator->getToken();
        $gExtra = json_decode($gToken["extra"], true);

        $extra = array("OS"         => $gExtra["OS"],
                       "AppVersion" => $gExtra["AppVersion"]);

        if (array_key_exists("client_name", $this->inputData)) {

            $extras["client_name"] = $this->inputData["client_name"];
        }

        if (isset($this->inputData) &&
            array_key_exists("device_id", $this->inputData)) {

            $tMgnt->addToken(array("client_id" => $this->inputData["device_id"],
                                   "extra"     => json_encode($extra)));
        }
        else if (array_key_exists("require_device", $gToken) &&
                 $gToken["require_device"]) {
            // reject if the toke requires a device and it is not present
            $this->forbidden();
        }
        else {
            // web based client
            // web clients need to get a new client token whenever
            // they want to authenticate a new user.
            $tMgnt->setMaxSeq(1);
            $tMgnt->addToken(array("client_id" => $_SERVER["REMOTE_IP"],
                                   "extra"     => json_encode($extra)));
        }

        $token = $tMgnt->getToken();

        if (isset($token) && !empty($token) &&
            !empty($token["kid"]) &&
            !empty($token["mac_key"]) &&
            !empty($token["access_key"])) {
            $this->data = array(
                "client_id" => $token["kid"],
                "mac_key" => $token["mac_key"],    // used for password encryption and is never shared after registration
                "client_secret" => $token["access_key"]
            );
        }
        else {
            $this->forbidden();
        }
    }
}

?>
