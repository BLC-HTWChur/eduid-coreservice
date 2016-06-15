<?php
/* *********************************************************************** *
 *
 * *********************************************************************** */

namespace EduID\Model;

class User extends DBManager {
    private $user;
    private $profile;

    public function findByMailAddress($mailAddress) {
        $this->user = null;

        $sqlstr = "SELECT u.user_uuid, u.user_passwd, u.salt, ui.mailaddress from users u, useridentities ui where ui.user_uuid = u.user_uuid and ui.mailaddress = ?";
        $sth = $this->db->prepare($sqlstr, array("TEXT"));
        $res = $sth->execute(array($mailAddress));

        if ($row = $res->fetchRow(MDB2_FETCHMODE_ASSOC)) {
            $this->user = array();

            foreach ($row as $k => $v) {
                $this->user[$k] = $v;
            }

            if (!empty($this->user)) {
                return true;
            }
        }

        return false;
    }

    public function findByUUID($uuid) {
        $this->user = null;

        $sqlstr = "SELECT user_uuid, user_passwd, salt from users where user_uuid = ?";
        $sth = $this->db->prepare($sqlstr, array("TEXT"));
        $res = $sth->execute(array($uuid));

        if ($row = $res->fetchRow(MDB2_FETCHMODE_ASSOC)) {
            $this->user = array();

            foreach ($row as $k => $v) {
                $this->user[$k] = $v;
            }
            $sth->free();
            return true;
        }

        $sth->free();
        return false;
    }

    public function authenticate( $password ) {
        if (!empty($this->user)) {

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

        if (!empty($this->user) &&
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
                            $aProfile[$f] = json_decode($v, true);
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
        if (!empty($this->user)) {
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

    public function isFederationUser() {
        $retval = false;
        if (!empty($this->user)) {
            $sqlstr = "select user_uuid from federation_users where user_uuid = ?";
            $sth = $this->db->prepare($sqlstr, array("TEXT"));
            $res = $sth->execute(array($this->user["user_uuid"]));

            if($row = $res->fetchRow(MDB2_FETCHMODE_ASSOC)) {
                // is a federation user if we find 1 row
                $retval = true;
            }
            $sth->free();
        }
        return $retval;
    }

    public function addFederationUser($uuid="") {
        if (empty($uuid) && !empty($this->user)) {
            $uuid = $this->user["user_uuid"];
        }

        if (!empty($uuid)) {

            $sqlstr = "insert into federation_users (user_uuid) values (?)";
            $sth = $this->db->prepare($sqlstr, array("TEXT"));
            $res = $sth->execute(array($uuid));

            $sth->free();
        }
    }

    public function addUser($options) {

        $core = array("user_password", "user_uuid", "salt");

        if (!empty($options) &&
            array_key_exists("user_password", $options)) {

            $salt = $this->randomString (10);
            $userpw = sha1($salt . $options["user_password"]);

            if (array_key_exists("user_uuid", $options)) {
                $userid = $options["user_uuid"];
            }
            else {
                $userid = $this->generateUuid();
            }

            // add the user to the database
            $sql = "insert into users (user_uuid, user_passwd, salt) values (?, ?, ?)";
            $sth = $this->db->prepare($sql, array("TEXT","TEXT","TEXT"));
            $res = $sth->execute(array($userid, $userpw, $salt));

            $sth->free();

            $identity = array();

            // now check for an identity
            if (array_key_exists("identity", $options)) {
                // separate identity
                $identity= $options["identity"];
            }
            else {
                foreach ($options as $f => $v) {
                    if (!in_array($f, $core) && !empty($v)) {
                        $identity[$f] = $v;
                    }
                }
            }

            if (!empty($identity)) {
                // $this->log("add identity " . json_encode($identity));

                $this->addUserIdentity($identity, $userid);
            }
        }
    }

    public function updateUserPassword($oldpassword, $newpassword) {

        if ($this->user &&
            $this->user["user_uuid"]) {

//            foreach ($this->user as $k => $v) {
//                $this->log("user info: $k :: $v");
//            }

            $pwd = sha1($this->user["salt"] . $oldpassword);

            if ($pwd == $this->user["user_passwd"] ) {
                $salt = $this->randomString (10);
                $userpw = sha1($salt . $newpassword);

                $sqlstr = "update users set user_passwd = ?, salt = ? where user_uuid = ?";

                $sth = $this->db->prepare($sqlstr, array("TEXT","TEXT","TEXT"));
                $res = $sth->execute(array($userpw, $salt, $this->user["user_uuid"]));

                $sth->free();

                return true;
            }
        }
        return false;
    }

    public function addUserIdentity($options, $uuid="") {
        $idlist = array();
        if (self::isAssoc($options)) {
            // $this->log("create single identity");
            $idlist[] = $this->prepareIdentity($options, $uuid);
        }
        else {
            // $this->log("create multiple identities");
            $idlist = array_map(function($e) {return $this->prepareIdentity($e, $uuid);}, $options);
        }

        // now add the fields
        $idlist = $this->filterValidObjects($idlist, array("user_uuid", "userid", "mailaddress"));

        // $this->log(json_encode($idlist));

        // verify that we have only new mail addresses
        $mlist = array();
        $sqllst = implode(",", $this->mapToAttribute($idlist, "mailaddress", true));
        $sqlstr = "SELECT mailaddress FROM useridentities WHERE mailaddress IN (".$sqllst.")";

        $sth = $this->db->prepare($sqlstr);
        $res = $sth->execute();

        while ($row = $res->fetchRow()) {
            $mlist[] = $row[0];
        }

        $sth->free();

        // filter existing mail addresses
        if (!empty($mlist)) {
            $idlist = $this->filterValidObjects($idlist, array("mailaddress" => $mlist));
        }

        // $this->log(json_encode($idlist));

        $attr = array("user_uuid", "userid", "mailaddress", "extra");

        $sql = "insert into useridentities (".implode(",", $attr).") values (?, ?, ?, ?)";
        $sth = $this->db->prepare($sql, array("TEXT","TEXT","TEXT","TEXT"));

        foreach ($this->flattenAttributes($idlist, $attr) as $id) {
            // $this->log("insert identity " . json_encode($id));
            $res = $sth->execute($id);
        }

        $sth->free();
    }

    private function createIdentityId(&$identity) {
        if (!array_key_exists("userid", $identity)) {
            $identity["userid"] = $this->generateUuid() . "@eduid.ch";
        }
    }

    private function prepareIdentity($identity, $uuid="") {
        $idfields = array("userid", "mailaddress");

        $id = null;

        if (empty($uuid) ){

            if ($this->user) {
                // use existing user identity
                $uuid = $this->user["user_uuid"];
            }
            else {
                return array();
            }
        }

        if (!empty($identity)) {

            $id = array("user_uuid" => $uuid, "extra" => array());

            $this->createIdentityId($identity);

            foreach ($idfields as $f) {
                if (array_key_exists($f, $identity) && !empty($identity[$f])) {
                    $id[$f] = $identity[$f];
                }
            }

            foreach (array_keys($identity) as $f) {

                if (!empty($identity[$f]) &&
                    !in_array($f, $idfields)) {

                    $id["extra"][$f] = $identity[$f];
                }
            }

            // bring extra field into the correct format
            $id["extra"] = json_encode($id["extra"]);
        }

        return $id;
    }

    // like make sys admin
    public function grantFederationUser() {
        if ($this->user) {
            // find if the selected user is already in the federation list

            $sqlstr = "select user_uuid from federation_users where user_uuid = ?";
            $sth = $this->db->prepare($sqlstr, array("TEXT"));
            $res = $sth->execute(array($this->user["user_uuid"]));

            $row = $res->fetchRow();

            $sth->free();

            // if not, we add the user
            if (!$row) {
                $sqlstr = "insert into federation_users (user_uuid) values (?)";
                $sth = $this->db->prepare($sqlstr, array("TEXT"));
                $res = $sth->execute(array($this->user["user_uuid"]));

                $sth->free();

                return true;

            }
            else {
                // already exists
                return true;
            }
        }
        return false;
    }

    // remove admin privileges
    public function revokeFederationUser() {
        if ($this->user) {
            $sqlstr = "delete from federation_users where user_uuid = ?";
            $sth = $this->db->prepare($sqlstr, array("TEXT"));
            $res = $sth->execute(array($this->user["user_uuid"]));

            $sth->free();
            return true;
        }
        return false;
    }

    public function getFederationUserList() {
        $retval = [];

        $sqlstr = "select u.user_uuid, u.mailaddress, u.extra from useridentities u, federation_users f where f.user_uuid = u.user_uuid";
        $sth = $this->db->prepare($sqlstr);
        $res = $sth->execute();

        while ($row = $res->fetchRow(MDB2_FETCHMODE_ASSOC)) {
            $aProfile = array(
                "user_uuid"   => $row["user_uuid"],
                "mailaddress" => $row["mailaddress"]
            );
            $e = json_decode($row["extra"], true);
            foreach($e as $k => $v) {
                $aProfile[$k] = $v;
            }

            $retval[] = $aProfile;
        }
        $sth->free();

        return $retval;
    }
}

?>
