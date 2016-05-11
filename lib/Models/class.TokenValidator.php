<?php
/* *********************************************************************** *
 *
 * *********************************************************************** */

require_once("Models/class.EduIDValidator.php");

class OAuth2TokenValidator extends EduIDValidator {

    private $token;
    private $token_type;  // oauth specific

    private $token_info;  // provided by the client
    private $token_data;  // provided by the DB

    private $accept_list = array();

    public function __construct($db) {
        parent::__construct($db);
        $this->db = $db;
        $this->accept_list = array("Bearer", "MAC");

        // check for the authorization header
        $headers = getallheaders();

        if (array_key_exists("Authorization", $headers) &&
            isset($headers["Authorization"]) &&
            !empty($headers["Authorization"]))
        {
            $authheader = $headers["Authorization"];
            $aHeadElems = explode(' ', $authheader);

            $this->token_type = $aHeadElems[0];
            $this->token  = $aHeadElems[1];
        }
    }

    public function ignoreTokenTypes($typeList) {
        if (isset($typeList)) {
            if (!is_array($typeList)) {
                $typeList = array($typeList);
            }
            foreach ($typeList as $tokenType) {
                $k = array_search($tokenType);
                array_splice($this->acceptTypes, $k, 1);
            }
        }
    }

    public function acceptTokenTypes($typeList) {

        if (isset($typeList)) {
            if (!is_array($typeList)) {
                $typeList = array($typeList);
            }

            foreach ($typeList as $tokenType) {

                if (!in_array($tokenType, $this->accept_list)) {
                    $this->accept_list[] = $tokenType;
                }
            }
        }
    }

    public function getToken() {
        return $this->token_data;
    }

    protected function validate() {
        $this->valid = false;

        if (!isset($this->token_type) ||
            empty($this->token_type)) {

            // nothin to validate
            return false;
        }

        if (!isset($this->token) ||
            empty($this->token)) {

            // no token to validate
            return false;
        }

        if (!in_array($this->token_type, $this->accept_list)) {

            // the script does not accept the provided token type;
            return false;
        }

        // This will transform Bearer Tokens accordingly
        $this->extractToken();

        if (!isset($this->token_info["kid"]) ||
            empty($this->token_info["kid"])) {

            // no token id
            return false;
        }

        $this->findToken();

        if (!isset($this->token_key)) {
            // token not found
            return false;
        }

        if ($this->token_data["consumed"] > 0) {
            return false;
        }

        if ($this->token_data["expires"] > 0 &&
            $this->token_data["expires"] < time()) {

            // consume token
            $this->consumeToken();
            return false;
        }

        // at this point we have already confirmed or rejected any
        // the bearer token
        if ($this->token_type != "Bearer") {
            // Run MAC compoarison

            if (!isset($this->token_info["mac"]) ||
                empty($this->token_info["mac"])) {

                // token is not signed ignore
                return false;
            }

            // check sequence
            if (!isset($this->token_info["ts"]) ||
                empty($this->token_info["ts"])) {

                // missing timestamp
                return false;
            }

            if ($this->token_data["seq_nr"] > 0 &&
                (!isset($this->token_info["seq_nr"]) ||
                empty($this->token_info["seq_nr"]))) {

                // no sequence provided
                $this->consumeToken();
                return false;
            }

            if ($this->token_info["seq-nr"] != $this->token_data["seq-nr"]) {
                // out of bounds
                $this->consumeToken();
                return false;
            }

            if ($this->token_type == "MAC") {
                if ($this->token_data["seq-nr"] == 1 &&
                    (!isset($this->token_info["access_token"]) ||
                     empty($this->token_info["access_token"]))) {

                    if ($this->token_info["access_token"] != $this->token_key) {
                        // invalid token during handshake
                        return false;
                    }
                }
            }

            // at this point we have to increase the sequence
            $this->sequenceStep();

            // first line is METHOD REQPATH+GETPARAM PROTOCOL VERSION
            // protocol version is (HTTP/1.1 or HTTP/2.0)
            $payload =  $_SERVER['REQUEST_METHOD'] . " " .
                        $_SERVER['REQUEST_URI'] . " " .
                        $_SERVER['SERVER_PROTOCOL']. "\n";

            $payload .=  $this->token_info["ts"] ."\n";

            if (array_key_exists("h", $this->token_info)) {
                $aTokenHeaders  = explode(":");
                $aRequestHeader = getallheaders();

                foreach ($aTokenHeaders as $header) {
                    switch($header) {
                        case "host":
                            $payload .= $_SERVER[HTTP_HOST] ."\n";
                            break;
                        case "client":
                            if (!empty($this->token_data["client_id"])) {
                                $payload .= $this->token_data["client_id"] ."\n";
                            }
                            break;
                        default:
                            if (array_key_exists($header, $aRequestHeader)) {
                                $payload .= $aRequestHeader[$header] . "\n";
                            }
                            else {
                                // according to RFC add NULL Content
                                $payload .= "\n";
                            }
                            break;
                    }
                }
            }
            else {
                $payload .= $_SERVER[HTTP_HOST] ."\n";
            }

            $testMac = hash_hmac("sha1", $payload, $this->token_data["mac-key"]);

            if ($testMac != $this->token_info["mac"]) {

                // bad mac
                return false;
            }
        }

        $this->valid = true;
        return true;
    }

    private function extractToken() {
        $this->token_info = array();

        if ($this->token_type == "MAC" ||
            $this->token_type == "Request") {

            $aTokenItems = explode(',', $this->token);
            foreach ($aTokenItems as $item)
            {
                $iKO = explode("=", $item);
                $this->token_info[$iKO[0]] = $iKO[1];
            }
        }
        else if ($this->token_type == "Bearer") {
            $this->token_info["kid"] = $this->token;
        }
    }

    private function findToken() {
        $aDBFields = array(
            "kid", "access_key", "mac_key", "mac_algorithm",
            "seq_nr", "expires", "consumed", "max_seq",
            "user_uuid", "service_uuid", "client_id"
        );

        $sqlstr = "select " . implode(", ", $aDBFields). " from tokens where token_type = ? and kid = ? and consumed = 0";

        $this->log($sqlstr);

        // for bearer and session tokens the token_id is the token_key
        $sth = $this->db->prepare($sqlstr, array("TEXT", "TEXT"));
        $res = $sth->execute(array($this->token_type,
                                   $this->token_info["kid"]));

        if (PEAR::isError($res)) {
            $this->log($res->getMessage());
        }

        $this->token_data = null;
        $this->token_key  = null;

        if ($row = $res->fetchRow(MDB2_FETCHMODE_ASSOC)) {
            $this->token_data = array();

            foreach ($row as $key => $value) {
                $this->token_data[$key] = $value;
            }

            $this->token_key = $this->token_data["access_token"];
            $this->token_data["type"] = $this->token_type;
        }

        $sth->free();
    }

    private function consumeToken() {
        $key = $this->token_key;

        $sqlstr = "update table tokens set consumed = ? where kid = ?";
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
        $sqlstr = "update table tokens set consumed = ? where parent_kid = ?";
        $sth = $this->db->prepare($sqlstr, array("INTEGER", "TEXT"));
        $res = $sth->execute(array($now,
                                   $key));

        if (PEAR::isError($res))
        {
            $this->error = $res->getMessage();
        }
        $sth->free();
    }

    private function sequenceStep() {
        $sqlstr = "update table tokens set seq_nr = ? where kid = ?";
        $sth = $this->db->prepare($sqlstr, array("INTEGER", "TEXT"));
        $res = $sth->execute(array($this->token_data["seq_nr"] + 1,
                                   $this->token_key));

        if (PEAR::isError($res))
        {
            $this->error = $res->getMessage();
        }
        $sth->free();
    }
}

?>
