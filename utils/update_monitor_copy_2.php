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
 * It should be run daily.
 * The script does not update the NRO admin table. These admins are updated in the eduroam DB
 * in a separate way and it is crucial that the updates are porformed more often (hourly)
 */
namespace utils;
require_once dirname(dirname(__FILE__)) . "/config/_config.php";

setlocale(LC_CTYPE, "en_US.UTF-8");

$timeStart = microtime(true);
$myDB = new UpdateFromMonitor();

foreach (array_keys($myDB->fields['eduroamv2']) as $table) {
    $myDB->updateTable('eduroamv2', $table);
}

print "Starting filling tables for eduroamv2\n";
foreach (array_keys($myDB->fields['eduroamv2']) as $table) {
    $myDB->fillTable($table);
}
print "Finished filling tables for eduroamv2\n";

print "Starting filling inst admin table\n";
$myDB->updateInstAdminTable('eduroamv2');

$myDB->fillTable('institution_admins');
print "Finished filling admin table\n";

$timeEnd = microtime(true);
$timeElapsed = $timeEnd - $timeStart;
printf("Whole update done in %.2fs\n",$timeElapsed);