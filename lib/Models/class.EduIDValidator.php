<?php
/* *********************************************************************** *
 *
 * *********************************************************************** */

class EduIDValidator extends RESTlingValidator {
    protected $db;
    protected $valid = false;

    protected $id_type;     // service, client, user
    protected $uuid;        // respective uuid

    public function __construct($db) {
        $this->db = $db;
    }
}

?>
