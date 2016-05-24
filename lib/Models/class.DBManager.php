<?php
/* *********************************************************************** *
 *
 * *********************************************************************** */

class DBManager extends \RESTling\Logger {
    protected $db;

    public function __construct($db){
        $this->db = $db;
    }

    protected function randomString($length=10) {
        $resstring = "";
        $chars = "abcdefghijklmnopqrstuvwxyz._ABCDEFGHIJKLNOPQRSTUVWXYZ-1234567890";
        $len = strlen($chars);
        for ($i = 0; $i < $length; $i++)
        {
            $x = rand(0, $len-1);
            $resstring .= substr($chars, $x, 1);
        }
        return $resstring;
    }
}

?>