<?php
/* 
 *******************************************************************************
 * Copyright 2011-2017 DANTE Ltd. and GÉANT on behalf of the GN3, GN3+, GN4-1 
 * and GN4-2 consortia
 *
 * License: see the web/copyright.php file in the file structure
 *******************************************************************************
 */

// called by ocsp_update.sh (which in turn should run via cron every minute)

/**
 * This script will first trigger an update of all issued certificates which
 * have not expired yet and whose stored OCSP statement is older than a week.
 */
require_once(dirname(dirname(__FILE__)) . "/config/_config.php");

$dbLink = \core\DBConnection::handle("INST");
$allSerials = $dbLink->exec("SELECT serial_number FROM silverbullet_certificate WHERE serial_number IS NOT NULL AND expiry > NOW() AND OCSP_timestamp < DATE_SUB(NOW(), INTERVAL 1 WEEK)");

while ($serialRow = mysqli_fetch_object($allSerials)) {
#    echo "Updating OCSP statement for serial number $serialRow->serial_number\n";
    core\ProfileSilverbullet::triggerNewOCSPStatement($serialRow->serial_number);
}

 /* 
  * and then writes all recently updated statements to a temporary directory. The 
  * calling script ocsp_update.sh should then scp all the files to their 
  * destination.
  */

$tempdir = __DIR__."/temp_ocsp";
mkdir($tempdir);

$allStatements = $dbLink->exec("SELECT serial_number,OCSP FROM silverbullet_certificate WHERE serial_number IS NOT NULL AND expiry > NOW() AND OCSP_timestamp > DATE_SUB(NOW(), INTERVAL 8 DAY)");

while ($statementRow = mysqli_fetch_object($allStatements)) {
#    echo "Writing OCSP statement for serial number $statementRow->serial_number\n";
    $filename = strtoupper(dechex($statementRow->serial_number)).".der";
    if (strlen($filename) % 2 == 1) {
        $filename = "0" . $filename;
    }
    $fileHandle = fopen($tempdir."/$filename","w");
    fwrite($fileHandle, $statementRow->OCSP);
    fclose($fileHandle);
}
