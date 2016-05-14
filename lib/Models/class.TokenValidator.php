<?php
/* *********************************************************************** *
 *
 * *********************************************************************** */

require_once("Models/class.EduIDValidator.php");

class OAuth2TokenValidator extends EduIDValidator {

    private $token;
    private $token_type;  // oauth specific
    private $token_key;

    private $token_info;  // provided by the client
    private $token_data;  // provided by the DB

    private $accept_type = array();
    private $accept_list = array();

    public function __construct($db) {
        parent::__construct($db);

        // header level
        $this->accept_list = array("Bearer",
                                   "MAC",
                                   "Basic");
        // token level
        $this->accept_type = array("Bearer",
                                   "MAC",
                                   "Grant",
                                   "Client");

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

    public function getTokenIssuer($type) {
        require_once("Models/class.TokenManager.php");

        $tm = new TokenManager($this->db, array("type"=> $type));
        $tm->setRootToken($this->token);

        return $tm;
    }

    public function getTokenUser() {
        if (isset($this->token) &&
            !empty($this->token) &&
            !empty($this->token["user_uuid"])) {

            require_once("Models/class.UserManager.php");

            $um = new UserManager($this->db);
            if ($um->findByUUID($this->token["user_uuid"])) {
                return $um;
            }
        }

        return null;
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

    public function resetAcceptedTokens($typeList){
        if (isset($typeList) && !empty($typeList)) {
            if (!is_array($typeList)) {
                $typeList = array($typeList);
            }

            $this->accept_list = $typeList;
        }
    }

    public function setAcceptedTokenTypes($typeList){
        if (isset($typeList) && !empty($typeList)) {
            if (!is_array($typeList)) {
                $typeList = array($typeList);
            }

            $this->accept_type = $typeList;
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
            $this->log("no token type available");
            return false;
        }

        if (!isset($this->token) ||
            empty($this->token)) {

            // no token to validate
            $this->log("no raw token available");
            return false;
        }

        if (!empty($this->accept_list) &&
            !in_array($this->token_type, $this->accept_list)) {

            // the script does not accept the provided token type;
            $this->log("token type not acecpted available");

            return false;
        }

        // This will transform Bearer Tokens accordingly
        $this->extractToken();

        if (!isset($this->token_info["kid"]) ||
            empty($this->token_info["kid"])) {

            $this->log("no token id available");
            // no token id
            return false;
        }

        $this->findToken();

        if (!isset($this->token_key)) {
            // token not found
            $this->log("no token available");
            return false;
        }

        if ($this->token_data["consumed"] > 0) {
            $this->log("token already consumed");
            return false;
        }

        if (in_array($this->token_type, $this->accept_type))

        if ($this->token_data["expires"] > 0 &&
            $this->token_data["expires"] < time()) {

            // consume token
            $this->consumeToken();
            $this->log("token expired - consume it!");
            return false;
        }

        // at this point we have already confirmed or rejected any
        // the bearer token
        if ($this->token_type != "Bearer") {

            if ($this->token_type == "MAC") {
                // Run MAC compoarison

                if (!isset($this->token_info["mac"]) ||
                    empty($this->token_info["mac"])) {

                    // token is not signed ignore
                    $this->log("token is not signed ignore");
                    return false;
                }

                // check sequence
                if (!isset($this->token_info["ts"]) ||
                    empty($this->token_info["ts"])) {

                    // missing timestamp
                    $this->log("missing timestamp");

                    return false;
                }

                if ($this->token_data["seq_nr"] > 0 &&
                    (!isset($this->token_info["seq_nr"]) ||
                    empty($this->token_info["seq_nr"]))) {

                    // no sequence provided
                    $this->log("missing seq_nr but requested");

                    $this->consumeToken();
                    return false;
                }

                if ($this->token_data["seq_nr"] > 0 &&
                    $this->token_info["seq_nr"] != $this->token_data["seq_nr"]) {
                    // out of bounds
                    $this->consumeToken();
                    $this->log("token sequence out of bounds");

                    return false;
                }

                if ($this->token_data["seq_nr"] == 1 &&
                    (!isset($this->token_info["access_token"]) ||
                     empty($this->token_info["access_token"]))) {

                    if ($this->token_info["access_token"] != $this->token_key) {
                        // invalid token during handshake
                        $this->log("bad token handshake");

                        return false;
                    }
                }

                // at this point we have to increase the sequence
                if ($this->token_data["seq_nr"] > 0) {
                    $this->sequenceStep();
                    // $this->log("seq step");
                }

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
                    $payload .= $_SERVER["HTTP_HOST"] ."\n";
                }

                $testMac = hash_hmac("sha1", $payload, $this->token_data["mac_key"]);

                if ($testMac != $this->token_info["mac"]) {

                    // bad mac
                    $this->log("mac mismatch " . $testMac . " <> " . $this->token_info["mac"]);
                    return false;
                }
            }
            else {
                if (!isset($this->token_info["access_key"])) {
                    $this->log("missing client secret");
                    return false;
                }

                // at this point we have to increase the sequence
                if ($this->token_data["seq_nr"] > 0) {
                    $this->sequenceStep();
                }

                if ($this->token_info["access_key"] != $this->token_data["access_key"]) {
                    $this->log("wrong client secret as access key");
                    return false;
                }
            }
        }

        $this->valid = true;
        return true;
    }

    private function extractToken() {
        $this->token_info = array();

        if ($this->token_type == "MAC") {

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
        else if ($this->token_type == "Basic" ) {
            $this->token_type = null; // we need to find out about the token type

            $authstr = base64_decode($this->token);

            $auth = explode(":", $authstr);

            $this->token_info["kid"]        = array_shift($auth);
            $this->token_info["access_key"] = array_shift($auth);
        }
    }

    private function findToken() {
        $aDBFields = array(
            "kid", "access_key", "mac_key", "mac_algorithm",
            "seq_nr", "expires", "consumed", "max_seq",
            "user_uuid", "service_uuid", "client_id", "token_type", "extra"
        );

        $sqlstr = "select " . implode(", ", $aDBFields). " from tokens where kid = ? and consumed = 0";

        $aTypes = array("TEXT");
        $aValues = array($this->token_info["kid"]);

        if (isset($this->token_type) && !empty($this->token_type)) {
            $sqlstr .= " AND token_type = ?";
            $aTypes[] = "TEXT";
            $aValues[] = $this->token_type;
        }

        // for bearer and session tokens the token_id is the token_key
        $sth = $this->db->prepare($sqlstr, $aTypes);
        $res = $sth->execute($aValues);

        if (PEAR::isError($res)) {
            $this->log($res->getMessage());
        }
        else {
            $this->token_data = null;
            $this->token_key  = null;

            if ($row = $res->fetchRow(MDB2_FETCHMODE_ASSOC)) {
                $this->token_data = array();

                foreach ($row as $key => $value) {
                    $this->token_data[$key] = $value;
                }

                $this->token_key = $this->token_data["access_key"];
                $this->token_type = $this->token_data["token_type"];
            }
            else {
                $this->log("no token found for kid " . implode(", ", $aValues));
            }

            $sth->free();
        }
    }

    private function consumeToken() {
        $key = $this->token_key;

        $sqlstr = "update  tokens set consumed = ? where kid = ?";
        $sth = $this->db->prepare($sqlstr, array("INTEGER", "TEXT"));

        $now = time();

        $res = $sth->execute(array($now,
                                   $key));

        if (PEAR::isError($res))
        {
            $this->error = $res->getMessage();
            $this->log($res->getMessage());
        }
        $sth->free();

        // consume all children
        $sqlstr = "update  tokens set consumed = ? where parent_kid = ? and token_type <> 'Refresh'";
        $sth = $this->db->prepare($sqlstr, array("INTEGER", "TEXT"));
        $res = $sth->execute(array($now,
                                   $key));

        if (PEAR::isError($res))
        {
            $this->error = $res->getMessage();
            $this->log($res->getMessage());

        }
        $sth->free();
    }

    private function sequenceStep() {
        $sqlstr = "update  tokens set seq_nr = ? where kid = ?";
        $sth = $this->db->prepare($sqlstr, array("INTEGER", "TEXT"));

        $res = $sth->execute(array($this->token_data["seq_nr"] + 1,
                                   $this->token_key));

        if (PEAR::isError($res))
        {
            $this->error = $res->getMessage();
            $this->log($res->getMessage());
        }
        $sth->free();
    }
}

?>
