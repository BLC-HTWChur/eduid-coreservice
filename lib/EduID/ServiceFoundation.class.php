<?php
/*
 *
 */
namespace EduID;

use EduID\Validator\Header\Token as TokenValidator;

require_once('MDB2.php');

/**
 *
 */
class ServiceFoundation extends \RESTling\Service {

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
            $this->status = \RESTling\Service::UNINITIALIZED;
            return;
        }

        $this->configuration = $aCfg;
    }

    public function getTokenUser() {
        return $this->tokenValidator->getTokenUser();
    }

    protected function getConfiguration($key) {

        if (isset($this->configuration) &&
            array_key_exists($key, $this->configuration)) {

            return $this->configuration[$key];
        }

        return null;
    }

    private function initDatabase() {
        if ($this->status == \RESTling\Service::OK) {
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

            $this->db =& \MDB2::factory($dsn,$options);

            if (\PEAR::isError($this->db)) {
                $this->fatal("cannot connect to database");
                $this->status = \RESTling\Service::UNINITIALIZED;
            }
        }
    }

    public function getDataBase() {
        return $this->db;
    }

    private function initSessionValidator() {
        if ($this->status == \RESTling\Service::OK) {
//            $sessionValidator = new SessionValidator($his->db);

            $this->tokenValidator   = new TokenValidator($this->db);

            $this->addHeaderValidator($this->tokenValidator);

            // by default we accept only MAC tokens
            $this->tokenValidator->resetAcceptedTokens(array("MAC"));
        }
    }
}

?>
