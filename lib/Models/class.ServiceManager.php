<?php
/* *********************************************************************** *
 *
 * *********************************************************************** */

require_once("Models/class.DBManager.php");

use Lcobucci\JWT\Signer as Signer;

class ServiceManager extends DBManager{
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
    }

    public function setOptions($options) {}

    public function findUserServices($user_id) {}

    public function findServiceByID($service_id) {
        if (isset($service_id) && !empty($service_id)) {
            $this->findService(array("service_uuid" => $service_id));
        }
    }
    public function findServiceURI($service_uri) {
        if (isset($service_uri) && !empty($service_uri)) {
            $this->findService(array("mainurl"        =>$service_uri,
                                     "token_endpoint" => $service_uri));
        }
    }

    private function findService($options=array()) {
        $this->service = null;
        $sqlstr = "SELECT service_uuid, name, mainurl, token_endpoint, rsdurl, info, token from services where ";

        if (isset($options) && !empty($options)) {
            $filter = array();
            $types  = array();
            $values = array();
            foreach (array("service_uuid", "mainurl", "token_endpoint") as $k) {
                if (array_key_exisis($k, $options)) {
                    array_push($filter, $k . '= ?');
                    array_push($types, $this->dbKeys[$k]);
                    array_push($values, $options[$k]);
                }
            }
            if (!empty($filter)) {
                $sqlstr .= implode(" OR ", $filter);

                $sth = $this->db->prepare($sqlstr, array("TEXT"));
                $res = $sth->execute(array($uuid));

                if ($row = $res->fetchRow(MDB2_FETCHMODE_ASSOC)) {
                    $service = array();
                    foreach ($row as $f => $v) {
                        if (isset($v) && !empty($v)) {
                            switch ($f) {
                                case "extra":
                                case "info":
                                    $service[$f] = json_decode($v);
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
        }
    }

    public function getTokenEndpoint() {
        if (isset($this->service)) {
            return $this->service["token"]["key"];
        }
        return null;
    }

    public function getSignKey() {
        if (isset($this->service)) {
            return $this->service["token"]["key"];
        }
        return null;
    }

    public function getTokenSigner() {
        // choose token alg
        $retval = null;

        list($alg, $level) = explode("S", $this->service["token"]["alg"]);

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
            $signerClass = "Signer\\" .$alg . "\\Sha" . $level;
            $retval = new $signerClass();
        }

        return $retval;
    }
}
?>
