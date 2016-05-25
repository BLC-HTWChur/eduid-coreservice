<?php
/* *********************************************************************** *
 *
 * *********************************************************************** */

/**
 *
 */
class ServicesService extends ServiceFoundation {

     protected function get() {
        $this->data = array("status"=> "OK",
                            "message"=>"POST user information");
    }
}
?>
