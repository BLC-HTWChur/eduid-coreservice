<?php
/*
 *
 */

require_once("Models/class.SessionValidator.php");
require_once("Models/class.TokenValidator.php");

class ServiceFoundation extends RESTling {

    protected $db;
    protected $configuration;

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
            $cfgobj = parse_init_file('config/eduid.ini', true);
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
            if (array_key_exists("database", $dbCfg)) {
                $dbname = $dbCfg["database"];
            }

            $this->db = new mysqli($server,
                                   $dbCfg["username"],
                                   $dbCfg["password"],
                                   $dbname);

            if ($this->db->connect_errno) {
                $this->fatal("cannot connect to database");
                $this->status = RESTling::UNINITIALIZED;
            }
        }
    }

    private function initSessionValidator() {
        if ($this->status == RESTling::OK) {
            $sessionValidator = new SessionValidator($his->db);
            $tokenValidator   = new OAuth2TokenValidator($this->db);

        }
    }
}

?>
