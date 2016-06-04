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

    public function findUserServices($user_id) {
        $sqlstr = "SELECT service_uuid, name, mainurl, token_endpoint, info from services s, serviceusers su where su.service_uuid = s.service_uuid and su.user_uuid = ?";
    
        $retval = array();
        if (isset($user_id) && !empty($user_id)) {
    
            $sth = $this->db->prepare($sqlstr, array("TEXT"));
            $res = $sth->execute(array($uuid));

            while ($row = $res->fetchRow(MDB2_FETCHMODE_ASSOC)) {
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
        if (isset($service_id) && !empty($service_id)) {
            return $this->findService(array("service_uuid" => $service_id));
        }
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
        if (isset($service_uri) && !empty($service_uri)) {
            return $this->findService(array("mainurl"        =>$service_uri,
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
        
            if (isset($this->service)) {
                return true;
            }
        }
        
        return false;
    }

    public function getTokenEndpoint() {
        if (isset($this->service)) {
            return $this->service["token_endpoint"];
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
            $signerClass = "Lcobucci\\JWT\\Signer\\" .$alg . "\\SHA" . $level;
            $retval = new $signerClass();
        }

        return $retval;
    }
}
?>
