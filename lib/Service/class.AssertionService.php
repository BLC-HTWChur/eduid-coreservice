<?php
/* *********************************************************************** *
 *
 * *********************************************************************** */

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


}

?>
