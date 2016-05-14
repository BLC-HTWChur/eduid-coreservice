<?php
/* *********************************************************************** *
 *
 * *********************************************************************** */

class EduIDValidator extends RESTlingValidator {
    protected $db;
    protected $valid = false;

    protected $id_type;     // service, client, user
    protected $uuid;        // respective uuid

    protected $allowEmptyMethod = array();
    protected $requireEmptyMethod = array();

    public function __construct($db) {
        $this->db = $db;
    }

    public function allowEmpty($methodList) {
        if (isset($methodList)) {
            if (!is_array($methodList)) {
                $methodLis = array($methodList);
            }
            foreach ($methodList as $m) {
                $m = strtolower($m);
                $this->allowEmptyMethod[] = $m;
            }
        }
    }
    public function requireEmpty($methodList) {
        if (isset($methodList)) {
            if (!is_array($methodList)) {
                $methodLis = array($methodList);
            }
            foreach ($methodList as $m) {
                $m = strtolower($m);
                $this->requireEmptyMethod[] = $m;
            }
        }
    }
}

?>
