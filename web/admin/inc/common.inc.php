<?php
/* 
 *******************************************************************************
 * Copyright 2011-2017 DANTE Ltd. and GÉANT on behalf of the GN3, GN3+, GN4-1 
 * and GN4-2 consortia
 *
 * License: see the web/copyright.php file in the file structure
 *******************************************************************************
 */
?>
<?php

require_once(dirname(dirname(dirname(dirname(__FILE__)))) . "/config/_config.php");

$allLocationCount = 0;

function tooltip($input) {
    $descriptions = [];
    if (count(CONFIG['CONSORTIUM']['ssid']) > 0) {
        $descriptions[sprintf(_("This attribute can be set if you want to configure an additional SSID besides the default SSIDs for %s. It is almost always a bad idea not to use the default SSIDs. The only exception is if you have premises with an overlap of the radio signal with another %s hotspot. Typical misconceptions about additional SSIDs include: I want to have a local SSID for my own users. It is much better to use the default SSID and separate user groups with VLANs. That approach has two advantages: 1) your users will configure %s properly because it is their everyday SSID; 2) if you use a custom name and advertise this one as extra secure, your users might at some point roam to another place which happens to have the same SSID name. They might then be misled to believe that they are connecting to an extra secure network while they are not."), CONFIG['CONSORTIUM']['name'], CONFIG['CONSORTIUM']['name'], CONFIG['CONSORTIUM']['name'])] = "media:SSID";
    }

    $find = array_search($input, $descriptions);

    if ($find === FALSE) {
        return "";
    }
    return "<span class='tooltip' onclick='alert(\"" . $find . "\")'><img src='../resources/images/icons/question-mark-icon.png" . "'></span>";
}

function check_upload_sanity($optiontype, $filename) {
    switch ($optiontype) {
        case "general:logo_file":
        case "fed:logo_file":
        case "internal:logo_from_url":
            // we check logo_file with ImageMagick
            $image = new Imagick();
            try {
                $image->readImageBlob($filename);
            } catch (ImagickException $exception) {
                echo "Error" . $exception->getMessage();
                return FALSE;
            }
            // image survived the sanity check
            return TRUE;
        case "eap:ca_file":
            // echo "Checking $optiontype with file $filename";
            $func = new \core\X509;
            $cert = $func->processCertificate($filename);
            if ($cert) {
                return TRUE;
            }
            // echo "Error! The certificate seems broken!";
            return FALSE;
        case "support:info_file":
            $info = new finfo();
            $filetype = $info->buffer($filename, FILEINFO_MIME_TYPE);

            // we only take plain text files in UTF-8!
            if ($filetype == "text/plain" && iconv("UTF-8", "UTF-8", $filename) !== FALSE) {
                return TRUE;
            }
            return FALSE;
        default:
            return FALSE;
    }
}

function getBlobFromDB($ref, $checkpublic) {
    $validator = new \web\lib\common\InputValidation();
    $reference = $validator->databaseReference($ref);

    if ($reference == FALSE) {
        return;
    }

    // the data is either public (just give it away) or not; in this case, only
    // release if the data belongs to admin himself
    if ($checkpublic) {
        // we might be called without session context (filepreview) so get the
        // context if needed
        if (session_status() != PHP_SESSION_ACTIVE) {
            session_start();
        }
        $owners = \core\EntityWithDBProperties::isDataRestricted($reference["table"], $reference["rowindex"]);

        $ownersCondensed = [];

        if ($owners !== FALSE) { // restricted datam see if we're authenticated and owners of the data
            $auth = new web\lib\admin\Authentication();
            if (!$auth->isAuthenticated()) {
                return FALSE; // admin-only, but we are not an admin
            }
            foreach ($owners as $oneowner) {
                $ownersCondensed[] = $oneowner['ID'];
            }
            if (array_search($_SESSION['user'], $ownersCondensed) === FALSE) {
                return FALSE; // wrong guy
            }
            // carry on and get the data
        }
    }


    $blob = \core\EntityWithDBProperties::fetchRawDataByIndex($reference["table"], $reference["rowindex"]);
    if (!$blob) {
        return FALSE;
    }
    return $blob;
}

function display_size($number) {
    if ($number > 1024 * 1024) {
        return round($number / 1024 / 1024, 2) . " MiB";
    }
    if ($number > 1024) {
        return round($number / 1024, 2) . " KiB";
    }
    return $number . " B";
}