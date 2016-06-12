<?php
namespace EduID\Validator\Data;

use EduID\Validator\Base as Validator;
use EduID\Model\Client as ClientModel;

class ClientUser extends Validator {
    private $user;
    private $federationOperations = array();

    private $client;

    public function getClient() {
        return $this->client;
    }

    public function validate() {
        $this->user = $this->service->getTokenUser();

        $cm = new ClientModel($this->db);
        $tu = $this->service->getTokenUser();

        if (!in_array($this->operation, array("get", "put")) {
            // check clientid in pathinfo
            if (!empty($this->path_info)) {

                $cid = $this->path_info[0];

                if(!$cm->findClient($cid)) {
                    $this->log("client not found");
                    $this->service->not_found();
                    return false;
                }

                if (!$cm->isClientAdmin($tu->getUUID()) &&
                    !$tu->isFederationUser()) {
                    $this->log("active user is neither client admin or sys admin");
                    return false;
                }
            }
        }

        $this->client = $cm;

        return true;
    }



}
?>