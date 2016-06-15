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

        $uid = $this->user->getUUID();
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

                if (!$cm->isClientAdmin($uid) &&
                    !$this->user->isFederationUser()) {
                    $this->log("active user is neither client admin or sys admin");
                    $this->service->forbidden();
                    return false;
                }

                if ($this->operation == "post" &&
                    !array_key_exists("version_id", $this->data)) {
                    $this->log("required version_id missing");
                    return false;
                }

                if (in_array($this->operation, array("put_user", "delete_user"))) {

                    $data = $this->data;

                    if (!empty($this->param)) {
                        $data = $this->param;
                    }

                    if (empty($data)) {
                        $this->log("grant parameters missing");
                        return false;
                    }

                    $this->user = null;
                    if (!array_key_exists("user_mail", $data)) {
                        $this->log("username not found");
                        // bad request
                        return false;
                    }

                    $um = new UserModel($this->db);
                    if (!$um->findByMailAddress($data["user_mail"])) {
                        $this->log("user not found " . $data["user_mail"]);
                        $this->service->not_found();
                        return false;
                    }

                    if ($uid == $um->getUUID()){
                        // MUST NOT HAPPEN
                        $this->log("user tries to the grant him/her self");
                        $this->service->no_content();
                        return false;
                    }

                    if ($this->operation != "delete_user" &&
                        $cm->isClientAdmin($um->getUUID())) {

                        $this->log("new user is already an admin");
                        $this->service->no_content();
                        return false;
                    }

                    // $this->log($um->getUUID());
                    $this->user = $um;
                }
            }
            else {
                $this->log("empty path");
                return false;
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