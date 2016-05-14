<?php
/* *********************************************************************** *
 *
 * *********************************************************************** */

require_once("Models/class.DBManager.php");

class UserManager extends DBManager{
    private $user;

    public function findByMailAddress($mailAddress) {
        $this->user = null;

        $sqlstr = "SELECT u.user_uuid, u.user_passwd, ui.mailAddress from users u, useridentities ui where ui.user_uuid = u.user_uuid and ui.mailAddress = ?";
        $sth = $this->db->prepare($sqlstr, array("TEXT"));
        $res = $this->execute(array($mailAddress));

        if ($row = $res->fetchRow(MDB2_FETCHMODE_ASSOC)) {
            $this->user = array();
            $aMap = array("ui.mailAddress" => "mailAddress",
                          "u.user_password" => "user_passwd",
                          "u.user_uuid "=> "user_uuid");

            foreach ($aMap as $k => $v) {
                $this->user[$v] = $row[$k];
            }
            return true;
        }

        return false;
    }

    public function findByUUID($uuid) {
        $this->user = null;

        $sqlstr = "SELECT user_uuid, user_passwd from users where user_uuid = ?";
        $sth = $this->db->prepare($sqlstr, array("TEXT"));
        $res = $this->execute(array($uuid));

        if ($row = $res->fetchRow(MDB2_FETCHMODE_ASSOC)) {
            $this->user = array();
            $aMap = array("user_passwd",
                          "user_uuid ");

            foreach ($aMap as $k) {
                $this->user[$k] = $row[$k];
            }

            return true;
        }

        return false;
    }

    public function authenticate( $authToken, $key ) {
        if (isset($this->user) && !empty($this->user)) {

            $testToken = sha1($key      . "\n".  // request token key
                              $this->user["mailAddress"] . "\n".  // user email address
                              $this->user["user_passwd"] . "\n"); // sha1 encrypted password

            if ($authToken == $testToken) {
                return true;
            }
        }

        $this->user = null;

        return false;
    }

    public function getUUID() {
        if (isset($this->user) && !empty($this->user)) {
            return $this->user["user_uuid"];
        }
        return null;
    }
}

?>
