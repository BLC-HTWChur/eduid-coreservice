<?php
/* *********************************************************************** *
 *
 * *********************************************************************** */

require_once("Models/class.DBManager.php");

class TokenManager extends DBManager{
    protected $root_token;
    protected $token;

    protected $root_token_type;   // service, client, user
    protected $token_type = "Bearer";  // service, client, user
    protected $uuid;        // respective uuid

    protected $mac_algorithm  = "hmac-sha-1";
    protected $expires_in     = 0;
    protected $max_seq        = 0;
    protected $use_sequence   = false;
    protected $dbKeys;

    protected $tokenLength;

    public function __construct($db, $options=array()) {
        parent::__construct($db);

        $this->setOptions($options);

        $this->dbKeys = array(
            "kid"           => "TEXT",
            "token_type"    => "TEXT",
            "access_key"    => "TEXT",
            "mac_key"       => "TEXT",
            "mac_algorithm" => "TEXT",
            "user_uuid"     => "TEXT",
            "service_uuid"  => "TEXT",
            "client_id"     => "TEXT",
            "parent_kid"    => "TEXT",
            "scope"         => "TEXT",
            "extra"         => "TEXT",
            "scope"         => "TEXT",
            "seq_nr"        => "INTEGER",
            "expires"       => "INTEGER",
            "consumed"      => "INTEGER",
            "max_seq"       => "INTEGER",
        );

        // this should be set via the options
        $this->tokenLength = array(
            "access_key" => 50,
            "mac_key"    => 100,
            "kid"        => 10
        );
    }

    public function setOptions($options) {
        if (isset($options) && !empty($options)) {
            if (array_key_exists("expires_in", $options)) {
                $this->expires_in = $options["expires_in"];
            }
            if (array_key_exists("mac_algorithm", $options)) {
                $this->mac_algorithm = $options["mac_algorithm"];
            }
            if (array_key_exists("use_sequence", $options)) {
                $this->use_sequence = $options["use_ssequence"]; // evaluates as Boolean
            }
            if (array_key_exists("max_sequence", $options)) {
                $this->max_seq = $options["max_sequence"];
            }
            if (array_key_exists("type", $options)) {
                $this->token_type = $options["type"];
            }
            if (array_key_exists("token_type", $options)) {
                $this->token_type = $options["token_type"];
            }
        }
    }

    /**
     * @function setRootToken()
     *
     * used by the OAuth Service to pass data from the TokenValidator
     */
    public function setRootToken($token) {
        if (isset($token) && !empty($token)) {

            $this->root_token = $token;
            $this->root_token_type = $token["type"];
            if (!isset($this->token_type) ||
                empty($this->token_type)) {

                $this->token_type = $this->root_token_type;
            }
         }
    }

    public function getToken() {
        return $this->token;
    }

    /**
     * change the type for new tokens
     */
    public function setTokenType($type="Bearer") {
        if (isset($type) && !empty($type)) {
            $this->token_type = $type;
        }
    }

    public function useSequence() {
        $this->use_sequence = true;
    }

    public function setMaxSeq($maxseq) {
        $this->use_sequence = true;
        if (isset($maxseq) && $maxseq > 0) {
            $this->max_seq = $maxseq;
        }
    }

    public function setToken($token) {
        $aValid = array("type");
        foreach ($this->dbKeys as $k => $v) {
            $aValid[] = $k;
        }

        $bOK = true;
        foreach (array_keys($token) as $tk) {
            if (!in_array($tk, $aValid)) {
                $bOK = false;
                break;
            }
        }

        if ($bOK){
            if (!array_key_exists('token_type', $token)) {
                $token['token_type'] = $token["type"];
            }

            $this->token = $token;
            $this->findRootToken();
        }
    }

    /**
     * function addToken
     *
     * initialise a token.
     *
     * this function sets external information such as the user_uuid etc.
     *
     * Allowed token values are
     * * type | token_type
     * * user_uuid
     * * client_id
     * * service_uuid
     * * scope
     * * extra
     * * mac_algorithm as a client can set a preference
     *
     * At least one of user_uuid, service_uuid, or client_id MUST be set.
     */
    public function addToken($token=array()) {
        $type = $this->token_type;
        if (array_key_exists("type", $token)) {
            $type = $token["type"];
        }
        if (array_key_exists("token_type", $token)) {
            $type = $token["token_type"];
        }

        $newToken = array();

        if (isset($this->root_token)) {
            if (!isset($type) || empty($type)) {
                $type = $this->root_token_type;
            }
            $newToken["parent_kid"] = $this->root_token["kid"];
        }

        if (isset($type) &&
            !empty($type) &&
            (
                (array_key_exists("user_uuid", $token) && !empty($token["user_uuid"])) ||
                (array_key_exists("service_uuid", $token) && !empty($token["service_uuid"])) ||
                (array_key_exists("client_id", $token) && !empty($token["client_id"]))
            )) {

            $newToken["token_type"] = $type;
            if (!$this->use_sequence) {
                $newToken["seq_nr"] = 0;
            }
            else if (isset($this->max_seq) &&
                     $this->max_seq > 0) {
                $newToken["max_seq"] = $this->max_seq;
            }

            if (isset($this->expires_in) &&
                     $this->expires_in > 0) {
                $now = time();
                $newToken["expires"] = $now + $this->expires_in;
            }

            $newToken["access_key"] = $this->randomString($this->tokenLength["access_key"]);
            if ($type == "Bearer") {
                $newToken["kid"] = $newToken["access_key"];
            }
            else {
                $newToken["kid"] = $this->randomString($this->tokenLength["kid"]);

                if (isset($this->mac_algorithm) &&
                    !empty($this->mac_algorithm)) {
                    $newToken["mac_algorithm"] = $this->mac_algorithm;
                }

                $newToken["mac_key"] = $this->randomString($this->tokenLength["mac_key"]);
            }

            foreach(array("user_uuid",
                          "client_id",
                          "service_uuid",
                          "scope",
                          "extra",
                          "mac_algorithm") as $key) {

                // inherit different approaches from the root token
                if (isset($this->root_token) &&
                    array_key_exists($key, $this->root_token) &&
                    isset($this->root_token[$key]) &&
                    !empty($this->root_token[$key])) {
                    $newToken[$key] = $this->root_token[$key];
                }

                if (array_key_exists($key, $token) &&
                    isset($token[$key]) &&
                    !empty($token[$key])) {

                    $newToken[$key] = $token[$key];
                }
            }

            // store the data into the database
            $aNames = array();
            $aValues = array();
            $aPH = array();
            $aTypes = array();

            foreach ( $this->dbKeys as $k => $v) {
                if (array_key_exists($k, $newToken)) {
                    $aTypes[] = $v;
                    $aNames[] = $k;
                    $aValues[] = $newToken[$k];
                    $aPH[] = '?';
                }
            }

            if (!empty($aNames)) {
                $sqlstr = "INSERT INTO tokens (".implode(",", $aNames).") VALUES (".implode(",", $aPH).")";
                $sth = $this->db->prepare($sqlstr, $aTypes);
                $res = $sth->execute($aValues);
                if(PEAR::isError($res)){
                    $this->log($res->getMessage() . " '" . $sqlstr . "' " . implode(", ", $aValues));
                }
                else {
                    $this->token = $newToken;
                    if (isset($this->expires_in) &&
                        $this->expires_in > 0) {
                        $this->token["expires_in"] = $this->expires_in;
                    }
                }
                $sth->free();

            }
        }
    }

    public function invalidateToken() {
        $this->consumeToken();
    }

    public function consumeRootToken() {
        if (isset($this->token)) {
            $this->consume_token_db($this->token["parent_kid"]);
        }
    }

    public function consumeToken() {
        if (isset($this->token)) {
            $this->consume_token_db($this->token["kid"]);
        }
    }

    public function findToken($token_id, $type="", $userUuid="", $active=false) {
        $sqlstr = "select token_key, user_uuid, client_id, token_parent, ttl, consumed, extra, sequence, mac_key, domain, service_uuid, token_type from tokens where and token_id = ?";

        if (isset($token_id) && !empty($token_id)) {
            $aTypes   = array("TEXT");
            $aValues  = array($token_id);

            if (isset($type) && !empty($type)) {
                $aTypes[] = $this->dbKeys["token_type"];
                $aValue[] = $type;
                $sqlstr .= " AND token_type = ?";
            }
            else if (isset($this->token_type) && !empty($this->token_type)) {
                $aTypes[] = $this->dbKeys["token_type"];
                $aValue[] = $this->token_type;
                $sqlstr .= " AND token_type = ?";
            }

            $aValues[] = $type;

            if (isset($userUuid) && !empty($userUuid)) {
                $sqlstr .= " AND user_uuid = ?";
                $aTypes[] = $this->dbKeys["user_uuid"];
                $aValues[] = $userUuid;
            }

            if (isset($active) && $active) {
                $sqlstr .= " AND consumed = 0";
            }

            if (isset($this->root_token)) {
                $sqlstr .= " AND parent_kid = ?";
                $aTypes[] = $this->dbKeys["parent_kid"];
                $aValues[] = $this->root_token["kid"];
            }

            // for bearer and session tokens the token_id is the token_key
            $sth = $this->db->prepare($sqlstr, $aTypes);
            $res = $sth->execute(array($this->token_type,
                                       $token_id));

            $this->token = null;

            if ($row = $res->fetchRow()) {
                $this->token = array();

                $this->token["kid"]               = $token_id;
                $this->token["token_type"]        = $row[11];

                $this->token["token_key"]         = $row[0];
                $this->token["user_uuid"]         = $row[1];
                $this->token["client_id"]         = $row[2];
                $this->token["parent_key"]        = $row[3];
                $this->token["ttl"]               = $row[4];
                $this->token["consumed"]          = $row[5];
                $this->token["extra"]             = $row[6];
                $this->token["seq_nr"]            = $row[7];
                $this->token["mac_key"]           = $row[8];
                $this->token_data["domain"]       = $row[9];
                $this->token_data["service_uuid"] = $row[10];
            }

            $sth->free();

            $this->findRootToken();
        }

        return (isset($this->token) && !empty($this->token));
    }

    public function findRootToken() {
        if (isset($this->token) &&
            array_key_exists("parent_kid", $this->token) &&
            isset($this->token["parent_kid"]) &&
            !empty($this->token["parent_kid"])) {

            $sqlstr = "select token_key, user_uuid, client_id, token_parent, ttl, consumed, extra, sequence, mac_key, domain, service_uuid, token_type from tokens where and token_id = ?";

            $aTypes   = array("TEXT");
            $aValues  = array($this->token["parent_kid"]);

            $sth = $this->db->prepare($sqlstr, $aTypes);
            $res = $sth->execute(array($this->token_type,
                                       $token_id));

            $this->root_token = null;

            if ($row = $res->fetchRow()) {
                $this->root_token = array();

                $this->root_token["kid"]          = $token_id;
                $this->root_token["token_type"]   = $row[11];
                $this->root_token_type            = $row[11];


                $this->root_token["token_key"]    = $row[0];
                $this->root_token["user_uuid"]    = $row[1];
                $this->root_token["client_id"]    = $row[2];
                $this->root_token["parent_key"]   = $row[3];
                $this->root_token["ttl"]          = $row[4];
                $this->root_token["consumed"]     = $row[5];
                $this->root_token["extra"]        = $row[6];
                $this->root_token["seq_nr"]       = $row[7];
                $this->root_token["mac_key"]      = $row[8];
                $this->root_token["domain"]       = $row[9];
                $this->root_token["service_uuid"] = $row[10];
            }

            $sth->free();
        }
    }

    /**
     * use this function if you need to add a different token information
     */
    public function prepareSubToken($type) {
        if (isset($type) &&
            !empty($type) &&
            isset($this->token)) {

            $tm = new TokenManager($this->db);

            $tm->setRootToken($this->token);
            $tm->setTokenType($type);

            return $tm;
        }
        return null;
    }

    /**
     * shortcut for Refresh Tokens
     */
    public function addSubToken($type) {
        $tm = $this->prepareSubToken($type);
        if (isset($tm)) {
            $tm->addToken(array());
        }
        return $tm;
    }

    public function eraseToken() {
        if (isset($this->token)) {
            $sqlstr = "DELETE FROM tokens WHERE kid = ?";
            $sth = $this->db->prepare($sqlstr, array("TEXT"));
            $res = $sth->execute(array($this->token["kid"]));
            if (PEAR::isError($res)) {
                $this->log($res->getMessage());
            }
            $sth->free();
        }
    }

    private function consume_token_db($key) {
        $sqlstr = "update table tokens set consumed = ? where token_key = ?";
        $sth = $this->db->prepare($sqlstr, array("INTEGER", "TEXT"));

        $now = time();

        $res = $sth->execute(array($now,
                                   $key));

        if (PEAR::isError($res))
        {
            $this->error = $res->getMessage();
        }
        $sth->free();

        // consume all children
        $sqlstr = "update table tokens set consumed = ? where token_parent = ? and token_type <> 'Refresh'";

        $sth = $this->db->prepare($sqlstr, array("INTEGER", "TEXT"));
        $res = $sth->execute(array($now,
                                   $key));

        if (PEAR::isError($res))
        {
            $this->error = $res->getMessage();
        }
        $sth->free();
    }
}
?>
