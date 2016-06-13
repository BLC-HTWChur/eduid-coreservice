<?php
namespace EduID\Validator\Data;

use EduID\Validator\Base as Validator;
use EduID\Model\Client   as ClientModel;
use EduID\Model\User     as UserModel;

class ClientUser extends Validator {
    private $user;
    private $federationOperations = array();

    private $client;

    public function getClient() {
        return $this->client;
    }
    public function getUser() {
        return $this->user;
    }


    public function validate() {
        $this->user = $this->service->getTokenUser();

        $cm = new ClientModel($this->db);

        if (!in_array($this->operation, array("get", "put"))) {
            // check clientid in pathinfo
            if (!empty($this->path_info)) {

                $cid = $this->path_info[0];

                if(!$cm->findClient($cid)) {
                    $this->log("client not found");
                    $this->service->not_found();
                    return false;
                }

                if (!$cm->isClientAdmin($this->user->getUUID()) &&
                    !$this->user->isFederationUser()) {
                    $this->log("active user is neither client admin or sys admin");
                    return false;
                }

                if ($this->operation == "post" &&
                    !array_key_exists("version_id", $this->data)) {
                    $this->log("required version_id missing");
                    return false;
                }

                if (in_array($this->operation, array("put_user", "delete_user"))) {

                    if (!array_key_exists("user_mail", $this->data)) {
                        $this->log("username not found");
                        return false;
                    }

                    $um = new UserModel($this->db);
                    $this->log(">> user mail = " . $this->data["user_mail"]);
                    if (!$um->findByMailAddress($this->data["user_mail"])) {
                        $this->service->not_found();
                        return false;
                    }
                    $this->log($um->getUUID());
                    $this->user = $um;
                }
            }
        }
        else if ($this->operation == "put") {
            if (!$this->user->isFederationUser()) {
                $this->log("new clients require federation users");
                $this->service->forbidden();
                return false;
            }
        }

        $this->client = $cm;

        return true;
    }
}
?>