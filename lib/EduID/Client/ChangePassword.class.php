<?php

namespace EduID\Client;
use EduID\Client as ClientBase;

class ChangePassword extends ClientBase {
    private $opwd;
    private $npwd;
    
    public function askPassword() {
        $this->opwd = readline("current password: ");
        $this->npwd = readline("new password: ");
        
        if (!empty($this->opwd) && 
            !empty($this->npwd) && 
            strtolower($this->opwd) != strtolower($this->npwd)) {
            return true;
        }
        return false;
    } 
    
    public function updatePassword() {
        $ud = array(
            "oldpassword" => $this->opwd,
            "newpassword" => $this->npwd
        );
        
        $this->curl->setPathInfo("user-profile");
        $this->curl->post(json_encode($ud), "application/json");
        
        if ($this->curl->getStatus() == 204 || $this->curl->getStatus() == 200) {
            $this->log("password updated");
            return true;
        }
        return false;
    }
}

?>