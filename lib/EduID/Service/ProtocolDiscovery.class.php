<?php
/* *********************************************************************** *
 *
 * *********************************************************************** */
namespace EduID\Service;

use EduID\ServiceFoundation;
use EduID\Model\Protocol;

/**
 *
 */
class ProtocolDiscovery extends ServiceFoundation {

    private $model;

    protected function initializeRun() {
        $this->model = new Protocol($this->db);
        
        $this->tokenValidator->resetAcceptedTokens(array("Bearer", "MAC"));
        $this->tokenValidator->setAcceptedTokenTypes(array("Bearer", "MAC"));
        $this->tokenValidator->requireUser();
        $this->tokenValidator->requireClient();
    }

    /**
     * request services for a list of services
     * - include all services in the list
     */
    protected function post_service() {
        // only service mainurls are accepted
        $this->data = $this->model->findRSDWithServiceUrlList($this->inputData);
        if (!$this->data) {
            $this->not_found();
        }
    }

    /**
     * request services for a list of protocols
     * - include service that match all listed protocols
     */
    protected function post_protocol() {
        // expect a list of protocol names
        $this->data = $this->model->findRSDWithProtocolList($this->inputData);
        if (!$this->data) {
            $this->not_found();
        }
    }
}
?>
