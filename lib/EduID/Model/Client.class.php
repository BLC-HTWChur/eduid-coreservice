<?php

namespace EduID\Model;

class Client extends DBManager {
    private $client;

    public function getClient() {
        return $this->client;
    }

    public function addClient($info) {
        if (isset($info) && !empty($info)) {
            $this->client = null;
            $this->log(json_encode($info));

            if ($this->checkMandatoryFields($info, array("client_id", "info"))) {

                $this->findClient($info["client_id"]);
                if (!$this->client) {
                    $this->log("create client");

                    $info["client_uuid"] = $this->generateUuid();

                    $sqlstr = "INSERT INTO clients (client_uuid, client_id, info) values (?, ?, ?)";

                    $this->log(json_encode($info));

                    $sth = $this->db->prepare($sqlstr, array("TEXT", "TEXT", "TEXT"));
                    $res = $sth->execute(array($info["client_uuid"],
                                               $info["client_id"], json_encode($info["info"])));
                    $sth->free();

                    $this->client = array("client_uuid" => $info["client_uuid"],
                                          "client_id"   => $info["client_id"],
                                          "info"        => $info["info"]);
                    return true;
                }
            }
        }
        return false;
    }

    public function findClient($id) {
        if (!empty($id) && is_string($id)) {

            $this->client = null;
            $sqlstr = "select client_uuid, client_id, info from clients where client_id = ?";

            $sth = $this->db->prepare($sqlstr, array("TEXT"));
            $res = $sth->execute(array($id));

            if ($row = $res->fetchRow(MDB2_FETCHMODE_ASSOC)) {
                $this->client = array();
                foreach ($row as $k => $v) {
                    if ($k == 'info') {
                        $this->client[$k] = json_decode($v, true);
                    }
                    else {
                        $this->client[$k] = $v;
                    }
                }
                return true;
            }
        }

        return false;
    }

    public function addClientAdmin($userid) {
        if ($this->client && !empty($userid)) {
            $sqlstr = "insert into clientadmins (user_uuid, client_uuid) values (?,?)";

            $sth = $this->db->prepare($sqlstr, array("TEXT", "TEXT"));
            $res = $sth->execute(array($userid, $this->client["client_uuid"]));
            $sth->free();
        }
    }

    public function isClientAdmin($userid) {
        if ($this->client && !empty($userid)) {
            $sqlstr = "select user_uuid from clientadmins where client_uuid = ? and user_uuid = ?";

            $sth = $this->db->prepare($sqlstr, array("TEXT", "TEXT"));
            $res = $sth->execute(array($this->client["client_uuid"], $userid));

            $row = $res->fetchRow();

            $sth->free();

            if ($row) {
                return true;
            }
        }
            return false;
    }

    public function getClientAdminList() {
        if ($this->client) {
            $sqlstr = "select c.user_uuid, u.mailaddress from clientadmins c, useridentities u where c.user_uuid = u.user_uuid and client_uuid = ?";

            $sth = $this->db->prepare($sqlstr, array("TEXT"));
            $res = $sth->execute(array($this->client["client_uuid"]));

            $retval = array();
            while ($row = $res->fetchRow(MDB2_FETCHMODE_ASSOC)) {
                $retval[] = $row;
            }

            $sth->free();

            return $retval;
        }
        return null;
    }

    public function removeClientAdmin($userid) {
        if ($this->client && !empty($userid)) {
            $sqlstr = "delete from clientadmins where user_uuid =? and client_uuid = ?";

            $sth = $this->db->prepare($sqlstr, array("TEXT", "TEXT"));
            $res = $sth->execute(array($userid, $this->client["client_uuid"]));
            $sth->free();
        }
    }

    public function addClientVersion($versionid) {

        if ($this->client &&
            !empty($versionid) &&
            is_string($versionid)) {

            $version = array($this->client["client_id"], $versionid);

            $token = new Token($this->db);
            $vstr  = implode(".", $version);

            if (!$token->findTokens(array("client_id" => $vstr))) {

                $this->log("Token does not exist, create a new one");

                $token->addToken(array(
                    "token_type" => "Bearer",
                    "client_id"  => $vstr,
                    "extra"      => $this->client["info"]
                ), true); // This is a special JTW Bearer Token

                return $token->getToken();
            }
        }
        return null;
    }

    public function getUserClients($userid) {
        if (!empty($userid)) {
            $sqlstr = "select c.client_uuid, c.client_id, c.info from clients c, clientadmins a where c.client_uuid = a.client_uuid and user_uuid = ?";

            $sth = $this->db->prepare($sqlstr, array("TEXT"));
            $res = $sth->execute(array($userid));

            $aRes = array();
            while ($row = $res->fetchRow(MDB2_FETCHMODE_ASSOC)) {
                $aRes[] =  $row;
            }

            $sth->free();

            return $aRes;
        }
        return null;
    }

    public function getAllClients() {
        $sqlstr = "select client_uuid, client_id, info from clients";

        $sth = $this->db->prepare($sqlstr);
        $res = $sth->execute();

        $aRes = array();
        while ($row = $res->fetchRow(MDB2_FETCHMODE_ASSOC)) {
            $aRes[] =  $row;
        }

        $sth->free();

        return $aRes;
    }
}

?>