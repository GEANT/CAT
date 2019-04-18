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

// called by ocsp_update.sh (which in turn should run via cron every minute)

/**
 * This script will first trigger an update of all issued certificates which
 * have not expired yet and whose stored OCSP statement is older than a week.
 * 
 * It works on two CAs, the RSA and ECDSA variant. There is a separate temp
 * subdir for both ( temp_ocsp_RSA and temp_ocsp_ECDSA ).
 */
require_once dirname(dirname(__FILE__)) . "/config/_config.php";
if (file_exists(__DIR__ . "/semaphore")) {
    exit(1); // another instance is still busy doing stuff. Don't interfere.
}
file_put_contents(__DIR__ . "/semaphore", "BUSY");
$dbLink = \core\DBConnection::handle("INST");
$allSerials = $dbLink->exec("SELECT serial_number, ca_type FROM silverbullet_certificate WHERE serial_number IS NOT NULL AND expiry > NOW() AND OCSP_timestamp < DATE_SUB(NOW(), INTERVAL 1 WEEK)");
// SELECT query -> always returns a mysql_result, not boolean
while ($serialRow = mysqli_fetch_object(/** @scrutinizer ignore-type */ $allSerials)) {
#    echo "Updating OCSP statement for serial number $serialRow->serial_number\n";
    $certObject = new \core\SilverbulletCertificate($serialRow->serial_number, $serialRow->ca_type);
    $caEngine = \core\SilverbulletCertificate::getCaEngine($serialRow->ca_type);
    // the engine knows the format of its own serial numbers, no reason to get excited
    $caEngine->triggerNewOCSPStatement(/** @scrutinizer ignore-type */ $certObject->serial);
}

 /* 
  * and then writes all recently updated statements to a temporary directory. The 
  * calling script ocsp_update.sh should then scp all the files to their 
  * destination.
  */

$tempdirBase = __DIR__."/temp_ocsp";
mkdir($tempdirBase."_RSA");
mkdir($tempdirBase."_ECDSA");

$allStatements = $dbLink->exec("SELECT serial_number,OCSP,ca_type FROM silverbullet_certificate WHERE serial_number IS NOT NULL AND expiry > NOW() AND OCSP_timestamp > DATE_SUB(NOW(), INTERVAL 8 DAY)");
// SELECT -> mysqli_result, not boolean
while ($statementRow = mysqli_fetch_object(/** @scrutinizer ignore-type */ $allStatements)) {
#    echo "Writing OCSP statement for serial number $statementRow->serial_number\n";
    $filename = strtoupper(dechex($statementRow->serial_number)).".der";
    if (strlen($filename) % 2 == 1) {
        $filename = "0" . $filename;
    }
    file_put_contents($tempdirBase."_".$statementRow->ca_type."/".$filename, $statementRow->OCSP);
}
unlink(__DIR__ . "/semaphore");