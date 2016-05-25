<?php
/* *********************************************************************** *
 *
 * *********************************************************************** */

/**
 *
 */
class AssertionService extends ServiceFoundation {
    protected function get() {
        $this->data = array("status"=> "OK",
                            "message"=>"POST user information");
    }
}

?>
