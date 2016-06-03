<?php
/* *********************************************************************** *
 *
 * *********************************************************************** */

require_once("Models/class.EduIDValidator.php");

use Lcobucci\JWT as JWT;
use Lcobucci\JWT\Signer as Signer;

class TokenValidator extends EduIDValidator {

    private $token;
    private $token_type;  // oauth specific
    private $token_key;

    private $token_info;  // provided by the client
    private $token_data;  // provided by the DB
    private $jwt_token;

    private $requireUUID = array();

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
            // $this->log("authorization header ". $authheader);

            $aHeadElems = explode(' ', $authheader);

            $this->token_type = $aHeadElems[0];
            $this->token  = $aHeadElems[1];
        }
    }

    public function getTokenIssuer($type) {
        require_once("Models/class.TokenManager.php");

        $tm = new TokenManager($this->db, array("type"=> $type));
        $tm->setRootToken($this->token_data);

        return $tm;
    }

    public function getTokenManager($type) {
        require_once("Models/class.TokenManager.php");

        $tm = new TokenManager($this->db);
        $tm->setToken($this->token_data);

        return $tm;
    }

    public function getTokenUser() {
        if (isset($this->token_data) &&
            !empty($this->token_data) &&
            !empty($this->token_data["user_uuid"])) {

            require_once("Models/class.UserManager.php");

            $um = new UserManager($this->db);
            if ($um->findByUUID($this->token_data["user_uuid"])) {
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

    public function requireUser() {
        if (!in_array("user_uuid", $this->requireUUID)) {
            $this->requireUUID[] = "user_uuid";
        }
    }

    public function requireService() {
        if (!in_array("service_uuid", $this->requireUUID)) {
            $this->requireUUID[] = "service_uuid";
        }
    }

    public function requireClient() {
        if (!in_array("client_id", $this->requireUUID)) {
            $this->requireUUID[] = "client_id";
        }
    }

    public function getToken() {
        return $this->token_data;
    }

    public function getJWT() {
        return $this->jwt_token;
    }

    public function verifyRawToken((string) $rawtoken) {
        if (isset($rawtoken) &&
            !empty($rawtoken) &&
            $rawtoken == $this->token) {

            return true;
        }
        return false;
    }

    public function verifyJWTClaim((string) $claim, (string) $value) {
        if (isset($value) &&
            !empty($value) &&
            isset($claim) &&
            !empty($claim) &&
            isset($this->jwt_token) &&
            $this->jwt_token->getClaim($claim) == $value) {

            return true;
        }

        return false;
    }

    protected function validate() {
        $this->valid = false;

        if (!isset($this->token_type) ||
            empty($this->token_type)) {

            // nothin to validate
            $this->log("no token type available");
            $this->log(json_encode(getallheaders()));
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

        // verify that the token is in our token store
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

        if (!in_array($this->token_type, $this->accept_type)) {
            $this->log("not accepted token type. Given type '" . $this->token_type . "'");
            return false;
        }

        if ($this->token_data["expires"] > 0 &&
            $this->token_data["expires"] < time()) {

            // consume token
            $this->consumeToken();
            $this->log("token expired - consume it!");
            return false;
        }

        if ($this->token_type == "Bearer") {
            // FIXME: TEST IF THE RAW TOKEN IS A KID in the DB

            // run JWT validation
            $alg = $this->jwt_token->getHeader("alg");

            if (!isset($alg) || empty($alg)) {
                $this->log("reject unprotected jwt");
                return false;
            }

            // enforce algorithm
            if ($this->token_data["mac_algorithm"] != $alg) {
                $this->log("invalid jwt sign method presented");
                $this->log("expected: '" . $this->token_data["mac_algorithm"] ."'");
                $this->log("received: '" . $alg."'");
                return false;
            }

            list($algo, $level) = explode("S", $alg);

            switch ($algo) {
                case "H": $algo = "Hmac"; break;
                case "R": $algo = "Rsa"; break;
                case "E": $algo = "Ecdsa"; break;
                default: $algo = ""; break;
            }
            switch ($level) {
                case "256":
                case "384":
                case "512":
                    break;
                default: $level = ""; break;
            }

            if (!empty($algo) && !empty($level)) {
                $signerClass = "Signer\\" . $algo . "\\Sha" . $level;
                $signer = new $signerClass();
            }

            if (!isset($signer)) {
                $this->log("no jwt signer found for " . $alg);
                return false;
            }

            if($this->jwt_token->verify($signer, $this->token_data["mac_key"])) {
                $this->log("jwt signature does not match key");
                return false;
            }

            if ($this->jwt_token->getClaim("iss") != $this->token_data["client_id"]) {
                $this->log("jwt issuer does not match");
                $this->log("expected: " . $this->token_data["client_id"]);
                $this->log("expected: " . $this->jwt_token->getClaim("iss"));
                return false;
            }

            // ignore sub, aud, and name for the time being.
        }
        else if ($this->token_type == "MAC") {
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

            // verify implicit sequence, don't allow resuing the time
            if (isset($this->token_data["last_access"]) &&
                $this->token_data["last_access"] > 0 &&
                $this->token_info["ts"] <= $this->token_data["last_access"]) {

                $this->log("new request is older that previous one");
                $this->service->forbidden();
                return false;
            }

            $this->updateLastAccess();

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

            $testMac = hash_hmac("sha1",
                                 $payload,
                                 $this->token_data["mac_key"]);

            if ($testMac != $this->token_info["mac"]) {

                // bad mac
                $this->log("mac mismatch ");
                $this->log("client token ". $this->token_info["mac"]);
                $this->log("verify token ". $testMac);

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

        if (isset($this->token_data) &&
            !empty($this->token_data)) {

            foreach ($this->requireUUID as $id) {
                if (array_key_exists($id, $this->token_data) &&
                    empty($this->token_data[$id])) {
                    $this->log("required data field is missing");
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
            $jwt = new JWT\Parser();
            $token = $jwt->parse($this->token);

            $this->token_info["kid"]  = $token->getHeader("kid");

            $this->jwt_token = $token;

            // $this->token_info["kid"] = $this->token;
        }
        else if ($this->token_type == "Basic" ) {
            $this->token_type = null; // we need to find out about the token type

            $authstr = base64_decode($this->token);

            //$this->log('authstr ' . $authstr);

            $auth = explode(":", $authstr);

            $this->token_info["kid"]        = array_shift($auth);
            $this->token_info["access_key"] = array_shift($auth);

            //$this->log('kid: ' . $this->token_info["kid"] );
            //$this->log('access_key: ' . $this->token_info["access_key"] );
        }
    }

    private function findToken() {
        $aDBFields = array(
            "kid", "access_key", "mac_key", "mac_algorithm",
            "seq_nr", "expires", "consumed", "max_seq",
            "user_uuid", "service_uuid", "client_id", "token_type", "extra",
            "last_access"
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

    private function updateLastAccess() {
        $sqlstr = "update  tokens set last_access = ? where kid = ?";
        $sth = $this->db->prepare($sqlstr, array("INTEGER", "TEXT"));

        $res = $sth->execute(array($this->token_info["ts"],
                                   $this->token_data["kid"]));

        if (PEAR::isError($res))
        {
            $this->error = $res->getMessage();
            $this->log($res->getMessage());
        }
        $sth->free();
    }
}

?>
