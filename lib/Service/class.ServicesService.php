<?php
/* *********************************************************************** *
 *
 * *********************************************************************** */


require_once("Models/class.EduIDValidator.php");
require_once("Models/class.UserManager.php");
require_once("Models/class.ServiceManager.php");
require_once("Validator/class.FederationUser.php");

/**
 *
 */
class ServicesService extends ServiceFoundation {

    private $serviceManager;

    public function __construct() {
        parent::__construct();

        $this->tokenValidator->resetAcceptedTokens(array("Bearer", "MAC"));

        $fu = new FederationUser($this->db);
        $fu->setOperations(array("get_federation", "post_federation"));
        $this->addHeaderValidator($fu);

        $this->serviceManager = new ServiceManager($this->db);
    }

    /**
     * get user services
     */
    protected function get() {
        $t = $this->tokenValidator->getToken();

        $this->data = $this->serviceManager->findUserServices($t["user_uuid"]);
    }

    /**
     * search the federation
     */
    //protected function post() {}

    /**
     * load the entire federation
     */
    // protected function get_federation() {}

    /**
     * add a new service to the federation
     */
    protected function put_federation() {
        $tm = $this->tokenValidator->getTokenManager("MAC");

        $this->inputData["token"] = $tm->newToken();

        $this->serviceManager->addService($this->inputData);

        $this->data = $this->inputData["token"];

    }
}
?>
