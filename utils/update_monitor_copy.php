<?php
/*
 * *****************************************************************************
 * Contributions to this work were made on behalf of the GÉANT project, a 
 * project that has received funding from the European Union’s Framework 
 * Programme 7 under Grant Agreements No. 238875 (GN3) and No. 605243 (GN3plus),
 * Horizon 2020 research and innovation programme under Grant Agreements No. 
 * 691567 (GN4-1) and No. 731122 (GN4-2).
 * On behalf of the aforementioned projects, GEANT Association is the sole owner
 * of the copyright in all material which was developed by a member of the GÉANT
 * project. GÉANT Vereniging (Association) is registered with the Chamber of 
 * Commerce in Amsterdam with registration number 40535155 and operates in the 
 * UK as a branch of GÉANT Vereniging.
 * 
 * Registered office: Hoekenrode 3, 1102BR Amsterdam, The Netherlands. 
 * UK branch address: City House, 126-130 Hills Road, Cambridge CB2 1PQ, UK
 *
 * License: see the web/copyright.inc.php file in the file structure or
 *          <base_url>/copyright.php after deploying the software
 */

/**
 * This script will download all data from the views in eduroam database and
 * update the local copy
 */

require_once dirname(dirname(__FILE__)) . "/config/_config.php";
setlocale(LC_CTYPE, "en_US.UTF-8");

class updateFromMonitor {
    private $db;
    private $db_local;

    private $tablesource = [
        'admin' => 'eduroam',
        'active_institution' => 'eduroam',
        'active_idp_institution' => 'eduroam',
        'active_SP_location_eduroamdb' => 'eduroam',
        'country_eduroamdb' => 'eduroam',
        'tls_inst' => 'eduroamv2',
        'tls_ro' => 'eduroamv2'
    ];

    public $fields = [
        'admin' => [
            ['id', 'i'],
            ['eptid', 's'],
            ['email', 's'],
            ['common_name', 's'],
            ['id_role', 'i'],
            ['role', 's'],
            ['realm', 's']
        ],
        'active_institution' => [
            ['id_institution', 'i'],
            ['ROid', 's'],
            ['inst_realm', 's'],
            ['country', 's'],
            ['name', 's'],
            ['contact', 's'],
            ['type', 's']
        ],
        'active_idp_institution' => [
            ['id_institution', 'i'],
            ['inst_realm', 's'],
            ['country', 's'],
            ['name', 's'],
            ['contact', 's']
        ],
        'active_SP_location_eduroamdb' => [
            ['country', 's'],
            ['country_eng', 's'],
            ['institutionid', 'i'],
            ['inst_name', 's'],
            ['sp_location', 's'],
            ['sp_location_contact', 's']
        ],
        'country_eduroamdb' => [
            ['country', 's'],
            ['country_eng', 's'],
            ['map_group', 's']
        ],
        'tls_ro' => [
            ['ROid', 's'],
            ['country', 's'],
            ['stage', 'i'],
            ['servers', 's'],
            ['contacts', 's'],
            ['ts', 's']
        ],
        'tls_inst' => [
            ['ROid', 's'],
            ['country', 's'],
            ['instid', 's'],
            ['stage', 'i'],
            ['type', 'i'],
            ['inst_name', 's'],
            ['servers', 's'],
            ['contacts', 's'],
            ['ts', 's']
        ]
    ];

    public function __construct() {
            $DB = \config\Master::DB['EXTERNAL_SOURCE'];
            $DB_LOCAL = \config\Master::DB['EXTERNAL'];
            $this->db = new mysqli($DB['host'], $DB['user'], $DB['pass'], $DB['db']);
            $this->db_local = new mysqli($DB_LOCAL['host'], $DB_LOCAL['user'], $DB_LOCAL['pass']);
    }

    public function update_table($table_name) {
        $table = 'view_'.$table_name;
        $tmp_table = 'tmp_'.$table_name;
        if ($this->tablesource[$table_name] == 'eduroam') {
            $this->db_local->select_db('monitor_copy');
        } elseif($this->tablesource[$table_name] == 'eduroamv2') {
            $this->db_local->select_db('eduroamv2');
        }
        $this->db_local->query("CREATE TEMPORARY TABLE $tmp_table SELECT * FROM $table LIMIT 0");
        $sourceDB = $this->tablesource[$table_name];
        $this->db->select_db($sourceDB);
        $result = $this->db->query("SET NAMES 'utf8'");
        $result = $this->db_local->query("SET NAMES 'utf8mb4'");
        $result = $this->db->query("SELECT * FROM $table");
        $queryFields = implode(',', array_column($this->fields[$table_name],0));
        while ($row = $result->fetch_assoc()) {
            $v = [];
            foreach ($this->fields[$table_name] as $field) {
                if ($field[1] === 's') {
                    if (isset($row[$field[0]])) {
                        $v[] = $this->escape($row[$field[0]]);
                    } else {
                        $v[] = "NULL";
                    }
                } else {
                    if (isset($row[$field[0]])) {
                        $v[] = $row[$field[0]];
                    } else {
                        $v[] = "NULL";
                    }
                }
            }
            $queryValues = implode(',',$v);
            $query = "INSERT INTO $tmp_table (".$queryFields.") VALUES (".$queryValues.")";
            $this->db_local->query($query);
        }

    }

    public function fill_table($table_name) {
        $table = 'view_'.$table_name;
        $tmp_table = 'tmp_'.$table_name;
        if ($this->tablesource[$table_name] == 'eduroam') {
                $this->db_local->select_db('monitor_copy');
        } elseif($this->tablesource[$table_name] == 'eduroamv2') {
                $this->db_local->select_db('eduroamv2');
        }
                $result = $this->db_local->query("SET NAMES 'utf8mb4'");
        $this->db_local->query("DELETE FROM $table");
        $this->db_local->query("INSERT INTO $table SELECT * from $tmp_table");
    }

    private function escape($inp) {
        $out=str_replace('\\','\\\\',$inp);
        $out=str_replace('"','\"',$out);
        $out=str_replace('?','\?',$out);
        $out = 'convert(cast(convert("'.$out.'" using latin1) as binary) using utf8)';
        return($out);
    }
}

$myDB = new updateFromMonitor();

foreach (array_keys($myDB->fields) as $table) {
    print("$table\n");
    $myDB->update_table($table);
}

foreach (array_keys($myDB->fields) as $table) {
    print("$table\n");
    $myDB->fill_table($table);
}

