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

require_once("common.inc.php");

function getImageFromDB($id) {

    // check if data is public for this blob call
    $blob = getBlobFromDB($id, TRUE);
    $finalBlob = base64_decode($blob);

    if ($finalBlob === FALSE) {
        return;
    }

    // Set data type and caching for 30 days
    $info = new finfo();
    $filetype = $info->buffer($finalBlob, FILEINFO_MIME_TYPE);
    header("Content-type: " . $filetype);

    switch ($filetype) {
        case "text/rtf": // fall-through, same treatment
        case "application/rtf":
            header("Content-Disposition: attachment; filename='download.rtf'");
            break;
        case "text/plain":
            header("Content-Disposition: attachment; filename='download.txt'");
            break;
        default:
            // do nothing special with the Content-Disposition header
    }

    header("Cache-Control: must-revalidate");
    $offset = 60 * 60 * 24 * 30;
    $ExpStr = "Expires: " . gmdate("D, d M Y H:i:s", time() + $offset) . " GMT";
    header($ExpStr);

    //  Print out the image
    echo $finalBlob;
}

$validator = new \web\lib\common\InputValidation();
if (isset($_GET["id"]) && $validator->databaseReference($_GET["id"])) {
    getImageFromDB($_GET["id"]);
} else {
    echo "No valid ID";
}