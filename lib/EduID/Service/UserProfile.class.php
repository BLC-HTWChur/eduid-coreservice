<?php
/* *********************************************************************** *
 *
 * *********************************************************************** */


namespace EduID\Service;

use EduID\ServiceFoundation;
use EduID\Model\User;
use EduID\Validator\Data\FederationUser;

class UserProfile extends ServiceFoundation {
    protected function initializeRun() {
        $this->tokenValidator->resetAcceptedTokens(array("Bearer", "MAC"));
        $this->tokenValidator->setAcceptedTokenTypes(array("Bearer", "MAC"));
        $this->tokenValidator->requireUser();
        $this->tokenValidator->requireClient();

        $fu = new FederationUser($this->db);
        $fu->setRequiredOperations(array("put_federation",
                                         "get_federation_admin",
                                         "put_federation_admin",
                                         "delete_federation_admin"));

        $this->addHeaderValidator($fu);
    }

    protected function get() {
        $this->log("get user profile");
        if ($user = $this->tokenValidator->getTokenUser()) {
            $this->data = $user->getAllProfiles();
        }
        else {
            $this->forbidden();
        }
    }

    protected function post() {
        $this->log("change user information for oneself");

        if (($user = $this->tokenValidator->getTokenUser()) &&
            array_key_exists("oldpassword", $this->inputData) &&
            !empty($this->inputData["oldpassword"]) &&
            array_key_exists("newpassword", $this->inputData) &&
            !empty($this->inputData["newpassword"])) {

            if (!$user->updateUserPassword($this->inputData["oldpassword"],
                                           $this->inputData["newpassword"])) {
                $this->forbidden();
            }
        }
        else {
            $this->forbidden();
        }

        // on success we get a 204
    }

    protected function put_federation() {
        $this->log("add a generic user to federation");

        $user = new User($this->db);

        $user->addUser($this->inputData);
    }

    protected function put_federation_admin() {
        $this->log("grant user as federation user");

        if (array_key_exists("user_mail", $this->inputData) &&
            !empty($this->inputData["user_mail"])) {

            $this->log($this->inputData["user_mail"]);

            $user = new User($this->db);
            if ($user->findByMailAddress($this->inputData["user_mail"])) {
                if (!$user->grantFederationUser()) {
                    $this->bad_request();
                }
            }
            else {
                $this->not_found();
            }
        }
        else {
            $this->bad_request();
        }
    }

    protected function delete_federation_admin() {
        $this->log("revoke federation user grant");
        $user = new User($this->db);


        $user_mail = $this->queryParam["user_mail"];

        $this->log($user_mail);

        if (isset($user_mail) &&
            !empty($user_mail) &&
            $user->findByMailAddress($user_mail)) {

            if($user->revokeFederationUser()) {
                $this->gone();
            }
            else {
                $this->not_found();
            }
        }
        else {
            $this->not_found();
        }
    }

    protected function get_federation_admin() {
        $this->log("get federation admins");
        $user = new User($this->db);
        $this->data = $user->getFederationUserList();
    }
}

?>
