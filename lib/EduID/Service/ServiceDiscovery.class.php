<?php
/* *********************************************************************** *
 *
 * *********************************************************************** */
namespace EduID\Service;

use EduID\ServiceFoundation;
use EduID\Validator\Data\FederationUser;
use EduID\Model\Token;
use EduID\Model\Service as ServiceModel;

/**
 *
 */
class ServiceDiscovery extends ServiceFoundation {

    private $serviceModel;

    public function __construct() {
        parent::__construct();

        $this->tokenValidator->resetAcceptedTokens(array("Bearer", "MAC"));

        $fu = new FederationUser($this->db);
        $fu->setOperations(array("get_federation", "post_federation"));
        $this->addHeaderValidator($fu);

        $this->serviceModel = new ServiceModel($this->db);
    }

    /**
     * get user services
     */
    protected function get_user() {
        $t = $this->tokenValidator->getToken();

        $this->data = $this->serviceModel->findUserServices($t["user_uuid"]);
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
        $tm = new Token($this->db, array("type"=>"MAC"));

        $this->inputData["token"] = $tm->newToken();

        $this->serviceModel->addService($this->inputData);

        $this->data = $this->inputData["token"];

    }
}
?>
