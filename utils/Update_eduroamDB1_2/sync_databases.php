<?php
/**
 * This a one-time procedure required to resync CAT with eduroamv2 institition
 * identifiers
 * 
 * First run update_monitor_copy.php with resultung databases set to the ones listed below
 * Next run this script
 * 
 */

require_once dirname(dirname(dirname(__FILE__))) . "/config/_config.php";
setlocale(LC_CTYPE, "en_US.UTF-8");
require 'config.php';



$db1 = $databases['eduroam'];
$db2 = $databases['eduroamv2'];
$cat_db = $databases['cat'];
$newDb = $databases['eduroam_new'];
$DB_LOCAL = \config\Master::DB['EXTERNAL'];
$db_local = new mysqli($DB_LOCAL['host'], $DB_LOCAL['user'], $DB_LOCAL['pass']);
$db_local->select_db($db2);


// copy view_active_institution from db1 to db2 as view_active_institution1
print "copy view_active_institution from $db1 to $db2 as view_active_institution1\n";
$db_local->query("DROP TABLE IF EXISTS view_active_institution1");
$db_local->query("CREATE TABLE view_active_institution1 SELECT * FROM ".$db1.".view_active_institution");

// create a copy of view _active_institution of db2 as view_active_institution2
print "create a copy of view _active_institution of $db2 as view_active_institution2\n";
$db_local->query("DROP TABLE IF EXISTS view_active_institution2");
$db_local->query("CREATE TABLE view_active_institution2 SELECT * FROM view_active_institution");

// copy view_admin from db1 ro db2
print "copy view_admin from $db1 to $db2\n";
$db_local->query("DROP TABLE IF EXISTS view_admin");
$db_local->query("CREATE TABLE view_admin SELECT * FROM $db1.view_admin");

// add inst_realm_sorted column to both new tables
print "add inst_realm_sorted column to both new tables\n";
$db_local->query("ALTER TABLE view_active_institution1 ADD inst_realm_sorted varchar(341) DEFAULT NULL");
$db_local->query("ALTER TABLE view_active_institution1 ADD cat_sync_id int DEFAULT NULL");
$db_local->query("ALTER TABLE view_active_institution2 ADD inst_realm_sorted varchar(341) DEFAULT NULL");
$db_local->query("ALTER TABLE view_active_institution2 ADD cat_sync_id int DEFAULT NULL");

// add instid1 column to view_active_institution2
print "add instid1 column to view_active_institution2\n";
$db_local->query("ALTER TABLE view_active_institution2 ADD instid1 int DEFAULT NULL");

// add sorted realms to both view_active_institution1 and view_active_institution2
print "add sorted realms to both view_active_institution1 and view_active_institution2\n";
$updates = [];
$q = "SELECT ROid,id_institution,inst_realm from view_active_institution1";
$result = $db_local->query($q);

while ($row = $result->fetch_assoc()) {
    $e1 = explode(',', $row['inst_realm']);
    sort($e1);
    $e = implode(',', $e1);
    $updates[] = "UPDATE view_active_institution1 set inst_realm_sorted='$e' where id_institution='".$row['id_institution']."' AND ROid='".$row['ROid']."'";
} 
$q = "SELECT ROid,instid,inst_realm from view_active_institution2";
$result = $db_local->query($q);

while ($row = $result->fetch_assoc()) {
    $e1 = explode(',', $row['inst_realm']);
    sort($e1);
    $e = implode(',', $e1);
    $updates[] = "UPDATE view_active_institution2 set inst_realm_sorted='$e' where instid='".$row['instid']."' AND ROid='".$row['ROid']."'";
}
print "finished sorting realms now running updates\n";
foreach ($updates as $update) {
    $db_local->query($update);
}

// add instid1 based on name, ROid and inst_realm_sorted
print "add instid1 based on name, ROid and inst_realm_sorted\n";
$q = "SELECT view_active_institution2.instid AS instid2, view_active_institution1.id_institution AS instid1, view_active_institution2.ROid AS ROid
        FROM
        view_active_institution2 JOIN view_active_institution1
        ON (view_active_institution2.inst_realm_sorted=view_active_institution1.inst_realm_sorted
                AND  view_active_institution2.ROid=view_active_institution1.ROid
                AND TRIM(view_active_institution2.name)=TRIM(view_active_institution1.name))";
$updates = [];
$result = $db_local->query($q);
while ($row = $result->fetch_assoc()) {
    $updates[] = "UPDATE view_active_institution2 set instid1=".$row['instid1']." where instid='".$row['instid2']."' AND ROid='".$row['ROid']."'";
}
print "finished prepareation, now running updates\n";
foreach ($updates as $update) {
    $db_local->query($update);
}

// add instid1 for institutions that do not send realms nased on names
print "add instid1 for institutions that do not send realms based on names\n";
$updates = [];
$q = "SELECT view_active_institution2.instid AS instid2, view_active_institution1.id_institution AS instid1, view_active_institution2.ROid AS ROid
        FROM
        view_active_institution1 JOIN view_active_institution2
        ON view_active_institution1.inst_realm=view_active_institution2.instid
        WHERE view_active_institution2.inst_realm=''
        AND TRIM(view_active_institution1.name)=TRIM(view_active_institution2.name)
        AND  view_active_institution2.ROid=view_active_institution1.ROid";

$updates = [];
$result = $db_local->query($q);
while ($row = $result->fetch_assoc()) {
    $updates[] = "UPDATE view_active_institution2 set instid1=".$row['instid1']." where instid='".$row['instid2']."' AND ROid='".$row['ROid']."'";
}
print "finished prepareation, now running updates\n";
foreach ($updates as $update) {
    $db_local->query($update);
}
 
// add CAT ids to view_active_institution1
print "add CAT ids to view_active_institution1\n";
$q = "select $cat_db.institution.inst_id as catid, view_active_institution1.id_institution as instid1 from
        $cat_db.institution join view_active_institution1
        on $cat_db.institution.external_db_id=view_active_institution1.id_institution";
$updates = [];
$result = $db_local->query($q);
while ($row = $result->fetch_assoc()) {
    $updates[] = "UPDATE view_active_institution1 set cat_sync_id=".$row['catid']." WHERE id_institution=".$row['instid1'];
}
foreach ($updates as $update) {
    $db_local->query($update);
}

// add CAT ids to view_active_institution2
print "add CAT ids to view_active_institution2\n";
$q ="update view_active_institution2 join view_active_institution1
        on view_active_institution2.instid1=view_active_institution1.id_institution
        set view_active_institution2.cat_sync_id=view_active_institution1.cat_sync_id";
$result = $db_local->query($q);

// create new eduroamv2
print "create $newDb\n";
$tables = [
    'view_active_institution2' => 'view_active_institution',
    'view_tls_inst' => 'view_tls_inst',
    'view_tls_ro' => 'view_tls_ro',
    'view_institution_admins' => 'view_institution_admins',
    'view_admin' => 'view_admin'
];

foreach ($tables as $source => $target) {
    $db_local->query("DROP TABLE IF EXISTS $newDb.".$target);
    $db_local->query("CREATE TABLE $newDb.".$target." SELECT * FROM $db2.".$source);
}

// replace external database identiviers in CAT database
print "replace external database identiviers in CAT database\n";

$q = "alter table $cat_db.institution add external_db_id2 varchar(64)";
$db_local->query($q);

$q = "update $cat_db.institution join $newDb.view_active_institution
    on $cat_db.institution.external_db_id=$newDb.view_active_institution.instid1
    set $cat_db.institution.external_db_id2 = $newDb.view_active_institution.instid";
$db_local->query($q);

$q = "update $cat_db.institution set external_db_id = NULL";
$db_local->query($q);

$q = "update $cat_db.institution set external_db_id=external_db_id2";
$db_local->query($q);

$q = "update $cat_db.institution set external_db_syncstate=0 where external_db_id is null";
$db_local->query($q);

$q = "alter table $cat_db.institution drop external_db_id2";
$db_local->query($q);

$q = "drop table $db2.view_active_institution1";
$db_local->query($q);

$q = "drop table $db2.view_active_institution2";
$db_local->query($q);


print "DONE\n";
