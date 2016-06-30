<?php

/* *********************************************************************** *
 *
 * *********************************************************************** */

namespace EduID\Model;

use Lcobucci\JWT\Signer as Signer;

class Service extends DBManager{
    protected $service;

    public function __construct($db, $options=array()) {
        parent::__construct($db);

        $this->setOptions($options);

        $this->dbKeys = array(
            "service_uuid"    => "TEXT",
            "name"            => "TEXT",
            "mainurl"         => "TEXT",
            "token_endpoint"  => "TEXT",
            "rsdurl"          => "TEXT",
            "token"           => "TEXT",
            "info"            => "TEXT"
        );

        $this->service = array();
    }

    public function setOptions($options) {}

    public function getUUID() {
        if (array_key_exists("service_uuid", $this->service)) {
            return $this->service["service_uuid"];
        }
        return null;
    }

    public function hasUUID() {
        if (!(is_array($this->service) &&
              array_key_exists("service_uuid", $this->service) &&
              !empty($this->service["service_uuid"]))) {

            return false;
        }
        return true;
    }

    public function addService($serviceDef) {
        $aFields = array_keys($this->dbKeys);

        $aTypes = array();
        $values = array();
        $aNames = array();

        $retval = false;

        if ($this->checkMandatoryFields($serviceDef,
                                        array("service_uuid",
                                              "name",
                                              "mainurl",
                                              "idurl",
                                              "rsdurl"))) {

            foreach ($aFields as $f) {
                if (array_key_exists($f, $serviceDef) &&
                    !empty($serviceDef[$f])) {

                    $aNames[] = $f;
                    $values[] = $serviceDef[$f];
                    $aTypes[] = "TEXT";
                }
            }

            $sqlstr = "INSERT INTO services (" . implode(",", $aNames) .
                      ") VALUES (".
                      implode(",", array_map(function($e){return "?";}, $aNames)) .
                      ")";

            $retval = true;
            $sth = $this->db->prepare($sqlstr, $aTypes);
            $res = $sth->execute($values);

            if(\PEAR::isError($res)) {
                $this->log($res->getMessage());
                $retval = false;
            }

            $sth->free();
        }
        return $retval;
    }

    public function fetchAllRsdServices() {
        $sqlstr = "select service_uuid, rsdurl, token from services where rsdurl is not null";
        $retval = [];

        $sth = $this->db->prepare($sqlstr, []);
        $res = $sth->execute();
        while ($row = $res->fetchRow(MDB2_FETCHMODE_ASSOC)) {
            $retval[] = $row;
        }
        $sth->free();

        return $retval;
    }

    public function findUserServices($user_id) {
        $sqlstr = "SELECT name, mainurl, token_endpoint, info from services s, serviceusers su where su.service_uuid = s.service_uuid and su.user_uuid = ?";

        $retval = array();
        if (!empty($user_id)) {

            $sth = $this->db->prepare($sqlstr, array("TEXT"));
            $res = $sth->execute(array($user_id));

            while ($row = $res->fetchRow(MDB2_FETCHMODE_ASSOC)) {
                $service = array();
                foreach ($row as $f => $v) {
                    if (isset($v) && !empty($v)) {
                        switch ($f) {
                            case "token":
                            case "info":
                                if (!empty($v)) {
                                    $service[$f] = json_decode($v, true);
                                }
                                else {
                                    $service[$f] = array();
                                }
                                break;
                            default:
                                $service[$f] = $v;
                                break;
                        }
                    }
                }

                if (!empty($service)) {
                    array_push($retval, $service);
                }
            }
            $sth->free();
        }

        return $retval;
    }

    /**
     * find service by service id
     *
     * @public function findServiceById($id)
     *
     * @param string $id : the uuid of the requested service.
     * @return bool : true - id has been found, otherwise not.
     */
    public function findServiceById($service_id) {
        if (!empty($service_id)) {
            return $this->findService(array("service_uuid" => $service_id));
        }
        return false;
    }

    /**
     * find service by service URL
     *
     * @public function findServiceByURI($id)
     *
     * @param string $uri : the uri of the requested service.
     * @return bool : true - id has been found, otherwise not.
     *
     * The function checks if the provided endpoint is either the main service URL
     * or a token endpoint. In both cases the service will get loaded.
     */
    public function findServiceByURI($service_uri) {
        if (!empty($service_uri)) {
            return $this->findService(array("mainurl"        => $service_uri,
                                            "token_endpoint" => $service_uri));
        }
        return false;
    }

    public function findServiceByName($name) {
        if (!empty($name)) {
            return $this->findService(array("name" => $name));
        }
        return false;
    }

    public function findServiceByNamePart($name) {
        if (!empty($name)) {
            return $this->findService(array("name" => $name, "like" => "both"));
        }
        return false;
    }

    public function getTokenEndpoint() {
        if (!empty($this->service)) {
            return $this->service["token_endpoint"];
        }
        return null;
    }

    public function getSignKey() {
        if (!empty($this->service)) {
            return $this->service["token"]["mac_key"];
        }
        return null;
    }

    public function getTokenSigner() {
        // choose token alg
        $retval = null;

        list($alg, $level) = explode("S", $this->service["token"]["mac_algorithm"]);

        switch ($alg) {
            case "H": $alg = "Hmac"; break;
            case "R": $alg = "Rsa"; break;
            case "E": $alg = "Ecdsa"; break;
            default: $alg = ""; break;
        }
        switch ($level) {
            case "256":
            case "384":
            case "512":
                break;
            default: $level = ""; break;
        }

        if (!empty($alg) && !empty($level)) {
            $signerClass = "Lcobucci\\JWT\\Signer\\" .$alg . "\\SHA" . $level;
            $retval = new $signerClass();
        }

        return $retval;
    }

    public function trackUser($options) {
        if (!empty($this->service) &&
            !empty($options) &&
            array_key_exists("user_uuid", $options)) {

            if (!array_key_exists("issued_at", $options)) {
                $options["issued_at"] = now();
            }

            $types = ["TEXT", "TEXT", "TEXT"];
            $values = [$options["issued_at"],
                       $options["user_uuid"],
                       $this->service["service_uuid"]];

            $sqlstr = "INSERT INTO serviceusers (last_access, user_uuid, service_uuid) values (?, ?, ?)";

            // did the user previously access the service
            if ($this->isServiceUser($options["user_uuid"])) {
                $sqlstr = "update serviceusers set last_access = ? where user_uuid = ? and service_uuid = ?";
            }
            $sth = $this->db->prepare($sqlstr, $types);
            $res = $sth->execute($values);
            $sth->free();
        }
    }

    public function lastRSDUpdate() {
        $retval = 0; // EPOCH
        if (!empty($this->service) &&
            array_key_exists("service_uuid", $this->service) &&
            !empty($this->service["service_uuid"])) {

            $sqlstr = "select last_update from serviceprotocols where service_uuid = ?";
            $sth = $this->db->prepare($sqlstr, ["TEXT"]);
            $res = $sth->execute([$this->service["service_uuid"]]);

            if ($row = $res->fetchRow()) {
                $retval = $row[0];
            }
            $sth->free();
        }
        return $retval;
    }

    // this method is used by the cron script
    public function updateServiceRSD($rsdString) {
        if (!empty($rsdString) &&
            !empty($this->service) &&
            array_key_exists("service_uuid", $this->service) &&
            strlen($this->service["service_uuid"])) {

            // test if the RSD is already present
            if ($this->lastRSDUpdate() > 0) {
                $this->log("update");
                $sqlstr = "update serviceprotocols set rsd = ?, last_update = ? where service_uuid = ?";
            }
            else {
                $this->log("insert");
                $sqlstr = "insert into serviceprotocols (rsd, last_update, service_uuid) values (?,?,?)";
            }

            $now = time();
            $sth = $this->db->prepare($sqlstr, ["TEXT", "INTEGER", "TEXT"]);
            $res = $sth->execute([$rsdString, $now, $this->service["service_uuid"]]);

            $sth->free();

            // extract and update the protocols
            $rsd  = json_decode($rsdString, true);
            $apis = $rsd["apis"];
            $rsdNames = array_keys($apis);
            $extNames = [];

            // find existing protocols
            $sqlstr = "select rsd_name from protocolnames where service_uuid = ?";

            $sth = $this->db->prepare($sqlstr, ["TEXT"]);

            $res = $sth->execute([$this->service["service_uuid"]]);

            while ($row = $res->fetchRow()) {
                $extNames[] = $row[0];
            }

            $sth->free();

            // add all non existing names
            $newsql = "insert into protocolnames (service_uuid, rsd_name) values (?, ?)";
            $sthi= $this->db->prepare($newsql, ["TEXT", "TEXT"]);

            foreach ($rsdNames as $pname)  {
                if (!in_array($pname, $extNames)) {
                    $sthi->execute([$this->service["service_uuid"], $pname]);
                }
            }

            $sthi->free();

            // remove all non-existing names
            $delsql = "delete from protocolnames where service_uuid = ? and rsd_name = ?";
            $sthd= $this->db->prepare($delsql, ["TEXT", "TEXT"]);

            foreach ($extNames as $pname)  {
               if (!in_array($pname, $rsdNames)) {
                    $sthd->execute([$this->service["service_uuid"], $pname]);
                }
            }

            $sthd->free();
        }
    }

    private function findService($options=array()) {
        $this->service = array();
        $sqlstr = "SELECT service_uuid, name, mainurl, token_endpoint, rsdurl, info, token from services where ";

        if (!empty($options)) {
            $filter = array();
            $types  = array();
            $values = array();
            foreach (array("service_uuid", "mainurl", "token_endpoint", "rsdurl", "name") as $k) {
                if (array_key_exists($k, $options)) {
                    $lk  = $options[$k];
                    $op = $k . ' = ?';

                    if (array_key_exists("like", $options) &&
                        $this->dbKeys[$k] == "TEXT") {

                        $op = $k . ' LIKE ?';
                        if ($options["like"] == "left" ||
                            $options["like"] == "both") {
                            $lk = $lk . "%";
                        }
                        if ($options["like"] == "left" ||
                            $options["like"] == "both") {
                            $lk = "%" . $lk;
                        }
                    }
                    $filter[] = $op;
                    $types[]  = $this->dbKeys[$k];
                    $values[] = $lk;
                }
            }
            if (!empty($filter)) {
                $sqlstr .= implode(" OR ", $filter);

                $this->log($sqlstr);
                $this->log(implode(" ", $values));

                $sth = $this->db->prepare($sqlstr, $types);
                $res = $sth->execute($values);

                if ($row = $res->fetchRow(MDB2_FETCHMODE_ASSOC)) {
                    $service = array();
                    foreach ($row as $f => $v) {
                        if (!empty($v)) {
                            switch ($f) {
                                case "token":
                                case "info":
                                    if (!empty($v)) {
                                        $service[$f] = json_decode($v, true);
                                    }
                                    else {
                                        $service[$f] = array();
                                    }
                                    break;
                                default:
                                    $service[$f] = $v;
                                    break;
                            }
                        }
                    }

                    if (!empty($service)) {
                        $this->service = $service;
                    }
                }

                $sth->free();
            }

            if (!empty($this->service)) {
                return true;
            }
        }

        return false;
    }

    private function isServiceUser($user) {
        $sqlstr = "select * from serviceusers where user_uuid = ? and service_uuid = ?";
        $sth = $this->db->prepare($sqlstr, ["TEXT", "TEXT"]);
        $res = $sth->execute([$user, $this->service["service_uuid"]]);
        $row = $res->fetchRow();
        $sth->free();
        return !empty($row);
    }
}

?>
