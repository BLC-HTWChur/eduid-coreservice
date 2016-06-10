<?php
namespace EduID\Model;

class Protocol extends DBManager {

    public function __construct($db, $options=array()) {
        parent::__construct($db);

        $this->dbKeys = array(
            "service_uuid"    => "TEXT",
            "last_update"     => "INTEGER",
            "rsd"             => "TEXT"
        );
    }

    public function findRsdWithServiceUrlList($list) {
        $sqlstr = "SELECT DISTINCT " . implode(",", array_keys($this->dbKeys))
                . " FROM serviceprotocols sp, services s"
                . " WHERE s.service_uuid = sp.service_uuid AND s.mainurl IN ("
                . implode(",", $this->quoteList($list))
                . ")";

        $service_list = array();

        $sth = $this->db->prepare($sqlstr);
        $res = $sth->execute();

        while ($row = $res->fetchRow(MDB2_FETCHMODE_ASSOC)) {
            $service_list[] = json_decode($row["rsd"], true);
        }

        $sth->free();

        return $service_list;
    }

    public function findRsdWithProtocolList($list) {
        $sqlstr = "SELECT DISTINCT " . implode(",", array_keys($this->dbKeys))
                . " FROM serviceprotocols sp, protocolnames p"
                . " WHERE p.service_uuid = sp.service_uuid AND p.rsd_name IN ("
                . implode(",", $this->quoteList($list))
                . ")";

        $service_list = array();

        $sth = $this->db->prepare($sqlstr);
        $res = $sth->execute();

        while ($row = $res->fetchRow(MDB2_FETCHMODE_ASSOC)) {
            $rsd = json_decode($row["rsd"], true);

            foreach ($list as $api) {
                if (!array_key_exists($api, $rsd)) {
                    $rsd = null
                    break;
                }
            }
            if ($rsd) {
                $service_list[] = $rsd;
            }
        }

        $sth->free();

    }

    private function quoteList($list) {
        $retval = array();
        foreach ($list as $value) {
            if (is_string($value)) {
                $retval[] = $this->db->quote($value, 'text');
            }
        }
        return $retval;
    }

}

?>
