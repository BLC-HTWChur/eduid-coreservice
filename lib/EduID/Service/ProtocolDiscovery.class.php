<?php
/* *********************************************************************** *
 *
 * *********************************************************************** */
namespace EduID\Service;

use EduID\ServiceFoundation;

/**
 *
 */
class ProtocolDiscovery extends ServiceFoundation {

 protected function get() {
        $this->data = array("status"=> "OK",
                            "message"=>"POST user information");
    }
}
?>
