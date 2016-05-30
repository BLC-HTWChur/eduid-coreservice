<?php
/* *********************************************************************** *
 *
 * *********************************************************************** */

// require_once("Models/class.JWTValidator.php");

/**
 *
 */
class AssertionService extends ServiceFoundation {

   /**
    *
    */
    public function __construct() {
        parent::__construct();
        $this->tokenValidator->resetAcceptedTokens(array("Bearer", "MAC"));            
    }

    protected function get() {
        $this->log("get data");
        $this->data = array('hello' => 'get');
    }
    
    protected function post() {
        $this->log("post data");
        $this->data = array('hello' => 'post');
    }
}

?>
