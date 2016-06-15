<?php

namespace EduID\Service;

use EduID\ServiceFoundation;
use EduID\Validator\Data\ClientUser;
use EduID\Model\Client as ClientModel;

class Client extends ServiceFoundation {

    private $clientValidator;
    private $clientModel;

    protected function initializeRun() {
        $this->clientValidator = new ClientUser($this->db);
        $this->addDataValidator($this->clientValidator);
    }

    private function getClient() {
        if (!$this->clientModel &&
            $this->clientValidator) {
            $this->clientModel = $this->clientValidator->getClient();
        }
        if (!$this->clientModel) {
            $this->clientModel= new ClientModel($this->db);
        }

        return $this->clientModel;
    }

    protected function get() {
        $tu = $this->getTokenUser();
        $cm = $this->getClient();

        if ($tu->isFederationUser()) {
            $this->log("get all clients");
            $this->data = $cm->getAllClients();
        }
        else {
            $this->log("get user clients");
            $this->data = $cm->getUserClients($tu->getUUID());
        }
    }

    protected function put() {
        // new client
        $tu = $this->getTokenUser();

        $cm = $this->getClient();

        $cm->addClient($this->inputData);
        $this->data = $cm->getClient();

    }

    protected function post() {
        // create new version
        $cm = $this->getClient();

        $this->data = $cm->addClientVersion($this->inputData["version_id"]);
    }

    protected function get_user() {
        $cm = $this->getClient();

        $this->data = $cm->getClientAdminList();
    }

    protected function put_user() {
        $cm = $this->getClient();
        $um = $this->clientValidator->getUser();

        $cm->addClientAdmin($um->getUUID());
    }

    protected function delete_user() {
        $cm = $this->getClient();
        $um = $this->clientValidator->getUser();

        // we have a client and a new version
        if ($um && $cm) {
            $cm->removeClientAdmin($um->getUUID());
            $this->gone();
        }
        else {
            $this->bad_request();
        }
    }
}

?>
