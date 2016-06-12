<?php
/* *********************************************************************** *
 *
 * *********************************************************************** */

namespace EduID\Model;
use EduID\ModelFoundation;

class DBManager extends ModelFoundation {
    protected $db;

    public function __construct($db){
        $this->db = $db;
    }

    
    protected function mapToAttribute($objList, $attributeName, $quote=false) {
        $f = function ($e) use ($attributeName, $quote) {
            return $quote ? $this->db->quote($e[$attributeName]) : $e[$attributeName];
        };

        return array_map($f, $objList);
    }
}

?>