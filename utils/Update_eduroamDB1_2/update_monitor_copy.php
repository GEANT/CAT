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

require_once dirname(dirname(dirname(__FILE__))) . "/config/_config.php";
setlocale(LC_CTYPE, "en_US.UTF-8");
require 'config.php';


class updateFromMonitor {
    private $db;
    private $db_local;
    public $databases;
   
    public $fields = [
        'eduroam' => [
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
        ],
        'eduroamv2' => [
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
            ],
            'active_institution' => [
                ['instid', 's'],
                ['ROid', 's'],
                ['inst_realm', 's'],
                ['country', 's'],
                ['name', 's'],
                ['contact', 's'],
                ['type', 'i'],
                ['last_change', 's']
            ],          
        ]
    ];

    public function __construct($databases) {
            $DB_EXT = \config\Master::DB['EXTERNAL_SOURCE'];
            $DB_LOCAL = \config\Master::DB['EXTERNAL'];
            $this->db_ext = new mysqli($DB_EXT['host'], $DB_EXT['user'], $DB_EXT['pass']);
            $this->db_local = new mysqli($DB_LOCAL['host'], $DB_LOCAL['user'], $DB_LOCAL['pass']);
            $this->databases = $databases;
    }
    
    /**
     * Creates a temporary table with data collected from the source
     * 
     * @param type $db
     * @param type $table_name
     */

    public function updateTable($db, $table_name) {
        print "Updating $db.$table_name\n";
        $this->db_local->select_db($this->databases[$db]);
        $table = 'view_'.$table_name;
        $tmpTable = 'tmp_'.$table_name;
        $this->db_local->query("CREATE TEMPORARY TABLE $tmpTable SELECT * FROM $table LIMIT 0");
        $this->db_ext->select_db($db);
        $this->db_ext->query("SET NAMES 'utf8'");
        $this->db_local->query("SET NAMES 'utf8mb4'");
        $result = $this->db_ext->query("SELECT * FROM $table");
        $queryFields = implode(',', array_column($this->fields[$db][$table_name],0));
        while ($row = $result->fetch_assoc()) {
            $v = [];
            foreach ($this->fields[$db][$table_name] as $field) {
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
            $query = "INSERT INTO $tmpTable (".$queryFields.") VALUES (".$queryValues.")";
            $this->db_local->query($query);
        }
    }
    
    public function updateInstAdminTable($db) {
        $this->db_local->select_db($this->databases[$db]);
        if ($db == 'eduroam') {
            $instid = "id_institution";
            $q = "SELECT convert(contact using utf8mb4), inst_realm, id_institution FROM view_active_institution";
        } else {
            $instid = "instid";
        }
        $q = "SELECT convert(contact using utf8mb4), inst_realm, $instid FROM view_active_institution";
        $this->db_local->query("CREATE TEMPORARY TABLE tmp_institution_admins SELECT * FROM view_institution_admins LIMIT 0");
        $result = $this->db_local->query($q);
        while ($row = $result->fetch_row()) {
            $contacts = \core\ExternalEduroamDBData::dissectCollapsedContacts($row[0]);
            $realms = explode(',', $row[1]);
            foreach ($contacts as $contact) {
                foreach ($realms as $realm) {
                    $email = empty($contact['mail']) ? 'NULL' :'"'.$contact['mail'].'"';
                    $name = empty($contact['name']) ? 'NULL' :'"'.$contact['name'].'"';
                    $phone = empty($contact['phone']) ? 'NULL' :'"'.$contact['phone'].'"';
                    $id = $db == 'eduroam' ? $row[2] : '"'.$row[2].'"';
                    $query = "INSERT INTO tmp_institution_admins (name, email, phone, inst_realm, $instid)"
                            . ' VALUES ('.$name.','.$email.','.$phone.',"'.$realm.'",'.$id.')';
                    $this->db_local->query($query);
                }
            }
        }
    }

    public function fillTable($db, $table_name) {
        $table = 'view_'.$table_name;
        $tmpTable = 'tmp_'.$table_name;
        $this->db_local->select_db($this->databases[$db]);
        $this->db_local->query("SET NAMES 'utf8mb4'");
        $this->db_local->query("DELETE FROM $table");
        $this->db_local->query("INSERT INTO $table SELECT * from $tmpTable");
    }

    private function escape($inp) {
        $out=str_replace('\\','\\\\',$inp);
        $out=str_replace('"','\"',$out);
        $out=str_replace('?','\?',$out);
        $out = 'convert(cast(convert("'.$out.'" using latin1) as binary) using utf8)';
        return($out);
    }
}


$myDB = new updateFromMonitor($databases);

foreach (array_keys($myDB->fields['eduroamv2']) as $table) {
    $myDB->updateTable('eduroamv2', $table);
}

print "Starting filling tables for eduroamv2\n";
foreach (array_keys($myDB->fields['eduroamv2']) as $table) {
    $myDB->fillTable('eduroamv2', $table);
}
print "Finished filling tables for eduroamv2\n";

foreach (array_keys($myDB->fields['eduroam']) as $table) {
    $myDB->updateTable('eduroam', $table);
}
print "Starting filling tables for eduroam\n";

foreach (array_keys($myDB->fields['eduroam']) as $table) {
    $myDB->fillTable('eduroam', $table);
}
print "Finished filling tables for eduroam\n";

print "Starting filling tables for eduroam\n";
$myDB->updateInstAdminTable('eduroam');

$myDB->fillTable('eduroam', 'institution_admins');
print "Finished filling tables for eduroam\n";

$myDB->updateInstAdminTable('eduroamv2');

$myDB->fillTable('eduroamv2', 'institution_admins');
print "Finished filling tables for eduroam\n";
