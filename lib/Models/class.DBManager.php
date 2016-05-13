<?php
/* *********************************************************************** *
 *
 * *********************************************************************** */

class DBManager extends Logger {
    protected $db;

    public function __construct($db){
        $this->db = $db;
    }
}

?>