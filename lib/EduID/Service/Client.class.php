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
        if (!$this->clientModel) {
            if ($this->clientValidator) {
                $this->clientModel = $this->clientValidator->getClient();
            }

            if (!$this->clientModel) {
                $this->clientModel= new ClientModel($this->db);
            }
        }

        return $this->clientModel;
    }

    protected function get() {
        $tu = $this->getTokenUser();
        $cm = $this->getClient();

        if ($tu->isFederationUser()) {
            $this->data = $cm->getAllClients();
        }
        else {
            $this->data = $cm->getUserClients($tu->getUUID());
        }
    }

    protected function put() {
        // new client
        $tu = $this->getTokenUser();
        if ($tu->isFederationUser()) {
            // create client
            $cm = $this->getClient();
            if ($cm->addClient($this->inputData)) {
                $this->data = $cm->getClient();
            }
            else {
                $this->bad_request();
            }
        }
        else {
            $this->forbidden();
        }
    }

    protected function post() {
        // create new version
        $ci = array_shift($this->path_info);
        $cm = $this->getClient();

        if ($cm->getClient() &&
            array_key_exists("version_id", $this->inputData)) {
            // we have a client and a new version

            $this->data = $cm->addClientVersion($this->inputData["version_id"]);
        }
        else {
            $this->bad_request();
        }
    }

    protected function get_user() {
        $cm = $this->getClient();
        if ($cm->getClient()) {
            $this->data = $cm->getClientAdminList();
        }
        else {
            $this->bad_request();
        }
    }

    protected function put_user() {
        $cm = $this->getClient();

        if ($cm->getClient() &&
            array_key_exists("version_id", $this->inputData)) {
            // we have a client and a new version

            $cm->addClientAdmin($this->inputData["user_id"]);
        }
        else {
            $this->bad_request();
        }
    }

    protected function delete_user() {
        $cm = $this->getClient();

        if ($cm->getClient() &&
            array_key_exists("version_id", $this->inputData)) {
            // we have a client and a new version

            $cm->removeClientAdmin($this->inputData["user_id"]);
        }
        else {
            $this->bad_request();
        }
    }
}

?>
