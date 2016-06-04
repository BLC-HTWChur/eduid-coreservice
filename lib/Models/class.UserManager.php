<?php
/* *********************************************************************** *
 *
 * *********************************************************************** */

require_once("Models/class.DBManager.php");

class UserManager extends DBManager {
    private $user;
    private $profile;

    public function findByMailAddress($mailAddress) {
        $this->user = null;

        $sqlstr = "SELECT u.user_uuid, u.user_passwd, u.salt, ui.mailAddress from users u, useridentities ui where ui.user_uuid = u.user_uuid and ui.mailAddress = ?";
        $sth = $this->db->prepare($sqlstr, array("TEXT"));
        $res = $sth->execute(array($mailAddress));

        if ($row = $res->fetchRow(MDB2_FETCHMODE_ASSOC)) {
            $this->user = array();
            $aMap = array("ui.mailaddress" => "mailAddress",
                          "u.user_password" => "user_passwd",
                          "u.user_uuid "=> "user_uuid");

            foreach ($row as $k => $v) {
                $this->user[$k] = $v;
            }
            return true;
        }

        return false;
    }

    public function findByUUID($uuid) {
        $this->user = null;

        $sqlstr = "SELECT user_uuid, user_passwd from users where user_uuid = ?";
        $sth = $this->db->prepare($sqlstr, array("TEXT"));
        $res = $sth->execute(array($uuid));

        if ($row = $res->fetchRow(MDB2_FETCHMODE_ASSOC)) {
            $this->user = array();
            $aMap = array("user_passwd",
                          "user_uuid");

            foreach ($aMap as $k) {
                $this->user[$k] = $row[$k];
            }
            $sth->free();
            return true;
        }

        $sth->free();
        return false;
    }

    public function authenticate( $password ) {
        if (isset($this->user) && !empty($this->user)) {
            
            $pwd = sha1($this->user["salt"] . $password);
            
            if ($pwd == $this->user["user_passwd"] ) {
                return true;
            }
        }

        $this->user = null;

        return false;
    }

    public function loadProfileIdentities() {
        $this->profile = null;

        if (isset($this->user) &&
            !empty($this->user) &&
            !empty($this->user["user_uuid"])) {

            $aFields = array("idp_uuid", "userid", "mailaddress", "invalid", "extra");
            $sqlstr = "select " . implode(",", $aFields) . " from useridentities where user_uuid = ?";
            $sth = $this->db->prepare($sqlstr, array("TEXT"));
            $res = $sth->execute(array($this->user["user_uuid"]));

            $this->profile = array();

            while ($row = $res->fetchRow(MDB2_FETCHMODE_ASSOC)) {
                $aProfile = array();
                foreach ($row as $f => $v) {
                    if (isset($v) && !empty($v)) {
                        if ($f == "extra") {
                            $aProfile[$f] = json_decode($v);
                        }
                        else {
                            $aProfile[$f] = $v;
                        }
                    }
                }
                $this->profile[] = $aProfile;
            }

            $sth->free();
        }
    }

    public function getUUID() {
        if (isset($this->user) && !empty($this->user)) {
            return $this->user["user_uuid"];
        }
        return null;
    }

    public function getAllProfiles() {
        if (!isset($this->profile)) {
            $this->loadProfileIdentities();
        }
        return $this->profile;
    }
}

?>
