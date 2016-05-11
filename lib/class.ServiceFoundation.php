<?php
/*
 *
 */

require_once('MDB2.php');

//require_once("Models/class.SessionValidator.php");
require_once("Models/class.TokenValidator.php");


class ServiceFoundation extends RESTling {

    protected $db;
    protected $configuration;

    protected $tokenValidator;

    public function __construct() {

        // init the service
        parent::__construct();
        // always strip the path_info because the services are called via eduid.php
        array_shift($this->path_info);

        $this->loadConfiguration();

        $this->initDataBase();

        $this->initSessionValidator();
    }

    private function loadConfiguration() {

        try {
            $aCfg = parse_ini_file('config/eduid.ini', true);
        }
        catch (Exception $e) {
            $this->fatal($e->getMessage());
            $this->status = RESTling::UNINITIALIZED;
            return;
        }

        $this->configuration = $aCfg;
    }

    protected function getConfiguration($key) {

        if (isset($this->configuration) &&
            array_key_exists($key, $this->configuration)) {

            return $this->configuration[$key];
        }

        return null;
    }

    private function initDatabase() {
        if ($this->status == RESTling::OK) {
            $dbCfg = $this->getConfiguration('database');

            $server = "localhost";
            $dbname = "eduid";

            if (array_key_exists("server", $dbCfg)) {
                $server = $dbCfg["server"];
            }
            if (array_key_exists("name", $dbCfg)) {
                $dbname = $dbCfg["name"];
            }

            $dsn = array("phptype"  => $dbCfg["driver"],
                         "username" => $dbCfg["user"],
                         "password" => $dbCfg["pass"],
                         "hostspec" => $server,
                         "database" => $dbname);

            $options = array(
//                "persistent" => true
            );

            $this->db =& MDB2::factory($dsn,$options);

            if (PEAR::isError($this->db)) {
                $this->fatal("cannot connect to database");
                $this->status = RESTling::UNINITIALIZED;
            }
        }
    }

    private function initSessionValidator() {
        if ($this->status == RESTling::OK) {
//            $sessionValidator = new SessionValidator($his->db);
            $this->tokenValidator   = new OAuth2TokenValidator($this->db);

            $this->addHeaderValidator($this->tokenValidator);
        }
    }
}

?>
